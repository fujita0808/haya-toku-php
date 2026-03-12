<?php

declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $url = getenv('DATABASE_URL');
    if (!$url) {
        throw new RuntimeException('DATABASE_URL not set');
    }

    $parts = parse_url($url);
    if ($parts === false) {
        throw new RuntimeException('DATABASE_URL parse failed');
    }

    $host = $parts['host'] ?? 'localhost';
    $port = (int)($parts['port'] ?? 5432);
    $user = isset($parts['user']) ? urldecode($parts['user']) : '';
    $pass = isset($parts['pass']) ? urldecode($parts['pass']) : '';
    $dbname = isset($parts['path']) ? ltrim($parts['path'], '/') : '';

    parse_str($parts['query'] ?? '', $query);

    $sslmode = $query['sslmode'] ?? 'require';

    $dsn = sprintf(
        'pgsql:host=%s;port=%d;dbname=%s;sslmode=%s',
        $host,
        $port,
        $dbname,
        $sslmode
    );

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $pdo->exec("SET TIME ZONE 'Asia/Tokyo'");

    return $pdo;
}
