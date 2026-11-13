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
    const addButton = event.target.closest('.add-to-cart');
    if (addButton) {
      if (!cart) return;
      const quantity = addButton.dataset.qty === undefined ? 1 : Number(addButton.dataset.qty);
      const added = cart.add(Number(addButton.dataset.id), quantity);
      notify(added ? 'Добавлено в заказ' : 'Не удалось добавить блюдо. Проверьте количество.');
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

  $$('[data-detail-focus]').forEach(button => button.addEventListener('click', () => {
    const image = $('#dish-main-image');
    if (image) image.style.objectPosition = button.dataset.detailFocus;
    $$('[data-detail-focus]').forEach(item => item.setAttribute('aria-pressed', String(item === button)));
  }));

  const categoryButtons = $$('.filter-btn[data-category]');
  const search = $('.menu-search[type="search"]');
  const sort = $('#sort-menu');
  const favoriteOnly = $('#favorites-only');
  const menuPagination = $('#menu-pagination');
  const originalDishOrder = new Map($$('.dish-card[data-category]').map((card, index) => [card, index]));
  let menuPage = 1;
  const menuPageSize = 6;
  function renderFavorites() {
    if (!cart) return;
    const ids = cart.getFavorites();
    $$('[data-favorite]').forEach(button => {
      const active = ids.includes(Number(button.dataset.favorite));
      button.textContent = active ? '♥' : '♡';
      button.setAttribute('aria-pressed', String(active));
    });
    filterMenu();
  }
  function filterMenu(resetPage = true) {
    if (resetPage) menuPage = 1;
    const matches = [];
    const category = $('.filter-btn.active[data-category]')?.dataset.category || 'all';
    const query = (search?.value || '').toLocaleLowerCase('ru');
    const favoriteIds = new Set(cart?.getFavorites() || []);
    const cards = $$('.dish-card[data-category]');
    cards.forEach(card => {
      const id = Number(card.querySelector('[data-favorite]')?.dataset.favorite || card.querySelector('.add-to-cart')?.dataset.id);
      const text = (card.dataset.search || card.textContent).toLocaleLowerCase('ru');
      const show = (category === 'all' || card.dataset.category === category)
        && text.includes(query)
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
        const addPage = (label, page, aria, active = false) => {
          const button = document.createElement('button');
          button.type = 'button'; button.className = 'menu-page-button' + (active ? ' active' : '');
          button.dataset.pageGo = String(page); button.textContent = label; button.setAttribute('aria-label', aria);
          if (active) button.setAttribute('aria-current', 'page');
          menuPagination.append(button);
        };
        addPage('‹', Math.max(1, menuPage - 1), 'Предыдущая страница');
        for (let page = 1; page <= pages; page++) addPage(String(page), page, 'Страница ' + page, page === menuPage);
        addPage('›', Math.min(pages, menuPage + 1), 'Следующая страница');
      }
    }
    const empty = $('.empty-state');
    if (empty && (categoryButtons.length || search || favoriteOnly)) empty.hidden = visible > 0;
  }
  categoryButtons.forEach(button => button.addEventListener('click', () => {
    categoryButtons.forEach(item => item.classList.toggle('active', item === button));
    filterMenu();
  }));
  menuPagination?.addEventListener('click', event => {
    const button = event.target.closest('[data-page-go]');
    if (!button) return;
    menuPage = Number(button.dataset.pageGo) || 1;
    filterMenu(false);
    $('#menu-catalog')?.scrollIntoView({ block: 'start', behavior: 'smooth' });
  });
  $$('[data-category-link]').forEach(link => link.addEventListener('click', () => {
    const button = categoryButtons.find(item => item.dataset.category === link.dataset.categoryLink);
    button?.click();
  }));
  search?.addEventListener('input', filterMenu);
  favoriteOnly?.addEventListener('click', () => { favoriteOnly.classList.toggle('active'); filterMenu(); });
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
    const button = $('button[type="submit"]', form);
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
      if (type === 'order') {
        cart.clearCheckoutRequestKey();
        cart.clear();
        if (window.TokyoCheckout) window.TokyoCheckout.reset(form);
        else form.reset();
      } else if (type === 'booking') { form.reset(); delete form.dataset.bookingRequestKey; }
      else if (type === 'review') form.reset();
      if (status) { status.textContent = result.message; status.className = 'form-status success'; }
    } catch (error) {
      if (status) { status.textContent = error.message; status.className = 'form-status error'; }
    } finally {
      if (type === 'order') cart?.finishCheckout();
      form.dataset.submitting = 'false';
      if (button) button.disabled = type === 'order' ? !(cart?.get().length || cart?.getCustomSets().length) : false;
    }
    });
  });
});
