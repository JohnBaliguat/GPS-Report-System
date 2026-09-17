<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';

class Settings
{
    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            foreach (db()->query('SELECT skey, svalue FROM settings')->fetchAll() as $r) {
                self::$cache[$r['skey']] = $r['svalue'];
            }
        }
        return self::$cache;
    }

    public static function get(string $key, string $default = ''): string
    {
        $all = self::all();
        return $all[$key] ?? $default;
    }

    public static function set(string $key, string $value): void
    {
        db()->prepare('INSERT INTO settings (skey, svalue) VALUES (?,?)
                       ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)')
            ->execute([$key, $value]);
        self::$cache = null;
    }

    public static function setMany(array $kv): void
    {
        foreach ($kv as $k => $v) {
            self::set($k, (string) $v);
        }
    }
}
