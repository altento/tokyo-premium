(function (global) {
  'use strict';

  const doc = global.document;
  const started = new WeakSet();
  const quickViewRoots = new WeakSet();
  const transitionRoots = new WeakSet();
  const motionRoots = new WeakSet();
  const dishRows = () => Array.isArray(global.DISHES) ? global.DISHES : [];
  const dishIndex = () => new Map(dishRows().map(dish => [Number(dish.id), dish]).filter(([id]) => Number.isSafeInteger(id) && id > 0));
  const one = (selector, root) => (root || doc).querySelector(selector);
  const many = (selector, root) => Array.from((root || doc).querySelectorAll(selector));
  const matches = (root, selector) => root instanceof Element && root.matches(selector) ? [root, ...many(selector, root)] : many(selector, root);
  const money = value => `${Math.max(0, Math.round(Number(value) || 0)).toLocaleString('ru-RU')} ₸`;
  const reduceMotion = () => global.matchMedia && global.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function setStatus(root, message) {
    const status = one('[data-tp-status]', root) || one('[role="status"]', root);
    if (status) {
      status.setAttribute('role', 'status');
      status.setAttribute('aria-live', 'polite');
      status.textContent = message;
    }
  }

  function safeText(element, value) {
    if (element) element.textContent = value == null ? '' : String(value);
  }

  function formatHref(link, format) {
    if (!link || !link.href || !format) return;
    try {
      const url = new URL(link.href, doc.baseURI);
      url.searchParams.set('format', format);
      link.href = url.pathname + url.search + url.hash;
    } catch (_) { /* Keep the authored route if the URL cannot be parsed. */ }
  }

  function initOmakase(root) {
    if (started.has(root)) return;
    started.add(root);
    const triggers = many('[data-tp-course]', root);
    const panels = many('[data-tp-course-panel]', root);
    if (!triggers.length || !panels.length) return;

    const activate = (step, focus) => {
      const current = triggers.find(button => String(button.dataset.step) === String(step)) || triggers[0];
      const activeStep = String(current.dataset.step || '1');
      triggers.forEach(button => {
        const active = button === current;
        button.setAttribute('role', 'tab');
        button.setAttribute('aria-selected', String(active));
        button.tabIndex = active ? 0 : -1;
        button.classList.toggle('is-active', active);
      });
      panels.forEach(panel => {
        const active = String(panel.dataset.step || '') === activeStep;
        panel.hidden = !active;
        panel.setAttribute('role', 'tabpanel');
        panel.tabIndex = 0;
      });
      const progress = one('[data-tp-omakase-progress]', root);
      const index = Math.max(0, triggers.indexOf(current));
      if (progress) {
        progress.setAttribute('role', 'progressbar');
        progress.setAttribute('aria-label', 'Прогресс дегустации Omakase');
        const percent = Math.round(((index + 1) / triggers.length) * 100);
        progress.setAttribute('aria-valuemin', '1');
        progress.setAttribute('aria-valuemax', String(triggers.length));
        progress.setAttribute('aria-valuenow', String(index + 1));
        progress.style.setProperty('--tp-progress', `${percent}%`);
      }
      if (focus) current.focus();
    };

    triggers[0].parentElement?.setAttribute('role', 'tablist');
    triggers[0].parentElement?.setAttribute('aria-label', 'Этапы дегустации');
    triggers.forEach((button, index) => {
      button.setAttribute('role', 'tab');
      const panel = panels.find(item => String(item.dataset.step) === String(button.dataset.step));
      if (panel) {
        if (!panel.id) panel.id = `tp-omakase-panel-${index + 1}`;
        if (!button.id) button.id = `tp-omakase-tab-${index + 1}`;
        button.setAttribute('aria-controls', panel.id);
        panel.setAttribute('aria-labelledby', button.id);
      }
      button.addEventListener('click', () => activate(button.dataset.step, false));
      button.addEventListener('keydown', event => {
        if (!['ArrowRight', 'ArrowDown', 'ArrowLeft', 'ArrowUp', 'Home', 'End'].includes(event.key)) return;
        event.preventDefault();
        const next = event.key === 'Home' ? 0 : event.key === 'End' ? triggers.length - 1
          : (index + (event.key === 'ArrowRight' || event.key === 'ArrowDown' ? 1 : -1) + triggers.length) % triggers.length;
        activate(triggers[next].dataset.step, true);
      });
    });

    root.setAttribute('role', 'region');
    root.setAttribute('aria-label', root.getAttribute('aria-label') || 'Этапы дегустации Omakase');
    const initial = triggers.find(button => button.getAttribute('aria-selected') === 'true') || triggers[0];
    activate(initial.dataset.step, false);
    many('[data-tp-omakase-select]', root).forEach(link => {
      const format = link.dataset.format || link.textContent.trim();
      if (link instanceof HTMLAnchorElement) formatHref(link, format);
      link.addEventListener('click', () => {
        try { global.sessionStorage.setItem('tokyo-selected-omakase', format); } catch (_) { }
      });
    });
  }

  function initSetBuilder(root) {
    if (started.has(root)) return;
    started.add(root);
    const selected = new Map();
    const minDistinct = Math.max(1, Number(root.dataset.minDistinct) || 3);
    const maxDistinct = Math.max(minDistinct, Number(root.dataset.maxDistinct) || 5);
    const maxQuantity = Math.max(maxDistinct, Number(root.dataset.maxQuantity) || 8);
    const status = one('[data-tp-status]', root);
    const composition = one('[data-tp-set-composition]', root.parentElement || doc);

    const render = () => {
      let pieces = 0;
      let estimate = 0;
      const index = dishIndex();
      selected.forEach((qty, id) => {
        const dish = index.get(id);
        if (!dish) { selected.delete(id); return; }
        pieces += qty;
        estimate += Math.max(0, Number(dish.price) || 0) * qty;
      });
      many('[data-tp-set-qty]', root).forEach(output => safeText(output, selected.get(Number(output.dataset.dishId)) || 0));
      many('[data-tp-set-dish]', root).forEach(card => {
        const id = Number(card.dataset.dishId);
        const qty = selected.get(id) || 0;
        card.classList.toggle('is-selected', qty > 0);
        if (card.matches('button,[role="button"]')) card.setAttribute('aria-pressed', String(qty > 0));
        const addButton = one('[data-tp-set-add-dish]', card);
        if (addButton) addButton.setAttribute('aria-label', qty ? `Добавить ещё ${dishIndex().get(id)?.name || 'блюдо'}; выбрано ${qty}` : `Добавить ${dishIndex().get(id)?.name || 'блюдо'} в сет`);
      });
      many('[data-tp-set-total]', root).forEach(output => safeText(output, money(estimate)));
      if (composition) {
        composition.replaceChildren();
        composition.setAttribute('aria-live', 'polite');
        composition.setAttribute('aria-relevant', 'additions text');
        if (selected.size === 0) {
          const empty = doc.createElement('p');
          empty.className = 'tp-set-empty';
          empty.setAttribute('role', 'status');
          safeText(empty, 'Сет пока пуст. Добавьте блюда слева, чтобы увидеть состав.');
          composition.append(empty);
        } else {
          const list = doc.createElement('ul');
          list.className = 'tp-set-composition-list';
          selected.forEach((qty, id) => {
            const dish = index.get(id);
            if (!dish) return;
            const line = doc.createElement('li');
            const title = doc.createElement('span'); safeText(title, dish.name || `Блюдо ${id}`);
            const amount = doc.createElement('strong'); safeText(amount, `× ${qty}`);
            line.append(title, amount); list.append(line);
          });
          composition.append(list);
        }
      }
      const percent = Math.min(100, Math.round((pieces / maxQuantity) * 100));
      many('[data-tp-set-progress]', root).forEach(progress => {
        progress.setAttribute('role', 'progressbar');
        progress.setAttribute('aria-label', 'Комплектация сета');
        progress.style.setProperty('--tp-progress', `${percent}%`);
        progress.setAttribute('aria-valuemin', '0');
        progress.setAttribute('aria-valuemax', String(maxQuantity));
        progress.setAttribute('aria-valuenow', String(pieces));
        progress.setAttribute('aria-valuetext', `${selected.size} из ${maxDistinct} разных блюд; ${pieces} из ${maxQuantity} порций`);
      });
      const add = one('[data-tp-set-add]', root);
      if (add) add.disabled = selected.size < minDistinct || selected.size > maxDistinct || pieces > maxQuantity;
      many('[data-tp-set-count]', root).forEach(output => safeText(output, `${selected.size} / ${maxDistinct} блюд · ${pieces} / ${maxQuantity} порций`));
      if (status && !status.textContent) status.textContent = `Выбрано блюд: ${selected.size} из ${maxDistinct}; порций: ${pieces} из ${maxQuantity}. Цена предварительная.`;
    };

    const change = (id, delta) => {
      const dish = dishIndex().get(id);
      if (!dish || !Number.isSafeInteger(delta) || Math.abs(delta) !== 1) return;
      const current = selected.get(id) || 0;
      const total = Array.from(selected.values()).reduce((sum, qty) => sum + qty, 0);
      const next = Math.max(0, Math.min(4, current + delta));
      if (delta > 0 && total >= maxQuantity) { setStatus(root, `В этом варианте не больше ${maxQuantity} порций.`); return; }
      if (delta > 0 && !current && selected.size >= maxDistinct) { setStatus(root, `В этом варианте не больше ${maxDistinct} разных блюд.`); return; }
      if (next) selected.set(id, next); else selected.delete(id);
      if (status) status.textContent = `${dish.name}: ${next ? `выбрано ${next}` : 'удалено из сета'}. Сумма — ориентировочная и перепроверяется сервером.`;
      render();
    };

    root.addEventListener('click', event => {
      const stepper = event.target.closest('[data-tp-set-step]');
      if (stepper && root.contains(stepper)) {
        event.preventDefault();
        change(Number(stepper.dataset.dishId), Math.sign(Number(stepper.dataset.tpSetStep)) || 1);
        return;
      }
      const addDish = event.target.closest('[data-tp-set-add-dish]');
      if (addDish && root.contains(addDish)) {
        event.preventDefault();
        change(Number(addDish.dataset.dishId), 1);
      }
    });

    const addSet = one('[data-tp-set-add]', root);
    addSet?.addEventListener('click', event => {
      event.preventDefault();
      const items = Array.from(selected, ([id, qty]) => ({ id, qty }));
      const quantity = items.reduce((sum, row) => sum + row.qty, 0);
      const distinct = items.length;
      if (distinct < minDistinct || distinct > maxDistinct || quantity > maxQuantity) {
        setStatus(root, `Вариант требует ${minDistinct}–${maxDistinct} разных блюд и не больше ${maxQuantity} порций.`);
        return;
      }
      if (items.length === 0) return;
      const estimate = items.reduce((sum, row) => sum + (Number(dishIndex().get(row.id)?.price) || 0) * row.qty, 0);
      const detail = {
        variant: root.dataset.setType || 'signature',
        setType: root.dataset.setType || 'signature',
        name: root.dataset.setName || 'Tokyo — авторский сет',
        items,
        estimatedTotal: estimate,
        priceIsEstimate: true
      };
      let responded = false;
      let timeout = 0;
      const respond = result => {
        if (responded) return;
        responded = true;
        if (timeout) global.clearTimeout(timeout);
        const accepted = result === true || Boolean(result && typeof result === 'object' && result.ok === true);
        if (accepted) {
          setStatus(root, 'Сет добавлен в общую корзину. Итоговая цена будет подтверждена сервером.');
          return;
        }
        const reason = result && typeof result === 'object' && typeof result.message === 'string' ? result.message : 'Корзина отклонила этот состав. Проверьте ограничения сета и попробуйте ещё раз.';
        setStatus(root, reason);
      };
      detail.respond = respond;
      const customEvent = new CustomEvent('tokyo:custom-set:add', { bubbles: true, cancelable: true, detail });
      doc.dispatchEvent(customEvent);
      if (responded) return;
      if (customEvent.defaultPrevented) {
        setStatus(root, 'Проверяем добавление набора в корзину…');
        timeout = global.setTimeout(() => respond({ ok: false, message: 'Корзина не подтвердила добавление. Проверьте состав и повторите попытку.' }), 8000);
        return;
      }
      if (typeof global.TokyoCart?.addCustomSet === 'function') {
        let result;
        try { result = global.TokyoCart.addCustomSet(detail); }
        catch (error) { respond({ ok: false, message: error?.message || 'Не удалось добавить сет в корзину.' }); return; }
        if (result && typeof result.then === 'function') {
          setStatus(root, 'Проверяем добавление набора в корзину…');
          timeout = global.setTimeout(() => respond({ ok: false, message: 'Корзина не подтвердила добавление. Проверьте состав и повторите попытку.' }), 8000);
          result.then(respond).catch(error => respond({ ok: false, message: error?.message || 'Не удалось добавить сет в корзину.' }));
        } else respond(result);
        return;
      }
      setStatus(root, 'Состав рассчитан для предпросмотра. Корзина не подтвердила добавление этого варианта.');
    });

    render();
  }

  const signals = {
    fish: ['рыб', 'лосос', 'угор', 'тунец', 'сашими', 'нигири', 'кревет', 'гребеш', 'икр'],
    seafood: ['рыб', 'лосос', 'угор', 'тунец', 'сашими', 'нигири', 'кревет', 'гребеш', 'икр'],
    plant: ['вегет', 'овощ', 'тофу', 'гриб', 'авокадо', 'салат', 'мисо'],
    vegetarian: ['вегет', 'овощ', 'тофу', 'гриб', 'авокадо', 'салат', 'мисо'],
    spicy: ['остр', 'чили', 'васаби', 'кимчи', 'перец'],
    light: ['салат', 'сашими', 'тартар', 'чай', 'лёгк', 'легк'],
    sweet: ['десерт', 'матча', 'моти', 'тирамису', 'морожен']
  };
  const signalText = dish => `${dish.name || ''} ${dish.description || ''} ${dish.category_name || ''} ${dish.category || ''}`.toLocaleLowerCase('ru-RU');

  function scoreDish(dish, state) {
    const text = signalText(dish);
    let score = 0;
    const preference = state.preference || 'fish';
    if ((signals[preference] || []).some(word => text.includes(word))) score += 5;
    if (preference === 'plant' && /мяс|рыб|лосос|тунец|угор|кревет|сашими/.test(text)) score -= 8;
    if (state.mood === 'light' && signals.light.some(word => text.includes(word))) score += 3;
    if (state.mood === 'celebration' && /сашими|сэт|сет|десерт|икр|премиум|ассорти/.test(text)) score += 2;
    if (state.occasion === 'discovery' && /омак|сашими|сэт|сет|авторск/.test(text)) score += 3;
    if (state.occasion === 'friends' && /сет|сэт|ролл|ассорти/.test(text)) score += 2;
    const price = Math.max(0, Number(dish.price) || 0);
    const perGuest = Number(state.budget || 0) / Math.max(1, Number(state.guests || 1));
    if (perGuest > 0) score += price <= perGuest * 0.72 ? 2 : price <= perGuest ? 0 : -4;
    return score;
  }

  function initPerfectEvening(root) {
    if (started.has(root)) return;
    started.add(root);
    const steps = many('[data-tp-evening-step]', root);
    const options = many('[data-tp-evening-option]', root);
    const state = Object.create(null);
    let current = 0;
    const renderStep = () => {
      steps.forEach((step, index) => { step.hidden = index !== current; step.setAttribute('aria-current', index === current ? 'step' : 'false'); });
      const progress = one('[data-tp-evening-progress]', root);
      if (progress) {
        progress.setAttribute('role', 'progressbar');
        progress.setAttribute('aria-label', 'Шаг подбора вечера');
        const percent = Math.round(((current + 1) / Math.max(1, steps.length)) * 100);
        progress.style.setProperty('--tp-progress', `${percent}%`);
        progress.setAttribute('aria-valuemin', '1');
        progress.setAttribute('aria-valuemax', String(Math.max(1, steps.length)));
        progress.setAttribute('aria-valuenow', String(current + 1));
      }
      many('[data-tp-evening-back]', root).forEach(back => { back.disabled = current === 0; });
      many('[data-tp-evening-next]', root).forEach(next => { next.textContent = current >= steps.length - 1 ? 'Показать подбор ↗' : 'Продолжить ↗'; });
    };

    options.forEach(option => option.addEventListener('click', () => {
      const field = option.dataset.field;
      if (!field) return;
      state[field] = option.value || option.dataset.value || option.textContent.trim();
      options.filter(item => item.dataset.field === field).forEach(item => {
        const active = item === option;
        item.setAttribute('aria-pressed', String(active));
        item.classList.toggle('is-selected', active);
      });
      const step = option.closest('[data-tp-evening-step]');
      const next = step && one('[data-tp-evening-next]', step);
      if (next) next.disabled = false;
    }));

    many('[data-tp-evening-input]', root).forEach(input => {
      const update = () => {
        state[input.dataset.tpEveningInput] = input.value;
        const next = input.closest('[data-tp-evening-step]')?.querySelector('[data-tp-evening-next]');
        if (next) next.disabled = !input.checkValidity();
      };
      input.addEventListener('input', update);
      input.addEventListener('change', update);
      update();
    });

    const renderResult = () => {
      const result = one('[data-tp-evening-result]', root);
      if (!result) return;
      result.replaceChildren();
      const guestCount = Math.max(1, Math.min(12, Number(state.guests || 2)));
      const budget = Math.max(0, Number(state.budget || 0));
      const dishes = dishRows().filter(dish => dish && Number(dish.active ?? 1) !== 0)
        .sort((a, b) => scoreDish(b, state) - scoreDish(a, state) || Number(a.price) - Number(b.price))
        .slice(0, 3);
      const headline = doc.createElement('p');
      headline.className = 'tp-evening-summary';
      const zoneByAtmosphere = { sushi: 'Суши-стойка', main: 'Основной зал', private: 'Камерная комната', omakase: 'Зал дегустации' };
      const zone = zoneByAtmosphere[state.atmosphere] || zoneByAtmosphere.main;
      safeText(headline, `Подбор для ${guestCount} гостей · предпочтительная зона: ${zone}.`);
      result.append(headline);
      if (!dishes.length) {
        const empty = doc.createElement('p');
        empty.className = 'tp-empty-state';
        safeText(empty, 'В каталоге пока нет блюд для подбора.');
        result.append(empty);
      }
      const list = doc.createElement('div');
      list.className = 'tp-evening-dishes';
      let total = 0;
      dishes.forEach(dish => {
        const price = Math.max(0, Number(dish.price) || 0);
        const article = doc.createElement('article');
        article.className = 'tp-recommendation-card';
        if (dish.image_url) {
          const image = doc.createElement('img');
          image.src = dish.image_url;
          image.alt = String(dish.name || 'Блюдо');
          image.loading = 'lazy';
          image.decoding = 'async';
          article.append(image);
        }
        const copy = doc.createElement('div');
        const title = doc.createElement('h4'); safeText(title, dish.name);
        const description = doc.createElement('p'); safeText(description, dish.description || 'Позиция учебного каталога.');
        const priceNode = doc.createElement('strong'); safeText(priceNode, money(price));
        const add = doc.createElement('button');
        add.type = 'button'; add.className = 'button secondary'; add.dataset.id = String(dish.id); add.classList.add('add-to-cart');
        add.setAttribute('aria-label', `Добавить ${dish.name} в корзину`); safeText(add, 'Добавить в корзину +');
        copy.append(title, description, priceNode, add);
        article.append(copy); list.append(article);
        total += price * guestCount;
      });
      result.append(list);
      const totalNode = doc.createElement('p');
      totalNode.className = 'tp-evening-estimate';
      safeText(totalNode, `Ориентир на выбранные позиции для ${guestCount} гостей: ${money(total)}. Стоимость учебная; сервер проверяет цены при заказе.`);
      result.append(totalNode);
      if (budget && total > budget) {
        const budgetNote = doc.createElement('p');
        budgetNote.className = 'tp-budget-note';
        safeText(budgetNote, 'Подбор превышает указанный ориентир бюджета; измените предпочтения или бюджет.');
        result.append(budgetNote);
      }
      const note = doc.createElement('small');
      safeText(note, 'Подбор сформирован по правилам каталога, а не нейросетью.');
      result.append(note);
      result.hidden = false;
      steps.forEach(step => { step.hidden = true; });
      const booking = one('[data-tp-evening-booking]', root);
      if (booking instanceof HTMLAnchorElement) {
        const url = new URL(booking.href, doc.baseURI);
        url.searchParams.set('zone', zone);
        if (state.atmosphere === 'omakase') url.searchParams.set('format', 'Сезонный');
        booking.href = url.pathname + url.search + url.hash;
      }
      setStatus(root, `Подбор готов. Ориентировочная сумма ${money(total)} для ${guestCount} гостей.`);
    };

    root.addEventListener('click', event => {
      const next = event.target.closest('[data-tp-evening-next]');
      if (next && root.contains(next)) {
        if (next.disabled || !steps.length) return;
        if (current >= steps.length - 1) { renderResult(); return; }
        current = Math.min(steps.length - 1, current + 1);
        renderStep();
        return;
      }
      const back = event.target.closest('[data-tp-evening-back]');
      if (back && root.contains(back)) { current = Math.max(0, current - 1); renderStep(); }
    });
    one('[data-tp-evening-restart]', root)?.addEventListener('click', () => {
      current = 0;
      Object.keys(state).forEach(key => delete state[key]);
      options.forEach(option => { option.setAttribute('aria-pressed', 'false'); option.classList.remove('is-selected'); });
      const result = one('[data-tp-evening-result]', root); if (result) result.hidden = true;
      steps.forEach(step => { step.hidden = false; });
      renderStep(); setStatus(root, 'Подбор сброшен.');
    });
    renderStep();
  }

  function makeFallbackFloorplan(root, message) {
    const zones = [
      ['Основной зал', ['1', '2', '3', '4', '5', '6']],
      ['Суши-стойка', ['7', '8', '9']],
      ['Камерная комната', ['10', '11']],
      ['Зал дегустации', ['12', '13']]
    ];
    const wrap = doc.createElement('div'); wrap.className = 'tp-floorplan-fallback';
    const note = doc.createElement('p'); note.className = 'tp-floorplan-note'; safeText(note, message || 'Выберите предпочтительную зону и стол. Доступность не проверяется.'); wrap.append(note);
    zones.forEach(([zone, tables]) => {
      const group = doc.createElement('fieldset');
      const legend = doc.createElement('legend'); safeText(legend, zone); group.append(legend);
      tables.forEach(table => {
        const button = doc.createElement('button');
        button.type = 'button'; button.dataset.tpZone = zone; button.dataset.tpTable = table;
        button.className = 'tp-seat-button'; button.setAttribute('aria-pressed', 'false');
        safeText(button, `Стол ${table}`); group.append(button);
      });
      wrap.append(group);
    });
    root.replaceChildren(wrap);
    bindFloorplanSelection(root);
  }

  function bindFloorplanSelection(root) {
    const form = root.dataset.form ? one(root.dataset.form) : root.closest('form') || one('.booking-form');
    const zoneInput = form?.querySelector('[name="zone"]');
    const tableInput = form?.querySelector('[name="table"]');
    const formTableStatus = form?.querySelector('.table-selection');
    const messageScope = root.closest('.table-map-wrap') || root.parentElement || doc;
    const message = one('[data-tp-floorplan-selection]', root) || one('[data-tp-floorplan-selection]', messageScope) || one('[data-tp-status]', root);
    const clearTablePreference = () => {
      if (tableInput) tableInput.value = '';
      many('[data-tp-table], [data-tp-seat-button]', root).forEach(item => {
        item.classList.remove('is-selected');
        item.setAttribute('aria-pressed', 'false');
      });
      if (message) safeText(message, 'Параметры визита изменились. Выберите столик заново; доступность не проверяется.');
      if (formTableStatus) safeText(formTableStatus, 'Параметры изменились. Выберите стол заново или оставьте поле пустым.');
    };
    ['date', 'time', 'guests', 'zone'].forEach(name => {
      form?.querySelector(`[name="${name}"]`)?.addEventListener('change', clearTablePreference);
    });
    const applyZone = (zone, announce = true) => {
      if (!zone) return;
      let zoneChanged = false;
      if (zoneInput) {
        if (zoneInput.tagName === 'SELECT') {
          const option = Array.from(zoneInput.options).find(item => item.value === zone || item.textContent.trim() === zone);
          if (option && zoneInput.value !== option.value) { zoneInput.value = option.value; zoneChanged = true; }
        } else if (zoneInput.value !== zone) { zoneInput.value = zone; zoneChanged = true; }
        if (zoneChanged) zoneInput.dispatchEvent(new Event('change', { bubbles: true }));
      }
      if (tableInput) tableInput.value = '';
      many('[data-tp-table], [data-tp-seat-button]', root).forEach(item => {
        item.classList.remove('is-selected');
        item.setAttribute('aria-pressed', 'false');
      });
      many('[data-tp-zone-hit]', root).forEach(item => {
        const active = item.closest('[data-tp-zone]')?.dataset.tpZone === zone;
        item.classList.toggle('is-selected', active);
        item.setAttribute('aria-pressed', String(active));
      });
      many('[data-tp-zone-select]', root).forEach(item => {
        const active = item.dataset.tpZoneSelect === zone;
        item.classList.toggle('is-selected', active);
        item.setAttribute('aria-pressed', String(active));
      });
      many('[data-tp-zone]', root).forEach(item => {
        if (!item.hasAttribute('data-tp-table')) item.classList.toggle('is-selected', item.dataset.tpZone === zone);
      });
      const zoneGroup = many('[data-tp-zone]', root).find(item => !item.hasAttribute('data-tp-table') && item.dataset.tpZone === zone);
      const zonePhoto = one('[data-tp-zone-photo]', root.parentElement || doc);
      if (zoneGroup?.dataset.tpPhoto && zonePhoto instanceof HTMLImageElement) {
        zonePhoto.src = zoneGroup.dataset.tpPhoto;
        zonePhoto.alt = `${zone} — иллюстративное изображение учебной концепции`;
      }
      const seatCaption = one('[data-tp-seat-caption]', root.parentElement || doc);
      if (seatCaption) safeText(seatCaption, `${zone}. Выберите предпочтительный стол; вместимость и доступность уточняются отдельно.`);
      if (announce) {
        const zoneMessage = `${zone}: выберите предпочтительный стол. Доступность не проверяется.`;
        safeText(message, zoneMessage);
        if (formTableStatus) safeText(formTableStatus, zoneMessage);
      }
    };
    const select = target => {
      const table = target.dataset.tpTable;
      const zone = target.dataset.tpZone || target.closest('[data-tp-zone]')?.dataset.tpZone;
      if (!table || !zone) return;
      applyZone(zone, false);
      many('[data-tp-table], [data-tp-seat-button]', root).forEach(item => {
        const active = item.dataset.tpTable === table && item.dataset.tpZone === zone;
        item.classList.toggle('is-selected', active);
        item.setAttribute('aria-pressed', String(active));
      });
      if (tableInput) tableInput.value = table;
      const seatCaption = one('[data-tp-seat-caption]', root.parentElement || doc);
      const capacity = target.dataset.capacity ? ` · до ${target.dataset.capacity} гостей по учебной схеме` : '';
      if (seatCaption) safeText(seatCaption, `${zone} · стол ${table}${capacity}. Предпочтение не подтверждает доступность.`);
      const selectedMessage = `Предпочтение сохранено: ${zone}, стол ${table}. Фактическая доступность подтверждается отдельно.`;
      safeText(message, selectedMessage);
      if (formTableStatus) safeText(formTableStatus, `Выбран предпочтительный стол №${table}. Фактическую доступность ресторан подтвердит отдельно.`);
    };
    root.addEventListener('click', event => {
      const target = event.target.closest('[data-tp-table]') || event.target.closest('[data-tp-seat-button]');
      if (target && root.contains(target)) { event.preventDefault(); select(target); return; }
      const zoneHit = event.target.closest('[data-tp-zone-hit]');
      if (zoneHit && root.contains(zoneHit)) {
        event.preventDefault();
        const zone = zoneHit.closest('[data-tp-zone]')?.dataset.tpZone;
        applyZone(zone);
        return;
      }
      const zoneButton = event.target.closest('[data-tp-zone-select]');
      if (zoneButton && root.contains(zoneButton)) { event.preventDefault(); applyZone(zoneButton.dataset.tpZoneSelect); }
    });
    root.addEventListener('keydown', event => {
      const target = event.target.closest('[data-tp-table]');
      if (target && ['Enter', ' '].includes(event.key)) { event.preventDefault(); select(target); return; }
      const zoneHit = event.target.closest('[data-tp-zone-hit]');
      if (zoneHit && ['Enter', ' '].includes(event.key)) {
        event.preventDefault(); applyZone(zoneHit.closest('[data-tp-zone]')?.dataset.tpZone);
      }
    });
    const initialZone = zoneInput?.value || zoneInput?.selectedOptions?.[0]?.textContent?.trim();
    if (initialZone) applyZone(initialZone, false);
  }

  async function initFloorplan(root) {
    if (started.has(root)) return;
    started.add(root);
    const asset = root.dataset.tpFloorplanSrc || root.dataset.floorplanSrc;
    if (!asset || !global.fetch || !global.DOMParser) { makeFallbackFloorplan(root); return; }
    try {
      const response = await global.fetch(new URL(asset, doc.baseURI), { credentials: 'same-origin' });
      if (!response.ok) throw new Error('SVG floorplan request failed');
      const markup = await response.text();
      const parsed = new DOMParser().parseFromString(markup, 'image/svg+xml');
      const svg = parsed.documentElement;
      if (svg.localName !== 'svg' || parsed.querySelector('parsererror')) throw new Error('Invalid floorplan SVG');
      // The imported file is a first-party static asset. Strip active content as a defense-in-depth measure.
      svg.querySelectorAll('script,foreignObject').forEach(node => node.remove());
      svg.querySelectorAll('*').forEach(node => Array.from(node.attributes).forEach(attribute => {
        if (/^on/i.test(attribute.name) || /javascript:/i.test(attribute.value)) node.removeAttribute(attribute.name);
      }));
      const imported = doc.importNode(svg, true);
      imported.classList.add('tp-seat-plan');
      imported.setAttribute('role', 'group');
      imported.setAttribute('aria-label', 'Интерактивная схема учебных залов. Выбор столика — пожелание, не подтверждение брони.');
      imported.querySelectorAll('[data-tp-zone-hit]').forEach(hit => {
        const zone = hit.closest('[data-tp-zone]')?.dataset.tpZone || 'зона';
        hit.setAttribute('role', 'button');
        hit.setAttribute('tabindex', '0');
        hit.setAttribute('aria-pressed', 'false');
        hit.setAttribute('aria-label', `Выбрать зону «${zone}»`);
      });
      root.replaceChildren(imported);
      imported.querySelectorAll('[data-tp-table]').forEach((seat, index) => {
        seat.setAttribute('role', 'button');
        seat.setAttribute('tabindex', '0');
        seat.setAttribute('aria-pressed', 'false');
        const capacity = seat.dataset.capacity ? `, вместимость по учебной схеме до ${seat.dataset.capacity} гостей` : '';
        seat.setAttribute('aria-label', `Выбрать предпочтительный стол ${seat.dataset.tpTable}, ${seat.dataset.tpZone || 'зона'}${capacity}`);
        if (!seat.id) seat.id = `tp-seat-${index + 1}`;
      });
      bindFloorplanSelection(root);
    } catch (_) {
      makeFallbackFloorplan(root, 'Схема не загрузилась. Используйте доступный список выбора ниже; доступность столов не проверяется.');
    }
  }

  function initGallery(root) {
    if (started.has(root)) return;
    started.add(root);
    const dialog = one('dialog', root) || one('.image-lightbox', root);
    if (!dialog) return;
    const image = one('img', dialog);
    if (!image) return;
    const items = () => many('[data-lightbox]', root).filter(button => !button.hidden && button.offsetParent !== null);
    let active = 0;
    let touchStart = null;
    const show = index => {
      const list = items();
      if (!list.length) return;
      active = (index + list.length) % list.length;
      const trigger = list[active];
      const src = trigger.dataset.lightbox;
      const thumb = one('img', trigger);
      image.src = src;
      image.alt = thumb?.alt || trigger.getAttribute('aria-label') || 'Иллюстративная фотография учебной концепции';
      const caption = one('[data-tp-gallery-caption]', dialog);
      safeText(caption, trigger.dataset.caption || thumb?.alt || `Кадр ${active + 1} из ${list.length}`);
    };
    let previous = one('[data-tp-gallery-prev]', dialog);
    let next = one('[data-tp-gallery-next]', dialog);
    if (!previous) { previous = doc.createElement('button'); previous.type = 'button'; previous.dataset.tpGalleryPrev = ''; previous.className = 'tp-gallery-control tp-gallery-prev'; previous.setAttribute('aria-label', 'Предыдущее фото'); safeText(previous, '←'); dialog.append(previous); }
    if (!next) { next = doc.createElement('button'); next.type = 'button'; next.dataset.tpGalleryNext = ''; next.className = 'tp-gallery-control tp-gallery-next'; next.setAttribute('aria-label', 'Следующее фото'); safeText(next, '→'); dialog.append(next); }
    root.addEventListener('click', event => {
      const trigger = event.target.closest('[data-lightbox]');
      if (!trigger || !root.contains(trigger)) return;
      const list = items();
      const index = list.indexOf(trigger);
      if (index >= 0) show(index);
    });
    previous.addEventListener('click', () => show(active - 1));
    next.addEventListener('click', () => show(active + 1));
    dialog.addEventListener('keydown', event => {
      if (event.key === 'ArrowLeft') { event.preventDefault(); show(active - 1); }
      if (event.key === 'ArrowRight') { event.preventDefault(); show(active + 1); }
    });
    dialog.addEventListener('touchstart', event => { touchStart = event.changedTouches[0]?.clientX ?? null; }, { passive: true });
    dialog.addEventListener('touchend', event => {
      const end = event.changedTouches[0]?.clientX;
      if (touchStart == null || end == null || Math.abs(end - touchStart) < 45) return;
      show(active + (end < touchStart ? 1 : -1)); touchStart = null;
    }, { passive: true });
  }

  function buildQuickView() {
    let dialog = one('#tp-quick-view-dialog');
    if (dialog) return dialog;
    dialog = doc.createElement('dialog');
    dialog.id = 'tp-quick-view-dialog'; dialog.className = 'tp-quick-view-dialog';
    dialog.setAttribute('aria-labelledby', 'tp-quick-view-title');
    const close = doc.createElement('button'); close.type = 'button'; close.className = 'tp-quick-view-close'; close.setAttribute('aria-label', 'Закрыть быстрый просмотр'); safeText(close, '×');
    const image = doc.createElement('img'); image.className = 'tp-quick-view-image'; image.alt = ''; image.loading = 'lazy';
    const copy = doc.createElement('div'); copy.className = 'tp-quick-view-copy';
    const category = doc.createElement('small'); category.dataset.tpQuickViewCategory = '';
    const title = doc.createElement('h2'); title.id = 'tp-quick-view-title';
    const description = doc.createElement('p'); description.dataset.tpQuickViewDescription = '';
    const price = doc.createElement('strong'); price.dataset.tpQuickViewPrice = '';
    const actions = doc.createElement('div'); actions.className = 'tp-quick-view-actions';
    const add = doc.createElement('button'); add.type = 'button'; add.className = 'button add-to-cart'; add.dataset.tpQuickViewAdd = ''; add.textContent = 'Добавить в корзину +';
    const detail = doc.createElement('a'); detail.className = 'button secondary'; detail.dataset.tpQuickViewDetail = ''; detail.textContent = 'Подробнее ↗';
    actions.append(add, detail); copy.append(category, title, description, price, actions); dialog.append(close, image, copy); doc.body.append(dialog);
    close.addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
    return dialog;
  }

  function initQuickView(root) {
    if (quickViewRoots.has(root)) return;
    quickViewRoots.add(root);
    root.addEventListener('click', event => {
      const trigger = event.target.closest('[data-tp-quick-view]');
      if (!trigger) return;
      const dish = dishIndex().get(Number(trigger.dataset.dishId));
      if (!dish) return;
      event.preventDefault();
      const dialog = buildQuickView();
      const image = one('.tp-quick-view-image', dialog);
      image.src = dish.image_url || ''; image.alt = dish.name || '';
      safeText(one('[data-tp-quick-view-category]', dialog), `${dish.category_name || dish.category || 'Tokyo Premium'} · ${dish.weight || ''}`);
      safeText(one('#tp-quick-view-title', dialog), dish.name);
      safeText(one('[data-tp-quick-view-description]', dialog), dish.description || 'Позиция демонстрационного меню.');
      safeText(one('[data-tp-quick-view-price]', dialog), `${money(dish.price)} · учебная цена`);
      const add = one('[data-tp-quick-view-add]', dialog); add.dataset.id = String(dish.id); add.setAttribute('aria-label', `Добавить ${dish.name} в корзину`);
      const detail = one('[data-tp-quick-view-detail]', dialog);
      const cardLink = trigger.closest('article')?.querySelector('a[href]');
      if (cardLink) detail.href = cardLink.href;
      else detail.hidden = true;
      if (dialog.showModal) dialog.showModal(); else dialog.setAttribute('open', '');
    });
  }

  function initMotion(root) {
    if (motionRoots.has(root)) return;
    motionRoots.add(root);
    if (reduceMotion()) return;
    const targets = matches(root, '[data-tp-reveal]');
    if (!targets.length) return;
    const gsap = global.gsap;
    if (gsap && global.ScrollTrigger && typeof gsap.fromTo === 'function') {
      if (global.ScrollTrigger && gsap.registerPlugin) gsap.registerPlugin(global.ScrollTrigger);
      if (typeof gsap.matchMedia === 'function') {
        const media = gsap.matchMedia();
        media.add('(prefers-reduced-motion: no-preference)', () => {
          targets.forEach((target, index) => gsap.fromTo(target, { autoAlpha: 0, y: 18 }, {
            autoAlpha: 1, y: 0, duration: 0.72, delay: Math.min(index % 4, 3) * 0.07, ease: 'power2.out',
            scrollTrigger: global.ScrollTrigger ? { trigger: target, start: 'top 88%', once: true } : undefined
          }));
        });
        return;
      }
    }
    if (!('IntersectionObserver' in global)) return;
    doc.documentElement.classList.add('tp-motion-ready');
    const observer = new IntersectionObserver(entries => entries.forEach(entry => {
      if (!entry.isIntersecting) return;
      entry.target.classList.add('tp-is-visible'); observer.unobserve(entry.target);
    }), { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
    targets.forEach(target => observer.observe(target));
  }

  function initTransitions(root) {
    if (transitionRoots.has(root)) return;
    transitionRoots.add(root);
    if (reduceMotion()) return;
    root.addEventListener('click', event => {
      const link = event.target.closest('a[data-tp-transition]');
      if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || link.target || link.hasAttribute('download')) return;
      let url;
      try { url = new URL(link.href, doc.baseURI); } catch (_) { return; }
      if (url.origin !== global.location.origin || url.hash && url.pathname === global.location.pathname) return;
      event.preventDefault();
      const duration = Math.max(0, Math.min(350, Number(link.dataset.transitionDuration) || 140));
      doc.documentElement.classList.add('tp-page-leaving');
      global.setTimeout(() => { global.location.assign(url.href); }, duration);
    });
  }

  function initMagnetic(root) {
    if (reduceMotion() || !global.matchMedia?.('(hover: hover) and (pointer: fine)').matches) return;
    matches(root, '[data-tp-magnetic]').forEach(button => {
      if (button.dataset.tpMagneticReady) return;
      button.dataset.tpMagneticReady = 'true';
      const strength = Math.max(0, Math.min(.18, Number(button.dataset.magneticStrength) || .08));
      let frame = 0;
      button.addEventListener('pointermove', event => {
        if (frame) global.cancelAnimationFrame(frame);
        frame = global.requestAnimationFrame(() => {
          const rect = button.getBoundingClientRect();
          const x = (event.clientX - rect.left - rect.width / 2) * strength;
          const y = (event.clientY - rect.top - rect.height / 2) * strength;
          button.style.transform = `translate3d(${x.toFixed(1)}px, ${y.toFixed(1)}px, 0)`;
        });
      });
      button.addEventListener('pointerleave', () => {
        if (frame) global.cancelAnimationFrame(frame);
        button.style.transform = '';
      });
    });
  }

  function init(root) {
    const scope = root || doc;
    matches(scope, '[data-tp-omakase]').forEach(initOmakase);
    matches(scope, '[data-tp-set-builder]').forEach(initSetBuilder);
    matches(scope, '[data-tp-perfect-evening]').forEach(initPerfectEvening);
    matches(scope, '[data-tp-floorplan]').forEach(node => { void initFloorplan(node); });
    matches(scope, '[data-tp-gallery]').forEach(initGallery);
    initQuickView(doc);
    initMotion(scope);
    initTransitions(doc);
    initMagnetic(scope);
  }

  global.TokyoExperienceUI = Object.freeze({ init, money });
  if (doc.readyState === 'loading') doc.addEventListener('DOMContentLoaded', () => init(doc), { once: true });
  else init(doc);
})(window);
