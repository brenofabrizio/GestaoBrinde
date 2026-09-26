<?php
/** @var array $app */
$settings = $app['settings'];
$color = e($settings['primary_color'] ?? '#2563EB');
$title = $app['page']['title'] ?? 'Entrar';
$company = e($settings['company_name'] ?? 'Controle de Brindes');
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Acesse o sistema de Controle de Brindes — <?= $company ?>">
  <meta name="robots" content="noindex, nofollow">
  <meta name="csrf-token" content="<?= e($app['csrf']) ?>">
  <meta name="base-url" content="<?= e($app['base_url']) ?>">
  <title><?= e($title . ' — ' . ($settings['company_name'] ?? '')) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="manifest" href="<?= e(url('/manifest.webmanifest')) ?>">
  <meta name="theme-color" content="#2563EB">
  <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/bootstrap.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
  <style>:root { --brand-primary: <?= $color ?>; }</style>
</head>
<body>
<div class="auth-wrap">
  <div class="auth-bg-watermark" aria-hidden="true">
    <img src="<?= e(asset('img/Emefarma_Símbolo_Azul Claro.png')) ?>" alt="">
  </div>
  <div class="auth-card fade-in">
    <div class="text-center mb-4">
      <?php if (!empty($settings['logo_url'])): ?>
        <img src="<?= e($settings['logo_url']) ?>" alt="<?= $company ?>" style="max-height:56px; margin-bottom: 12px">
      <?php else: ?>
        <img src="<?= e(asset('img/Emefarma_Símbolo_Azul Claro.png')) ?>" alt="<?= $company ?>" style="height:64px; margin:0 auto 12px; display:block; object-fit:contain; filter: drop-shadow(0 4px 12px rgba(37,99,235,0.2));">
      <?php endif; ?>
      <h1 class="h5 fw-bold mb-0" style="letter-spacing:-0.02em; color: #1e293b"><?= $company ?></h1>
      <p class="text-muted small mt-1 mb-0">Sistema de Controle de Brindes</p>
    </div>
    <?= $content ?? '' ?>
  </div>
</div>
<script src="<?= e(asset('js/api.js')) ?>"></script>
<script src="<?= e(asset('js/offline.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<?= $scripts ?? '' ?>
<script src="<?= e(asset('vendor/alpinejs/alpine.min.js')) ?>"></script>
</body>
</html>

