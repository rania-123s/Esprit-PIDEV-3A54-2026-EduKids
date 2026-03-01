(() => {
  const root = document.getElementById('navbarNotifications');
  if (!root) {
    return;
  }

  const triggerButton = document.getElementById('navbarNotificationsBtn');
  const badge = document.getElementById('navbarNotificationsBadge');
  const list = document.getElementById('navbarNotificationsList');
  const readAllButton = document.getElementById('navbarNotificationsReadAll');
  const headerCount = document.getElementById('navbarNotificationsCount');
  const menu = root.querySelector('.nav-notification-menu');

  const urls = {
    list: root.dataset.listUrl || '',
    unreadCount: root.dataset.unreadCountUrl || '',
    readTemplate: root.dataset.readUrlTemplate || '',
    readAll: root.dataset.readAllUrl || '',
  };
  const csrf = root.dataset.csrf || '';

  let listLoading = false;
  let pollTimer = null;
  let menuOpen = false;

  const updateMenuPosition = () => {
    if (!menu || !triggerButton) {
      return;
    }

    const rect = triggerButton.getBoundingClientRect();
    const viewportWidth = document.documentElement.clientWidth || window.innerWidth || 0;
    const safeViewport = Math.max(viewportWidth, 320);
    const menuWidth = Math.min(360, Math.max(240, safeViewport - 24));
    const right = Math.max(12, safeViewport - rect.right);
    const top = Math.max(12, rect.bottom + 8);

    menu.style.setProperty('position', 'fixed', 'important');
    menu.style.setProperty('top', `${Math.round(top)}px`, 'important');
    menu.style.setProperty('right', `${Math.round(right)}px`, 'important');
    menu.style.setProperty('left', 'auto', 'important');
    menu.style.setProperty('width', `${Math.round(menuWidth)}px`, 'important');
    menu.style.setProperty('max-width', 'calc(100vw - 24px)', 'important');
    menu.style.setProperty('z-index', '3000', 'important');
    menu.style.setProperty('transform', 'none', 'important');
    menu.style.setProperty('pointer-events', 'auto', 'important');
  };

  const setMenuOpen = (open) => {
    if (!menu || !triggerButton) {
      return;
    }

    menuOpen = open;

    if (open) {
      updateMenuPosition();
      menu.hidden = false;
      menu.style.setProperty('display', 'block', 'important');
      menu.style.setProperty('visibility', 'visible', 'important');
      menu.style.setProperty('opacity', '1', 'important');
    } else {
      menu.hidden = true;
      menu.style.setProperty('display', 'none', 'important');
    }

    root.classList.toggle('show', open);
    menu.classList.toggle('show', open);
    triggerButton.setAttribute('aria-expanded', open ? 'true' : 'false');
  };

  const isMenuOpen = () => menuOpen;

  const resolveUrl = (template, id) => String(template || '').replace('{id}', String(id));

  const readJsonSafe = async (response) => {
    try {
      return await response.json();
    } catch {
      return null;
    }
  };

  const apiFetch = (url, options = {}) => {
    const headers = options.headers ? { ...options.headers } : {};
    if (csrf) {
      headers['X-CSRF-TOKEN'] = csrf;
    }

    return fetch(url, {
      ...options,
      headers,
    });
  };

  const setBadgeCount = (count) => {
    if (!badge) {
      return;
    }

    const normalized = Number.isFinite(Number(count)) ? Math.max(0, Number(count)) : 0;
    if (headerCount) {
      if (normalized <= 0) {
        headerCount.textContent = 'Aucun nouveau message';
      } else {
        const suffix = normalized > 1 ? 's' : '';
        headerCount.textContent = `${normalized} nouveau${suffix} message${suffix}`;
      }
    }

    if (normalized <= 0) {
      badge.hidden = true;
      badge.textContent = '0';
      return;
    }

    badge.hidden = false;
    badge.textContent = normalized > 9 ? '9+' : String(normalized);
  };

  const escapeHtml = (value) => {
    const div = document.createElement('div');
    div.textContent = value || '';
    return div.innerHTML;
  };

  const formatTimeAgo = (isoDate) => {
    if (!isoDate) {
      return '';
    }

    const parsed = new Date(isoDate);
    if (Number.isNaN(parsed.getTime())) {
      return '';
    }

    const diffMs = Date.now() - parsed.getTime();
    const diffMinutes = Math.max(0, Math.floor(diffMs / 60000));
    if (diffMinutes < 1) {
      return 'a l instant';
    }

    if (diffMinutes < 60) {
      return `il y a ${diffMinutes} min`;
    }

    const diffHours = Math.floor(diffMinutes / 60);
    if (diffHours < 24) {
      return `il y a ${diffHours} h`;
    }

    const diffDays = Math.floor(diffHours / 24);
    return `il y a ${diffDays} j`;
  };

  const renderEmpty = (message) => {
    if (!list) {
      return;
    }

    list.innerHTML = `<div class="nav-notification-empty">${escapeHtml(message)}</div>`;
  };

  const renderNotifications = (items) => {
    if (!list) {
      return;
    }

    list.innerHTML = '';
    if (!Array.isArray(items) || items.length === 0) {
      renderEmpty('Aucune notification.');
      return;
    }

    items.forEach((item) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'nav-notification-item';
      button.dataset.id = String(item.id || '');
      button.dataset.chatUrl = String(item.chatUrl || '/chat');

      const sender = item.sender || {};
      const avatarUrl = String(sender.avatarUrl || '');
      const initials = String(sender.initials || 'U');
      const defaultText = sender.name
        ? `${sender.name} a envoye un message`
        : 'Nouveau message';
      const text = String(item.text || defaultText);
      const time = formatTimeAgo(item.createdAt);

      const avatarHtml = avatarUrl
        ? `<img class="nav-notification-avatar" src="${escapeHtml(avatarUrl)}" alt="${escapeHtml(sender.name || 'Avatar')}">`
        : `<span class="nav-notification-avatar-fallback">${escapeHtml(initials)}</span>`;

      button.innerHTML = `
        ${avatarHtml}
        <span class="nav-notification-body">
          <span class="nav-notification-row">
            <span class="nav-notification-text">${escapeHtml(text)}</span>
            <span class="nav-notification-time">${escapeHtml(time)}</span>
          </span>
        </span>
      `;

      list.appendChild(button);
    });
  };

  const fetchUnreadCount = async () => {
    if (!urls.unreadCount) {
      return;
    }

    try {
      const response = await fetch(urls.unreadCount, { cache: 'no-store' });
      const payload = await readJsonSafe(response);
      if (!response.ok) {
        return;
      }

      setBadgeCount(payload?.count ?? 0);
    } catch {
      // silent
    }
  };

  const fetchNotifications = async () => {
    if (!urls.list || listLoading) {
      return;
    }

    listLoading = true;
    renderEmpty('Chargement...');
    try {
      const response = await fetch(urls.list, { cache: 'no-store' });
      const payload = await readJsonSafe(response);
      if (!response.ok) {
        renderEmpty('Impossible de charger les notifications.');
        return;
      }

      renderNotifications(payload?.items || []);
    } catch {
      renderEmpty('Erreur de chargement.');
    } finally {
      listLoading = false;
    }
  };

  const markNotificationAsRead = async (notificationId) => {
    if (!urls.readTemplate || !notificationId) {
      return;
    }

    try {
      const response = await apiFetch(resolveUrl(urls.readTemplate, notificationId), {
        method: 'POST',
      });
      const payload = await readJsonSafe(response);
      if (!response.ok) {
        return;
      }

      if (typeof payload?.unreadCount !== 'undefined') {
        setBadgeCount(payload.unreadCount);
      } else {
        await fetchUnreadCount();
      }
    } catch {
      // silent
    }
  };

  const markAllAsRead = async () => {
    if (!urls.readAll) {
      return;
    }

    if (readAllButton) {
      readAllButton.disabled = true;
    }

    try {
      const response = await apiFetch(urls.readAll, { method: 'POST' });
      const payload = await readJsonSafe(response);
      if (!response.ok) {
        return;
      }

      setBadgeCount(payload?.unreadCount ?? 0);
      await fetchNotifications();
    } catch {
      // silent
    } finally {
      if (readAllButton) {
        readAllButton.disabled = false;
      }
    }
  };

  if (triggerButton) {
    triggerButton.addEventListener('click', (event) => {
      event.preventDefault();
      event.stopPropagation();

      const nextOpen = !isMenuOpen();
      setMenuOpen(nextOpen);
      void fetchNotifications();
      void fetchUnreadCount();
    });
  }

  if (readAllButton) {
    readAllButton.addEventListener('click', (event) => {
      event.preventDefault();
      void markAllAsRead();
    });
  }

  if (list) {
    list.addEventListener('click', (event) => {
      const target = event.target;
      if (!(target instanceof HTMLElement)) {
        return;
      }

      const item = target.closest('.nav-notification-item');
      if (!(item instanceof HTMLElement)) {
        return;
      }

      const notificationId = Number(item.dataset.id || 0);
      const chatUrl = item.dataset.chatUrl || '/chat';

      void (async () => {
        await markNotificationAsRead(notificationId);
        setMenuOpen(false);
        window.location.href = chatUrl;
      })();
    });
  }

  document.addEventListener('click', (event) => {
    const target = event.target;
    if (!(target instanceof Node)) {
      return;
    }

    if (!root.contains(target)) {
      setMenuOpen(false);
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      setMenuOpen(false);
    }
  });

  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
      void fetchUnreadCount();
    }
  });

  window.addEventListener('resize', () => {
    if (isMenuOpen()) {
      updateMenuPosition();
    }
  });

  window.addEventListener('scroll', () => {
    if (isMenuOpen()) {
      updateMenuPosition();
    }
  }, true);

  pollTimer = window.setInterval(() => {
    void fetchUnreadCount();
  }, 20000);

  window.addEventListener('beforeunload', () => {
    if (pollTimer) {
      clearInterval(pollTimer);
      pollTimer = null;
    }
  });

  setMenuOpen(false);
  void fetchUnreadCount();
})();

