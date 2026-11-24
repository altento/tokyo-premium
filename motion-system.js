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
    const autoplayButton = hero.querySelector('[data-home-hero-autoplay]');
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
    const storyTargets = [...document.querySelectorAll('[data-story-reveal]')];
    const storyChapters = [...document.querySelectorAll('[data-story-chapter]')];
    let activeIndex = 0;
    let changing = false;
    let queuedIndex = null;
    let queuedAnnouncement = true;
    let autoplayTimer = 0;
    let autoplayEnabled = !reducedMotion.matches;
    let pointerPaused = false;
    let focusPaused = false;
    let pointerFrame = 0;
    let progressFrame = 0;
    const gsapMotion = !reducedMotion.matches && window.gsap && window.ScrollTrigger;
    if (gsapMotion) window.gsap.registerPlugin(window.ScrollTrigger);

    const progress = document.createElement('div');
    progress.className = 'story-scroll-progress';
    progress.setAttribute('aria-hidden', 'true');
    document.body.append(progress);
    const updateProgress = () => {
      progressFrame = 0;
      const scrollable = Math.max(1, document.documentElement.scrollHeight - window.innerHeight);
      const value = Math.max(0, Math.min(1, window.scrollY / scrollable));
      progress.style.setProperty('--story-progress', value.toFixed(4));
    };
    const scheduleProgress = () => {
      if (!progressFrame) progressFrame = requestAnimationFrame(updateProgress);
    };
    if (!gsapMotion) {
      window.addEventListener('scroll', scheduleProgress, { passive: true });
      window.addEventListener('resize', scheduleProgress, { passive: true });
      window.addEventListener('load', scheduleProgress, { once: true });
      updateProgress();
    }

    const activateChapter = chapter => {
      storyChapters.forEach(item => item.removeAttribute('data-story-active'));
      chapter.setAttribute('data-story-active', 'true');
    };
    if (gsapMotion) {
      document.body.classList.add('story-motion-ready', 'story-motion-gsap');
      window.ScrollTrigger.create({
        start: 0,
        end: 'max',
        onUpdate: self => progress.style.setProperty('--story-progress', self.progress.toFixed(4))
      });
      storyTargets.forEach(target => {
        const start = 'top 88%';
        window.gsap.fromTo(target, { autoAlpha: 0, y: window.matchMedia('(max-width: 760px)').matches ? 14 : 22 }, {
          autoAlpha: 1,
          y: 0,
          duration: window.matchMedia('(max-width: 760px)').matches ? 0.6 : 0.78,
          ease: 'power2.out',
          onComplete: () => target.classList.add('story-visible'),
          scrollTrigger: { trigger: target, start, once: true }
        });
        const photo = target.querySelector('img');
        if (photo) window.gsap.fromTo(photo, { scale: 1.035 }, {
          scale: 1,
          duration: 1.3,
          ease: 'power2.out',
          scrollTrigger: { trigger: target, start, once: true }
        });
      });
      storyChapters.forEach(chapter => window.ScrollTrigger.create({
        trigger: chapter,
        start: 'top 58%',
        end: 'bottom 42%',
        onToggle: self => { if (self.isActive) activateChapter(chapter); }
      }));
      const initialChapter = storyChapters.find(chapter => {
        const bounds = chapter.getBoundingClientRect();
        return bounds.top <= window.innerHeight * 0.58 && bounds.bottom >= window.innerHeight * 0.58;
      }) || storyChapters[0];
      if (initialChapter) activateChapter(initialChapter);
    } else if (!reducedMotion.matches && 'IntersectionObserver' in window) {
      document.body.classList.add('story-motion-ready');
      const revealObserver = new IntersectionObserver(entries => entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('story-visible');
          revealObserver.unobserve(entry.target);
        }
      }), { threshold: 0.12, rootMargin: '0px 0px -6% 0px' });
      storyTargets.forEach(target => revealObserver.observe(target));

      const chapterObserver = new IntersectionObserver(entries => entries.forEach(entry => {
        if (entry.isIntersecting) activateChapter(entry.target);
      }), { threshold: 0, rootMargin: '-42% 0px -42% 0px' });
      storyChapters.forEach(chapter => chapterObserver.observe(chapter));
    } else {
      storyTargets.forEach(target => target.classList.add('story-visible'));
      if (storyChapters[0]) activateChapter(storyChapters[0]);
    }

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
    const syncAutoplayControl = () => {
      if (!autoplayButton) return;
      autoplayButton.hidden = reducedMotion.matches;
      autoplayButton.setAttribute('aria-pressed', String(autoplayEnabled && !reducedMotion.matches));
      autoplayButton.setAttribute('aria-label', autoplayEnabled ? 'Приостановить автоматическую смену слайдов' : 'Возобновить автоматическую смену слайдов');
      autoplayButton.textContent = autoplayEnabled ? 'Ⅱ' : '▶';
    };
    const scheduleAutoplay = () => {
      window.clearTimeout(autoplayTimer);
      autoplayTimer = 0;
      syncAutoplayControl();
      if (!autoplayButton || !autoplayEnabled || reducedMotion.matches || document.hidden || pointerPaused || focusPaused) return;
      autoplayTimer = window.setTimeout(() => {
        autoplayTimer = 0;
        void showScene(activeIndex + 1, false);
      }, 7800);
    };

    const showScene = async (index, announce = true) => {
      if (changing) { queuedIndex = index; queuedAnnouncement = announce; return; }
      const target = (index + scenes.length) % scenes.length;
      if (target === activeIndex) return;
      const scene = scenes[target];
      const photoUrl = safeHref(scene.image);
      if (!photoUrl) return;

      changing = true;
      hero.setAttribute('aria-busy', 'true');

      let incomingReady = false;
      if (photoUrl !== image.src) {
        nextImage.src = photoUrl;
        try { await nextImage.decode(); incomingReady = nextImage.naturalWidth > 0; } catch { /* The existing scene remains visible if an optional photo fails. */ }
      }

      if (photoUrl !== image.src && !incomingReady) {
        nextImage.removeAttribute('src');
        hero.classList.remove('is-crossfading', 'is-text-changing');
        hero.removeAttribute('aria-busy');
        changing = false;
        if (status && announce) status.textContent = 'Не удалось загрузить фотографию. Оставлена текущая сцена.';
        const queued = queuedIndex;
        const queuedAnnounce = queuedAnnouncement;
        queuedIndex = null;
        if (queued !== null && queued !== target && queued !== activeIndex) void showScene(queued, queuedAnnounce);
        else scheduleAutoplay();
        return;
      }

      if (reducedMotion.matches || photoUrl === image.src) {
        if (photoUrl !== image.src) {
        image.src = photoUrl;
        }
        image.alt = String(scene.alt || 'Сцена Tokyo Premium — учебная концепция');
        nextImage.removeAttribute('src');
      } else {
        hero.classList.add('is-crossfading');
      }

      hero.classList.add('is-text-changing');
      await delay(reducedMotion.matches ? 0 : 120);
      updateCopy(scene);
      activeIndex = target;
      hero.dataset.homeHeroScene = String(activeIndex);
      syncControls();
      if (status && announce) status.textContent = `Сцена ${activeIndex + 1} из ${scenes.length}: ${scene.eyebrow}.`;
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
        const queuedAnnounce = queuedAnnouncement;
        queuedIndex = null;
        if (queued !== activeIndex) void showScene(queued, queuedAnnounce);
        else scheduleAutoplay();
      } else scheduleAutoplay();
    };

    controls.addEventListener('click', event => {
      const button = event.target.closest('button');
      if (!button) return;
      if (button === autoplayButton) {
        autoplayEnabled = !autoplayEnabled;
        scheduleAutoplay();
      } else if (button.hasAttribute('data-home-hero-index')) void showScene(Number(button.dataset.homeHeroIndex));
      else if (button.hasAttribute('data-home-hero-step')) void showScene(activeIndex + Number(button.dataset.homeHeroStep));
    });
    controls.addEventListener('keydown', event => {
      if (event.key !== 'ArrowRight' && event.key !== 'ArrowLeft') return;
      const currentDot = event.target.closest('[data-home-hero-index]');
      if (!currentDot) return;
      event.preventDefault();
      const step = event.key === 'ArrowRight' ? 1 : -1;
      const nextIndex = (Number(currentDot.dataset.homeHeroIndex) + step + scenes.length) % scenes.length;
      dots[nextIndex]?.focus();
      void showScene(nextIndex);
    });
    controls.hidden = false;
    syncAutoplayControl();
    hero.addEventListener('pointerenter', () => { pointerPaused = true; scheduleAutoplay(); }, { passive: true });
    hero.addEventListener('pointerleave', () => { pointerPaused = false; scheduleAutoplay(); }, { passive: true });
    hero.addEventListener('focusin', () => { focusPaused = true; scheduleAutoplay(); });
    hero.addEventListener('focusout', event => {
      if (!hero.contains(event.relatedTarget)) { focusPaused = false; scheduleAutoplay(); }
    });
    document.addEventListener('visibilitychange', scheduleAutoplay);
    reducedMotion.addEventListener?.('change', event => {
      autoplayEnabled = !event.matches;
      scheduleAutoplay();
    });
    scheduleAutoplay();

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
