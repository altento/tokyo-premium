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
  const boundBookingInputs = new WeakSet();
  const originalOptionStates = new WeakMap();
  function updateBookingDateLimits() {
    const instant = new Date();
    const bounds = restaurantDateBounds(instant);
    const timeParts = new Intl.DateTimeFormat('en-GB', { timeZone: restaurantTimeZone, hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }).formatToParts(instant);
    const clock = Object.fromEntries(timeParts.map(part => [part.type, part.value]));
    const currentTime = `${clock.hour}:${clock.minute}`;
    document.querySelectorAll('.home-booking-form input[type="date"], .booking-form input[type="date"]').forEach(input => {
      input.min = bounds.min;
      input.max = bounds.max;
      input.dataset.restaurantTimeZone = restaurantTimeZone;
      const dateError = input.value && input.value < bounds.min ? 'Выберите сегодняшнюю или будущую дату.' : input.value && input.value > bounds.max ? 'Выберите дату в ближайшие 365 дней.' : '';
      input.setCustomValidity(dateError);
      if (dateError) input.setAttribute('aria-invalid', 'true');
      else input.removeAttribute('aria-invalid');
      const form = input.form;
      const time = form?.querySelector('select[name="time"]');
      if (time) {
        const elapsed = option => input.value && (input.value < bounds.min || (input.value === bounds.min && option.value <= currentTime));
        [...time.options].forEach(option => {
          if (!originalOptionStates.has(option)) originalOptionStates.set(option, option.disabled);
          option.disabled = originalOptionStates.get(option) || Boolean(option.value && elapsed(option));
        });
        const selected = time.selectedOptions[0];
        const timeError = selected?.disabled ? 'Выберите будущее время визита по часовому поясу ресторана.' : '';
        time.setCustomValidity(timeError);
        if (timeError) time.setAttribute('aria-invalid', 'true'); else time.removeAttribute('aria-invalid');
        let hint = form.querySelector('[data-booking-time-hint]');
        if (!hint) {
          hint = document.createElement('p'); hint.dataset.bookingTimeHint = ''; hint.className = 'booking-time-hint';
          hint.id = `${time.id || 'booking-time'}-hint`; hint.setAttribute('role', 'status'); hint.setAttribute('aria-live', 'polite');
          time.after(hint);
          time.setAttribute('aria-describedby', [time.getAttribute('aria-describedby'), hint.id].filter(Boolean).join(' '));
        }
        const futureOptions = [...time.options].some(option => option.value && !option.disabled);
        hint.textContent = input.value === bounds.min && !futureOptions ? 'На сегодня в расписании не осталось будущего времени. Выберите другую дату.' : timeError || `Местное время ресторана: ${currentTime}. Время визита должно быть в будущем.`;
        hint.classList.toggle('is-error', Boolean(timeError));
      }
      if (!boundBookingInputs.has(input)) {
        boundBookingInputs.add(input);
        input.addEventListener('input', updateBookingDateLimits);
        input.addEventListener('change', updateBookingDateLimits);
        time?.addEventListener('change', updateBookingDateLimits);
        form?.addEventListener('focusin', updateBookingDateLimits);
        form?.addEventListener('reset', () => queueMicrotask(updateBookingDateLimits));
      }
    });
  }
  window.TokyoPremiumBookingDates = { bounds: restaurantDateBounds, update: updateBookingDateLimits, validate: form => { updateBookingDateLimits(); return form.reportValidity(); }, timeZone: restaurantTimeZone };
  updateBookingDateLimits();
  document.addEventListener('visibilitychange', () => { if (!document.hidden) updateBookingDateLimits(); });
  window.addEventListener('pageshow', updateBookingDateLimits);
  document.addEventListener('click', event => {
    const control = event.target.closest('button,input[type="submit"]');
    if (control?.form?.dataset.api === 'booking') updateBookingDateLimits();
  }, true);
  document.addEventListener('submit', event => {
    if (event.target?.dataset.api !== 'booking') return;
    updateBookingDateLimits();
    if (!event.target.reportValidity()) { event.preventDefault(); event.stopImmediatePropagation(); }
  }, true);
  if (document.querySelector('form[data-api="booking"]')) window.setInterval(() => { if (!document.hidden) updateBookingDateLimits(); }, 60000);

  const header = document.querySelector('.site-header');
  const nav = document.querySelector('#site-navigation');
  const toggle = header?.querySelector('.mobile-menu');
  if (!header || !nav || !toggle) return;
  header.querySelector('.header-cta')?.setAttribute('aria-label', 'Бронировать столик');

  // Keep the desktop reference at 1024px; use the accessible menu below that breakpoint.
  const mobile = window.matchMedia('(max-width: 1023px)');
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
    zoneGrid?.closest('.section')?.setAttribute('id', 'spaces');
    zoneGrid?.querySelectorAll('.zone-card').forEach(card => card.removeAttribute('data-gallery-filter'));
  }
  const bookingForm = document.querySelector('.booking-form');
  if (bookingForm) {
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
