<?php
// includes/db.php

class Database {
    private static $connection = null;

    public static function connect() {
        if (self::$connection === null) {
            $conn_string = sprintf(
                "host=%s port=%s dbname=%s user=%s password=%s",
                DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS
            );

            self::$connection = pg_connect($conn_string);

            if (!self::$connection) {
                die("Datenbankverbindung fehlgeschlagen");
            }
        }

        return self::$connection;
    }

    public static function query($sql, $params = []) {
        $conn = self::connect();

        if (empty($params)) {
            $result = pg_query($conn, $sql);
        } else {
            $result = pg_query_params($conn, $sql, $params);
        }

        if (!$result) {
            error_log("SQL Error: " . pg_last_error($conn));
            return false;
        }

        return $result;
    }

    // 🔹 NEU
    public static function execute($sql, $params = []) {
        return self::query($sql, $params);
    }

    public static function fetchOne($sql, $params = []) {
        $result = self::query($sql, $params);
        return $result ? pg_fetch_assoc($result) : null;
    }

    public static function fetchAll($sql, $params = []) {
        $result = self::query($sql, $params);
        return $result ? pg_fetch_all($result) : [];
    }
}
