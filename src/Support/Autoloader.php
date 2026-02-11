<?php

namespace DigitalRoyalty\Beacon\Support;

final class Autoloader
{
    public static function register(string $srcDir): void
    {
        spl_autoload_register(static function (string $class) use ($srcDir) {
            $prefix = 'DigitalRoyalty\\Beacon\\';

            if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
                return;
            }

            $relative = substr($class, strlen($prefix)); // e.g. Admin\SettingsPage
            $path = rtrim($srcDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
                . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';

            if (is_readable($path)) {
                require_once $path;
            }
        });
    }
}
