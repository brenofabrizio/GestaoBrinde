<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Industry;
use App\Models\Item;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\DB;

final class ImportLegacyJson
{
    public function run(string $directory, bool $dryRun, OutputStyle $output): array
    {
        $directory = rtrim($directory, '/\\');
        $cadastros = $this->read($directory, 'cadastros.json');
        $brindes = $this->read($directory, 'brindes.json');
        $summary = [
            'categories' => count($cadastros['categorias'] ?? []),
            'departments' => count($cadastros['departamentos'] ?? []),
            'industries' => count($cadastros['industrias'] ?? []),
            'locations' => count($cadastros['locais'] ?? []),
            'suppliers' => count($cadastros['fornecedores'] ?? []),
            'items' => count($brindes),
            'stock' => count($brindes),
            'skipped_users' => count($this->read($directory, 'usuarios.json')),
        ];

        if ($dryRun) {
            $output->comment('Modo simulação: nenhum dado será gravado.');
            return $summary;
        }

        DB::transaction(function () use ($cadastros, $brindes): void {
            foreach ($cadastros['categorias'] ?? [] as $row) {
                $name = $this->name($row);
                if ($name !== '') {
                    Category::query()->updateOrCreate(['name' => $name], [
                        'description' => $row['description'] ?? $row['descricao'] ?? null,
                        'active' => (bool) ($row['active'] ?? $row['ativo'] ?? true),
                    ]);
                }
            }

            foreach (['departamentos' => 'departments', 'locais' => 'locations', 'fornecedores' => 'suppliers'] as $source => $table) {
                foreach ($cadastros[$source] ?? [] as $row) {
                    $name = $this->name($row);
                    if ($name !== '') {
                        DB::table($table)->updateOrInsert(
                            ['name' => $name],
                            ['active' => (int) ($row['active'] ?? $row['ativo'] ?? 1), 'updated_at' => now(), 'created_at' => now()],
                        );
                    }
                }
            }

            foreach ($cadastros['industrias'] ?? [] as $row) {
                $name = $this->name($row);
                if ($name !== '') {
                    Industry::query()->updateOrCreate(['name' => $name], [
                        'cnpj' => $row['cnpj'] ?? null,
                        'active' => (bool) ($row['active'] ?? $row['ativo'] ?? true),
                    ]);
                }
            }

            $categoryId = Category::query()->value('id');
            foreach ($brindes as $row) {
                $code = trim((string) ($row['code'] ?? $row['codigo'] ?? ''));
                $name = $this->name($row);
                if ($code === '' || $name === '' || $categoryId === null) continue;
                $item = Item::query()->updateOrCreate(['code' => $code], [
                    'name' => $name,
                    'category_id' => $row['category_id'] ?? $categoryId,
                    'unit_value' => $row['unit_value'] ?? $row['valor_unitario'] ?? null,
                    'min_stock' => (int) ($row['min_stock'] ?? $row['estoque_minimo'] ?? 0),
                    'status' => $row['status'] ?? 'ativo',
                ]);
                DB::table('stock')->updateOrInsert(
                    ['item_id' => $item->id],
                    ['qty_on_hand' => (int) ($row['qty_on_hand'] ?? $row['saldo'] ?? 0), 'qty_reserved' => 0, 'updated_at' => now()],
                );
            }
        });

        return $summary;
    }

    private function read(string $directory, string $file): array
    {
        $path = $directory . DIRECTORY_SEPARATOR . $file;
        if (! is_file($path)) return [];
        $decoded = json_decode((string) file_get_contents($path), true);
        if (! is_array($decoded)) return [];
        return $decoded;
    }

    private function name(array $row): string
    {
        return trim((string) ($row['name'] ?? $row['nome'] ?? ''));
    }
}
