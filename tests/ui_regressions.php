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
expectContains('resources/views/pages/items/show.php', 'Abrir formulário Lecom', 'Detalhe do brinde possui botão Lecom');
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
