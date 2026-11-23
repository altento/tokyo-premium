document.addEventListener('DOMContentLoaded', () => {
  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
  const cart = window.TokyoCart;

  const menu = $('.main-nav');
  const mobileMenu = $('.mobile-menu');
  mobileMenu?.addEventListener('click', () => {
    const open = menu.classList.toggle('open');
    mobileMenu.setAttribute('aria-expanded', String(open));
  });

  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver(entries => entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('visible');
        observer.unobserve(entry.target);
      }
    }), { threshold: .12 });
    $$('.reveal').forEach(node => observer.observe(node));
  } else $$('.reveal').forEach(node => node.classList.add('visible'));

  const toast = $('.toast');
  let toastTimer;
  function notify(message) {
    if (!toast) return;
    toast.textContent = message;
    toast.classList.add('show');
    window.clearTimeout(toastTimer);
    toastTimer = window.setTimeout(() => toast.classList.remove('show'), 3200);
  }

  const panel = $('.cart-panel');
  const cartToggles = $$('.cart-toggle');
  let lastTrigger = null;
  if (panel) { panel.inert = true; panel.setAttribute('role', 'dialog'); panel.setAttribute('aria-modal', 'true'); }
  function closeCart() {
    panel?.classList.remove('open');
    if (panel) panel.inert = true;
    panel?.setAttribute('aria-hidden', 'true');
    lastTrigger?.focus();
  }
  function renderCart() {
    if (!cart) return;
    const items = cart.get();
    const customSets = cart.customSetSnapshot();
    const lines = panel ? $('.cart-lines', panel) : null;
    const count = cart.count();
    $$('.header-cart-count, .mobile-cart-count').forEach(badge => { badge.textContent = String(count); });
    cartToggles.forEach(cartToggle => {
      const countNode = $('b', cartToggle);
      if (countNode) countNode.textContent = String(count);
      cartToggle.setAttribute('aria-label', `Открыть быстрый просмотр заказа, позиций: ${count}`);
    });
    if (!lines) return;
    lines.replaceChildren();
    if (items.length === 0 && customSets.length === 0) {
      const empty = document.createElement('p');
      empty.className = 'empty-state';
      empty.textContent = 'Корзина пока пуста. Выберите блюдо в меню.';
      lines.append(empty);
    }
    const dishes = new Map(cart.catalog().map(dish => [Number(dish.id), dish]));
    for (const item of items) {
      const dish = dishes.get(item.id);
      if (!dish) continue;
      const row = document.createElement('article');
      row.className = 'cart-line';
      const copy = document.createElement('div');
      const name = document.createElement('strong');
      name.textContent = dish.name;
      const price = document.createElement('small');
      price.textContent = `${Number(dish.price).toLocaleString('ru-RU')} ₸ за порцию`;
      copy.append(name, price);
      const controls = document.createElement('div');
      controls.className = 'cart-qty';
      controls.append(
        cartAction('decrease', item.id, 'Уменьшить количество'),
        Object.assign(document.createElement('span'), { textContent: String(item.qty) }),
        cartAction('increase', item.id, 'Увеличить количество'),
        cartAction('remove', item.id, `Удалить ${dish.name}`)
      );
      row.append(copy, controls);
      lines.append(row);
    }
    const dishesById = new Map(cart.catalog().map(dish => [Number(dish.id), dish]));
    for (const set of customSets) {
      const row = document.createElement('article');
      row.className = 'cart-line cart-custom-set-line';
      const copy = document.createElement('div');
      const name = document.createElement('strong');
      name.textContent = set.name;
      const details = document.createElement('small');
      details.textContent = `Авторский сет · оценка ${set.unitPrice.toLocaleString('ru-RU')} ₸ за сет`;
      const composition = document.createElement('small');
      composition.textContent = set.items.map(item => `${dishesById.get(item.id)?.name || 'Блюдо'} × ${item.qty}`).join(' · ');
      copy.append(name, details, composition);
      const controls = document.createElement('div');
      controls.className = 'cart-qty';
      controls.append(
        customSetAction('decrease', set.key, 'Уменьшить количество сета'),
        Object.assign(document.createElement('span'), { textContent: String(set.qty) }),
        customSetAction('increase', set.key, 'Увеличить количество сета'),
        customSetAction('remove', set.key, `Удалить ${set.name}`)
      );
      row.append(copy, controls);
      lines.append(row);
    }
    const total = panel ? $('.cart-total strong', panel) : null;
    if (total) total.textContent = `${cart.total().toLocaleString('ru-RU')} ₸`;
  }
  function cartAction(action, id, label) {
    const button = document.createElement('button');
    button.type = 'button';
    button.dataset.cartAction = action;
    button.dataset.id = String(id);
    button.setAttribute('aria-label', label);
    button.textContent = action === 'decrease' ? '−' : action === 'increase' ? '+' : 'Удалить';
    return button;
  }
  function customSetAction(action, key, label) {
    const button = document.createElement('button');
    button.type = 'button';
    button.dataset.cartAction = action;
    button.dataset.customSetKey = key;
    button.setAttribute('aria-label', label);
    button.textContent = action === 'decrease' ? '−' : action === 'increase' ? '+' : 'Удалить';
    return button;
  }

  cartToggles.forEach(cartToggle => cartToggle.addEventListener('click', () => {
    if (!panel) return;
    lastTrigger = cartToggle;
    panel.classList.add('open');
    panel.inert = false;
    panel.setAttribute('aria-hidden', 'false');
    $('.cart-close', panel)?.focus();
  }));
  if (panel) $('.cart-close', panel)?.addEventListener('click', closeCart);
  panel?.addEventListener('click', event => {
    const button = event.target.closest('[data-cart-action]');
    if (!button || !cart) return;
    const customSetKey = button.dataset.customSetKey;
    if (customSetKey) {
      if (button.dataset.cartAction === 'increase') cart.adjustCustomSet(customSetKey, 1);
      else if (button.dataset.cartAction === 'decrease') cart.adjustCustomSet(customSetKey, -1);
      else if (button.dataset.cartAction === 'remove') cart.removeCustomSet(customSetKey);
      return;
    }
    const id = Number(button.dataset.id);
    if (button.dataset.cartAction === 'increase') cart.adjust(id, 1);
    else if (button.dataset.cartAction === 'decrease') cart.adjust(id, -1);
    else if (button.dataset.cartAction === 'remove') cart.remove(id);
  });
  document.addEventListener('keydown', event => {
    if (!panel?.classList.contains('open')) return;
    if (event.key === 'Escape') { closeCart(); return; }
    if (event.key !== 'Tab') return;
    const focusable = $$('a[href],button:not(:disabled),input:not(:disabled),select:not(:disabled),textarea:not(:disabled),[tabindex]:not([tabindex="-1"])', panel).filter(item => !item.hidden);
    if (!focusable.length) { event.preventDefault(); return; }
    const first = focusable[0], last = focusable[focusable.length - 1];
    if (event.shiftKey && (document.activeElement === first || !panel.contains(document.activeElement))) {
      event.preventDefault(); last.focus();
    } else if (!event.shiftKey && (document.activeElement === last || !panel.contains(document.activeElement))) {
      event.preventDefault(); first.focus();
    }
  });

  document.addEventListener('click', event => {
    const repeatOrderButton = event.target.closest('[data-repeat-server-order]');
    if (repeatOrderButton) {
      const status = repeatOrderButton.closest('.account-order-content')?.querySelector('[data-order-repeat-status]');
      if (!status || !cart) return;
      let payload;
      try { payload = JSON.parse(repeatOrderButton.dataset.repeatServerOrder || ''); } catch (_) { payload = null; }
      status.replaceChildren();
      if (!payload || !cart.addOrder(payload)) {
        status.textContent = 'Не удалось восстановить состав. Проверьте корзину и доступность блюд.';
        status.className = 'form-status error account-order-repeat-status';
      } else {
        status.append(document.createTextNode('Состав добавлен. Проверьте текущие цены и корзину перед оформлением. '));
        const link = document.createElement('a');
        link.className = 'account-inline-link';
        link.href = 'pages.php?page=cart';
        link.textContent = 'Открыть корзину ↗';
        status.append(link);
        status.className = 'form-status success account-order-repeat-status';
      }
      status.hidden = false;
      return;
    }
    const addButton = event.target.closest('.add-to-cart');
    if (addButton) {
      if (!cart) return;
      const quantity = addButton.dataset.qty === undefined ? 1 : Number(addButton.dataset.qty);
      const id = Number(addButton.dataset.id);
      const feedback = (message, error = false) => {
        const status = addButton.closest('dialog')?.querySelector('[data-cart-action-status]');
        if (status) { status.textContent = message; status.className = 'form-status ' + (error ? 'error' : 'success'); }
        else notify(message);
      };
      const existing = cart.get().find(item => item.id === id)?.qty || 0;
      const remaining = Math.max(0, cart.maxQty - existing);
      if (Number.isSafeInteger(quantity) && quantity > remaining) {
        feedback(remaining ? `Можно добавить ещё ${remaining} шт. этого блюда. Лимит в корзине — ${cart.maxQty}.` : `В корзине уже ${cart.maxQty} шт. этого блюда. Измените количество в корзине.`, true);
        return;
      }
      const added = cart.addMany([{ id, qty: quantity }]);
      feedback(added ? `Добавлено в заказ: ${quantity} шт.` : 'Не удалось добавить блюдо. Проверьте количество и доступность.', !added);
      return;
    }
    const favoriteButton = event.target.closest('[data-favorite]');
    if (favoriteButton && cart) {
      cart.toggleFavorite(Number(favoriteButton.dataset.favorite));
      renderFavorites();
    }
  });

  $$('[data-qty-step]').forEach(button => button.addEventListener('click', () => {
    const output = $('#dish-qty');
    const addButton = $('.detail-actions .add-to-cart');
    if (!output || !addButton) return;
    const current = Number(output.value || output.textContent || 1);
    const step = Number(button.dataset.qtyStep);
    const quantity = Math.max(1, Math.min(cart?.maxQty || 20, current + (Number.isInteger(step) ? step : 0)));
    output.value = String(quantity);
    output.textContent = String(quantity);
    addButton.dataset.qty = String(quantity);
  }));

  $('[data-detail-gallery]')?.addEventListener('click', event => {
    const button = event.target.closest?.('[data-detail-gallery-image]');
    const image = $('#dish-main-image');
    if (!button || !image) return;
    const source = button.dataset.detailGalleryImage;
    if (!source) return;
    const reveal = () => image.classList.remove('is-changing');
    image.classList.add('is-changing');
    image.addEventListener('load', reveal, { once: true });
    image.addEventListener('error', reveal, { once: true });
    image.alt = button.dataset.detailAlt || image.alt;
    if (button.dataset.detailSrcset) image.setAttribute('srcset', button.dataset.detailSrcset);
    else image.removeAttribute('srcset');
    image.src = source;
    if (image.complete) requestAnimationFrame(reveal);
    const caption = $('[data-detail-gallery-caption]');
    if (caption) caption.textContent = button.dataset.detailCaption || '';
    document.querySelectorAll('[data-detail-gallery-image]').forEach(item => {
      const active = item === button;
      item.classList.toggle('is-active', active);
      item.setAttribute('aria-pressed', String(active));
    });
  });

  const categoryButtons = $$('.filter-btn[data-category]');
  const menuIngredientButtons = $$('.menu-subfilter[data-menu-ingredient]');
  const menuIngredientMatchers = {
    tuna: /тун(ец|ца|цу|цом|ц)/u,
    salmon: /лосос/u,
    eel: /угор/u,
    shrimp: /кревет/u,
    scallop: /гребеш/u,
    roe: /икр/u
  };
  const menuCards = $$('.dish-card[data-category]');
  const matchesMenuIngredient = (card, ingredient) => {
    if (!ingredient || ingredient === 'all') return true;
    const matcher = menuIngredientMatchers[ingredient];
    return Boolean(matcher?.test((card.dataset.search || card.textContent).toLocaleLowerCase('ru')));
  };
  menuIngredientButtons.forEach(button => {
    const ingredient = button.dataset.menuIngredient;
    const matchingCount = ingredient === 'all' ? menuCards.length : menuCards.filter(card => matchesMenuIngredient(card, ingredient)).length;
    button.dataset.matchCount = String(matchingCount);
    button.hidden = ingredient !== 'all' && matchingCount === 0;
  });
  const search = $('.menu-search[type="search"]');
  const sort = $('#sort-menu');
  const menuViewButtons = $$('[data-menu-view]');
  const menuGrid = $('.menu-page .dish-grid');
  const favoriteOnly = $('#favorites-only');
  const menuPagination = $('#menu-pagination');
  const originalDishOrder = new Map($$('.dish-card[data-category]').map((card, index) => [card, index]));
  let menuPage = 1;
  let menuPageSize = window.matchMedia('(max-width: 700px)').matches ? 6 : 12;
  function renderFavorites() {
    if (!cart) return;
    const ids = cart.getFavorites();
    $$('[data-favorite]').forEach(button => {
      const active = ids.includes(Number(button.dataset.favorite));
      button.textContent = active ? '♥' : '♡';
      button.setAttribute('aria-pressed', String(active));
    });
    filterMenu(false);
  }
  function filterMenu(resetPage = true) {
    if (resetPage) menuPage = 1;
    const matches = [];
    const category = $('.filter-btn.active[data-category]')?.dataset.category || 'all';
    const ingredient = $('.menu-subfilter[aria-pressed="true"]')?.dataset.menuIngredient || 'all';
    const query = (search?.value || '').trim().toLocaleLowerCase('ru');
    const favoriteIds = new Set(cart?.getFavorites() || []);
    const cards = menuGrid ? $$('.dish-card[data-category]', menuGrid) : menuCards;
    cards.forEach(card => {
      const id = Number(card.querySelector('[data-favorite]')?.dataset.favorite || card.querySelector('.add-to-cart')?.dataset.id);
      const text = (card.dataset.search || card.textContent).toLocaleLowerCase('ru');
      const show = (category === 'all' || card.dataset.category === category)
        && text.includes(query)
        && matchesMenuIngredient(card, ingredient)
        && (!favoriteOnly?.classList.contains('active') || favoriteIds.has(id));
      if (show) matches.push(card);
    });
    const visible = matches.length;
    const pages = menuPagination ? Math.max(1, Math.ceil(visible / menuPageSize)) : 1;
    if (menuPage > pages) menuPage = pages;
    const matchIndex = new Map(matches.map((card, index) => [card, index]));
    cards.forEach(card => {
      const index = matchIndex.get(card);
      card.hidden = index === undefined || (Boolean(menuPagination) && (index < (menuPage - 1) * menuPageSize || index >= menuPage * menuPageSize));
    });
    if (menuPagination) {
      menuPagination.replaceChildren();
      menuPagination.hidden = pages < 2;
      if (pages > 1) {
        const addPage = (label, page, aria, active = false, disabled = false) => {
          const button = document.createElement('button');
          button.type = 'button'; button.className = 'menu-page-button' + (active ? ' active' : '');
          button.dataset.pageGo = String(page); button.textContent = label; button.setAttribute('aria-label', aria);
          if (active) button.setAttribute('aria-current', 'page');
          button.disabled = disabled;
          menuPagination.append(button);
        };
        addPage('‹', Math.max(1, menuPage - 1), 'Предыдущая страница', false, menuPage === 1);
        for (let page = 1; page <= pages; page++) addPage(String(page), page, 'Страница ' + page, page === menuPage);
        addPage('›', Math.min(pages, menuPage + 1), 'Следующая страница', false, menuPage === pages);
      }
    }
    const empty = $('.menu-page .empty-state');
    if (empty && (categoryButtons.length || search || favoriteOnly)) empty.hidden = visible > 0;
    if (menuGrid) {
      let status = $('.menu-results-status');
      if (!status) { status = document.createElement('p'); status.className = 'menu-results-status'; status.setAttribute('role', 'status'); status.setAttribute('aria-live', 'polite'); menuGrid.before(status); }
      status.textContent = `Найдено блюд: ${visible}${visible ? ` · Страница ${menuPage} из ${pages}` : ''}.`;
    }
  }
  const menuPageBreakpoint = window.matchMedia('(max-width: 700px)');
  menuPageBreakpoint.addEventListener?.('change', event => {
    menuPageSize = event.matches ? 6 : 12;
    filterMenu();
  });
  categoryButtons.forEach(button => button.addEventListener('click', () => {
    categoryButtons.forEach(item => { item.classList.toggle('active', item === button); item.setAttribute('aria-pressed', String(item === button)); });
    filterMenu();
  }));
  menuIngredientButtons.forEach(button => button.addEventListener('click', () => {
    menuIngredientButtons.forEach(item => item.setAttribute('aria-pressed', String(item === button)));
    filterMenu();
  }));
  menuPagination?.addEventListener('click', event => {
    const button = event.target.closest('[data-page-go]');
    if (!button) return;
    menuPage = Number(button.dataset.pageGo) || 1;
    filterMenu(false);
    menuPagination.querySelector('[aria-current="page"]')?.focus({ preventScroll: true });
    $('#menu-catalog')?.scrollIntoView({ block: 'start', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' });
  });
  $$('[data-category-link]').forEach(link => link.addEventListener('click', () => {
    const button = categoryButtons.find(item => item.dataset.category === link.dataset.categoryLink);
    button?.click();
  }));
  search?.addEventListener('input', filterMenu);
  menuViewButtons.forEach(button => button.addEventListener('click', () => {
    if (!menuGrid || !['grid', 'list'].includes(button.dataset.menuView)) return;
    menuGrid.dataset.view = button.dataset.menuView;
    menuViewButtons.forEach(item => item.setAttribute('aria-pressed', String(item === button)));
  }));
  categoryButtons.forEach(button => button.setAttribute('aria-pressed', String(button.classList.contains('active'))));
  favoriteOnly?.setAttribute('aria-pressed', String(favoriteOnly.classList.contains('active')));
  favoriteOnly?.addEventListener('click', () => { favoriteOnly.classList.toggle('active'); favoriteOnly.setAttribute('aria-pressed', String(favoriteOnly.classList.contains('active'))); filterMenu(); });
  sort?.addEventListener('change', () => {
    const grid = $('.dish-grid');
    if (!grid) return;
    const cards = $$('.dish-card[data-category]', grid);
    const dishes = new Map((window.DISHES || []).map(dish => [Number(dish.id), dish]));
    cards.sort((a, b) => {
      const idA = Number(a.querySelector('[data-favorite]')?.dataset.favorite || a.querySelector('.add-to-cart')?.dataset.id);
      const idB = Number(b.querySelector('[data-favorite]')?.dataset.favorite || b.querySelector('.add-to-cart')?.dataset.id);
      const dishA = dishes.get(idA), dishB = dishes.get(idB);
      if (sort.value === 'price-asc') return Number(dishA?.price || 0) - Number(dishB?.price || 0);
      if (sort.value === 'price-desc') return Number(dishB?.price || 0) - Number(dishA?.price || 0);
      if (sort.value === 'name') return String(dishA?.name || '').localeCompare(String(dishB?.name || ''), 'ru');
      return (originalDishOrder.get(a) ?? 0) - (originalDishOrder.get(b) ?? 0);
    });
    cards.forEach(card => grid.append(card));
    filterMenu();
  });

  document.addEventListener('tokyo-cart-updated', renderCart);
  document.addEventListener('tokyo-favorites-updated', renderFavorites);
  renderCart();
  renderFavorites();

  document.querySelectorAll('form[data-api]').forEach(form => {
    if (form.dataset.api === 'booking') {
      const clearBookingKey = () => { if (form.dataset.submitting !== 'true') delete form.dataset.bookingRequestKey; };
      form.addEventListener('input', clearBookingKey);
      form.addEventListener('change', clearBookingKey);
      form.addEventListener('reset', clearBookingKey);
    }
    form.addEventListener('submit', async event => {
    event.preventDefault();
    const type = form.dataset.api;
    const status = $('.form-status', form);
    const button = event.submitter instanceof HTMLButtonElement ? event.submitter : $('button[type="submit"], input[type="submit"]', form) || [...document.querySelectorAll('button[type="submit"][form], input[type="submit"][form]')].find(control => control.form === form);
    if (form.dataset.submitting === 'true') return;
    if (type === 'order' && !(cart?.get().length || cart?.getCustomSets().length)) {
      if (status) { status.textContent = 'Добавьте блюда или соберите сет перед оформлением.'; status.className = 'form-status error'; }
      return;
    }
    if (type === 'order' && !(cart && await cart.beginCheckout())) {
      if (status) { status.textContent = 'Оформление уже выполняется. Подождите завершения запроса.'; status.className = 'form-status error'; }
      return;
    }
    form.dataset.submitting = 'true';
    if (button) button.disabled = true;
    if (status) { status.textContent = 'Отправляем…'; status.className = 'form-status'; }
    try {
      const data = new FormData(form);
      data.set('type', type);
      const submittedValues = (type === 'review' || type === 'contact') ? JSON.stringify([...data].filter(([name]) => name !== 'type')) : '';
      let preservedDraft = false;
      if (type === 'order' && cart) {
        const payload = cart.orderPayload();
        data.set('items', JSON.stringify(payload.items));
        data.set('custom_sets', JSON.stringify(payload.custom_sets));
      }
      const headers = { 'X-CSRF-Token': data.get('csrf') || '' };
      if (type === 'order' && cart) headers['Idempotency-Key'] = cart.checkoutRequestKey();
      if (type === 'booking') {
        if (!form.dataset.bookingRequestKey) {
          form.dataset.bookingRequestKey = window.crypto?.randomUUID?.() || `${Date.now().toString(36)}.${Math.random().toString(36).slice(2)}`;
        }
        headers['Idempotency-Key'] = form.dataset.bookingRequestKey;
      }
      const response = await fetch('api.php', { method: 'POST', body: data, headers });
      const result = await response.json();
      if (!response.ok || !result.ok) throw new Error(result.message || 'Не удалось отправить форму.');
      let paymentRedirect = '';
      if (type === 'order' && result.payment_method === 'freedompay' && result.payment_token) {
        const paymentData = new FormData();
        paymentData.set('csrf', data.get('csrf') || '');
        paymentData.set('payment_token', result.payment_token);
        const paymentResponse = await fetch('payment.php?action=start', {
          method: 'POST', body: paymentData, headers: { 'X-CSRF-Token': data.get('csrf') || '' }
        });
        const paymentResult = await paymentResponse.json();
        if (!paymentResponse.ok || !paymentResult.ok || (!paymentResult.paid && !paymentResult.redirect_url)) {
          throw new Error(paymentResult.message || 'Не удалось открыть страницу оплаты. Заказ сохранён; повторите попытку.');
        }
        paymentRedirect = paymentResult.redirect_url || '';
      }
      if (type === 'order') {
        cart.clearCheckoutRequestKey();
        cart.clear();
        if (window.TokyoCheckout) window.TokyoCheckout.reset(form);
        else form.reset();
      } else if (type === 'booking') { form.reset(); delete form.dataset.bookingRequestKey; }
      else if (type === 'review' || type === 'contact') {
        preservedDraft = JSON.stringify([...new FormData(form)]) !== submittedValues;
        if (!preservedDraft) form.reset();
      }
      if (status) { status.textContent = paymentRedirect ? (result.payment_test_mode ? 'Открываем тестовую страницу оплаты. Деньги не списываются.' : 'Открываем защищённую страницу оплаты.') : result.message + (preservedDraft ? ' Новые изменения сохранены в форме и пока не отправлены.' : ''); status.className = 'form-status success'; }
      if (paymentRedirect) window.location.assign(paymentRedirect);
    } catch (error) {
      if (status) { status.textContent = error.message; status.className = 'form-status error'; }
    } finally {
      if (type === 'order') cart?.finishCheckout();
      form.dataset.submitting = 'false';
      if (button) button.disabled = type === 'order' ? !(cart?.get().length || cart?.getCustomSets().length) : false;
    }
    });
  });
  document.querySelectorAll('form[data-payment-retry]').forEach(form => {
    form.addEventListener('submit', async event => {
      event.preventDefault();
      const button = form.querySelector('button[type="submit"]');
      const status = form.querySelector('.form-status');
      if (!button || button.disabled) return;
      button.disabled = true;
      if (status) { status.textContent = 'Подготавливаем платёжную страницу…'; status.className = 'form-status'; }
      try {
        const data = new FormData(form);
        const response = await fetch('payment.php?action=start', { method:'POST', body:data, headers:{'X-CSRF-Token':data.get('csrf') || ''} });
        const result = await response.json();
        if (!response.ok || !result.ok) throw new Error(result.message || 'Не удалось продолжить оплату.');
        if (result.paid) {
          if (status) { status.textContent = 'Заказ уже оплачен.'; status.className = 'form-status success'; }
          button.hidden = true;
          return;
        }
        if (!result.redirect_url) throw new Error('Ссылка на оплату не получена.');
        window.location.assign(result.redirect_url);
      } catch (error) {
        if (status) { status.textContent = error.message; status.className = 'form-status error'; }
      } finally {
        if (!button.hidden) button.disabled = false;
      }
    });
  });
});
