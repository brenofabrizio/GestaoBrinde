<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LegacyJsonImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_requests_items_movements_audit_and_reports_reconciled_balances(): void
    {
        User::factory()->create();
        $directory = $this->legacyFixture([
            'brindes.json' => [['id' => 1, 'codigo' => 'B-1', 'nome' => 'Caneca', 'categoria' => 'Geral', 'saldo' => 10]],
            'solicitacoes.json' => [['id' => 77, 'codigo' => 'SOL-77', 'usuario_id' => 1, 'finalidade' => 'Evento', 'status' => 'aprovada']],
            'solicitacao_itens.json' => [['solicitacao_id' => 77, 'brinde_id' => 1, 'quantidade' => 2]],
            'movimentacoes.json' => [['id' => 8, 'brinde_id' => 1, 'tipo' => 'entrada', 'quantidade' => 3, 'saldo_apos' => 13]],
            'auditoria.json' => [['id' => 9, 'usuario_id' => 1, 'acao' => 'importado', 'entidade' => 'solicitacoes', 'registro_id' => 77, 'before_json' => ['status' => 'rascunho', 'password_hash' => 'never-store-me'], 'after_json' => ['status' => 'aprovada', 'qty' => 2]]],
        ]);

        $this->assertSame(0, Artisan::call('brindes:import-json', ['--path' => $directory]));

        $this->assertSame([], json_decode(file_get_contents($directory.DIRECTORY_SEPARATOR.'import_invalid.json'), true), file_get_contents($directory.DIRECTORY_SEPARATOR.'import_invalid.json'));
        $this->assertDatabaseHas('requests', ['code' => 'SOL-77', 'purpose' => 'Evento']);
        $this->assertDatabaseHas('request_items', ['qty_requested' => 2]);
        $this->assertDatabaseHas('stock_movements', ['type' => 'entrada', 'qty' => 3, 'balance_after' => 13]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'importado']);
        $audit = DB::table('audit_logs')->where('action', 'importado')->first();
        $this->assertSame(['status' => 'rascunho'], json_decode($audit->old_values, true));
        $this->assertSame(['status' => 'aprovada', 'qty' => 2], json_decode($audit->new_values, true));
        $this->assertDatabaseHas('stock', ['qty_on_hand' => 13]);
        $this->assertFileExists($directory.DIRECTORY_SEPARATOR.'import_invalid.json');
        $this->assertSame([], json_decode(file_get_contents($directory.DIRECTORY_SEPARATOR.'import_invalid.json'), true));
    }

    public function test_import_preserves_categories_for_each_legacy_item(): void
    {
        $directory = $this->legacyFixture([
            'cadastros.json' => ['categorias' => [['id' => 30, 'nome' => 'Bebidas'], ['id' => 31, 'nome' => 'Vestuário']]],
            'brindes.json' => [
                ['id' => 1, 'codigo' => 'CAT-1', 'nome' => 'Garrafa', 'categoria' => 'Bebidas', 'saldo' => 2],
                ['id' => 2, 'codigo' => 'CAT-2', 'nome' => 'Camiseta', 'categoria' => 'Vestuário', 'saldo' => 3],
            ],
        ]);

        Artisan::call('brindes:import-json', ['--path' => $directory]);

        $categoryByCode = DB::table('items')->join('categories', 'categories.id', '=', 'items.category_id')->pluck('categories.name', 'items.code')->all();
        $this->assertSame(['CAT-1' => 'Bebidas', 'CAT-2' => 'Vestuário'], $categoryByCode);
    }

    public function test_dry_run_reports_malformed_json_files(): void
    {
        $directory = $this->legacyFixture([]);
        file_put_contents($directory.DIRECTORY_SEPARATOR.'brindes.json', '{broken');

        Artisan::call('brindes:import-json', ['--path' => $directory, '--dry-run' => true]);

        $report = json_decode(file_get_contents($directory.DIRECTORY_SEPARATOR.'import_invalid.json'), true);
        $this->assertSame('brindes.json', $report[0]['file']);
        $this->assertSame('JSON inválido', $report[0]['reason']);
        $this->assertDatabaseCount('items', 0);
    }

    public function test_dry_run_reports_missing_item_references_in_requests_and_movements(): void
    {
        $requester = User::factory()->create();
        $directory = $this->legacyFixture([
            'solicitacoes.json' => [['id' => 12, 'codigo' => 'SOL-12', 'usuario_id' => $requester->id, 'finalidade' => 'Evento']],
            'solicitacao_itens.json' => [['solicitacao_id' => 12, 'brinde_id' => 999, 'quantidade' => 2]],
            'movimentacoes.json' => [['id' => 5, 'brinde_id' => 999, 'tipo' => 'entrada', 'quantidade' => 1]],
        ]);

        Artisan::call('brindes:import-json', ['--path' => $directory, '--dry-run' => true]);

        $report = json_decode(file_get_contents($directory.DIRECTORY_SEPARATOR.'import_invalid.json'), true);
        $invalidFiles = array_column($report, 'file');
        $this->assertContains('solicitacao_itens.json', $invalidFiles);
        $this->assertContains('movimentacoes.json', $invalidFiles);
    }

    public function test_reimporting_legacy_movements_does_not_duplicate_history(): void
    {
        User::factory()->create();
        $directory = $this->legacyFixture([
            'brindes.json' => [['id' => 1, 'codigo' => 'B-IDEMP', 'nome' => 'Item idempotente', 'saldo' => 4]],
            'movimentacoes.json' => [['id' => 88, 'brinde_id' => 1, 'tipo' => 'entrada', 'quantidade' => 2, 'saldo_apos' => 6]],
            'auditoria.json' => [['id' => 12, 'acao' => 'importado']],
        ]);

        Artisan::call('brindes:import-json', ['--path' => $directory]);
        Artisan::call('brindes:import-json', ['--path' => $directory]);

        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertDatabaseHas('stock', ['qty_on_hand' => 6]);
    }

    public function test_dry_run_reports_invalid_records_without_persisting(): void
    {
        $directory = $this->legacyFixture([
            'solicitacoes.json' => [['id' => 77, 'codigo' => '', 'usuario_id' => 999, 'finalidade' => '']],
            'solicitacao_itens.json' => [['solicitacao_id' => 77, 'brinde_id' => 999, 'quantidade' => 0]],
            'movimentacoes.json' => [['brinde_id' => 999, 'tipo' => 'entrada', 'quantidade' => 0]],
            'auditoria.json' => [['acao' => 'segredo', 'password' => 'do-not-import']],
        ]);

        $this->assertSame(0, Artisan::call('brindes:import-json', ['--path' => $directory, '--dry-run' => true]));

        $this->assertDatabaseCount('requests', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertFileExists($directory.DIRECTORY_SEPARATOR.'import_invalid.json');
        $report = file_get_contents($directory.DIRECTORY_SEPARATOR.'import_invalid.json');
        $this->assertStringNotContainsString('do-not-import', $report);
        $this->assertStringNotContainsString('password', $report);
        $this->assertStringContainsString('solicitacoes', $report);
    }

    private function legacyFixture(array $files): string
    {
        $directory = storage_path('framework/testing/legacy-'.uniqid('', true));
        mkdir($directory, 0777, true);
        foreach ($files as $name => $rows) {
            file_put_contents($directory.DIRECTORY_SEPARATOR.$name, json_encode($rows));
        }
        if (! array_key_exists('cadastros.json', $files)) {
            file_put_contents($directory.DIRECTORY_SEPARATOR.'cadastros.json', json_encode(['categorias' => [['nome' => 'Geral']]]));
        }

        return $directory;
    }
}
