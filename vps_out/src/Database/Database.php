<?php

declare(strict_types=1);

namespace MangaNexus\Database;

use Exception;
use PDO;

class Database
{
    private static ?PDO $pdo = null;

    /**
     * Get the PDO connection singleton.
     */
    public static function getConnection(): PDO
    {
        if (self::$pdo === null) {
            $dbPath = getenv('DB_PATH_TEST') ?: (defined('DB_PATH') ? DB_PATH : dirname(dirname(__DIR__)) . '/data/manga.db');

            if ($dbPath !== ':memory:') {
                $dbDir = dirname($dbPath);
                if (!is_dir($dbDir)) {
                    mkdir($dbDir, 0755, true);
                }
            }

            try {
                $dbExists = file_exists($dbPath);
                $pdo = new PDO("sqlite:" . $dbPath);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

                // Performance tweaks
                $pdo->exec("PRAGMA journal_mode = WAL;");
                $pdo->exec("PRAGMA foreign_keys = ON;");
                $pdo->exec("PRAGMA synchronous = NORMAL;");
                $pdo->exec("PRAGMA temp_store = MEMORY;");
                $pdo->exec("PRAGMA cache_size = -8000;"); // 8MB Cache

                self::$pdo = $pdo;
            } catch (Exception $e) {
                throw new Exception("Database Connection Failure: " . $e->getMessage());
            }
        }

        return self::$pdo;
    }
}
