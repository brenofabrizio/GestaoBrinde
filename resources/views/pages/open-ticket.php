<?php
$code = (int) ($app['page']['query']['codigo'] ?? 26);
$version = (int) ($app['page']['query']['versao'] ?? 10);
$configured = trim((string) setting('lecom_supply_form_url', ''));
$target = $configured !== '' ? $configured : '';
if ($target !== '') {
    $target = str_replace(['{codigo}', '{versao}'], [(string) $code, (string) $version], $target);
}
ob_start(); ?>
<div class="card card-body" style="max-width:720px">
  <h2 class="h5">Abrir chamado de suprimentos</h2>
  <p class="text-muted">Processo Lecom: código <?= e((string) $code) ?> · versão <?= e((string) $version) ?>.</p>
  <?php if ($target !== ''): ?>
    <a class="btn btn-primary align-self-start" href="<?= e($target) ?>" target="_blank" rel="noopener">Continuar para o Lecom</a>
  <?php else: ?>
    <div class="alert alert-warning mb-3">O endereço do portal Lecom ainda não foi configurado.</div>
    <p class="small text-muted mb-0">Peça ao Administrador para informar a URL do formulário Lecom em Configurações. O botão usará automaticamente o código 26 e a versão 10.</p>
  <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
include BASE_PATH . '/resources/views/layouts/app.php';
