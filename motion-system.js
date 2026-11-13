(() => {
  const init = () => {
    const hero = document.querySelector('[data-home-hero]');
    if (!hero) return;

    const data = document.querySelector('#home-hero-scenes');
    const controls = hero.querySelector('[data-home-hero-controls]');
    const image = hero.querySelector('.home-hero-photo--active');
    const nextImage = hero.querySelector('[data-home-hero-next]');
    const title = hero.querySelector('[data-home-hero-title]');
    const accent = hero.querySelector('[data-home-hero-accent]');
    const eyebrow = hero.querySelector('[data-home-hero-eyebrow]');
    const lead = hero.querySelector('[data-home-hero-lead]');
    const primary = hero.querySelector('[data-home-hero-primary]');
    const secondary = hero.querySelector('[data-home-hero-secondary]');
    const status = hero.querySelector('[data-home-hero-status]');
    if (!data || !controls || !image || !nextImage || !title || !accent || !eyebrow || !lead || !primary || !secondary) return;

    let scenes;
    try { scenes = JSON.parse(data.textContent); } catch { return; }
    if (!Array.isArray(scenes) || scenes.length < 2) return;

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const staticRoutes = {
      'menu.php': 'menu.html',
      'pages.php?page=booking': 'booking.html',
      'pages.php?page=omakase': 'omakase.html',
      'set-builder.php': 'set-builder.html',
      'about.php': 'about.html',
      'pages.php?page=gallery': 'gallery.html'
    };
    const dots = [...controls.querySelectorAll('[data-home-hero-index]')];
    let activeIndex = 0;
    let changing = false;
    let queuedIndex = null;
    let pointerFrame = 0;

    const safeHref = value => {
      try {
        const route = window.TOKYO_STATIC_DEMO ? (staticRoutes[value] || value) : value;
        const url = new URL(route, window.location.href);
        return url.origin === window.location.origin ? url.href : null;
      } catch { return null; }
    };
    const updateLink = (link, config, arrow) => {
      const href = safeHref(config?.href);
      if (!href || typeof config?.label !== 'string') return;
      link.href = href;
      link.replaceChildren(document.createTextNode(config.label));
      if (arrow) {
        const mark = document.createElement('span');
        mark.setAttribute('aria-hidden', 'true');
        mark.textContent = ' ↗';
        link.append(mark);
      }
    };
    const updateCopy = scene => {
      eyebrow.textContent = String(scene.eyebrow || 'TOKYO PREMIUM');
      title.textContent = String(scene.title || 'Tokyo Premium');
      accent.textContent = String(scene.accent || 'японская кухня');
      lead.textContent = String(scene.lead || 'Японская кухня как искусство момента.');
      updateLink(primary, scene.primary, true);
      updateLink(secondary, scene.secondary, false);
    };
    const syncControls = () => dots.forEach((dot, index) => dot.setAttribute('aria-pressed', String(index === activeIndex)));
    const delay = ms => new Promise(resolve => window.setTimeout(resolve, ms));

    const showScene = async index => {
      if (changing) { queuedIndex = index; return; }
      const target = (index + scenes.length) % scenes.length;
      if (target === activeIndex) return;
      const scene = scenes[target];
      const photoUrl = safeHref(scene.image);
      if (!photoUrl) return;

      changing = true;
      hero.setAttribute('aria-busy', 'true');

      let incomingReady = false;
      if (!reducedMotion.matches && photoUrl !== image.src) {
        nextImage.src = photoUrl;
        try { await nextImage.decode(); incomingReady = nextImage.naturalWidth > 0; } catch { /* The existing scene remains visible if an optional photo fails. */ }
      }

      if (reducedMotion.matches || !incomingReady || photoUrl === image.src) {
        if (reducedMotion.matches || incomingReady || photoUrl === image.src) {
        image.src = photoUrl;
        image.alt = String(scene.alt || 'Сцена Tokyo Premium — учебная концепция');
        }
        nextImage.removeAttribute('src');
      } else {
        hero.classList.add('is-crossfading');
      }

      hero.classList.add('is-text-changing');
      await delay(reducedMotion.matches ? 0 : 120);
      updateCopy(scene);
      activeIndex = target;
      syncControls();
      if (status) status.textContent = `Сцена ${activeIndex + 1} из ${scenes.length}: ${scene.eyebrow}.`;
      requestAnimationFrame(() => hero.classList.remove('is-text-changing'));

      if (!reducedMotion.matches && hero.classList.contains('is-crossfading')) {
        await delay(820);
        image.src = photoUrl;
        image.alt = String(scene.alt || 'Сцена Tokyo Premium — учебная концепция');
        hero.classList.remove('is-crossfading');
        nextImage.removeAttribute('src');
      }

      hero.removeAttribute('aria-busy');
      changing = false;
      if (queuedIndex !== null) {
        const queued = queuedIndex;
        queuedIndex = null;
        if (queued !== activeIndex) showScene(queued);
      }
    };

    controls.addEventListener('click', event => {
      const button = event.target.closest('button');
      if (!button) return;
      if (button.hasAttribute('data-home-hero-index')) showScene(Number(button.dataset.homeHeroIndex));
      else if (button.hasAttribute('data-home-hero-step')) showScene(activeIndex + Number(button.dataset.homeHeroStep));
    });
    controls.addEventListener('keydown', event => {
      if (event.key !== 'ArrowRight' && event.key !== 'ArrowLeft') return;
      const currentDot = event.target.closest('[data-home-hero-index]');
      if (!currentDot) return;
      event.preventDefault();
      const step = event.key === 'ArrowRight' ? 1 : -1;
      const nextIndex = (Number(currentDot.dataset.homeHeroIndex) + step + scenes.length) % scenes.length;
      dots[nextIndex]?.focus();
      showScene(nextIndex);
    });
    controls.hidden = false;

    if (!reducedMotion.matches) {
      requestAnimationFrame(() => hero.classList.add('is-ready'));
      const finePointer = window.matchMedia('(hover: hover) and (pointer: fine)');
      if (finePointer.matches) {
        hero.addEventListener('pointermove', event => {
          if (pointerFrame) cancelAnimationFrame(pointerFrame);
          pointerFrame = requestAnimationFrame(() => {
            const rect = hero.getBoundingClientRect();
            const x = ((event.clientX - rect.left) / rect.width - 0.5) * -7;
            const y = ((event.clientY - rect.top) / rect.height - 0.5) * -5;
            hero.style.setProperty('--hero-shift-x', `${x.toFixed(1)}px`);
            hero.style.setProperty('--hero-shift-y', `${y.toFixed(1)}px`);
          });
        }, { passive: true });
        hero.addEventListener('pointerleave', () => {
          hero.style.setProperty('--hero-shift-x', '0px');
          hero.style.setProperty('--hero-shift-y', '0px');
        }, { passive: true });
      }
    }
  };

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
  else init();
})();
