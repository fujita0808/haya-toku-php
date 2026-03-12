<?php

function db()
{
    static $pdo = null;

    if ($pdo) {
        return $pdo;
    }

    $url = getenv("DATABASE_URL");

    if (!$url) {
        throw new Exception("DATABASE_URL not set");
    }

    $db = parse_url($url);

    $host = $db["host"];
    $port = $db["port"];
    $user = $db["user"];
    $pass = $db["pass"];
    $name = ltrim($db["path"], "/");

    $dsn = "pgsql:host=$host;port=$port;dbname=$name";

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    return $pdo;
}
