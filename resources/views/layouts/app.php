<?php
/** @var array $app */
$user = $app['user'];
$settings = $app['settings'];
$color = e($settings['primary_color'] ?? '#2563EB');
$title = $app['page']['title'] ?? '';
$unread = 0;
$mustChangePassword = !empty($user['must_change_password']);
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <meta name="csrf-token" content="<?= e($app['csrf']) ?>">
  <meta name="base-url" content="<?= e($app['base_url']) ?>">
  <title><?= e($title . ' — ' . ($settings['company_name'] ?? '')) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/bootstrap.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
  <style>:root { --brand-primary: <?= $color ?>; }</style>
</head>
<body data-must-change-password="<?= $mustChangePassword ? '1' : '0' ?>">
<div class="app-shell">
  <aside class="sidebar d-none d-lg-flex" role="complementary" aria-label="Menu lateral">
    <a class="sidebar-brand" href="<?= e(url('/dashboard')) ?>" aria-label="Ir para o painel">
      <?php if (!empty($settings['logo_url'])): ?>
        <img src="<?= e($settings['logo_url']) ?>" alt="<?= e($settings['company_name']) ?>">
      <?php else: ?>
        <div class="sidebar-brand-icon" aria-hidden="true"><i class="bi bi-gift"></i></div>
      <?php endif; ?>
      <span><?= e($settings['company_name']) ?></span>
    </a>
    <nav class="sidebar-nav" role="navigation" aria-label="Navegação principal">
      <?php foreach ($app['menu'] as $group): ?>
        <div class="nav-group" role="presentation"><?= e($group['group']) ?></div>
        <?php foreach ($group['items'] as $item): ?>
          <a class="nav-link<?= $item['active'] ? ' active' : '' ?>"
             href="<?= e($item['url']) ?>"
             <?= $item['active'] ? 'aria-current="page"' : '' ?>>
            <i class="bi bi-<?= e($item['icon']) ?>" aria-hidden="true"></i>
            <span><?= e($item['label']) ?></span>
          </a>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar-footer">
      v<?= defined('APP_VERSION') ? e(APP_VERSION) : '1.2.0' ?>
    </div>
  </aside>
  <div class="main">
    <header class="topbar" role="banner">
      <button class="btn btn-outline-secondary d-lg-none" type="button"
              data-bs-toggle="offcanvas" data-bs-target="#mobileNav"
              aria-label="Abrir menu" aria-expanded="false" aria-controls="mobileNav">
        <i class="bi bi-list" aria-hidden="true"></i>
      </button>
      <h1><?= e($title) ?></h1>
      <form class="d-none d-md-flex flex-grow-1" style="max-width:300px"
            action="<?= e(url('/brindes')) ?>" method="get" role="search" aria-label="Busca rápida">
        <input class="form-control form-control-sm" type="search" name="q"
               placeholder="Buscar brinde, código…" aria-label="Buscar brinde">
      </form>
      <?php if (!$mustChangePassword): ?>
      <div class="position-relative">
        <button class="btn btn-light" type="button" id="bellBtn"
                aria-label="Notificações" aria-haspopup="dialog" aria-expanded="false" aria-controls="notificationPopover">
          <i class="bi bi-bell" aria-hidden="true"></i>
        </button>
        <span class="bell-dot d-none" id="bellDot" aria-hidden="true"></span>
        <div id="notificationPopover" class="notification-popover shadow" role="dialog" aria-label="Notificações" hidden>
          <div class="notification-popover-header">
            <strong>Notificações</strong>
            <button type="button" class="btn btn-sm btn-link" id="notificationReadAll">Marcar todas como lidas</button>
          </div>
          <div id="notificationList" class="notification-list">
            <div class="notification-empty">Carregando…</div>
          </div>
          <div class="notification-popover-footer">
            <a href="<?= e(url('/notificacoes')) ?>" id="notificationSeeAll">Ver todas</a>
          </div>
        </div>
      </div>
      <?php endif; ?>
      <div class="dropdown">
        <button class="btn btn-light dropdown-toggle user-chip" data-bs-toggle="dropdown"
                aria-expanded="false" aria-haspopup="true" id="userMenuBtn">
          <i class="bi bi-person-circle" aria-hidden="true"></i>
          <span class="d-none d-sm-inline"><?= e($user['name'] ?? '') ?></span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenuBtn">
          <li class="dropdown-item-text small text-muted"><?= e($user['role']['name'] ?? '') ?></li>
          <li><a class="dropdown-item" href="<?= e(url('/perfil')) ?>">Meu perfil</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><button class="dropdown-item text-danger" type="button" id="logoutBtn">
            <i class="bi bi-box-arrow-left me-1" aria-hidden="true"></i>Sair
          </button></li>
        </ul>
      </div>
    </header>
    <div class="offcanvas offcanvas-start text-bg-dark" id="mobileNav" tabindex="-1"
         aria-labelledby="mobileNavTitle">
      <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="mobileNavTitle"><?= e($settings['company_name']) ?></h5>
        <button class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Fechar menu"></button>
      </div>
      <div class="offcanvas-body">
        <?php foreach ($app['menu'] as $group): ?>
          <div class="nav-group"><?= e($group['group']) ?></div>
          <?php foreach ($group['items'] as $item): ?>
            <a class="nav-link<?= $item['active'] ? ' active' : '' ?>" href="<?= e($item['url']) ?>">
              <i class="bi bi-<?= e($item['icon']) ?>" aria-hidden="true"></i> <?= e($item['label']) ?>
            </a>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="page" role="main">
      <?= $content ?? '' ?>
    </div>
  </div>
</div>
<script src="<?= e(asset('vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('js/api.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<script>
document.getElementById('logoutBtn')?.addEventListener('click', async function () {
  this.disabled = true;
  try { await Api.post('/api/auth/logout', {}); } catch (e) {}
  location.href = <?= json_script(url('/login')) ?>;
});
<?php if (!$mustChangePassword): ?>
(async function () {
  try {
    const { meta } = await Api.get('/api/notifications', { per_page: 1, unread: 1 });
    if (meta && meta.unread > 0) document.getElementById('bellDot')?.classList.remove('d-none');
  } catch (e) {}
})();
<?php endif; ?>
</script>
<?= $scripts ?? '' ?>
<script src="<?= e(asset('vendor/alpinejs/alpine.min.js')) ?>"></script>
</body>
</html>

