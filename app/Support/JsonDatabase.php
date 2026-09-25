<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Db;
use RuntimeException;

/**
 * Organized JSON mirror for local/homologation data.
 *
 * The application keeps MySQL/SQLite as its relational runtime database. This
 * class creates one JSON file per table so data can be inspected, versioned,
 * backed up and reset without mixing users, stock, requests and audit trails.
 */
final class JsonDatabase
{
    /** @var list<string> */
    private const TABLES = [
        'roles', 'permissions', 'role_permissions', 'users', 'departments',
        'categories', 'industries', 'locations', 'suppliers', 'items', 'stock',
        'stock_positions', 'stock_movements', 'requests', 'request_items',
        'request_status_history', 'approvals', 'deliveries', 'delivery_items',
        'events', 'event_allocations', 'notifications', 'audit_log', 'settings',
        'login_attempts', 'idempotency_keys', 'sequences', 'stock_exit_orders',
    ];

    public static function directory(): string
    {
        $configured = (string) (getenv('JSON_DB_PATH') ?: '');
        return rtrim($configured !== '' ? $configured : BASE_PATH . '/storage/json', '/\\');
    }

    /** Export all known relational tables into separate UTF-8 JSON files. */
    public static function mirrorFromPdo(): void
    {
        self::ensureDirectory();
        foreach (self::TABLES as $table) {
            $rows = [];
            try {
                $rows = Db::fetchAll('SELECT * FROM `' . $table . '`');
                $rows = array_map(static function (array $row) use ($table): array {
                    if ($table === 'users') {
                        unset($row['password_hash']);
                    }
                    if ($table === 'settings' && isset($row['key']) && in_array((string) $row['key'], ['app.key', 'APP_KEY'], true)) {
                        $row['value'] = '[REDACTED]';
                    }
                    return $row;
                }, $rows);
            } catch (\Throwable) {
                // Optional tables can be absent during an installation upgrade.
            }
            self::write($table, $rows);
        }
        self::write('_manifest', [
            'generated_at' => now(),
            'timezone' => date_default_timezone_get(),
            'source' => 'PDO relational database',
            'tables' => self::TABLES,
        ]);
    }

    /** Reset only the JSON mirror. The relational database is never deleted here. */
    public static function reset(): void
    {
        self::ensureDirectory();
        foreach (self::TABLES as $table) {
            self::write($table, []);
        }
        self::write('_manifest', [
            'generated_at' => now(),
            'reset_at' => now(),
            'timezone' => date_default_timezone_get(),
            'source' => 'manual JSON reset',
            'tables' => self::TABLES,
        ]);
    }

    /** @return list<array<string,mixed>> */
    public static function read(string $table): array
    {
        self::assertTable($table);
        $path = self::path($table);
        if (!is_file($path)) {
            return [];
        }
        $decoded = json_decode((string) file_get_contents($path), true);
        return is_array($decoded) ? array_values(array_filter($decoded, 'is_array')) : [];
    }

    private static function write(string $table, array $rows): void
    {
        $path = self::path($table);
        if (file_put_contents($path, json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Não foi possível gravar o espelho JSON: ' . $path);
        }
    }

    private static function path(string $table): string
    {
        self::assertTable($table);
        return self::directory() . '/' . $table . '.json';
    }

    private static function ensureDirectory(): void
    {
        if (!is_dir(self::directory()) && !mkdir(self::directory(), 0775, true) && !is_dir(self::directory())) {
            throw new RuntimeException('Não foi possível criar o diretório JSON.');
        }
    }

    private static function assertTable(string $table): void
    {
        if ($table !== '_manifest' && !in_array($table, self::TABLES, true)) {
            throw new RuntimeException('Tabela JSON não autorizada: ' . $table);
        }
    }
}
