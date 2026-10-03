<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$checks = 0;
$failures = [];

function expectContains(string $path, string $needle, string $label): void
{
    global $root, $checks, $failures;
    $checks++;
    $content = (string) file_get_contents($root . '/' . $path);
    if (!str_contains($content, $needle)) {
        $failures[] = $label . " — faltando: {$needle}";
    }
}

function expectNotContains(string $path, string $needle, string $label): void
{
    global $root, $checks, $failures;
    $checks++;
    $content = (string) file_get_contents($root . '/' . $path);
    if (str_contains($content, $needle)) {
        $failures[] = $label . " — encontrado: {$needle}";
    }
}

expectContains('resources/views/pages/stock/index.php', 'Api.fmt.qty(m.quantity)', 'Livro de movimentações usa quantity da API');
expectNotContains('resources/views/pages/stock/index.php', 'Api.fmt.qty(m.qty)', 'Livro de movimentações não usa chave inexistente qty');
expectContains('resources/views/pages/trade/show.php', 'loadError', 'Detalhe TRADE possui estado de erro');
expectContains('resources/views/pages/trade/show.php', 'catch (e)', 'Detalhe TRADE trata falha da API');
expectContains('resources/views/pages/trade/form.php', 'items.length === 0', 'Formulário TRADE valida inclusão de brindes');
expectContains('resources/views/pages/trade/form.php', 'UI.fieldErrors', 'Formulário TRADE exibe erros de campos');
expectContains('resources/views/layouts/app.php', 'notificationPopover', 'Layout possui modal flutuante de notificações');
expectContains('resources/views/layouts/auth.php', 'auth-login-shell', 'Login usa o novo shell compacto alinhado à interface do sistema');
expectContains('resources/views/layouts/auth.php', 'auth-login-card', 'Login mantém cartão central focado nas credenciais');
expectContains('public/assets/css/app.css', '.auth-login-shell', 'Estilo do novo layout de autenticação está definido');
expectContains('resources/views/pages/auth/login.php', 'auth-submit', 'Login possui ação visual principal');
expectContains('resources/views/layouts/app.php', 'Marcar todas como lidas', 'Modal de notificações possui marcar todas como lidas');
expectContains('resources/views/layouts/app.php', 'refreshDataBtn', 'Cabeçalho possui botão para atualizar os dados');
expectContains('public/assets/js/app.js', "searchParams.set('_refresh'", 'Botão atualizar força nova leitura da página');
expectContains('app/Services/SettingsService.php', 'lecom_supply_form_url', 'URL Lecom é configurável');
expectContains('app/Services/SettingsService.php', 'lecom_portal_url', 'Portal Lecom é configurável');
expectContains('app/Services/SettingsService.php', 'lecomApiStartUrl', 'Endpoint de abertura Lecom é gerado');
expectContains('app/Services/SettingsService.php', 'lecomWorkspaceStartUrl', 'Rota Workspace da biblioteca é gerada');
expectContains('resources/views/pages/settings/index.php', 'lecom_process_version', 'Versão do processo Lecom é configurável');
expectContains('resources/views/pages/trade/form.php', 'Abrir chamado no Lecom', 'Nova solicitação possui botão para abrir chamado');
expectContains('resources/views/pages/trade/form.php', 'LecomSSOTicket', 'Nova solicitação lê o ticket SSO do cookie');
expectContains('resources/views/pages/trade/form.php', 'ticket-sso', 'Nova solicitação envia o header ticket-sso');
expectContains('resources/views/pages/trade/form.php', 'workspace/api/process/start', 'Nova solicitação usa o endpoint Workspace');
expectNotContains('resources/views/pages/trade/index.php', '/abrir-chamado?', 'Índice TRADE não possui aba separada de abertura');
expectNotContains('app/pages.php', "'/abrir-chamado'", 'Rota da aba separada de abertura foi removida');
expectContains('resources/views/pages/items/show.php', 'Abrir formulário Lecom', 'Detalhe do brinde possui botão Lecom');
expectContains('resources/views/pages/stock/_form.php', 'data-can-lookups', 'Entrada não consulta cadastros sem permissão');
expectContains('app/Support/Installer.php', "'stock.entry'", 'Perfil CD recebe permissão de entrada na atualização');
expectContains('app/Support/DemoSqliteStore.php', 'return true;', 'Homologação inicia com fluxo fictício completo');
expectContains('app/Support/VercelJsonStore.php', 'Authorization: Bearer ', 'Snapshots JSON autenticam o Blob com Bearer token');
expectContains('app/Support/Installer.php', 'resetVercelDemoToBlank', 'Homologação limpa dados operacionais');
expectContains('app/Support/DemoSeeder.php', 'runCatalogs', 'Homologação recria cadastros e brindes antes do fluxo');
expectContains('app/Support/DemoSeeder.php', 'CH-DEMO-HOJE', 'Base fictícia possui entrada do dia');
expectContains('app/Support/DemoSeeder.php', 'NF-DEMO-001', 'Base fictícia percorre aprovação e recebimento TRADE');
expectContains('app/Support/DemoSeeder.php', "date('Y-m-d 10:00:00')", 'Dados fictícios usam horário estável');
expectContains('app/bootstrap.php', 'date_default_timezone_set((string) Config::get', 'Fuso é aplicado antes da criação da base demo');
expectContains('app/bootstrap.php', "json-runtime-v1.sqlite", 'SQLite é apenas cache efêmero do snapshot JSON');
expectContains('app/bootstrap.php', 'JsonDatabase::restoreFromBlob', 'Runtime restaura o snapshot JSON antes de servir a requisição');
expectContains('public/sw.js', "const VERSION = 'gestao-brindes-v3'", 'Cache antigo do navegador é invalidado');
expectContains('public/sw.js', "fetch(request, { cache: 'no-store' })", 'APIs não usam snapshots antigos');
expectContains('public/sw.js', "'/assets/js/state-sync.js'", 'Cache inclui sincronização entre abas');
expectContains('public/assets/js/api.js', 'BrindesSync?.touch(path)', 'Mutações invalidam os dados das telas abertas');
expectContains('public/assets/js/api.js', 'CSRF_INVALID', 'Mutações renovam CSRF expirado antes de falhar');
expectContains('resources/views/pages/trade/show.php', 'Solicitação aprovada e encaminhada', 'Aprovação TRADE confirma a transição concluída');
expectContains('resources/views/pages/trade/show.php', "this.loadError = ''", 'Falha de atualização não bloqueia detalhe TRADE já carregado');
expectContains('resources/views/pages/requests/show.php', 'Não foi possível carregar a solicitação', 'Detalhe interno exibe erro em vez de ficar em branco');
expectContains('resources/views/pages/requests/show.php', 'BrindesSync?.listen', 'Detalhe interno atualiza após alterações');
expectContains('public/assets/js/state-sync.js', "localStorage.setItem(KEY", 'localStorage guarda somente a revisão dos dados');
expectContains('public/assets/js/state-sync.js', 'server database remains the source of truth', 'Sincronização não cria uma segunda base de negócio no navegador');
expectNotContains('public/assets/js/state-sync.js', 'setInterval', 'Sincronização não faz polling que sobrescreve dados atuais');
expectContains('public/assets/js/state-sync.js', 'origin !== TAB_ID', 'A aba que gravou não recarrega snapshot antigo da própria alteração');
expectContains('resources/views/pages/requests/index.php', 'Solicitações internas', 'Tela interna explica seu fluxo');
expectContains('resources/views/pages/trade/index.php', 'Solicitações TRADE', 'Tela TRADE explica seu fluxo de compra');
expectContains('resources/views/pages/trade/index.php', 'Number.isInteger(Number(r.id))', 'Lista TRADE bloqueia links com identificador inválido');
expectContains('resources/views/pages/items/index.php', 'BrindesSync?.listen', 'Aba Brindes atualiza após alterações de estoque');
expectContains('app/Support/JsonDatabase.php', "use App\\Core\\Env;", 'Espelho JSON resolve Env no namespace correto');
expectContains('app/Support/JsonDatabase.php', "sys_get_temp_dir() . '/brindes/storage/json'", 'Espelho JSON funciona no runtime Vercel');
expectContains('app/Support/JsonDatabase.php', 'new VercelJsonStore', 'Snapshots JSON são sincronizados ao storage persistente');
expectContains('app/Support/JsonDatabase.php', 'BLOB_STORE_ID', 'Runtime exige o identificador do storage alvo');
expectContains('app/Support/JsonDatabase.php', 'documentsForTables', 'Snapshots JSON são agrupados por domínio alterado');
expectContains('app/Core/Db.php', 'mutatedTables', 'Runtime rastreia tabelas alteradas para sincronização incremental');
expectContains('app/Core/Db.php', "Env::get('JSON_DB_MIRROR', false) === true && !Env::get('VERCEL')", 'Inicialização Vercel não publica o seed antes de restaurar o snapshot remoto');
expectContains('app/Support/VercelJsonStore.php', 'x-if-match: ', 'Manifesto JSON usa escrita condicional para evitar perda concorrente');
expectContains('app/Support/VercelJsonStore.php', 'headEtag', 'CAS lê ETag atual do manifesto privado');
expectContains('app/Support/JsonDatabase.php', 'restoreSnapshot', 'Instância fria restaura tabelas a partir dos JSONs');
expectContains('app/Support/JsonDatabase.php', 'sqlite_fingerprint', 'Cache local não é confiável após interrupção de gravação');
expectContains('app/bootstrap.php', 'if (!$restored)', 'Primeira inicialização detecta que ainda não há snapshot remoto');
expectContains('app/bootstrap.php', 'JsonDatabase::mirrorFromPdo();', 'Primeira inicialização publica baseline completo independente de SQL rastreado');
expectContains('public/index.php', 'CONCURRENT_UPDATE', 'Conflito concorrente retorna 409 explícito');
expectContains('app/Controllers/LookupController.php', 'LOWER(name)', 'Cadastros rejeitam duplicidade sem diferenciar maiúsculas');
expectContains('app/Support/Installer.php', 'lookups.view', 'Restrição do CD remove acesso a Cadastros');
expectContains('app/Support/Installer.php', 'requests.view_all', 'Restrição do CD remove acesso geral a solicitações');
expectContains('app/Support/SqliteSchema.php', "datetime('now','-3 hours')", 'SQLite grava timestamps no timezone da aplicação');
expectContains('bin/export-json.php', 'redacted_user_fields', 'Exportação JSON declara campos sensíveis removidos');

if ($failures !== []) {
    fwrite(STDERR, "FAIL {$checks} checks\n" . implode("\n", array_map(fn (string $f) => '- ' . $f, $failures)) . "\n");
    exit(1);
}

echo "OK {$checks} checks\n";
