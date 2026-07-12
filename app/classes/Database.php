<?php

require_once __DIR__ . '/../config/config.php';

class Database
{
    private static $db = null;

    public static function get(): SQLite3
    {
        if (self::$db === null)
        {
            self::$db = new SQLite3(DB_PATH);
        }

        return self::$db;
    }
}
