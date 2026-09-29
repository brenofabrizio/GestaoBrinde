<?php

use App\Services\DatabaseBackupService;
use App\Services\ImportLegacyJson;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('brindes:import-json {--path= : Diretório dos JSON legados} {--dry-run : Apenas simula a importação}', function (): void {
    $path = (string) ($this->option('path') ?: base_path((string) env('LEGACY_JSON_PATH', '../database/json')));
    $summary = app(ImportLegacyJson::class)->run($path, (bool) $this->option('dry-run'), $this->output);
    $this->table(['Entidade', 'Quantidade'], collect($summary)->map(fn ($value, $key) => [$key, is_array($value) ? json_encode($value) : $value])->values()->all());
})->purpose('Importa cadastros e brindes dos JSON legados com transação.');

Artisan::command('brindes:backup {--force : Permite executar em production} ', function (): void {
    try {
        $path = app(DatabaseBackupService::class)->backup(storage_path('app/backups'), (bool) $this->option('force'));
        $this->info('Backup created: '.$path);
    } catch (Throwable $exception) {
        $this->error($exception->getMessage());
        $this->fail($exception->getMessage());
    }
})->purpose('Cria um backup seguro do banco SQLite configurado.');

Artisan::command('brindes:restore {backup : Caminho do backup dentro de storage/app/backups} {--force : Permite executar em production}', function (): void {
    try {
        app(DatabaseBackupService::class)->restore((string) $this->argument('backup'), (bool) $this->option('force'));
        $this->info('Database restored.');
    } catch (Throwable $exception) {
        $this->error($exception->getMessage());
        $this->fail($exception->getMessage());
    }
})->purpose('Restaura um backup SQLite validado.');
