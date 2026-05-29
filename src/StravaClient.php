<?php

class StravaUnauthorizedException extends RuntimeException {}

class StravaRateLimitException extends RuntimeException
{
    public function __construct(string $message, public readonly array $rateLimit = [])
    {
        parent::__construct($message);
    }
}

class StravaClient
{
    private const AUTH_URL = 'https://www.strava.com/oauth/authorize';
    private const TOKEN_URL = 'https://www.strava.com/oauth/token';
    private const API_BASE = 'https://www.strava.com/api/v3';

    private array $lastRateLimit = [];

    public function __construct(
        private string $clientId,
        private string $clientSecret,
        private string $redirectUri,
    ) {}

    /**
     * @return array{limit?:array{short:int,long:int},usage?:array{short:int,long:int}}
     */
    public function lastRateLimit(): array
    {
        return $this->lastRateLimit;
    }

    public function authorizationUrl(string $state): string
    {
        $params = http_build_query([
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'redirect_uri' => $this->redirectUri,
            'approval_prompt' => 'auto',
            'scope' => 'read,activity:read_all,profile:read_all',
            'state' => $state,
        ]);
        return self::AUTH_URL . '?' . $params;
    }

    public function exchangeCode(string $code): array
    {
        return $this->postForm(self::TOKEN_URL, [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'grant_type' => 'authorization_code',
        ]);
    }

    public function refreshToken(string $refreshToken): array
    {
        return $this->postForm(self::TOKEN_URL, [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);
    }

    public function getAthlete(string $accessToken): array
    {
        return $this->get('/athlete', $accessToken);
    }

    public function getActivities(string $accessToken, int $perPage = 100, int $page = 1, ?int $after = null): array
    {
        $query = ['per_page' => $perPage, 'page' => $page];
        if ($after) {
            $query['after'] = $after;
        }
        return $this->get('/athlete/activities?' . http_build_query($query), $accessToken);
    }

    public function fetchRecentActivities(string $accessToken, int $days = 84): array
    {
        $after = time() - ($days * 86400);
        $all = [];
        $page = 1;
        while (true) {
            $batch = $this->getActivities($accessToken, 100, $page, $after);
            if (empty($batch)) {
                break;
            }
            $all = array_merge($all, $batch);
            if (count($batch) < 100) {
                break;
            }
            $page++;
            if ($page > 5) {
                break;
            }
        }
        return $all;
    }

    private function get(string $path, string $accessToken): array
    {
        $ch = curl_init(self::API_BASE . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
            CURLOPT_TIMEOUT => 30,
        ]);
        $this->captureHeaders($ch);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException("Strava API network error on {$path}");
        }
        if ($status === 401) {
            throw new StravaUnauthorizedException("Strava 401 on {$path}");
        }
        if ($status === 429) {
            throw new StravaRateLimitException("Strava rate limit exceeded on {$path}", $this->lastRateLimit);
        }
        if ($status >= 400) {
            throw new RuntimeException("Strava API error ({$status}): {$response}");
        }
        return json_decode($response, true) ?? [];
    }

    private function postForm(string $url, array $fields): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($fields),
            CURLOPT_TIMEOUT => 30,
        ]);
        $this->captureHeaders($ch);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException("Strava token endpoint network error");
        }
        if ($status === 401) {
            throw new StravaUnauthorizedException("Strava 401 on token endpoint");
        }
        if ($status === 429) {
            throw new StravaRateLimitException("Strava token rate limit exceeded", $this->lastRateLimit);
        }
        if ($status >= 400) {
            throw new RuntimeException("Strava token endpoint error ({$status}): {$response}");
        }
        return json_decode($response, true) ?? [];
    }

    private function captureHeaders($ch): void
    {
        $this->lastRateLimit = [];
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, $header) {
            if (preg_match('/^x-ratelimit-(limit|usage):\s*(\d+),(\d+)/i', trim($header), $m)) {
                $this->lastRateLimit[strtolower($m[1])] = [
                    'short' => (int)$m[2],
                    'long'  => (int)$m[3],
                ];
            }
            return strlen($header);
        });
    }
}
