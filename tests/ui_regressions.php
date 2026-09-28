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
expectContains('app/Support/DemoSqliteStore.php', 'brindes-demo-v6.sqlite', 'Homologação inicia namespace novo');
expectContains('app/Support/DemoSqliteStore.php', 'return false;', 'Homologação inicia sem dados fictícios');
expectContains('app/Support/Installer.php', 'resetVercelDemoToBlank', 'Homologação limpa dados operacionais');
expectContains('app/Support/DemoSeeder.php', 'CH-DEMO-HOJE', 'Base fictícia possui entrada do dia');
expectContains('app/Support/DemoSeeder.php', "date('Y-m-d 10:00:00')", 'Dados fictícios usam horário estável');
expectContains('app/bootstrap.php', 'date_default_timezone_set((string) Config::get', 'Fuso é aplicado antes da criação da base demo');
expectContains('public/sw.js', "const VERSION = 'gestao-brindes-v2'", 'Cache antigo do navegador é invalidado');
expectContains('public/sw.js', "fetch(request, { cache: 'no-store' })", 'APIs não usam snapshots antigos');
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
