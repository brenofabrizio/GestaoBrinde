<?php
$code = (int) ($app['page']['query']['codigo'] ?? 26);
$version = (int) ($app['page']['query']['versao'] ?? 10);
$portal = trim((string) setting('lecom_portal_url', ''));
$legacy = trim((string) setting('lecom_supply_form_url', ''));
$target = $portal !== '' ? rtrim($portal, '/') . '/form-web/?' . http_build_query([
    'processId' => $code,
    'version' => $version,
    'newWS' => 'true',
], '', '&', PHP_QUERY_RFC3986) : '';
if ($target === '' && $legacy !== '') {
    $target = str_replace(['{codigo}', '{versao}'], [(string) $code, (string) $version], $legacy);
}
$apiBase = rtrim(trim((string) setting('lecom_api_base_url', 'https://api.lecom.com.br/service/bpm/api')), '/');
$apiEndpoint = $apiBase . '/v1/process-instances';
$portalHost = $portal !== '' ? parse_url($portal, PHP_URL_HOST) : null;
$workspaceEndpoint = $portal !== '' ? rtrim($portal, '/') . '/workspace/api/process/start?' . http_build_query([
    'processId' => $code,
    'version' => $version,
], '', '&', PHP_QUERY_RFC3986) : '';
ob_start(); ?>
<div class="card card-body" style="max-width:760px">
  <h2 class="h5">Abrir chamado de suprimentos</h2>
  <p class="text-muted">Processo Lecom: código <?= e((string) $code) ?> · versão <?= e((string) $version) ?>.</p>
  <?php if ($target !== ''): ?>
    <a class="btn btn-primary align-self-start" href="<?= e($target) ?>" target="_blank" rel="noopener">Continuar para o Lecom</a>
  <?php else: ?>
    <div class="alert alert-warning mb-3">O endereço do portal Lecom ainda não foi configurado.</div>
    <p class="small text-muted mb-0">Peça ao Administrador para informar o endereço base do portal Lecom em Configurações. O sistema usará automaticamente o código 26 e a versão 10.</p>
  <?php endif; ?>
  <hr>
  <details class="small">
    <summary class="fw-semibold">Como o link de criação é formado pela API</summary>
    <p class="mt-2 mb-2">A biblioteca documenta a abertura via <code>POST</code> para:</p>
    <p><code><?= e($apiEndpoint) ?></code></p>
    <p class="mb-1">Cabeçalhos: <code>apikey</code> (somente no servidor) e <code>X-Server: <?= e((string) ($portalHost ?: 'dominio-do-portal')) ?></code>.</p>
    <pre class="mb-2"><code><?= e(json_encode(['processId' => $code, 'version' => $version, 'language' => 'pt_BR'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></code></pre>
    <p class="mb-1">No script local da biblioteca, o fluxo SSO usa <code>PUT</code> para:</p>
    <p><code><?= e($workspaceEndpoint !== '' ? $workspaceEndpoint : '{portal}/workspace/api/process/start?processId=' . $code . '&version=' . $version) ?></code></p>
    <p class="mb-2">Essa rota depende do cookie SSO do Lecom e, por isso, deve ser chamada dentro do portal. Em ambos os casos, a resposta fornece <code>processInstanceId</code>.</p>
    <p class="mb-0">O formulário da instância é aberto em <code><?= e(($portal !== '' ? rtrim($portal, '/') : '{portal}') . '/workspace/form-app/{processInstanceId}/1/1?isNewForm=true') ?></code>.</p>
  </details>
</div>
<?php
$content = ob_get_clean();
include BASE_PATH . '/resources/views/layouts/app.php';
