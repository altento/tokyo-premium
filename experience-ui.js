(function (global) {
  'use strict';

  const doc = global.document;
  const staticDemo = Boolean(global.TOKYO_STATIC_DEMO);
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
    const variant = root.dataset.setType || 'signature';
    const draftKey = `tokyo-custom-set-draft-v1:${variant}`;
    const allowedDishIds = new Set(many('[data-tp-set-dish]', root).map(card => Number(card.dataset.dishId)).filter(Number.isSafeInteger));
    const minDistinct = Math.max(1, Number(root.dataset.minDistinct) || 3);
    const maxDistinct = Math.max(minDistinct, Number(root.dataset.maxDistinct) || 5);
    const maxQuantity = Math.max(maxDistinct, Number(root.dataset.maxQuantity) || 8);
    const status = one('[data-tp-status]', root);
    const composition = one('[data-tp-set-composition]', root.parentElement || doc);

    const persistDraft = () => {
      try {
        if (selected.size) global.localStorage.setItem(draftKey, JSON.stringify(Array.from(selected, ([id, qty]) => ({ id, qty }))));
        else global.localStorage.removeItem(draftKey);
      } catch (_) { /* A blocked local store should not block set building. */ }
    };
    try {
      const saved = global.localStorage.getItem(draftKey);
      if (saved) {
        const rows = JSON.parse(saved);
        if (!Array.isArray(rows) || rows.length > maxDistinct) throw new Error('Invalid saved set');
        let total = 0;
        for (const row of rows) {
          const id = Number(row?.id);
          const qty = Number(row?.qty);
          if (!Number.isSafeInteger(id) || !allowedDishIds.has(id) || !Number.isSafeInteger(qty) || qty < 1 || qty > 4 || selected.has(id)) throw new Error('Invalid saved set item');
          total += qty;
          if (total > maxQuantity) throw new Error('Saved set exceeds the variant limit');
          selected.set(id, qty);
        }
      }
    } catch (_) {
      selected.clear();
      try { global.localStorage.removeItem(draftKey); } catch (_) { /* Ignore unavailable storage. */ }
    }

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
      persistDraft();
      if (status) status.textContent = `${dish.name}: ${next ? `выбрано ${next}` : 'удалено из сета'}. Черновик сохранён в этом браузере; ${staticDemo ? 'стоимость — локальная оценка без серверной проверки.' : 'цена ориентировочная и перепроверяется сервером.'}`;
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
          selected.clear();
          persistDraft();
          render();
          setStatus(root, staticDemo
            ? 'Сет добавлен в локальную корзину браузера. Стоимость остаётся демонстрационной и не проверяется сервером.'
            : 'Сет добавлен в общую корзину. Итоговая цена будет подтверждена сервером.');
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
    if (selected.size && status) status.textContent = `Черновик восстановлен из этого браузера · ${selected.size} блюд. ${staticDemo ? 'Сумма ориентировочная; серверной проверки в этой демоверсии нет.' : 'Сумма ориентировочная; сервер проверит состав и цену.'}`;
  }

  const signals = {
    fish: ['рыб', 'лосос', 'угор', 'тунец', 'сашими', 'нигири', 'кревет', 'гребеш', 'икр'],
    meat: ['мяс', 'говядин', 'утк', 'куриц', 'цыпл', 'свинин', 'бекон'],
    plant: ['вегет', 'овощ', 'тофу', 'гриб', 'авокадо', 'салат'],
    spicy: ['остр', 'чили', 'васаби', 'кимчи', 'перец'],
    light: ['салат', 'сашими', 'тартар', 'чай', 'лёгк', 'легк'],
    sets: ['сет', 'сэт', 'омак', 'дегустац']
  };
  const signalText = dish => `${dish.name || ''} ${dish.description || ''} ${dish.category_name || ''} ${dish.category || ''}`.toLocaleLowerCase('ru-RU');
  const productKind = dish => {
    const text = signalText(dish);
    if (/напит|drink|beverage|чай|матча|лимонад|кофе|саке/.test(text)) return 'drink';
    if (/десерт|dessert|моти|тирамису|морожен/.test(text)) return 'dessert';
    if (/(?:^|\W)(?:сет|сэт)|омак|set menu/.test(text)) return 'set';
    return 'dish';
  };

  function scoreDish(dish, state) {
    const text = signalText(dish);
    const kind = productKind(dish);
    let score = 0;
    const preference = state.preference || 'fish';
    if (preference === 'sets') score += kind === 'set' ? 12 : -6;
    else if ((signals[preference] || []).some(word => text.includes(word))) score += 6;
    if (preference === 'plant' && /мяс|рыб|лосос|тунец|угор|кревет|сашими|говядин|утк|куриц/.test(text)) score -= 10;
    if (state.occasion === 'romantic' && /тартар|сашими|десерт|чай|матча|моти|тирамису/.test(text)) score += 3;
    if (state.occasion === 'celebration' && /сашими|сэт|сет|десерт|икр|премиум|ассорти/.test(text)) score += 3;
    if (state.occasion === 'discovery' && /омак|сашими|сэт|сет|авторск/.test(text)) score += 3;
    if (state.occasion === 'friends' && /сет|сэт|ролл|ассорти/.test(text)) score += 2;
    if (state.atmosphere === 'omakase' && kind === 'set') score += 10;
    const price = Math.max(0, Number(dish.price) || 0) / (kind === 'set' ? 2 : 1);
    const perGuest = Math.max(0, Number(state.budget || 0));
    if (perGuest > 0) score += price <= perGuest * 0.72 ? 2 : price <= perGuest ? 0 : -4;
    return score;
  }

  function initPerfectEvening(root) {
    if (started.has(root)) return;
    started.add(root);
    const steps = many('[data-tp-evening-step]', root);
    const options = many('[data-tp-evening-option]', root);
    const inputs = many('[data-tp-evening-input]', root);
    const state = Object.create(null);
    const booking = one('[data-tp-evening-booking]', root);
    const initialBookingHref = booking instanceof HTMLAnchorElement ? booking.getAttribute('href') || booking.href : '';
    let current = 0;
    let recommendedPlan = null;
    const refreshNextButtons = () => {
      many('[data-tp-evening-next]', root).forEach(next => {
        const step = next.closest('[data-tp-evening-step]');
        const option = step && one('[data-tp-evening-option]', step);
        const input = step && one('[data-tp-evening-input]', step);
        next.disabled = option ? !state[option.dataset.field] : input ? !input.checkValidity() : false;
      });
    };
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
      refreshNextButtons();
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
      if (step) refreshNextButtons();
    }));

    inputs.forEach(input => {
      const update = () => {
        state[input.dataset.tpEveningInput] = input.value;
        refreshNextButtons();
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
      const groupBudget = budget * guestCount;
      const dishes = dishRows().filter(dish => dish && Number(dish.active ?? 1) !== 0 && Number(dish.price) >= 0);
      const byId = new Map(dishes.map(dish => [Number(dish.id), dish]));
      const pairings = Array.isArray(global.TP_DISH_PAIRINGS) ? global.TP_DISH_PAIRINGS : [];
      const setVariants = Array.isArray(global.TP_SET_VARIANTS) ? global.TP_SET_VARIANTS.filter(row => row && row.slug && row.name) : [];
      const mains = dishes.filter(dish => !['drink', 'dessert'].includes(productKind(dish)))
        .sort((a, b) => scoreDish(b, state) - scoreDish(a, state) || Number(a.price) - Number(b.price));
      const pickPaired = (main, pairingType, fallbackKind) => {
        const linked = pairings
          .filter(pair => Number(pair.dish_id) === Number(main.id) && pair.pairing_type === pairingType)
          .sort((a, b) => Number(a.sort_order || 0) - Number(b.sort_order || 0))
          .map(pair => byId.get(Number(pair.paired_dish_id)))
          .filter(dish => dish && Number(dish.id) !== Number(main.id));
        return linked[0] || dishes.filter(dish => productKind(dish) === fallbackKind && Number(dish.id) !== Number(main.id))
          .sort((a, b) => Number(a.price) - Number(b.price))[0] || null;
      };
      const makePlan = (main, includeExtras) => {
        const isSet = productKind(main) === 'set';
        const lines = [{ dish: main, qty: isSet ? Math.max(1, Math.ceil(guestCount / 2)) : guestCount, role: isSet ? 'Сет для компании' : 'Основное блюдо' }];
        const drink = pickPaired(main, 'drink', 'drink');
        if (drink) lines.push({ dish: drink, qty: guestCount, role: 'Напиток к блюду' });
        if (includeExtras) {
          const side = pickPaired(main, 'dish', 'dish');
          if (side && Number(side.id) !== Number(drink?.id)) lines.push({ dish: side, qty: Math.max(1, Math.ceil(guestCount / 2)), role: 'Дополнение к столу' });
          const dessert = pickPaired(main, 'dessert', 'dessert');
          if (dessert && !lines.some(line => Number(line.dish.id) === Number(dessert.id))) lines.push({ dish: dessert, qty: Math.max(1, Math.ceil(guestCount / 2)), role: 'Десерт для компании' });
        }
        const total = lines.reduce((sum, line) => sum + Math.max(0, Number(line.dish.price) || 0) * line.qty, 0);
        return { main, lines, total, full: includeExtras };
      };
      const planCandidates = mains.map(main => ({ core: makePlan(main, false), full: makePlan(main, true), score: scoreDish(main, state) }));
      const wantsSet = state.preference === 'sets' || state.atmosphere === 'omakase';
      const variantSlug = state.atmosphere === 'omakase' ? 'omakase' : state.occasion === 'celebration' ? 'seasonal' : 'signature';
      const setVariant = wantsSet ? setVariants.find(row => String(row.slug) === variantSlug) : null;
      const makeSetPlan = (main, variant) => {
        const unique = new Map();
        makePlan(main, true).lines.forEach(line => {
          if (unique.size < Number(variant.maximum_distinct_items || 8)) unique.set(Number(line.dish.id), { dish: line.dish, qty: 1, role: line.role });
        });
        const fillers = dishes.filter(dish => productKind(dish) !== 'set' && !unique.has(Number(dish.id)))
          .sort((a, b) => scoreDish(b, state) - scoreDish(a, state) || Number(a.price) - Number(b.price));
        for (const dish of fillers) {
          if (unique.size >= Number(variant.maximum_distinct_items || 8)) break;
          unique.set(Number(dish.id), { dish, qty: 1, role: productKind(dish) === 'drink' ? 'Напиток к сету' : productKind(dish) === 'dessert' ? 'Десерт к сету' : 'Блюдо сета' });
        }
        const components = Array.from(unique.values()).slice(0, Number(variant.maximum_distinct_items || 8));
        if (components.length < Number(variant.minimum_distinct_items || 3) || components.length > 8 || components.length > Number(variant.maximum_total_quantity || 12)) return null;
        const setQty = Math.min(3, Math.max(1, Math.ceil(guestCount / 4)));
        const perSet = components.reduce((sum, line) => sum + Math.max(0, Number(line.dish.price) || 0), 0);
        return {
          main,
          lines: components,
          total: perSet * setQty,
          full: true,
          setVariant: variant,
          customSetPayload: { setType: String(variant.slug), name: String(variant.name), qty: setQty, items: components.map(line => ({ id: Number(line.dish.id), qty: 1 })) }
        };
      };
      const setCandidates = setVariant ? mains.map(main => ({ plan: makeSetPlan(main, setVariant), score: scoreDish(main, state) })).filter(item => item.plan) : [];
      let chosen = null;
      if (wantsSet && setCandidates.length) {
        const affordable = budget > 0 ? setCandidates.find(item => item.plan.total <= groupBudget) : null;
        chosen = (affordable || setCandidates.slice().sort((a, b) => a.plan.total - b.plan.total || b.score - a.score)[0])?.plan || null;
      } else if (budget > 0) {
        chosen = planCandidates.find(item => item.full.total <= groupBudget)?.full
          || planCandidates.find(item => item.core.total <= groupBudget)?.core
          || planCandidates.slice().sort((a, b) => a.core.total - b.core.total || b.score - a.score)[0]?.core;
      } else {
        chosen = planCandidates[0]?.full || null;
      }
      recommendedPlan = chosen;
      const headline = doc.createElement('p');
      headline.className = 'tp-evening-summary';
      const zoneByAtmosphere = { sushi: 'Суши-бар', main: 'Основной зал', private: 'Приватная комната', omakase: 'Суши-бар' };
      const zone = zoneByAtmosphere[state.atmosphere] || zoneByAtmosphere.main;
      safeText(headline, `Подбор для ${guestCount} гостей · предпочтительная зона: ${zone}.`);
      result.append(headline);
      if (budget > 0) {
        const budgetNode = doc.createElement('p');
        budgetNode.className = 'tp-evening-budget-summary';
        safeText(budgetNode, `Ориентир: ${money(budget)} на гостя · ${money(groupBudget)} на компанию.`);
        result.append(budgetNode);
      }
      if (!chosen) {
        const empty = doc.createElement('p');
        empty.className = 'tp-empty-state';
        safeText(empty, 'В каталоге пока нет блюд для подбора.');
        result.append(empty);
      }
      const list = doc.createElement('div');
      list.className = 'tp-evening-dishes';
      if (chosen?.customSetPayload) {
        const article = doc.createElement('article');
        article.className = 'tp-recommendation-card tp-evening-plan-card tp-evening-set-card';
        article.dataset.role = 'Сет для компании';
        article.dataset.variant = chosen.customSetPayload.setType;
        article.dataset.qty = String(chosen.customSetPayload.qty);
        const copy = doc.createElement('div');
        const role = doc.createElement('span'); role.className = 'tp-evening-plan-role'; safeText(role, `Сет для ${guestCount} гостей`);
        const title = doc.createElement('h4'); safeText(title, chosen.setVariant.name);
        const description = doc.createElement('p'); safeText(description, `${chosen.lines.length} позиций из доступного меню · ${chosen.customSetPayload.qty} ${chosen.customSetPayload.qty === 1 ? 'сет' : 'сета'}.`);
        const components = doc.createElement('ul'); components.className = 'tp-evening-set-components';
        chosen.lines.forEach(line => {
          const item = doc.createElement('li');
          const name = doc.createElement('span'); safeText(name, line.dish.name);
          const price = doc.createElement('small'); safeText(price, money(line.dish.price));
          item.append(name, price); components.append(item);
        });
        copy.append(role, title, description, components);
        article.append(copy); list.append(article);
      } else (chosen?.lines || []).forEach(line => {
        const dish = line.dish;
        const price = Math.max(0, Number(dish.price) || 0) * line.qty;
        const article = doc.createElement('article');
        article.className = 'tp-recommendation-card tp-evening-plan-card';
        article.dataset.tpEveningPlanLine = '';
        article.dataset.id = String(dish.id);
        article.dataset.qty = String(line.qty);
        article.dataset.role = line.role;
        if (dish.image_url) {
          const image = doc.createElement('img');
          image.src = dish.image_url;
          image.alt = String(dish.name || 'Блюдо');
          image.loading = 'lazy';
          image.decoding = 'async';
          article.append(image);
        }
        const copy = doc.createElement('div');
        const role = doc.createElement('span'); role.className = 'tp-evening-plan-role'; safeText(role, line.role);
        const title = doc.createElement('h4'); safeText(title, dish.name);
        const description = doc.createElement('p'); safeText(description, dish.description || 'Позиция учебного каталога.');
        const quantity = doc.createElement('small'); safeText(quantity, `${line.qty} ${line.qty === 1 ? 'порция' : 'порции'} · ${money(price)} на компанию`);
        const add = doc.createElement('button');
        add.type = 'button'; add.className = 'button secondary'; add.dataset.tpEveningAddLine = ''; add.dataset.id = String(dish.id); add.dataset.qty = String(line.qty);
        add.setAttribute('aria-label', `Добавить ${line.qty} × ${dish.name} в корзину`); safeText(add, 'Добавить позицию +');
        copy.append(role, title, description, quantity, add);
        article.append(copy); list.append(article);
      });
      result.append(list);
      const totalNode = doc.createElement('p');
      totalNode.className = 'tp-evening-estimate';
      const total = chosen?.total || 0;
      safeText(totalNode, `Ориентир на подбор для ${guestCount} гостей: ${money(total)}. ${staticDemo ? 'Демо-стоимость рассчитана в браузере и не проверяется сервером.' : 'Стоимость учебная; сервер проверяет цены при заказе.'}`);
      result.append(totalNode);
      if (budget && total > groupBudget) {
        const budgetNote = doc.createElement('p');
        budgetNote.className = 'tp-budget-note';
        safeText(budgetNote, 'Даже наиболее доступная подходящая комбинация выходит за ваш ориентир. Проверьте состав и итог перед оформлением.');
        result.append(budgetNote);
      } else if (budget && chosen && !chosen.full) {
        const budgetNote = doc.createElement('p');
        budgetNote.className = 'tp-budget-note';
        safeText(budgetNote, 'Чтобы уложиться в бюджет, в подбор включены основное блюдо и напиток без дополнительных позиций.');
        result.append(budgetNote);
      }
      const note = doc.createElement('small');
      safeText(note, 'Сочетания напитков и дополнений взяты из каталога ресторана. Подбор сформирован по правилам, а не нейросетью.');
      result.append(note);
      if (chosen) {
        const addPlan = doc.createElement('button');
        addPlan.type = 'button'; addPlan.className = 'button tp-evening-add-plan'; addPlan.dataset.tpEveningAddPlan = '';
        safeText(addPlan, 'Добавить весь подбор в корзину ↗');
        result.append(addPlan);
      }
      result.hidden = false;
      steps.forEach(step => { step.hidden = true; });
      if (booking instanceof HTMLAnchorElement) {
        const url = new URL(initialBookingHref || booking.href, doc.baseURI);
        url.searchParams.set('zone', zone);
        url.searchParams.set('guests', String(guestCount));
        if (chosen?.setVariant) {
          const format = chosen.setVariant.slug === 'omakase' ? 'Премиальный' : chosen.setVariant.slug === 'seasonal' ? 'Сезонный' : 'Начало';
          url.searchParams.set('format', format);
        }
        else url.searchParams.delete('format');
        if (chosen) url.searchParams.set('experience', chosen.lines.map(line => `${line.qty} × ${line.dish.name}`).join(', ').slice(0, 180));
        else url.searchParams.delete('experience');
        booking.href = url.pathname + url.search + url.hash;
      }
      setStatus(root, `Подбор готов. Ориентировочная сумма ${money(total)} для ${guestCount} гостей.`);
    };

    root.addEventListener('click', event => {
      const addPlan = event.target.closest('[data-tp-evening-add-plan]');
      if (addPlan && root.contains(addPlan)) {
        if (!recommendedPlan || !global.TokyoCart || typeof global.TokyoCart.addMany !== 'function') {
          setStatus(root, 'Не удалось добавить подбор. Обновите страницу и попробуйте ещё раз.');
          return;
        }
        const accepted = recommendedPlan.customSetPayload
          ? global.TokyoCart.addCustomSet(recommendedPlan.customSetPayload)
          : global.TokyoCart.addMany(recommendedPlan.lines.map(line => ({ id: Number(line.dish.id), qty: line.qty })));
        if (accepted) { addPlan.disabled = true; safeText(addPlan, 'Подбор добавлен в корзину ✓'); setStatus(root, 'Все позиции подбора добавлены в корзину.'); }
        else setStatus(root, 'Подбор не добавлен целиком: проверьте лимит корзины или количество блюд. Состав корзины не менялся.');
        return;
      }
      const addLine = event.target.closest('[data-tp-evening-add-line]');
      if (addLine && root.contains(addLine)) {
        const accepted = global.TokyoCart && global.TokyoCart.add(Number(addLine.dataset.id), Number(addLine.dataset.qty));
        setStatus(root, accepted ? 'Позиция добавлена в корзину.' : 'Не удалось добавить позицию в корзину.');
        return;
      }
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
      inputs.forEach(input => {
        input.value = input.defaultValue;
        state[input.dataset.tpEveningInput] = input.value;
      });
      const result = one('[data-tp-evening-result]', root);
      if (result) { result.hidden = true; result.replaceChildren(); }
      recommendedPlan = null;
      if (booking instanceof HTMLAnchorElement) booking.href = initialBookingHref;
      renderStep(); setStatus(root, 'Подбор сброшен.');
    });
    renderStep();
  }

  function makeFallbackFloorplan(root, message) {
    const zones = [
      ['Основной зал', ['1', '2', '3', '4', '5', '6']],
      ['Суши-бар', ['7', '8', '9']],
      ['Приватная комната', ['10', '11']],
      ['Летняя терраса', ['12', '13']]
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
    ['date', 'time', 'guests'].forEach(name => {
      form?.querySelector(`[name="${name}"]`)?.addEventListener('change', clearTablePreference);
    });
    zoneInput?.addEventListener('change', () => {
      clearTablePreference();
      applyZone(zoneInput.value, false);
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
      many('[data-tp-floorplan-zone-options]', root).forEach(item => {
        item.hidden = item.dataset.tpFloorplanZoneOptions !== zone;
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
    const requestedZone = new URL(doc.location.href).searchParams.get('zone');
    const hasRequestedZone = requestedZone && many('[data-tp-zone]', root).some(item => !item.hasAttribute('data-tp-table') && item.dataset.tpZone === requestedZone);
    const initialZone = hasRequestedZone ? requestedZone : (zoneInput?.value || zoneInput?.selectedOptions?.[0]?.textContent?.trim());
    if (initialZone) applyZone(initialZone, false);
  }

  function appendMobileFloorplanPicker(root, svg) {
    const tables = many('[data-tp-table]', svg);
    const zones = new Map();
    tables.forEach(table => {
      const zone = table.dataset.tpZone;
      if (!zone) return;
      if (!zones.has(zone)) zones.set(zone, []);
      zones.get(zone).push(table);
    });
    if (!zones.size) return;

    const picker = doc.createElement('section');
    picker.className = 'tp-floorplan-mobile-picker';
    picker.setAttribute('aria-label', 'Выбор зоны и предпочтительного столика');
    const hint = doc.createElement('p');
    hint.className = 'tp-floorplan-mobile-hint';
    safeText(hint, 'На телефоне выберите зону, затем крупную кнопку столика. Это пожелание, не проверка наличия.');
    const zoneNav = doc.createElement('div');
    zoneNav.className = 'tp-floorplan-mobile-zones';
    zoneNav.setAttribute('role', 'group');
    zoneNav.setAttribute('aria-label', 'Зона ресторана');
    const panels = [];

    zones.forEach((zoneTables, zone) => {
      const zoneButton = doc.createElement('button');
      zoneButton.type = 'button';
      zoneButton.className = 'tp-floorplan-mobile-zone';
      zoneButton.dataset.tpZoneSelect = zone;
      zoneButton.setAttribute('aria-pressed', 'false');
      safeText(zoneButton, zone);
      zoneNav.append(zoneButton);

      const panel = doc.createElement('div');
      panel.className = 'tp-floorplan-mobile-zone-options';
      panel.dataset.tpFloorplanZoneOptions = zone;
      panel.hidden = true;
      panel.setAttribute('role', 'group');
      panel.setAttribute('aria-label', `Предпочтительный столик · ${zone}`);
      const title = doc.createElement('h3');
      title.className = 'tp-floorplan-mobile-zone-title';
      safeText(title, `${zone} · выберите номер`);
      const buttons = doc.createElement('div');
      buttons.className = 'tp-floorplan-mobile-table-grid';
      zoneTables.forEach(table => {
        const number = table.dataset.tpTable;
        const capacity = table.dataset.capacity || '';
        const button = doc.createElement('button');
        button.type = 'button';
        button.className = 'tp-floorplan-mobile-table';
        button.dataset.tpSeatButton = '';
        button.dataset.tpTable = number;
        button.dataset.tpZone = zone;
        if (capacity) button.dataset.capacity = capacity;
        button.setAttribute('aria-pressed', 'false');
        button.setAttribute('aria-label', `Выбрать предпочтительный стол ${number}, ${zone}${capacity ? `, до ${capacity} гостей по учебной схеме` : ''}`);
        safeText(button, `Стол ${number}`);
        buttons.append(button);
      });
      panel.append(title, buttons);
      panels.push(panel);
    });

    picker.append(hint, zoneNav, ...panels);
    root.append(picker);
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
      appendMobileFloorplanPicker(root, imported);
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
      const tileCaption = trigger.matches('.gallery-tile') ? trigger.querySelector(':scope > span')?.textContent?.trim() : '';
      const zoneCaption = trigger.closest('.zone-card')?.querySelector('h3')?.textContent?.trim();
      safeText(caption, trigger.dataset.caption?.trim() || tileCaption || zoneCaption || trigger.getAttribute('aria-label')?.replace(/^Увеличить(?: фото)?:\s*/i, '').trim() || thumb?.alt || `Кадр ${active + 1} из ${list.length}`);
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

  function initBookingPrefill() {
    const form = one('#full-booking-form');
    if (!form) return;
    let params;
    try { params = new URLSearchParams(global.location.search); } catch (_) { return; }
    const zone = params.get('zone') || '';
    const zoneField = form.elements.namedItem('zone');
    if (zoneField && Array.from(zoneField.options || []).some(option => option.value === zone)) zoneField.value = zone;
    const format = params.get('format') || '';
    const formatField = form.elements.namedItem('format');
    if (formatField && Array.from(formatField.options || []).some(option => option.value === format)) formatField.value = format;
    const guests = params.get('guests') || '';
    const guestsField = form.elements.namedItem('guests');
    if (/^(?:[1-9]|1[0-2])$/.test(guests) && guestsField && Array.from(guestsField.options || []).some(option => option.value === guests)) guestsField.value = guests;
    const experience = (params.get('experience') || '').replace(/[\u0000-\u001f\u007f]/g, ' ').trim().slice(0, 180);
    const noteField = form.elements.namedItem('message');
    if (experience && noteField && !noteField.value.trim()) noteField.value = `Подбор меню: ${experience}`;
  }

  function init(root) {
    const scope = root || doc;
    initBookingPrefill();
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
