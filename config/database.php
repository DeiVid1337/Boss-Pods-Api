<?php

use Illuminate\Support\Str;

/**
 * Parse DATABASE_URL/DB_URL safely for PostgreSQL (evita "invalid integer value" para port no Railway).
 * Retorna array com host, port, database, username, password ou null se não usar URL.
 */
$pgsqlFromUrl = function (): ?array {
    $url = env('DATABASE_URL') ?: env('DB_URL');
    if (empty($url) || ! is_string($url)) {
        return null;
    }
    $parsed = parse_url($url);
    if ($parsed === false || empty($parsed['host'])) {
        return null;
    }
    $port = isset($parsed['port']) && is_numeric($parsed['port'])
        ? (int) $parsed['port']
        : 5432;
    $path = $parsed['path'] ?? '';
    $database = ($path !== '/' && $path !== '')
        ? ltrim(explode('?', $path)[0], '/')
        : null;
    return [
        'host' => $parsed['host'],
        'port' => $port,
        'database' => $database,
        'username' => $parsed['user'] ?? null,
        'password' => $parsed['pass'] ?? null,
    ];
};

$pgsqlUrlConfig = $pgsqlFromUrl();

// Valores pgsql com fallback seguro (evita port/database vazios ou inválidos no Railway)
$pgsqlFromParsed = is_array($pgsqlUrlConfig) ? $pgsqlUrlConfig : [];
$pgsqlHost = env('DB_HOST') ?: ($pgsqlFromParsed['host'] ?? null) ?: '127.0.0.1';
$pgsqlPortRaw = env('DB_PORT') ?: ($pgsqlFromParsed['port'] ?? null) ?: '5432';
$pgsqlPort = (is_numeric($pgsqlPortRaw) && (int) $pgsqlPortRaw > 0) ? (int) $pgsqlPortRaw : 5432;
$pgsqlDatabase = env('DB_DATABASE') ?: ($pgsqlFromParsed['database'] ?? null) ?: 'laravel';
$pgsqlUsername = env('DB_USERNAME') ?: ($pgsqlFromParsed['username'] ?? null) ?: 'root';
$pgsqlPassword = env('DB_PASSWORD') ?: ($pgsqlFromParsed['password'] ?? null) ?: '';

return [



    'default' => env('DB_CONNECTION', 'sqlite'),



    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
            'transaction_mode' => 'DEFERRED',
        ],

        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                (PHP_VERSION_ID >= 80500 ? \Pdo\Mysql::ATTR_SSL_CA : \PDO::MYSQL_ATTR_SSL_CA) => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'mariadb' => [
            'driver' => 'mariadb',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                (PHP_VERSION_ID >= 80500 ? \Pdo\Mysql::ATTR_SSL_CA : \PDO::MYSQL_ATTR_SSL_CA) => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => null, // Nunca passar URL ao parser do Laravel (evita "port" inválido no Railway)
            'host' => $pgsqlHost,
            'port' => $pgsqlPort,
            'database' => $pgsqlDatabase,
            'username' => $pgsqlUsername,
            'password' => $pgsqlPassword,
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => env('DB_SSLMODE', 'prefer'),
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
        ],

    ],



    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],



    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-database-'),
            'persistent' => env('REDIS_PERSISTENT', false),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

    ],

];
