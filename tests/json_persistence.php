<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$testDatabase = tempnam(sys_get_temp_dir(), 'json-persistence-');
$testJsonDir = sys_get_temp_dir() . '/json-persistence-' . bin2hex(random_bytes(5));
putenv('DB_DRIVER=sqlite');
putenv('DB_PATH=' . $testDatabase);
putenv('JSON_DB_PATH=' . $testJsonDir);
require $root . '/app/bootstrap.php';
\App\Support\Installer::install(true, static function (string $_line): void {});
\App\Core\Db::pdo()->exec('PRAGMA journal_mode=DELETE');

use App\Support\VercelJsonStore;
use App\Support\JsonDatabase;
use App\Support\JsonSnapshotConflict;

$checks = 0;
$failures = [];
$blobs = [];
$failPath = null;
$authorizationHeaders = [];
$conditionalHeaders = [];
$privateAccessHeaders = [];
$injectCasConflict = false;

$transport = static function (string $method, string $url, string $token, ?string $body, array $headers, int $timeout) use (&$blobs, &$failPath, &$authorizationHeaders, &$conditionalHeaders, &$privateAccessHeaders, &$injectCasConflict): array {
    foreach ($headers as $header) {
        if (str_starts_with($header, 'Authorization:')) {
            $authorizationHeaders[] = $header;
        }
        if (str_starts_with(strtolower($header), 'x-if-match:')) {
            $conditionalHeaders[] = $header;
        }
        if (str_starts_with(strtolower($header), 'x-vercel-blob-access:')) {
            $privateAccessHeaders[] = $header;
        }
    }
    if ($method === 'PUT') {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $pathname = (string) ($query['pathname'] ?? '');
        if ($failPath !== null && str_ends_with($pathname, $failPath)) {
            return ['code' => 503, 'body' => 'simulated storage failure'];
        }
        if ($pathname === 'brindes-json/v1/current.json' && $injectCasConflict && isset($blobs[$pathname])) {
            $injectCasConflict = false;
            $external = json_decode($blobs[$pathname], true);
            if (is_array($external)) {
                $external['revision'] = 'external-writer';
                $blobs[$pathname] = json_encode($external, JSON_UNESCAPED_SLASHES);
            }
        }
        $allowOverwrite = in_array('x-allow-overwrite: 1', $headers, true);
        $ifMatch = null;
        foreach ($headers as $header) {
            if (str_starts_with(strtolower($header), 'x-if-match:')) {
                $ifMatch = trim(substr($header, strpos($header, ':') + 1));
            }
        }
        if (isset($blobs[$pathname])) {
            if (!$allowOverwrite) {
                return ['code' => 409, 'body' => 'already exists'];
            }
            $currentEtag = hash('sha256', (string) $blobs[$pathname]);
            if ($ifMatch !== null && !hash_equals($currentEtag, $ifMatch)) {
                return ['code' => 412, 'body' => 'precondition failed'];
            }
        }
        $blobs[$pathname] = $body ?? '';
        return ['code' => 200, 'body' => json_encode(['url' => 'https://unit.private.blob.vercel-storage.com/' . rawurlencode($pathname)])];
    }

    $path = (string) parse_url($url, PHP_URL_PATH);
    if ($path === '/') {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        if (isset($query['url'])) {
            $metadataUrl = (string) $query['url'];
            $metadataPath = rawurldecode(ltrim((string) parse_url($metadataUrl, PHP_URL_PATH), '/'));
            if (!isset($blobs[$metadataPath])) {
                return ['code' => 404, 'body' => 'not found'];
            }
            return ['code' => 200, 'body' => json_encode([
                'etag' => hash('sha256', (string) $blobs[$metadataPath]),
                'pathname' => $metadataPath,
                'url' => $metadataUrl,
            ])];
        }
        $prefix = (string) ($query['prefix'] ?? '');
        $listed = [];
        foreach ($blobs as $pathname => $contents) {
            if (str_starts_with($pathname, $prefix)) {
                $listed[] = [
                    'pathname' => $pathname,
                    'uploadedAt' => '2026-10-02T00:00:00.000Z',
                    'url' => 'https://unit.private.blob.vercel-storage.com/' . rawurlencode($pathname),
                ];
            }
        }
        return ['code' => 200, 'body' => json_encode(['blobs' => $listed])];
    }
    $pathname = rawurldecode(ltrim($path, '/'));
    return isset($blobs[$pathname])
        ? ['code' => 200, 'body' => $blobs[$pathname]]
        : ['code' => 404, 'body' => 'not found'];
};

$expect = static function (bool $condition, string $label) use (&$checks, &$failures): void {
    $checks++;
    if (!$condition) {
        $failures[] = $label;
    }
};

