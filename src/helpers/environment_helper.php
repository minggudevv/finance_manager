<?php
class Environment {
    private static $env = null;
    private static $config = [];

    public static function load() {
        if (self::$env === null) {
            $envFile = __DIR__ . '/../../.env';
            if (file_exists($envFile)) {
                $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                        list($key, $value) = explode('=', $line, 2);
                        self::$config[trim($key)] = trim($value);
                    }
                }
            }
            self::$env = self::get('APP_ENV', 'production');
        }
        return self::$env;
    }

    public static function get($key, $default = null) {
        return isset(self::$config[$key]) ? self::$config[$key] : $default;
    }

    public static function isDevelopment() {
        return self::load() === 'development';
    }

    public static function isProduction() {
        return self::load() === 'production';
    }
}
