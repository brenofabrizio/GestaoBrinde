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
expectContains('app/Support/DemoSqliteStore.php', 'brindes-demo-v7.sqlite', 'Homologação inicia namespace novo');
expectContains('app/Support/DemoSqliteStore.php', "'Authorization: ' . 'Bearer ' . \$token", 'Persistência Vercel autentica o Blob com Bearer token');
expectNotContains('app/Support/DemoSqliteStore.php', 'Authorization: ***', 'Persistência Vercel não usa header mascarado');
expectContains('app/Support/DemoSqliteStore.php', 'return true;', 'Homologação inicia com fluxo fictício completo');
expectContains('app/Support/Installer.php', 'resetVercelDemoToBlank', 'Homologação limpa dados operacionais');
expectContains('app/Support/DemoSeeder.php', 'runCatalogs', 'Homologação recria cadastros e brindes antes do fluxo');
expectContains('app/Support/DemoSeeder.php', 'CH-DEMO-HOJE', 'Base fictícia possui entrada do dia');
expectContains('app/Support/DemoSeeder.php', 'NF-DEMO-001', 'Base fictícia percorre aprovação e recebimento TRADE');
expectContains('app/Support/DemoSeeder.php', "date('Y-m-d 10:00:00')", 'Dados fictícios usam horário estável');
expectContains('app/bootstrap.php', 'date_default_timezone_set((string) Config::get', 'Fuso é aplicado antes da criação da base demo');
expectContains('app/bootstrap.php', "demo-v7.sqlite", 'Runtime SQLite usa o mesmo namespace da base demo');
expectContains('public/sw.js', "const VERSION = 'gestao-brindes-v3'", 'Cache antigo do navegador é invalidado');
expectContains('public/sw.js', "fetch(request, { cache: 'no-store' })", 'APIs não usam snapshots antigos');
expectContains('public/sw.js', "'/assets/js/state-sync.js'", 'Cache inclui sincronização entre abas');
expectContains('public/assets/js/api.js', 'BrindesSync?.touch(path)', 'Mutações invalidam os dados das telas abertas');
expectContains('public/assets/js/api.js', 'CSRF_INVALID', 'Mutações renovam CSRF expirado antes de falhar');
expectContains('resources/views/pages/trade/show.php', 'Solicitação aprovada e encaminhada', 'Aprovação TRADE confirma a transição concluída');
expectContains('resources/views/pages/trade/show.php', "this.loadError = ''", 'Falha de atualização não bloqueia detalhe TRADE já carregado');
expectContains('public/assets/js/state-sync.js', "localStorage.setItem(KEY", 'localStorage guarda somente a revisão dos dados');
expectContains('public/assets/js/state-sync.js', 'server database remains the source of truth', 'Sincronização não cria uma segunda base de negócio no navegador');
expectContains('resources/views/pages/requests/index.php', 'Solicitações internas', 'Tela interna explica seu fluxo');
expectContains('resources/views/pages/trade/index.php', 'Solicitações TRADE', 'Tela TRADE explica seu fluxo de compra');
expectContains('resources/views/pages/trade/index.php', 'Number.isInteger(Number(r.id))', 'Lista TRADE bloqueia links com identificador inválido');
expectContains('resources/views/pages/items/index.php', 'BrindesSync?.listen', 'Aba Brindes atualiza após alterações de estoque');
expectContains('app/Support/JsonDatabase.php', "use App\\Core\\Env;", 'Espelho JSON resolve Env no namespace correto');
expectContains('app/Support/JsonDatabase.php', "sys_get_temp_dir() . '/brindes/storage/json'", 'Espelho JSON funciona no runtime Vercel');
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
