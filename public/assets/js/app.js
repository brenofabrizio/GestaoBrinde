(function (global) {
  'use strict';

  function toast(msg, type) {
    type = type || 'info';
    let wrap = document.querySelector('.toast-wrap');
    if (!wrap) {
      wrap = document.createElement('div');
      wrap.className = 'toast-wrap';
      wrap.setAttribute('aria-live', 'polite');
      document.body.appendChild(wrap);
    }
    const el = document.createElement('div');
    el.className = 'toast-item ' + (type === 'error' || type === 'err' ? 'err' : type === 'ok' || type === 'success' ? 'ok' : 'info');
    el.textContent = msg;
    wrap.appendChild(el);
    setTimeout(function () { el.remove(); }, 4200);
  }

  function fieldErrors(root, fields) {
    clearErrors(root);
    Object.entries(fields || {}).forEach(function ([name, msg]) {
      const input = root.querySelector('[name="' + name + '"]');
      if (input) {
        input.classList.add('is-invalid');
        let fb = input.parentElement.querySelector('.invalid-feedback');
        if (!fb) {
          fb = document.createElement('div');
          fb.className = 'invalid-feedback';
          input.parentElement.appendChild(fb);
        }
        fb.textContent = msg;
        fb.style.display = 'block';
      } else {
        toast(msg, 'err');
      }
    });
  }

  function clearErrors(root) {
    root.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
    root.querySelectorAll('.invalid-feedback').forEach(function (el) { el.textContent = ''; el.style.display = 'none'; });
  }

  function confirmDialog(message) {
    return Promise.resolve(window.confirm(message));
  }

  async function purgeDialog(expected) {
    const typed = window.prompt('Digite "' + expected + '" para confirmar a exclusão definitiva:');
    return typed && typed.trim().toLowerCase() === String(expected).toLowerCase() ? typed.trim() : null;
  }

  function qs() {
    return Object.fromEntries(new URLSearchParams(location.search));
  }

  function debounce(fn, ms) {
    let t;
    return function () {
      const args = arguments;
      clearTimeout(t);
      t = setTimeout(function () { fn.apply(null, args); }, ms);
    };
  }

  function badge(kind, slug) {
    const map = (Api.labels[kind] || {})[slug] || { label: slug, color: 'secondary' };
    return '<span class="badge text-bg-' + map.color + ' badge-stock-' + slug + '">' + map.label + '</span>';
  }

  function pager(meta) {
    if (!meta || meta.last_page <= 1) return '';
    let html = '<nav class="mt-3"><ul class="pagination pagination-sm mb-0">';
    for (let p = 1; p <= meta.last_page; p++) {
      html += '<li class="page-item' + (p === meta.page ? ' active' : '') + '"><a class="page-link" href="#" data-page="' + p + '">' + p + '</a></li>';
    }
    return html + '</ul></nav>';
  }

  async function catchApi(fn, form) {
    try {
      return await fn();
    } catch (e) {
      if (e && e.name === 'AbortError') return;
      if (e && e.code === 'VALIDATION_ERROR' && form) fieldErrors(form, e.fields);
      else toast((e && e.message) || 'Erro inesperado', 'err');
      throw e;
    }
  }

  function signaturePad(canvas) {
    if (!canvas || !window.SignaturePad) return null;
    var ratio = Math.max(window.devicePixelRatio || 1, 1);
    var w = Math.max(canvas.offsetWidth || 360, 280);
    var h = Math.max(canvas.offsetHeight || 140, 120);
    canvas.width = Math.floor(w * ratio);
    canvas.height = Math.floor(h * ratio);
    canvas.style.width = w + 'px';
    canvas.style.height = h + 'px';
    var ctx = canvas.getContext('2d');
    ctx.scale(ratio, ratio);
    return new SignaturePad(canvas, {
      backgroundColor: 'rgb(255,255,255)',
      penColor: 'rgb(15, 23, 42)',
      minWidth: 0.8,
      maxWidth: 2.6
    });
  }

  function initNotifications() {
    const btn = document.getElementById('bellBtn');
    const pop = document.getElementById('notificationPopover');
    const list = document.getElementById('notificationList');
    const dot = document.getElementById('bellDot');
    const readAll = document.getElementById('notificationReadAll');
    if (!btn || !pop || !list) return;

    let loaded = false;
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[c]));
    const render = (rows) => {
      list.innerHTML = rows.length ? rows.map((n) => `
        <button type="button" class="notification-row${n.read_at ? '' : ' unread'}" data-notification-id="${n.id}">
          <span class="notification-row-title">${escapeHtml(n.subject)}</span>
          <span class="notification-row-time">${escapeHtml(Api.fmt.datetime(n.created_at))}</span>
          <span class="notification-row-body">${escapeHtml(n.body)}</span>
        </button>`).join('') : '<div class="notification-empty">Nenhuma notificação.</div>';
      list.querySelectorAll('[data-notification-id]').forEach((row) => row.addEventListener('click', async () => {
        const id = row.getAttribute('data-notification-id');
        const target = rows.find((n) => String(n.id) === String(id));
        if (target && !target.read_at) {
          try { await Api.post('/api/notifications/' + id + '/read'); target.read_at = new Date().toISOString(); row.classList.remove('unread'); refreshUnread(); } catch (e) { toast(e.message, 'err'); }
        }
        if (target && target.link_url) location.href = Api.url(target.link_url);
      }));
    };
    const refreshUnread = async () => {
      try {
        const { meta } = await Api.get('/api/notifications', { per_page: 1, unread: 1 });
        if (meta && meta.unread > 0) dot?.classList.remove('d-none'); else dot?.classList.add('d-none');
      } catch (_) {}
    };
    const load = async () => {
      try {
        const { data, meta } = await Api.get('/api/notifications', { per_page: 6 });
        render(data || []);
        loaded = true;
        if (meta && meta.unread > 0) dot?.classList.remove('d-none'); else dot?.classList.add('d-none');
      } catch (e) {
        list.innerHTML = '<div class="notification-empty text-danger">Não foi possível carregar as notificações.</div>';
      }
    };
    const close = () => { pop.hidden = true; btn.setAttribute('aria-expanded', 'false'); };
    btn.addEventListener('click', async () => {
      const opening = pop.hidden;
      pop.hidden = !opening;
      btn.setAttribute('aria-expanded', opening ? 'true' : 'false');
      if (opening && !loaded) await load();
    });
    readAll?.addEventListener('click', async () => {
      try { await Api.post('/api/notifications/read-all'); await load(); await refreshUnread(); toast('Notificações marcadas como lidas.', 'ok'); }
      catch (e) { toast(e.message, 'err'); }
    });
    document.addEventListener('click', (event) => {
      if (!pop.hidden && !pop.contains(event.target) && !btn.contains(event.target)) close();
    });
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && !pop.hidden) close(); });
    refreshUnread();
  }

  global.UI = { toast, fieldErrors, clearErrors, confirmDialog, confirm: confirmDialog, purgeDialog, qs, debounce, badge, pager, catchApi, signaturePad, initNotifications };
  document.addEventListener('DOMContentLoaded', initNotifications);
})(window);
