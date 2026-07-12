<?php

class EventLogger
{
    public static function log(
        string $level,
        string $message
    ): void
    {
        try
        {
            $db = new SQLite3(
                '/opt/tkgateway/database/gateway.db'
            );

            $stmt = $db->prepare(
            '
            INSERT INTO events
            (
                level,
                message
            )
            VALUES
            (
                :level,
                :message
            )
            '
            );

            $stmt->bindValue(
                ':level',
                $level,
                SQLITE3_TEXT
            );

            $stmt->bindValue(
                ':message',
                $message,
                SQLITE3_TEXT
            );

            $stmt->execute();
        }
        catch(Exception $e)
        {
            error_log(
                'EventLogger Fehler: ' .
                $e->getMessage()
            );
        }
    }
}
