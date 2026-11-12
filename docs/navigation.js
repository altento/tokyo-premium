(() => {
  const restaurantTimeZone = 'Asia/Qyzylorda';
  const isoDate = (year, month, day) => `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
  function restaurantDateBounds(instant = new Date()) {
    const parts = new Intl.DateTimeFormat('en-CA', {
      timeZone: restaurantTimeZone, year: 'numeric', month: '2-digit', day: '2-digit'
    }).formatToParts(instant);
    const values = Object.fromEntries(parts.map(part => [part.type, part.value]));
    const today = new Date(Date.UTC(Number(values.year), Number(values.month) - 1, Number(values.day)));
    const latest = new Date(today);
    latest.setUTCDate(latest.getUTCDate() + 365);
    return {
      min: isoDate(today.getUTCFullYear(), today.getUTCMonth() + 1, today.getUTCDate()),
      max: isoDate(latest.getUTCFullYear(), latest.getUTCMonth() + 1, latest.getUTCDate())
    };
  }
  function updateBookingDateLimits() {
    const bounds = restaurantDateBounds();
    document.querySelectorAll('.home-booking-form input[type="date"], .booking-form input[type="date"]').forEach(input => {
      input.min = bounds.min;
      input.max = bounds.max;
      input.dataset.restaurantTimeZone = restaurantTimeZone;
      input.setCustomValidity(input.value && input.value < bounds.min ? 'Выберите сегодняшнюю или будущую дату.' : '');
      if (input.value && input.value < bounds.min) input.setAttribute('aria-invalid', 'true');
      else input.removeAttribute('aria-invalid');
    });
  }
  window.TokyoPremiumBookingDates = { bounds: restaurantDateBounds, update: updateBookingDateLimits, timeZone: restaurantTimeZone };
  updateBookingDateLimits();
  document.addEventListener('visibilitychange', () => { if (!document.hidden) updateBookingDateLimits(); });
  window.addEventListener('pageshow', updateBookingDateLimits);

  const header = document.querySelector('.site-header');
  const nav = document.querySelector('#site-navigation');
  const toggle = header?.querySelector('.mobile-menu');
  if (!header || !nav || !toggle) return;

  const mobile = window.matchMedia('(max-width: 900px)');
  let returnFocus = false;

  const mobileUtilities = nav.querySelector('.mobile-nav-utilities');
  // The header cart link is the single cart entry point. The duplicate menu link
  // is removed while JS is active; it remains available as a no-JS fallback.
  mobileUtilities?.querySelector('a[href*="cart"]')?.remove();

  function setOpen(open, { focus = false, restore = false } = {}) {
    if (!mobile.matches) open = false;
    nav.classList.toggle('open', open);
    nav.setAttribute('aria-hidden', String(!open && mobile.matches));
    if (mobile.matches) nav.toggleAttribute('inert', !open);
    else nav.removeAttribute('inert');
    toggle.setAttribute('aria-expanded', String(open));
    toggle.setAttribute('aria-label', open ? 'Закрыть меню' : 'Открыть меню');
    document.body.classList.toggle('nav-open', open);
    if (open && focus) nav.querySelector('a')?.focus();
    if (!open && restore && returnFocus) toggle.focus();
    if (!open) returnFocus = false;
  }

  setOpen(false);

  // Capture this click before the legacy handler in luxury.js so the menu only toggles once.
  toggle.addEventListener('click', event => {
    if (!mobile.matches) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    const opening = toggle.getAttribute('aria-expanded') !== 'true';
    returnFocus = opening;
    setOpen(opening, { focus: opening });
  }, true);

  nav.addEventListener('click', event => {
    if (!mobile.matches || !event.target.closest('a')) return;
    setOpen(false);
  });

  document.addEventListener('keydown', event => {
    if (toggle.getAttribute('aria-expanded') !== 'true') return;
    if (event.key === 'Escape') {
      event.preventDefault();
      setOpen(false, { restore: true });
      return;
    }
    if (event.key !== 'Tab') return;
    const items = [...nav.querySelectorAll('a[href], button:not([disabled])')].filter(el => el.offsetParent !== null);
    if (!items.length) return;
    const first = items[0], last = items[items.length - 1];
    if (event.shiftKey && (document.activeElement === first || document.activeElement === toggle)) {
      event.preventDefault(); last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault(); first.focus();
    }
  });

  document.addEventListener('pointerdown', event => {
    if (mobile.matches && toggle.getAttribute('aria-expanded') === 'true' && !header.contains(event.target)) setOpen(false);
  });

  mobile.addEventListener('change', () => setOpen(false));

  // Gallery controls apply to the gallery only; the room cards remain visible as context.
  const galleryGrid = document.querySelector('#gallery-grid');
  if (galleryGrid) {
    const zoneGrid = document.querySelector('.zone-grid');
    const zones = [...(zoneGrid?.querySelectorAll('.zone-card') || [])];
    const roomAssets = [
      'assets/generated/room-sushi-counter.webp',
      'assets/generated/room-main-hall.webp',
      'assets/generated/room-private-dining.webp',
      'assets/generated/room-garden-terrace.webp'
    ];
    zoneGrid?.closest('.section')?.setAttribute('id', 'spaces');
    zones.forEach((card, index) => {
      card.removeAttribute('data-gallery-filter');
      const image = card.querySelector('img');
      const trigger = card.querySelector('[data-lightbox]');
      const src = roomAssets[index];
      if (image && src) image.src = src;
      if (trigger && src) trigger.dataset.lightbox = src;
    });
    const galleryAssets = [
      'assets/generated/room-main-hall.webp',
      'assets/generated/room-sushi-counter.webp',
      'assets/food/17-omakase-set.webp',
      'assets/generated/room-garden-terrace.webp',
      'assets/generated/room-private-dining.webp',
      'assets/food/04-matcha-latte.webp'
    ];
    [...galleryGrid.querySelectorAll('.gallery-tile')].forEach((tile, index) => {
      const src = galleryAssets[index];
      const image = tile.querySelector('img');
      if (image && src) image.src = src;
      if (src) tile.dataset.lightbox = src;
    });
  }

  const bookingForm = document.querySelector('.booking-form');
  if (bookingForm) {
    const dateInput = bookingForm.querySelector('input[type="date"]');
    if (dateInput) {
      const localIsoDate = date => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
      const today = new Date();
      const end = new Date(today.getFullYear(), today.getMonth(), today.getDate());
      end.setDate(end.getDate() + 365);
      dateInput.min = localIsoDate(today);
      dateInput.max = localIsoDate(end);
    }
    const tableInput = bookingForm.querySelector('[name="table"]');
    const tableStatus = document.querySelector('.table-selection');
    const tableButtons = [...document.querySelectorAll('[data-table-select]')];
    const resetTable = () => {
      if (tableInput) tableInput.value = '';
      tableButtons.forEach(button => {
        button.setAttribute('aria-pressed', 'false');
        button.classList.remove('selected');
      });
      if (tableStatus) tableStatus.textContent = 'Параметры изменились. Выберите стол заново или оставьте поле пустым.';
    };
    ['date', 'time', 'guests', 'zone'].forEach(name => bookingForm.querySelector(`[name="${name}"]`)?.addEventListener('change', resetTable));
  }
})();
