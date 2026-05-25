<?php

class I18n
{
    private const SUPPORTED = ['en', 'fr'];
    private const DEFAULT = 'en';

    private static string $locale = self::DEFAULT;
    /** @var array<string,array<string,string>> */
    private static array $cache = [];

    public static function init(): void
    {
        if (isset($_GET['lang']) && in_array($_GET['lang'], self::SUPPORTED, true)) {
            $_SESSION['locale'] = $_GET['lang'];
            $params = $_GET;
            unset($params['lang']);
            $path = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
            $url = $path . ($params ? '?' . http_build_query($params) : '');
            header('Location: ' . $url);
            exit;
        }

        if (!empty($_SESSION['locale']) && in_array($_SESSION['locale'], self::SUPPORTED, true)) {
            self::$locale = $_SESSION['locale'];
            return;
        }

        $accept = strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
        foreach (explode(',', $accept) as $part) {
            $code = substr(trim(explode(';', $part)[0]), 0, 2);
            if (in_array($code, self::SUPPORTED, true)) {
                self::$locale = $code;
                return;
            }
        }
    }

    public static function locale(): string
    {
        return self::$locale;
    }

    public static function supported(): array
    {
        return self::SUPPORTED;
    }

    public static function translate(string $key): string
    {
        foreach ([self::$locale, self::DEFAULT] as $loc) {
            if (!isset(self::$cache[$loc])) {
                $file = __DIR__ . '/lang/' . $loc . '.php';
                self::$cache[$loc] = is_file($file) ? require $file : [];
            }
            if (isset(self::$cache[$loc][$key])) {
                return self::$cache[$loc][$key];
            }
        }
        return $key;
    }
}

function t(string $key, ...$args): string
{
    $str = I18n::translate($key);
    return $args ? vsprintf($str, $args) : $str;
}

function lang_url(string $locale): string
{
    $params = $_GET;
    $params['lang'] = $locale;
    $path = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
    return $path . '?' . http_build_query($params);
}
