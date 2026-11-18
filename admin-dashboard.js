(() => {
  const nav = document.querySelector('.admin-jump-nav');
  const search = document.getElementById('admin-search-q');
  const imageInputs = document.querySelectorAll('[data-admin-image-source]');

  for (const input of imageInputs) {
    const form = input.closest('form');
    const preview = form?.querySelector('[data-admin-image-preview]');
    const hint = form?.querySelector('[data-admin-image-hint]');
    if (!preview || !hint) continue;

    const updatePreview = () => {
      const value = input.value.trim();
      const localAsset = /^assets\/food\/[A-Za-z0-9][A-Za-z0-9._-]*\.webp$/.test(value);
      let safeRemote = '';
      try {
        const url = new URL(value);
        const decodedPath = decodeURIComponent(url.pathname);
        const safePath = decodedPath !== '' && !decodedPath.includes('\\') && !/%2f|%5c/i.test(url.pathname)
          && !decodedPath.split('/').some((part) => part === '.' || part === '..');
        if (url.protocol === 'https:' && ['images.unsplash.com', 'plus.unsplash.com'].includes(url.hostname.toLowerCase())
          && safePath && !url.username && !url.password && !url.hash && (!url.port || url.port === '443')) safeRemote = url.href;
      } catch {}

      const source = localAsset ? value : safeRemote;
      preview.hidden = true;
      preview.removeAttribute('src');
      hint.textContent = value
        ? (source ? 'Загружаем предпросмотр…' : 'Разрешены только локальные assets/food/*.webp или HTTPS images.unsplash.com.')
        : 'Предпросмотр появится после ввода адреса фото.';
      if (!source) return;

      preview.onload = () => { preview.hidden = false; hint.textContent = 'Так фото будет выглядеть в меню.'; };
      preview.onerror = () => { preview.hidden = true; hint.textContent = 'Не удалось загрузить фото. Проверьте путь и доступность файла.'; };
      preview.src = source;
    };

    input.addEventListener('input', updatePreview);
    updatePreview();
  }

  if (!nav) return;

  const links = [...nav.querySelectorAll('a[href^="#"]')];
  const sections = links
    .map((link) => document.getElementById(decodeURIComponent(link.hash.slice(1))))
    .filter(Boolean);

  const setCurrentSection = (id) => {
    for (const link of links) {
      if (link.hash === `#${id}`) link.setAttribute('aria-current', 'location');
      else link.removeAttribute('aria-current');
    }
  };

  if (location.hash) setCurrentSection(decodeURIComponent(location.hash.slice(1)));

  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries) => {
      const current = entries
        .filter((entry) => entry.isIntersecting)
        .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];
      if (current) setCurrentSection(current.target.id);
    }, { rootMargin: '-155px 0px -62% 0px', threshold: [0, 0.1, 0.35, 0.6] });

    for (const section of sections) observer.observe(section);
  }

  document.addEventListener('keydown', (event) => {
    if (event.key !== '/' || event.ctrlKey || event.metaKey || event.altKey || event.isComposing) return;
    const target = event.target;
    if (target instanceof HTMLElement && (target.isContentEditable || target.closest('input, textarea, select, [contenteditable="true"]'))) return;
    if (!search) return;
    event.preventDefault();
    search.focus({ preventScroll: true });
    search.scrollIntoView({ block: 'center' });
  });

  const refreshButton = document.querySelector('[data-admin-refresh-notifications]');
  const notificationStatus = document.querySelector('[data-admin-notification-status]');
  if (refreshButton && notificationStatus) {
    const countNodes = [...document.querySelectorAll('[data-admin-notification-count]')];
    const previousCounts = new Map(countNodes.map((node) => [
      node.dataset.adminNotificationCount,
      Math.max(0, Number.parseInt(node.textContent || '0', 10) || 0)
    ]));
    const liveNotice = document.querySelector('[data-admin-live-notice]');
    const liveMessage = liveNotice?.querySelector('[data-admin-live-message]');
    const liveLink = liveNotice?.querySelector('[data-admin-live-link]');
    const liveDismiss = liveNotice?.querySelector('[data-admin-live-dismiss]');
    const notificationLabels = {
      bookings: 'новая бронь',
      orders: 'новый заказ',
      reviews: 'новый отзыв',
      contact_messages: 'новое обращение',
      booking_emails: 'письмо по брони в очереди'
    };
    const notificationUrls = {
      bookings: '?notification=bookings#bookings',
      orders: '?notification=orders#orders',
      reviews: '?notification=reviews#reviews',
      contact_messages: '?notification=contact_messages#contact_messages',
      booking_emails: '?notification=booking_emails#bookings'
    };
    const formatTime = (value) => new Intl.DateTimeFormat('ru-RU', {
      hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Qyzylorda'
    }).format(new Date(value));
    let busy = false;
    let lastSuccess = 0;

    liveDismiss?.addEventListener('click', () => {
      if (liveNotice) liveNotice.hidden = true;
    });

    const refreshNotifications = async () => {
      if (busy || document.visibilityState === 'hidden') return;
      busy = true;
      refreshButton.disabled = true;
      refreshButton.setAttribute('aria-busy', 'true');
      try {
        const response = await fetch('admin.php?ajax=notifications', {
          credentials: 'same-origin',
          cache: 'no-store',
          headers: { Accept: 'application/json' }
        });
        if (response.status === 401) throw new Error('session');
        if (!response.ok) throw new Error('unavailable');
        const payload = await response.json();
        if (!payload?.counts || !payload?.checkedAt) throw new Error('invalid-response');

        const increases = [];
        for (const node of countNodes) {
          const key = node.dataset.adminNotificationCount;
          const count = Number(payload.counts[key]);
          if (!Number.isSafeInteger(count) || count < 0) continue;
          const previous = previousCounts.get(key) ?? count;
          if (count > previous && notificationLabels[key]) {
            increases.push({ key, delta: count - previous });
          }
          previousCounts.set(key, count);
          node.textContent = String(count);
          node.classList.toggle('has-unread', count > 0);
          const link = node.closest('a[data-admin-notification-label]');
          if (link) link.setAttribute('aria-label', `${link.dataset.adminNotificationLabel}, новых: ${count}`);
        }
        if (increases.length && liveNotice && liveMessage && liveLink) {
          const ordered = ['bookings', 'orders', 'reviews', 'contact_messages', 'booking_emails'];
          increases.sort((a, b) => ordered.indexOf(a.key) - ordered.indexOf(b.key));
          liveMessage.textContent = `Появились новые записи: ${increases.map(({ key, delta }) => `${notificationLabels[key]}${delta > 1 ? ` · +${delta}` : ''}`).join(' · ')}.`;
          liveLink.href = notificationUrls[increases[0].key];
          liveLink.textContent = increases[0].key === 'booking_emails' ? 'Открыть очередь писем ↗' : `Открыть раздел «${notificationLabels[increases[0].key]}» ↗`;
          liveNotice.hidden = false;
        }
        lastSuccess = Date.now();
        notificationStatus.textContent = `Обновлено в ${formatTime(payload.checkedAt)} · следующее автообновление через минуту.`;
      } catch (error) {
        notificationStatus.textContent = error.message === 'session'
          ? 'Сессия панели завершилась. Обновите страницу и войдите снова.'
          : `Не удалось обновить счётчики. Последняя успешная проверка: ${lastSuccess ? new Date(lastSuccess).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' }) : 'нет'}.`;
      } finally {
        busy = false;
        refreshButton.disabled = false;
        refreshButton.removeAttribute('aria-busy');
      }
    };

    refreshButton.addEventListener('click', refreshNotifications);
    window.setInterval(refreshNotifications, 60_000);
    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'visible' && Date.now() - lastSuccess >= 60_000) refreshNotifications();
    });
  }
})();
