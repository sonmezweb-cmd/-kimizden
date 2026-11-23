<?php
declare(strict_types=1);

namespace App;

/** mysqldump gerektirmeyen PHP tabanlı veritabanı yedeği (storage/backups, web kökü dışı). */
final class Backup
{
    public static function create(): string
    {
        $dir = App::storagePath('backups');
        if (!is_dir($dir)) @mkdir($dir, 0770, true);
        $file = $dir . '/ikimizden-' . gmdate('Ymd-His') . '.sql.gz';
        $gz = gzopen($file, 'wb6');
        $pdo = DB::pdo();
        gzwrite($gz, "-- İkimizden yedeği " . gmdate('c') . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
        foreach ($pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN) as $table) {
            $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(\PDO::FETCH_NUM)[1];
            gzwrite($gz, "DROP TABLE IF EXISTS `$table`;\n$create;\n\n");
            $st = $pdo->query("SELECT * FROM `$table`");
            while ($row = $st->fetch(\PDO::FETCH_NUM)) {
                $vals = array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v), $row);
                gzwrite($gz, "INSERT INTO `$table` VALUES (" . implode(',', $vals) . ");\n");
            }
            gzwrite($gz, "\n");
        }
        gzwrite($gz, "SET FOREIGN_KEY_CHECKS=1;\n");
        gzclose($gz);
        @chmod($file, 0640);
        return $file;
    }
}
