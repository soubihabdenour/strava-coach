<?php

class GeminiClient
{
    private string $apiKey;
    private string $model;

    public function __construct(string $apiKey, string $model = 'gemini-2.5-flash')
    {
        $this->apiKey = $apiKey;
        $this->model = $model;
    }

    /**
     * @param array<int,array{role:string,text:string}> $history role is 'user' or 'model'
     */
    public function chat(string $systemPrompt, array $history): string
    {
        $contents = array_map(fn($m) => [
            'role' => $m['role'],
            'parts' => [['text' => $m['text']]],
        ], $history);

        $body = [
            'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 1024,
            ],
        ];

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-goog-api-key: ' . $this->apiKey,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($resp === false) {
            throw new RuntimeException("Gemini request failed: {$err}");
        }

        $data = json_decode($resp, true);

        if ($code >= 400) {
            $msg = $data['error']['message'] ?? "HTTP {$code}";
            throw new RuntimeException("Gemini API error: {$msg}");
        }

        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        if ($text === '') {
            $reason = $data['candidates'][0]['finishReason'] ?? 'unknown';
            throw new RuntimeException("Gemini returned no text (finish: {$reason}).");
        }
        return $text;
    }
}