$expect(JsonDatabase::documentsForTables(['stock_movements']) === ['inventory.json'], 'movimentação atualiza apenas o JSON de estoque');
$expect(JsonDatabase::documentsForTables(['request_items', 'approvals']) === ['requests.json'], 'TRADE atualiza apenas o JSON de solicitações');
$expect(count(JsonDatabase::documentsForTables(null)) === 7, 'snapshot inicial contém os sete domínios JSON');
\App\Core\Db::clearMutationState();
\App\Core\Db::insert('sequences', ['name' => 'local-json', 'period' => '2030', 'last_value' => 7]);
$expect(\App\Core\Db::mutatedTables() === ['sequences'], 'SQL identifica a tabela alterada para atualizar apenas seu domínio');
JsonDatabase::mirrorFromPdo(['sequences']);
$localSequences = JsonDatabase::read('sequences');
$expect(count(array_filter($localSequences, static fn (array $row): bool => ($row['name'] ?? null) === 'local-json')) === 1, 'espelho local JSON reflete uma gravação SQL');

$restoreDocuments = [];
foreach (\App\Support\Installer::tables() as $table) {
    $file = JsonDatabase::documentsForTables([$table])[0];
    $restoreDocuments[$file] ??= ['schema_version' => 1, 'generated_at' => date(DATE_ATOM), 'tables' => []];
    $restoreDocuments[$file]['tables'][$table] = [];
}
$restoreDocuments['system.json']['tables']['sequences'] = [
    ['name' => 'json-test', 'period' => '2030', 'last_value' => 41],
];
\App\Core\Db::insert('sequences', ['name' => 'stale-test', 'period' => '2030', 'last_value' => 9]);
try {
    JsonDatabase::restoreSnapshot($restoreDocuments, 'revision-test-1');
    $restored = \App\Core\Db::fetch('SELECT last_value FROM sequences WHERE name = ? AND period = ?', ['json-test', '2030']);
    $stale = \App\Core\Db::fetch('SELECT last_value FROM sequences WHERE name = ? AND period = ?', ['stale-test', '2030']);
    $expect((int) ($restored['last_value'] ?? 0) === 41, 'restaura os dados JSON no SQLite de execução');
    $expect($stale === null, 'substitui o estado antigo pelo snapshot JSON completo');
    $expect(JsonDatabase::localSnapshotRevision($testDatabase) === 'revision-test-1', 'cache aceita banco cujo hash corresponde ao marcador remoto');
    \App\Core\Db::insert('sequences', ['name' => 'crash-test', 'period' => '2030', 'last_value' => 99]);
    $expect(JsonDatabase::localSnapshotRevision($testDatabase) === null, 'cache modificado sem commit JSON força restauração');
    JsonDatabase::restoreSnapshot($restoreDocuments, 'revision-test-2');
    $crash = \App\Core\Db::fetch('SELECT last_value FROM sequences WHERE name = ? AND period = ?', ['crash-test', '2030']);
    $expect($crash === null, 'nova restauração descarta estado local que não foi salvo no Blob');
    $expect(JsonDatabase::localSnapshotRevision($testDatabase) === 'revision-test-2', 'cache reidratado recebe hash e revisão novos');
} catch (RuntimeException $error) {
    $expect(false, 'snapshot JSON válido deve ser restaurado: ' . $error->getMessage());
}
try {
    JsonDatabase::restoreSnapshot(['inventory.json' => []], 'invalid-revision');
    $expect(false, 'snapshot incompleto precisa ser recusado');
} catch (RuntimeException) {
    $current = \App\Core\Db::fetch('SELECT last_value FROM sequences WHERE name = ? AND period = ?', ['json-test', '2030']);
    $expect((int) ($current['last_value'] ?? 0) === 41, 'snapshot inválido não apaga o último estado restaurado');
}

