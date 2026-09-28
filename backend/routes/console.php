<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('brindes:import-json {--path= : Diretório dos JSON legados} {--dry-run : Apenas simula a importação}', function (): void {
    $path = (string) ($this->option('path') ?: base_path((string) env('LEGACY_JSON_PATH', '../database/json')));
    $summary = app(\App\Services\ImportLegacyJson::class)->run($path, (bool) $this->option('dry-run'), $this->output);
    $this->table(['Entidade', 'Quantidade'], collect($summary)->map(fn ($value, $key) => [$key, $value])->values()->all());
})->purpose('Importa cadastros e brindes dos JSON legados com transação.');
