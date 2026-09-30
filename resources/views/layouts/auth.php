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
  <div class="auth-layout fade-in">
    <aside class="auth-brand-panel" aria-label="Identidade do sistema">
      <div class="auth-brand-mark">
        <?php if (!empty($settings['logo_url'])): ?>
          <img src="<?= e($settings['logo_url']) ?>" alt="<?= $company ?>">
        <?php else: ?>
          <img src="<?= e(asset('img/Emefarma_Símbolo_Azul Claro.png')) ?>" alt="<?= $company ?>">
        <?php endif; ?>
      </div>
      <span class="auth-eyebrow">Gestão simples, controle completo</span>
      <h1><?= $company ?></h1>
      <p>Controle seus brindes com mais clareza, segurança e rastreabilidade em cada etapa.</p>
      <div class="auth-highlights" aria-label="Recursos do sistema">
        <span><i class="bi bi-box-seam" aria-hidden="true"></i> Estoque atualizado</span>
        <span><i class="bi bi-check2-circle" aria-hidden="true"></i> Solicitações e aprovações</span>
        <span><i class="bi bi-shield-check" aria-hidden="true"></i> Histórico e auditoria</span>
      </div>
    </aside>
    <main class="auth-card" aria-labelledby="login-title">
      <div class="auth-card-header">
        <div class="auth-mobile-logo">
          <?php if (!empty($settings['logo_url'])): ?>
            <img src="<?= e($settings['logo_url']) ?>" alt="<?= $company ?>">
          <?php else: ?>
            <img src="<?= e(asset('img/Emefarma_Símbolo_Azul Claro.png')) ?>" alt="<?= $company ?>">
          <?php endif; ?>
        </div>
        <span class="auth-kicker">Acesso seguro</span>
        <h2 id="login-title">Bem-vindo de volta</h2>
        <p>Entre para acessar o Controle de Brindes.</p>
      </div>
      <?= $content ?? '' ?>
      <p class="auth-security-note"><i class="bi bi-lock-fill" aria-hidden="true"></i> Seus dados são protegidos por uma conexão segura.</p>
    </main>
  </div>
</div>
<script src="<?= e(asset('js/api.js')) ?>"></script>
<script src="<?= e(asset('js/offline.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<?= $scripts ?? '' ?>
<script src="<?= e(asset('vendor/alpinejs/alpine.min.js')) ?>"></script>
</body>
</html>

