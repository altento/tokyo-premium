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
})();
