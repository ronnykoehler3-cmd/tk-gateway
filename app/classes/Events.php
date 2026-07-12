<?php

require_once __DIR__ . '/Database.php';

class Events
{
    public static function add(string $level, string $message): void
    {
        $db = Database::get();

        $stmt = $db->prepare(
            'INSERT INTO events (level, message)
             VALUES (:level, :message)'
        );

        $stmt->bindValue(':level', $level, SQLITE3_TEXT);
        $stmt->bindValue(':message', $message, SQLITE3_TEXT);

        $stmt->execute();
    }

    public static function get(int $limit = 100): array
    {
        $db = Database::get();

        $result = $db->query(
            'SELECT *
             FROM events
             ORDER BY id DESC
             LIMIT ' . intval($limit)
        );

        $events = [];

        while ($row = $result->fetchArray(SQLITE3_ASSOC))
        {
            $events[] = $row;
        }

        return $events;
    }
}
