<?php
declare(strict_types=1);

namespace App;

final class Migrator
{
    public static function dir(): string
    {
        return APP_ROOT . '/database/migrations';
    }

    public static function ensureTable(): void
    {
        DB::pdo()->exec('CREATE TABLE IF NOT EXISTS migrations (name VARCHAR(190) NOT NULL PRIMARY KEY, applied_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    public static function pending(): array
    {
        self::ensureTable();
        $done = array_column(DB::all('SELECT name FROM migrations'), 'name');
        $files = glob(self::dir() . '/*.sql') ?: [];
        sort($files);
        return array_values(array_filter(array_map('basename', $files), fn($f) => !in_array($f, $done, true)));
    }

    /** SQL dosyasını ifadelere böler (dosyalarda ; yalnız ifade sonunda kullanılır). */
    public static function statements(string $sql): array
    {
        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
        return array_values(array_filter(array_map('trim', explode(";\n", str_replace("\r\n", "\n", $sql . "\n")))));
    }

    public static function run(): array
    {
        $applied = [];
        foreach (self::pending() as $f) {
            foreach (self::statements((string)file_get_contents(self::dir() . '/' . $f)) as $st) {
                DB::pdo()->exec(rtrim($st, ';'));
            }
            DB::insert('migrations', ['name' => $f, 'applied_at' => DB::now()]);
            $applied[] = $f;
        }
        return $applied;
    }
}
