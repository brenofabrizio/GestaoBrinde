<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Industry;
use App\Models\Item;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\DB;

final class ImportLegacyJson
{
    private array $invalid = [];

    public function run(string $directory, bool $dryRun, OutputStyle $output): array
    {
        $directory = rtrim($directory, '/\\');
        $this->invalid = [];
        $cadastros = $this->read($directory, 'cadastros.json');
        $brindes = $this->read($directory, 'brindes.json');
        $requests = $this->read($directory, 'solicitacoes.json');
        $requestItems = $this->read($directory, 'solicitacao_itens.json');
        $movements = $this->read($directory, 'movimentacoes.json');
        $audits = $this->read($directory, 'auditoria.json');
        $summary = [
            'categories' => count($cadastros['categorias'] ?? []),
            'departments' => count($cadastros['departamentos'] ?? []),
            'industries' => count($cadastros['industrias'] ?? []),
            'locations' => count($cadastros['locais'] ?? []),
            'suppliers' => count($cadastros['fornecedores'] ?? []),
            'items' => 0, 'stock' => 0, 'requests' => 0, 'request_items' => 0,
            'movements' => 0, 'audit' => 0, 'skipped_users' => count($this->read($directory, 'usuarios.json')),
            'invalid' => 0, 'balances' => [],
        ];

        if (! $dryRun && $this->invalid !== []) {
            $this->writeInvalidReport($directory);
            $summary['invalid'] = count($this->invalid);
            $output->error('Importação cancelada: existem arquivos JSON inválidos.');

            return $summary;
        }

        if ($dryRun) {
            $this->validateAndCount($cadastros, $brindes, $requests, $requestItems, $movements, $audits, $summary);
            $this->writeInvalidReport($directory);
            $summary['invalid'] = count($this->invalid);
            $output->comment('Modo simulação: nenhum dado será gravado.');

            return $summary;
        }

        DB::transaction(function () use ($cadastros, $brindes, $requests, $requestItems, $movements, $audits, &$summary): void {
            $categoryIdsByLegacyId = $this->importLookups($cadastros);
            $categoryId = Category::query()->value('id');
            $itemsByLegacyId = [];
            foreach ($brindes as $index => $row) {
                if (! is_array($row)) {
                    $this->invalid[] = $this->bad('brindes.json', $index, 'Registro inválido');

                    continue;
                }
                $code = trim((string) ($row['code'] ?? $row['codigo'] ?? ''));
                $name = $this->name($row);
                $legacyCategoryId = $row['category_id'] ?? $row['categoria_id'] ?? null;
                $categoryName = trim((string) ($row['category'] ?? $row['categoria_nome'] ?? $row['categoria'] ?? ''));
                $itemCategoryId = $categoryName !== ''
                    ? Category::query()->firstOrCreate(['name' => $categoryName], ['active' => true])->id
                    : ($categoryIdsByLegacyId[(string) $legacyCategoryId] ?? $categoryId);
                if ($code === '' || $name === '' || $itemCategoryId === null) {
                    $this->invalid[] = $this->bad('brindes.json', $index, 'Código, nome ou categoria ausente');

                    continue;
                }
                $item = Item::query()->updateOrCreate(['code' => $code], [
                    'name' => $name, 'category_id' => (int) $itemCategoryId,
                    'unit_value' => $row['unit_value'] ?? $row['valor_unitario'] ?? null,
                    'min_stock' => (int) ($row['min_stock'] ?? $row['estoque_minimo'] ?? 0),
                    'status' => $row['status'] ?? 'ativo',
                ]);
                $legacyId = $row['id'] ?? null;
                if ($legacyId !== null) {
                    $itemsByLegacyId[(string) $legacyId] = $item->id;
                }
                DB::table('stock')->insertOrIgnore([
                    'item_id' => $item->id,
                    'qty_on_hand' => (int) ($row['qty_on_hand'] ?? $row['saldo'] ?? 0),
                    'qty_reserved' => 0,
                    'updated_at' => now(),
                ]);
                $summary['items']++;
                $summary['stock']++;
            }
            $requestIds = [];
            foreach ($requests as $index => $row) {
                if (! is_array($row)) {
                    $this->invalid[] = $this->bad('solicitacoes.json', $index, 'Registro inválido');

                    continue;
                }
                $requesterId = (int) ($row['requester_id'] ?? $row['usuario_id'] ?? 0);
                $code = trim((string) ($row['code'] ?? $row['codigo'] ?? ''));
                $purpose = trim((string) ($row['purpose'] ?? $row['finalidade'] ?? ''));
                if ($code === '' || $purpose === '' || ! DB::table('users')->where('id', $requesterId)->exists()) {
                    $this->invalid[] = $this->bad('solicitacoes.json', $index, 'Código, finalidade ou solicitante inválido');

                    continue;
                }
                $legacyId = (string) ($row['id'] ?? $row['solicitacao_id'] ?? $code);
                $id = DB::table('requests')->where('code', $code)->value('id');
                if ($id) {
                    DB::table('requests')->where('id', $id)->update(['purpose' => $purpose, 'status' => $row['status'] ?? 'rascunho', 'updated_at' => now()]);
                } else {
                    $id = DB::table('requests')->insertGetId([
                        'code' => $code, 'requester_id' => $requesterId,
                        'department_id' => $this->foreignId('departments', $row['department_id'] ?? $row['departamento_id'] ?? null),
                        'industry_id' => $this->foreignId('industries', $row['industry_id'] ?? $row['industria_id'] ?? null),
                        'purpose' => $purpose, 'recipient' => $row['recipient'] ?? $row['destinatario'] ?? null,
                        'needed_date' => $row['needed_date'] ?? $row['data_necessaria'] ?? null,
                        'purchase_ticket_no' => $row['purchase_ticket_no'] ?? $row['chamado_compra'] ?? null,
                        'status' => $row['status'] ?? 'rascunho', 'notes' => $row['notes'] ?? $row['observacoes'] ?? null,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                $requestIds[$legacyId] = (int) $id;
                $summary['requests']++;
            }
            foreach ($requestItems as $index => $row) {
                $requestId = $requestIds[(string) ($row['request_id'] ?? $row['solicitacao_id'] ?? '')] ?? null;
                $itemId = $itemsByLegacyId[(string) ($row['item_id'] ?? $row['brinde_id'] ?? '')] ?? null;
                if (! $itemId && isset($row['item_id'])) {
                    $itemId = DB::table('items')->where('id', $row['item_id'])->value('id');
                }
                if (! $itemId && isset($row['brinde_id'])) {
                    $itemId = DB::table('items')->where('id', $row['brinde_id'])->value('id');
                }
                $qty = (int) ($row['qty_requested'] ?? $row['quantidade'] ?? $row['quantidade_solicitada'] ?? 0);
                if (! $requestId || ! $itemId || $qty < 1) {
                    $this->invalid[] = $this->bad('solicitacao_itens.json', $index, 'Solicitação, brinde ou quantidade inválida');

                    continue;
                }
                DB::table('request_items')->updateOrInsert(['request_id' => $requestId, 'item_id' => $itemId], ['qty_requested' => $qty, 'qty_approved' => $row['qty_approved'] ?? $row['quantidade_aprovada'] ?? null, 'qty_delivered' => (int) ($row['qty_delivered'] ?? $row['quantidade_entregue'] ?? 0), 'qty_reserved' => 0, 'unit_value' => $row['unit_value'] ?? $row['valor_unitario'] ?? null, 'created_at' => now(), 'updated_at' => now()]);
                $summary['request_items']++;
            }
            $this->importMovements($movements, $itemsByLegacyId, $summary);
            foreach ($audits as $index => $row) {
                $action = trim((string) ($row['action'] ?? $row['acao'] ?? ''));
                if ($action === '') {
                    $this->invalid[] = $this->bad('auditoria.json', $index, 'Ação de auditoria ausente');

                    continue;
                }
                $sourceId = (string) ($row['id'] ?? $row['audit_id'] ?? json_encode($row, JSON_THROW_ON_ERROR));
                $sourceKey = hash('sha256', 'auditoria.json:'.$sourceId);
                if (DB::table('audit_logs')->where('legacy_source_key', $sourceKey)->exists()) {
                    continue;
                }
                DB::table('audit_logs')->insert([
                    'user_id' => $this->foreignId('users', $row['user_id'] ?? $row['usuario_id'] ?? null),
                    'user_name' => $row['user_name'] ?? $row['usuario_nome'] ?? null,
                    'action' => $action,
                    'entity_type' => $row['entity_type'] ?? $row['entidade'] ?? null,
                    'entity_id' => $row['entity_id'] ?? $row['registro_id'] ?? null,
                    'entity_label' => $row['entity_label'] ?? $row['entidade_rotulo'] ?? $row['label'] ?? null,
                    'old_values' => $this->encodeAuditSnapshot($row['old_values'] ?? $row['before_json'] ?? $row['before'] ?? null),
                    'new_values' => $this->encodeAuditSnapshot($row['new_values'] ?? $row['after_json'] ?? $row['after'] ?? null),
                    'ip_address' => $row['ip_address'] ?? $row['ip'] ?? null,
                    'user_agent' => $row['user_agent'] ?? null,
                    'legacy_source_key' => $sourceKey,
                    'created_at' => $row['created_at'] ?? $row['data_hora'] ?? now(),
                ]);
                $summary['audit']++;
            }
        });
        $summary['balances'] = DB::table('stock')->join('items', 'items.id', '=', 'stock.item_id')->orderBy('items.code')->get(['items.code', 'stock.qty_on_hand'])->mapWithKeys(fn ($row) => [$row->code => (int) $row->qty_on_hand])->all();
        $summary['invalid'] = count($this->invalid);
        $this->writeInvalidReport($directory);

        return $summary;
    }

    private function validateAndCount(array $cadastros, array $brindes, array $requests, array $requestItems, array $movements, array $audits, array &$summary): void
    {
        $legacyItemIds = [];
        foreach ($brindes as $index => $row) {
            if (! is_array($row)) {
                $this->invalid[] = $this->bad('brindes.json', $index, 'Registro inválido');

                continue;
            }
            $code = trim((string) ($row['code'] ?? $row['codigo'] ?? ''));
            $name = $this->name($row);
            if ($code === '' || $name === '') {
                $this->invalid[] = $this->bad('brindes.json', $index, 'Código ou nome ausente');

                continue;
            }
            if (isset($row['id'])) {
                $legacyItemIds[(string) $row['id']] = true;
            }
            $summary['items']++;
            $summary['stock']++;
        }

        $requestIds = [];
        foreach ($requests as $index => $row) {
            if (! is_array($row)) {
                $this->invalid[] = $this->bad('solicitacoes.json', $index, 'Registro inválido');

                continue;
            }
            $code = trim((string) ($row['code'] ?? $row['codigo'] ?? ''));
            $purpose = trim((string) ($row['purpose'] ?? $row['finalidade'] ?? ''));
            $requesterId = (int) ($row['requester_id'] ?? $row['usuario_id'] ?? 0);
            if ($code === '' || $purpose === '' || ! DB::table('users')->where('id', $requesterId)->exists()) {
                $this->invalid[] = $this->bad('solicitacoes.json', $index, 'Código, finalidade ou solicitante inválido');

                continue;
            }
            $requestIds[(string) ($row['id'] ?? $row['solicitacao_id'] ?? $code)] = true;
            $summary['requests']++;
        }

        foreach ($requestItems as $index => $row) {
            if (! is_array($row)) {
                $this->invalid[] = $this->bad('solicitacao_itens.json', $index, 'Registro inválido');

                continue;
            }
            $requestRef = (string) ($row['request_id'] ?? $row['solicitacao_id'] ?? '');
            $itemRef = $row['item_id'] ?? $row['brinde_id'] ?? null;
            $quantity = (int) ($row['qty_requested'] ?? $row['quantidade'] ?? $row['quantidade_solicitada'] ?? 0);
            if (! isset($requestIds[$requestRef]) || ! $this->legacyItemExists($itemRef, $legacyItemIds) || $quantity < 1) {
                $this->invalid[] = $this->bad('solicitacao_itens.json', $index, 'Solicitação, brinde ou quantidade inválida');

                continue;
            }
            $summary['request_items']++;
        }

        foreach ($movements as $index => $row) {
            if (! is_array($row)) {
                $this->invalid[] = $this->bad('movimentacoes.json', $index, 'Registro inválido');

                continue;
            }
            $itemRef = $row['item_id'] ?? $row['brinde_id'] ?? null;
            $type = trim((string) ($row['type'] ?? $row['tipo'] ?? ''));
            $quantity = (int) ($row['qty'] ?? $row['quantidade'] ?? 0);
            if (! $this->legacyItemExists($itemRef, $legacyItemIds) || $type === '' || $quantity === 0) {
                $this->invalid[] = $this->bad('movimentacoes.json', $index, 'Brinde, tipo ou quantidade inválida');

                continue;
            }
            $summary['movements']++;
        }

        foreach ($audits as $index => $row) {
            if (! is_array($row) || trim((string) ($row['action'] ?? $row['acao'] ?? '')) === '') {
                $this->invalid[] = $this->bad('auditoria.json', $index, 'Ação de auditoria ausente');

                continue;
            }
            $summary['audit']++;
        }
    }

    private function legacyItemExists(mixed $itemRef, array $legacyItemIds): bool
    {
        if ($itemRef === null || $itemRef === '') {
            return false;
        }

        return isset($legacyItemIds[(string) $itemRef])
            || (is_numeric($itemRef) && DB::table('items')->where('id', (int) $itemRef)->exists());
    }

    private function importMovements(array $movements, array $itemsByLegacyId, array &$summary): void
    {
        foreach ($movements as $index => $row) {
            $itemId = $itemsByLegacyId[(string) ($row['item_id'] ?? $row['brinde_id'] ?? '')] ?? null;
            if (! $itemId && isset($row['item_id'])) {
                $itemId = DB::table('items')->where('id', $row['item_id'])->value('id');
            }
            if (! $itemId && isset($row['brinde_id'])) {
                $itemId = DB::table('items')->where('id', $row['brinde_id'])->value('id');
            }
            $qty = (int) ($row['qty'] ?? $row['quantidade'] ?? 0);
            $type = (string) ($row['type'] ?? $row['tipo'] ?? '');
            if (! $itemId || $qty === 0 || $type === '') {
                $this->invalid[] = $this->bad('movimentacoes.json', $index, 'Brinde, tipo ou quantidade inválida');

                continue;
            }
            $legacyKey = 'legacy-'.hash('sha256', (string) ($row['id'] ?? $row['movimentacao_id'] ?? json_encode($row, JSON_THROW_ON_ERROR)));
            if (DB::table('stock_movements')->where('idempotency_key', $legacyKey)->exists()) {
                continue;
            }

            $balance = (int) ($row['balance_after'] ?? $row['saldo_apos'] ?? DB::table('stock')->where('item_id', $itemId)->value('qty_on_hand'));
            $timestamp = $row['created_at'] ?? $row['data_hora'] ?? now();
            DB::table('stock_movements')->insert([
                'item_id' => $itemId,
                'type' => $type,
                'qty' => $qty,
                'balance_after' => $balance,
                'user_id' => $this->foreignId('users', $row['user_id'] ?? $row['usuario_id'] ?? null),
                'reason' => $row['reason'] ?? $row['motivo'] ?? null,
                'notes' => $row['notes'] ?? $row['observacoes'] ?? null,
                'document_ref' => $row['document_ref'] ?? $row['documento'] ?? null,
                'request_id' => null,
                'idempotency_key' => $legacyKey,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
            DB::table('stock')->where('item_id', $itemId)->update(['qty_on_hand' => $balance, 'updated_at' => now()]);
            $summary['movements']++;
        }
    }

    private function encodeAuditSnapshot(mixed $snapshot): ?string
    {
        if (is_string($snapshot)) {
            $snapshot = json_decode($snapshot, true);
        }
        if (! is_array($snapshot)) {
            return null;
        }

        $safe = $this->removeSensitiveAuditFields($snapshot);

        return json_encode($safe, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    private function removeSensitiveAuditFields(array $data): array
    {
        foreach ($data as $key => $value) {
            if (preg_match('/password|token|secret|authorization|(?:api|private)[_-]?key/i', (string) $key) === 1) {
                unset($data[$key]);
            } elseif (is_array($value)) {
                $data[$key] = $this->removeSensitiveAuditFields($value);
            }
        }

        return $data;
    }

    private function importLookups(array $cadastros): array
    {
        $categoryIdsByLegacyId = [];
        foreach ($cadastros['categorias'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = $this->name($row);
            if ($name !== '') {
                $category = Category::query()->updateOrCreate(['name' => $name], ['description' => $row['description'] ?? $row['descricao'] ?? null, 'active' => (bool) ($row['active'] ?? $row['ativo'] ?? true)]);
                $legacyId = $row['id'] ?? $row['categoria_id'] ?? null;
                if ($legacyId !== null) {
                    $categoryIdsByLegacyId[(string) $legacyId] = $category->id;
                }
            }
        }
        foreach (['departamentos' => 'departments', 'locais' => 'locations', 'fornecedores' => 'suppliers'] as $source => $table) {
            foreach ($cadastros[$source] ?? [] as $row) {
                $name = $this->name($row);
                if ($name !== '') {
                    DB::table($table)->updateOrInsert(['name' => $name], ['active' => (int) ($row['active'] ?? $row['ativo'] ?? 1), 'updated_at' => now(), 'created_at' => now()]);
                }
            }
        }
        foreach ($cadastros['industrias'] ?? [] as $row) {
            $name = $this->name($row);
            if ($name !== '') {
                Industry::query()->updateOrCreate(['name' => $name], ['cnpj' => $row['cnpj'] ?? null, 'active' => (bool) ($row['active'] ?? $row['ativo'] ?? true)]);
            }
        }

        return $categoryIdsByLegacyId;
    }

    private function read(string $directory, string $file): array
    {
        $path = $directory.DIRECTORY_SEPARATOR.$file;
        if (! is_file($path)) {
            return [];
        }
        $contents = file_get_contents($path);
        $decoded = json_decode((string) $contents, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            $this->invalid[] = ['file' => $file, 'row' => 0, 'reason' => 'JSON inválido'];

            return [];
        }

        return $decoded;
    }

    private function writeInvalidReport(string $directory): void
    {
        $invalid = $this->invalid;
        usort($invalid, fn (array $a, array $b) => [$a['file'], $a['row']] <=> [$b['file'], $b['row']]);
        file_put_contents($directory.DIRECTORY_SEPARATOR.'import_invalid.json', json_encode($invalid, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL, LOCK_EX);
    }

    private function bad(string $file, int $index, string $reason): array
    {
        return ['file' => $file, 'row' => $index + 1, 'reason' => $reason];
    }

    private function foreignId(string $table, mixed $id): ?int
    {
        return $id !== null && DB::table($table)->where('id', (int) $id)->exists() ? (int) $id : null;
    }

    private function name(array $row): string
    {
        return trim((string) ($row['name'] ?? $row['nome'] ?? ''));
    }
}