$store = new VercelJsonStore('unit', $transport, 'store_unit');
try {
    new VercelJsonStore('unit', $transport);
    $expect(false, 'store id é obrigatório ao usar credencial de escrita');
} catch (RuntimeException) {
    $expect(true, 'store id é obrigatório ao usar credencial de escrita');
}
$expect($store->hasSnapshot() === false, 'storage identifica que ainda não existe snapshot');
$store->save([
    'inventory.json' => ['items' => [['id' => 11, 'name' => 'Caneca']]],
    'trade.json' => ['requests' => [['id' => 21, 'status' => 'pendente']]],
]);
$first = $store->load();
$expect(in_array('Authorization: Bearer unit', $authorizationHeaders, true), 'requisições privadas usam Authorization Bearer');
$expect(in_array('x-vercel-blob-access: private', $privateAccessHeaders, true), 'JSONs são armazenados sem acesso público');
$expect($store->hasSnapshot() === true, 'storage detecta o manifesto publicado');
$expect(($first['inventory.json']['items'][0]['id'] ?? null) === 11, 'restaura o JSON de estoque da versão publicada');
$manifestPath = 'brindes-json/v1/current.json';
$validManifestBody = $blobs[$manifestPath];
$tamperedManifest = json_decode($validManifestBody, true);
$tamperedManifest['files']['trade.json'] = 'https://attacker.private.blob.vercel-storage.com/injected.json';
$blobs[$manifestPath] = json_encode($tamperedManifest, JSON_UNESCAPED_SLASHES);
try {
    $store->loadSnapshot();
    $expect(false, 'manifesto não pode redirecionar token para outro Blob host');
} catch (RuntimeException) {
    $expect(true, 'manifesto não pode redirecionar token para outro Blob host');
}
$blobs[$manifestPath] = $validManifestBody;
$snapshot = $store->loadSnapshot();
$expect(is_string($snapshot['revision'] ?? null) && $snapshot['revision'] !== '', 'retorna a revisão do manifesto carregado');
$expect(($snapshot['documents']['trade.json']['requests'][0]['status'] ?? null) === 'pendente', 'revisão identifica exatamente os documentos restaurados');
$unchanged = $store->loadSnapshot($snapshot['revision']);
$expect(array_key_exists('documents', $unchanged) && $unchanged['documents'] === null, 'não baixa novamente arquivos da mesma revisão');
$expect(($first['trade.json']['requests'][0]['status'] ?? null) === 'pendente', 'restaura o JSON TRADE da mesma versão');
$expect(isset($blobs['brindes-json/v1/current.json']), 'publica o manifesto somente após os arquivos JSON versionados');

$oldManifest = $blobs['brindes-json/v1/current.json'];
$failPath = 'brindes-json/v1/snapshots/failing/inventory.json';
$store->save([
    'inventory.json' => ['items' => [['id' => 12, 'name' => 'Garrafa']]],
], $snapshot['revision']);
$second = $store->load();
$expect(($second['inventory.json']['items'][0]['id'] ?? null) === 12, 'a versão nova substitui a versão anterior após commit');
$expect(($second['trade.json']['requests'][0]['status'] ?? null) === 'pendente', 'atualizar um domínio preserva os demais arquivos JSON');
$expect($oldManifest !== $blobs['brindes-json/v1/current.json'], 'o manifesto identifica a versão íntegra atual');

$failPath = '/ledger.json';
try {
    $latestRevision = $store->loadSnapshot()['revision'];
    $store->save(['ledger.json' => ['movements' => []]], $latestRevision);
    $expect(false, 'falha de upload interrompe o commit');
} catch (RuntimeException) {
    $expect(true, 'falha de upload interrompe o commit');
}
$afterFailure = $store->load();
$expect(($afterFailure['inventory.json']['items'][0]['id'] ?? null) === 12, 'falha parcial mantém o manifesto na última versão íntegra');

$baseRevision = $store->loadSnapshot()['revision'];
$injectCasConflict = true;
try {
    $store->save(['inventory.json' => ['items' => [['id' => 99, 'name' => 'Concorrente']]]], $baseRevision);
    $expect(false, 'gravação concorrente não pode sobrescrever snapshot alheio');
} catch (JsonSnapshotConflict) {
    $expect(true, 'gravação concorrente não pode sobrescrever snapshot alheio');
}
$afterConflict = $store->loadSnapshot();
$expect(($afterConflict['revision'] ?? null) === 'external-writer', 'conflito conserva a revisão publicada pelo outro escritor');
$expect(($afterConflict['documents']['inventory.json']['items'][0]['id'] ?? null) === 12, 'conflito não perde o último estoque confirmado');
$expect($conditionalHeaders !== [], 'manifesto é publicado com compare-and-swap por ETag');

try {
    (new VercelJsonStore('', $transport))->save(['inventory.json' => []]);
    $expect(false, 'recusa gravação sem token de persistência');
} catch (RuntimeException) {
    $expect(true, 'recusa gravação sem token de persistência');
}

try {
    \App\Core\Db::disconnect();
    foreach ([$testDatabase, $testDatabase . '-wal', $testDatabase . '-shm'] as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
    foreach (glob($testJsonDir . '/*.json') ?: [] as $path) {
        @unlink($path);
    }
    @unlink($testJsonDir . '/.snapshot-revision');
    @rmdir($testJsonDir);
} catch (Throwable) {
}

if ($failures !== []) {
    fwrite(STDERR, 'FAIL ' . $checks . " checks\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo 'OK ' . $checks . " checks\n";
