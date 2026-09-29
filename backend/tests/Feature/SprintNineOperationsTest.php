<?php

namespace Tests\Feature;

use App\Services\DatabaseBackupService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SprintNineOperationsTest extends TestCase
{
    public function test_readiness_reports_database_status(): void
    {
        $response = $this->getJson('/api/v1/readiness');

        $response->assertOk()
            ->assertJsonPath('ready', true)
            ->assertJsonPath('checks.database.status', 'ok')
            ->assertJsonStructure(['ready', 'service', 'checks' => ['database' => ['status', 'driver']]]);
    }

    public function test_backup_and_restore_round_trip_a_configured_sqlite_file(): void
    {
        $database = storage_path('framework/testing-roundtrip.sqlite');
        File::ensureDirectoryExists(dirname($database));
        File::put($database, 'before');
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $database]);

        $service = app(DatabaseBackupService::class);
        $backup = $service->backup(storage_path('app/backups'));
        File::put($database, 'after');
        $service->restore($backup);

        $this->assertSame('before', File::get($database));
        File::delete([$database, $backup]);
    }

    public function test_backup_command_refuses_production_without_force(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('brindes:backup')
            ->expectsOutputToContain('refused')
            ->assertExitCode(1);
    }

    public function test_restore_command_requires_force_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('brindes:restore backup.sqlite')
            ->expectsOutputToContain('refused')
            ->assertExitCode(1);
    }

    public function test_backup_and_restore_commands_reject_unsupported_database_driver(): void
    {
        config(['database.default' => 'pgsql']);

        $this->artisan('brindes:backup')
            ->expectsOutputToContain('unsupported')
            ->assertExitCode(1);

        $this->artisan('brindes:restore backup.sqlite')
            ->expectsOutputToContain('unsupported')
            ->assertExitCode(1);
    }
}
