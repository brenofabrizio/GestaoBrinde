<?php
/** @var array $app */
$user = $app['user'];
$settings = $app['settings'];
$color = e($settings['primary_color'] ?? '#2563EB');
$title = $app['page']['title'] ?? '';
$unread = 0;
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
<body>
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
      <div class="position-relative">
        <button class="btn btn-light" type="button"
           aria-label="Notificações" id="bellBtn" aria-controls="notificationModal" aria-expanded="false">
          <i class="bi bi-bell" aria-hidden="true"></i>
        </button>
        <span class="bell-dot d-none" id="bellDot" aria-hidden="true"></span>
      </div>
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
<div class="modal fade" id="notificationModal" tabindex="-1" aria-labelledby="notificationModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable modal-dialog-end">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title fs-5" id="notificationModalTitle">Notificações</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body" id="notificationPreview" aria-live="polite">
        <div class="text-muted">Carregando notificações…</div>
      </div>
      <div class="modal-footer justify-content-between">
        <button class="btn btn-outline-secondary btn-sm" type="button" id="markAllNotifications">Marcar todas como lidas</button>
        <a class="btn btn-primary btn-sm" href="<?= e(url('/notificacoes')) ?>">Ver todas</a>
      </div>
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
(async function () {
  try {
    const { meta } = await Api.get('/api/notifications', { per_page: 1, unread: 1 });
    if (meta && meta.unread > 0) document.getElementById('bellDot')?.classList.remove('d-none');
  } catch (e) {}
})();
(function () {
  const bell = document.getElementById('bellBtn');
  const dot = document.getElementById('bellDot');
  const preview = document.getElementById('notificationPreview');
  const modalEl = document.getElementById('notificationModal');
  const markAll = document.getElementById('markAllNotifications');
  if (!bell || !preview || !modalEl) return;
  let modal = null;
  const escapeHtml = (value) => String(value ?? '').replace(/[&<>\"']/g, (ch) => ({'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;',"'":'&#039;'}[ch]));
  const setDot = (visible) => dot?.classList.toggle('d-none', !visible);
  const render = (rows) => {
    if (!rows.length) {
      preview.innerHTML = '<div class="empty py-4">Nenhuma notificação nova.</div>';
      return;
    }
    preview.innerHTML = rows.map((n) => {
      const link = n.link_url ? Api.url(n.link_url) : '#';
      return '<a class="notification-preview-item d-block text-decoration-none' + (n.read_at ? '' : ' unread') + '" href="' + escapeHtml(link) + '" data-notification-id="' + Number(n.id) + '">' +
        '<div class="fw-semibold">' + escapeHtml(n.subject) + '</div>' +
        '<div class="small text-muted">' + escapeHtml(n.body || '') + '</div>' +
        '<div class="small text-muted mt-1">' + escapeHtml(Api.fmt.datetime(n.created_at)) + '</div></a>';
    }).join('');
  };
  const load = async () => {
    preview.innerHTML = '<div class="text-muted">Carregando notificações…</div>';
    try {
      const response = await Api.get('/api/notifications', { per_page: 5 });
      render(Array.isArray(response.data) ? response.data : []);
      setDot(Number(response.meta?.unread || 0) > 0);
    } catch (e) { preview.innerHTML = '<div class="alert alert-danger mb-0">' + escapeHtml(e.message || 'Não foi possível carregar as notificações.') + '</div>'; }
  };
  bell.addEventListener('click', () => {
    modal = modal || (window.bootstrap ? bootstrap.Modal.getOrCreateInstance(modalEl) : null);
    if (modal) modal.show(); else modalEl.classList.add('show');
    bell.setAttribute('aria-expanded', 'true');
    load();
  });
  preview.addEventListener('click', async (event) => {
    const link = event.target.closest('[data-notification-id]');
    if (!link) return;
    const id = Number(link.dataset.notificationId);
    if (!id || !link.classList.contains('unread')) return;
    try { await Api.post('/api/notifications/' + id + '/read'); link.classList.remove('unread'); } catch (e) {}
  });
  markAll?.addEventListener('click', async () => {
    markAll.disabled = true;
    try { await Api.post('/api/notifications/read-all'); await load(); setDot(false); } catch (e) { UI.toast(e.message || 'Não foi possível marcar as notificações.', 'err'); }
    markAll.disabled = false;
  });
  modalEl.addEventListener('hidden.bs.modal', () => bell.setAttribute('aria-expanded', 'false'));
})();
</script>
<?= $scripts ?? '' ?>
<script src="<?= e(asset('vendor/alpinejs/alpine.min.js')) ?>"></script>
</body>
</html>

