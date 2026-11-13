(function () {
  'use strict';
  const staticDemo = window.TOKYO_STATIC_DEMO === true || /(^|\.)github\.io$/i.test(location.hostname);
  const catalog = Array.isArray(window.DISHES) ? window.DISHES : [];
  const money = value => `${Math.max(0, Math.round(Number(value) || 0)).toLocaleString('ru-RU')} ₸`;

  document.querySelectorAll('[data-copy-code]').forEach(button => {
    button.addEventListener('click', async () => {
      const code = String(button.dataset.copyCode || '').trim();
      if (!code) return;
      try { await navigator.clipboard.writeText(code); button.textContent = 'Скопировано'; }
      catch (_) { button.textContent = `Код: ${code}`; }
      window.setTimeout(() => { button.textContent = button.dataset.label || 'Скопировать код'; }, 1800);
    });
  });

  document.querySelectorAll('[data-copy-invite]').forEach(button => {
    button.addEventListener('click', async () => {
      let invite;
      try {
        const url = new URL(String(button.dataset.copyInvite || ''), location.href);
        if (url.origin !== location.origin) return;
        invite = url.href;
      } catch (_) { return; }
      try {
        await navigator.clipboard.writeText(invite);
        button.textContent = 'Ссылка скопирована';
      } catch (_) {
        button.textContent = invite;
      }
      window.setTimeout(() => { button.textContent = button.dataset.label || 'Скопировать ссылку'; }, 2200);
    });
  });

  const secretForm = document.querySelector('[data-tp-secret-unlock]');
  if (secretForm) {
    secretForm.addEventListener('submit', async event => {
      event.preventDefault();
      const form = event.currentTarget;
      const status = form.querySelector('[data-tp-secret-status]');
      const result = form.querySelector('[data-tp-secret-results]');
      const code = String(new FormData(form).get('code') || '').trim();
      const button = form.querySelector('button[type="submit"]');
      if (!code) return;
      button.disabled = true;
      status.textContent = staticDemo
        ? 'Проверяем локальный демонстрационный код…'
        : 'Проверяем доступ по серверным правилам…';
      try {
        let items;
        if (staticDemo) {
          if (code.toUpperCase() !== 'TOKYO-DEMO') throw new Error('Для статической демоверсии используйте код TOKYO-DEMO. Он открывает только локальный предпросмотр.');
          const previewIds = new Set([17, 18, 19, 20]);
          const demoCatalog = Array.isArray(window.TOKYO_SECRET_DEMO_ITEMS) ? window.TOKYO_SECRET_DEMO_ITEMS : catalog;
          items = demoCatalog.filter(dish => previewIds.has(Number(dish.id)));
        } else {
          const body = new URLSearchParams(new FormData(form));
          body.set('type', 'secret_unlock');
          const response = await fetch('api.php', { method: 'POST', body, credentials: 'same-origin', headers: { Accept: 'application/json' } });
          const payload = await response.json();
          if (!response.ok || !payload.ok) throw new Error(payload.message || 'Доступ не подтверждён.');
          items = payload.items || [];
        }
        result.replaceChildren();
        if (!items.length) throw new Error('Для этого кода нет доступных позиций.');
        // The shared cart validates IDs against the live catalog. Extend it only
        // after the server has granted access (or the static demo code was accepted).
        const mergedCatalog = new Map((Array.isArray(window.DISHES) ? window.DISHES : []).map(dish => [Number(dish.id), dish]));
        items.forEach(dish => mergedCatalog.set(Number(dish.id), dish));
        window.DISHES = Array.from(mergedCatalog.values());
        const title = document.createElement('h3');
        title.textContent = staticDemo ? 'Демонстрационный предпросмотр' : 'Доступные позиции';
        const note = document.createElement('p');
        note.textContent = staticDemo
          ? 'Это локальный просмотр открытых учебных блюд. Он не является серверной авторизацией.'
          : 'Состав вернул сервер по текущим правилам доступа.';
        const grid = document.createElement('div'); grid.className = 'dish-grid';
        items.forEach(dish => {
          const card = document.createElement('article'); card.className = 'dish-card';
          const imageWrap = document.createElement('div'); imageWrap.className = 'dish-image-wrap';
          const image = document.createElement('img'); image.src = String(dish.image_url || ''); image.alt = String(dish.name || 'Блюдо'); image.loading = 'lazy'; image.decoding = 'async'; imageWrap.append(image);
          const copy = document.createElement('div'); copy.className = 'dish-meta';
          const eyebrow = document.createElement('small'); eyebrow.textContent = 'SECRET MENU · учебная позиция';
          const heading = document.createElement('h3'); heading.textContent = String(dish.name || 'Позиция меню');
          const description = document.createElement('p'); description.textContent = String(dish.description || '');
          const bottom = document.createElement('div'); bottom.className = 'dish-bottom';
          const price = document.createElement('strong'); price.className = 'dish-price'; price.textContent = money(dish.price);
          const add = document.createElement('button'); add.type = 'button'; add.className = 'add-to-cart'; add.dataset.id = String(Number(dish.id)); add.textContent = 'Добавить +';
          bottom.append(price, add); copy.append(eyebrow, heading, description, bottom); card.append(imageWrap, copy); grid.append(card);
        });
        result.append(title, note, grid); result.hidden = false;
        try { localStorage.setItem('tokyo-secret-menu-code', staticDemo ? 'TOKYO-DEMO' : code); } catch (_) {}
        status.textContent = staticDemo ? 'Демо-доступ открыт только в этом браузере.' : 'Сервер разрешил просмотр позиций.';
      } catch (error) {
        result.replaceChildren(); result.hidden = true;
        status.textContent = error.message || 'Не удалось проверить доступ.';
      } finally { button.disabled = false; }
    });
  }

  const giftForm = document.querySelector('[data-tp-gift-form]');
  if (giftForm) {
    const preview = document.querySelector('[data-tp-gift-preview]');
    const recipient = document.querySelector('[data-tp-gift-recipient]');
    const greeting = document.querySelector('[data-tp-gift-greeting]');
    const amount = document.querySelector('[data-tp-gift-amount]');
    const print = document.querySelector('[data-tp-gift-print]');
    const status = document.querySelector('[data-tp-gift-status]');
    const key = 'tokyo-demo-gift-preview-v1';
    const updatePreview = (data, saved) => {
      recipient.textContent = data.recipient_name || 'Вашего гостя';
      greeting.textContent = data.greeting || 'Пусть этот вечер будет посвящён вкусам, разговорам и времени вместе.';
      amount.textContent = money(data.amount);
      preview.dataset.theme = ['sakura', 'enso', 'gold'].includes(data.theme) ? data.theme : 'sakura';
      print.disabled = !saved;
    };
    giftForm.addEventListener('change', () => {
      const data = Object.fromEntries(new FormData(giftForm).entries());
      updatePreview(data, false);
    });
    giftForm.addEventListener('submit', async event => {
      event.preventDefault();
      const button = giftForm.querySelector('button[type="submit"]');
      const data = Object.fromEntries(new FormData(giftForm).entries());
      data.amount = Number(data.amount);
      if (!giftForm.reportValidity()) return;
      button.disabled = true;
      status.textContent = 'Сохраняем только демонстрационный эскиз…';
      try {
        if (staticDemo) {
          const previewRecord = { recipient_name: data.recipient_name, greeting: data.greeting, amount: data.amount, theme: data.theme, status: 'preview_only', saved_at: new Date().toISOString() };
          const saved = JSON.parse(localStorage.getItem(key) || '[]');
          saved.push(previewRecord); localStorage.setItem(key, JSON.stringify(saved.slice(-10)));
        } else {
          const body = new URLSearchParams(new FormData(giftForm)); body.set('type', 'gift_preview');
          const response = await fetch('api.php', { method: 'POST', body, credentials: 'same-origin', headers: { Accept: 'application/json' } });
          const payload = await response.json();
          if (!response.ok || !payload.ok) throw new Error(payload.message || 'Не удалось сохранить эскиз.');
        }
        updatePreview(data, true);
        status.textContent = staticDemo
          ? 'Эскиз сохранён только в localStorage этого браузера. Он не является сертификатом и не был отправлен.'
          : 'Эскиз сохранён с учебным статусом preview_only; он не оплачен, не отправлен и не погашается.';
      } catch (error) { status.textContent = error.message || 'Не удалось сохранить эскиз.'; }
      finally { button.disabled = false; }
    });
    print.addEventListener('click', () => { if (!print.disabled) window.print(); });
    giftForm.dispatchEvent(new Event('change'));
  }

  const quoteRoot = document.querySelector('[data-tp-checkout-quote]');
  if (quoteRoot) {
    const form = document.querySelector('#checkout-form');
    const quoteButton = quoteRoot.querySelector('[data-tp-quote]');
    const quoteStatus = quoteRoot.querySelector('[data-tp-quote-status]');
    const quoteTotal = quoteRoot.querySelector('[data-tp-quote-total]');
    const giftChoice = quoteRoot.querySelector('[data-tp-gift-choice]');
    const giftSelect = quoteRoot.querySelector('[name="gift_dish_id"]');
    const clearQuote = message => { quoteTotal.hidden = true; quoteTotal.textContent = ''; if (message) quoteStatus.textContent = message; };
    const staticQuote = (payload, fields) => {
      const subtotal = Number(window.TokyoCart?.total() || 0);
      const fulfillment = String(fields.get('fulfillment') || 'delivery');
      const code = String(fields.get('promo_code') || '').trim().toUpperCase();
      let discount = 0;
      if (code) {
        if (code === 'DEMO10') discount = Math.min(5000, Math.floor(subtotal * 0.10));
        else if (code === 'TOKYOHOUR') {
          const parts = new Intl.DateTimeFormat('en-GB', { timeZone: 'Asia/Qyzylorda', weekday: 'short', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }).formatToParts(new Date());
          const values = Object.fromEntries(parts.map(part => [part.type, part.value]));
          const hour = Number(values.hour);
          const weekday = ['Mon','Tue','Wed','Thu','Fri'].includes(values.weekday);
          if (!weekday || hour < 14 || hour >= 17) throw new Error('TOKYOHOUR действует в учебном сценарии по будням с 14:00 до 17:00, Asia/Qyzylorda.');
          discount = Math.min(4000, Math.floor(subtotal * 0.15));
        } else throw new Error('В локальной демонстрации доступны коды DEMO10 и TOKYOHOUR.');
      }
      return { subtotal, discount_total: discount, delivery_fee: 0, total: Math.max(0, subtotal - discount), promo_code: code, requires_gift_choice: false, gift_options: [], demo_only: true, fulfillment, estimated: true };
    };
    const renderQuote = data => {
      quoteTotal.textContent = 'Подытог ' + money(data.subtotal) + ' · скидка ' + money(data.discount_total) + ' · получение ' + money(data.delivery_fee) + ' · итог ' + money(data.total) + (data.estimated ? ' · локальная оценка' : ' · серверный расчёт');
      quoteTotal.hidden = false;
      giftSelect.replaceChildren(new Option('Выберите подарок', ''));
      const options = Array.isArray(data.gift_options) ? data.gift_options : [];
      options.forEach(item => giftSelect.add(new Option(item.name + ' · подарок', String(item.id))));
      const needed = Boolean(data.requires_gift_choice && options.length);
      giftChoice.hidden = !needed;
      giftSelect.disabled = !needed;
      giftSelect.required = needed;
      if (!needed) giftSelect.value = '';
      quoteStatus.textContent = data.estimated
        ? 'Это только локальный расчёт GitHub Pages. Он не резервирует акцию и не подтверждает скидку.'
        : 'Сервер пересчитал корзину, ограничения и доступный остаток подарков. Цена будет проверена повторно при заказе.';
    };
    quoteButton?.addEventListener('click', async () => {
      if (!form || !window.TokyoCart) return;
      quoteButton.disabled = true;
      quoteStatus.textContent = 'Проверяем корзину и условия предложения…';
      quoteTotal.hidden = true;
      try {
        const cartPayload = window.TokyoCart.orderPayload();
        if (!cartPayload.items.length && !cartPayload.custom_sets.length) throw new Error('Сначала добавьте блюдо или соберите сет.');
        const fields = new FormData(form);
        fields.set('type', 'offer_quote');
        fields.set('items', JSON.stringify(cartPayload.items));
        fields.set('custom_sets', JSON.stringify(cartPayload.custom_sets));
        let quote;
        if (staticDemo) quote = staticQuote(cartPayload, fields);
        else {
          const response = await fetch('api.php', { method: 'POST', body: new URLSearchParams(fields), credentials: 'same-origin', headers: { Accept: 'application/json' } });
          const body = await response.json();
          if (!response.ok || !body.ok) throw new Error(body.message || 'Не удалось рассчитать предложения.');
          quote = body;
        }
        renderQuote(quote);
      } catch (error) { clearQuote(error.message || 'Не удалось рассчитать итог.'); }
      finally { quoteButton.disabled = false; }
    });
    form?.addEventListener('reset', () => { giftSelect.required = false; giftSelect.disabled = true; giftChoice.hidden = true; clearQuote('Корзина сброшена. Для нового расчёта проверьте состав и условия ещё раз.'); });
    document.addEventListener('tokyo-cart-updated', () => clearQuote('Состав корзины изменился. Пересчитайте промокод и итог перед оформлением.'));
  }


  const privilegeRoot = document.querySelector('[data-tp-demo-loyalty]');
  if (privilegeRoot && staticDemo) {
    const readArray = key => { try { const value = JSON.parse(localStorage.getItem(key) || '[]'); return Array.isArray(value) ? value : []; } catch (_) { return []; } };
    const orders = readArray('tokyo-demo-orders');
    const spend = orders.reduce((sum, order) => sum + Math.max(0, Number(order.total) || 0), 0);
    const points = Math.floor(spend / 100);
    const tiers = [{name:'Silver',threshold:0},{name:'Gold',threshold:150000},{name:'Platinum',threshold:400000}];
    const current = tiers.slice().reverse().find(tier => spend >= tier.threshold) || tiers[0];
    const next = tiers.find(tier => tier.threshold > current.threshold);
    privilegeRoot.querySelector('[data-tp-loyalty-tier]').textContent = current.name;
    privilegeRoot.querySelector('[data-tp-loyalty-card]').dataset.tier = current.name;
    privilegeRoot.querySelector('[data-tp-loyalty-points]').textContent = points.toLocaleString('ru-RU');
    const progress = privilegeRoot.querySelector('[data-tp-loyalty-progress]');
    const percent = next ? Math.min(100, Math.round(((spend-current.threshold)/(next.threshold-current.threshold))*100)) : 100;
    progress.value = percent; progress.setAttribute('aria-label', next ? 'Демо-прогресс к '+next.name : 'Достигнут наивысший демо-уровень');
    privilegeRoot.querySelector('[data-tp-loyalty-progress-label]').textContent = next ? 'До '+next.name+': '+Math.max(0,next.threshold-spend).toLocaleString('ru-RU')+' ₸ учебного оборота.' : 'Высший уровень локального демонстрационного сценария.';
    const referral = privilegeRoot.querySelector('[data-tp-demo-referral]');
    let referralCode = '';
    try { referralCode = localStorage.getItem('tokyo-demo-referral-code') || ''; if (!/^TP-DEMO-[A-Z0-9]{6}$/.test(referralCode)) { referralCode = 'TP-DEMO-'+Math.random().toString(36).slice(2,8).toUpperCase(); localStorage.setItem('tokyo-demo-referral-code',referralCode); } } catch (_) { referralCode = 'TP-DEMO-LOCAL'; }
    referral.textContent = referralCode;
    privilegeRoot.querySelector('[data-tp-copy-referral]')?.addEventListener('click', async event => { const button=event.currentTarget; try { await navigator.clipboard.writeText(referralCode); button.textContent='Скопировано'; } catch (_) { button.textContent=referralCode; } window.setTimeout(()=>{button.textContent='Скопировать код';},1600); });
  }

})();
