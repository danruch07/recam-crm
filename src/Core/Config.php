<?php
declare(strict_types=1);

namespace Recam\Core;


final class Config
{
    private static array $data = [];

    public static function load(string $file): void
    {
        self::$data = require $file;
    }

    public static function get(string $path, mixed $default = null): mixed
    {
        $value = self::$data;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }
}
