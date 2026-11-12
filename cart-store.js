(function (global) {
  'use strict';

  const CART_KEY = 'tokyo-cart-v1';
  const FAVORITES_KEY = 'tokyo-favorites-v1';
  const CHECKOUT_LOCK_KEY = 'tokyo-order-submit-lock';
  const CHECKOUT_REQUEST_KEY = 'tokyo-order-request-key';
  const LEGACY_KEYS = ['tokyo-cart', 'cart'];
  const MAX_LINES = 30;
  const MAX_QTY = 20;
  const MAX_CUSTOM_SETS = 3;
  let memoryCart = [];
  let memoryCustomSets = [];
  let memoryFavorites = [];
  let memoryLockUntil = 0;
  let ownLock = '';
  let webLockRelease = null;
  let memoryRequest = null;

  function storage() {
    try { return global.localStorage; } catch (_) { return null; }
  }

  function catalog() {
    const rows = Array.isArray(global.DISHES) ? global.DISHES : [];
    return new Map(rows.map(row => [positiveId(row && row.id), row]).filter(pair => pair[0]));
  }

  function positiveId(value) {
    const id = typeof value === 'number' ? value : (typeof value === 'string' && /^\d+$/.test(value) ? Number(value) : NaN);
    return Number.isSafeInteger(id) && id > 0 ? id : 0;
  }

  function validQty(value) {
    return Number.isSafeInteger(value) && value > 0;
  }

  function normalizeCart(value) {
    const known = catalog();
    const input = Array.isArray(value) ? value : [];
    const merged = new Map();
    for (const row of input) {
      if (!row || typeof row !== 'object') continue;
      const id = positiveId(row.id);
      const qty = row.qty;
      if (!id || !validQty(qty) || (known.size && !known.has(id))) continue;
      merged.set(id, Math.min(MAX_QTY, (merged.get(id) || 0) + Math.min(MAX_QTY, qty)));
      if (merged.size >= MAX_LINES) break;
    }
    return Array.from(merged, ([id, qty]) => ({ id, qty }));
  }

  function normalizeCustomSets(value) {
    if (!Array.isArray(value)) return [];
    const known = catalog();
    const sets = new Map();
    let setSlots = MAX_CUSTOM_SETS;
    for (const row of value) {
      if (setSlots <= 0) break;
      if (!row || typeof row !== 'object') continue;
      const setType = String(row.setType ?? row.set_type ?? row.variant ?? 'signature').trim().slice(0, 32) || 'signature';
      const items = normalizeCart(row.items);
      if (items.length < 3 || items.length > 8 || items.some(item => item.qty > 4) || (known.size && items.some(item => !known.has(item.id)))) continue;
      const hasQty = Object.prototype.hasOwnProperty.call(row, 'qty');
      if (hasQty && (!Number.isSafeInteger(row.qty) || row.qty < 1)) continue;
      const requestedQty = hasQty ? row.qty : 1;
      const signature = items.slice().sort((a, b) => a.id - b.id).map(item => `${item.id}:${item.qty}`).join(',');
      const key = `set:${setType}:${signature}`;
      const current = sets.get(key);
      const qty = Math.min(requestedQty, setSlots, MAX_CUSTOM_SETS - (current?.qty || 0));
      if (qty < 1) continue;
      sets.set(key, {
        key,
        setType,
        name: String(row.name || 'Авторский сет').trim().slice(0, 80) || 'Авторский сет',
        qty: (current?.qty || 0) + qty,
        items
      });
      setSlots -= qty;
    }
    return Array.from(sets.values());
  }

  function normalizeState(value) {
    if (Array.isArray(value)) return { items: normalizeCart(value), customSets: [] };
    if (!value || typeof value !== 'object') return { items: [], customSets: [] };
    return {
      items: normalizeCart(value.items),
      customSets: normalizeCustomSets(value.customSets ?? value.custom_sets)
    };
  }

  function parse(text) {
    if (text === null || text === undefined || text === '') return { found: false, value: [] };
    try { return { found: true, value: JSON.parse(text), corrupt: false }; }
    catch (_) { return { found: true, value: [], corrupt: true }; }
  }

  function legacyItems(value) {
    if (Array.isArray(value)) return value;
    if (!value || typeof value !== 'object') return [];
    return Object.entries(value).map(([key, row]) => {
      if (!row || typeof row !== 'object') return null;
      return { id: row.id ?? key, qty: row.qty ?? row.quantity ?? row.count };
    }).filter(Boolean);
  }

  function emit(name, detail) {
    try { global.document && global.document.dispatchEvent(new global.CustomEvent(name, { detail })); } catch (_) { }
  }

  function emitCartUpdated() {
    emit('tokyo-cart-updated', {
      snapshot: snapshotAll(),
      orderPayload: orderPayload(),
      count: count(),
      estimatedTotal: total()
    });
  }

  function writeState(value) {
    const clean = normalizeState(value);
    memoryCart = clean.items;
    memoryCustomSets = clean.customSets;
    clearCheckoutRequestKey();
    const store = storage();
    if (store) {
      try {
        store.setItem(CART_KEY, JSON.stringify(clean));
        LEGACY_KEYS.forEach(key => store.removeItem(key));
      } catch (_) { }
    }
    emitCartUpdated();
    return clean;
  }

  function getState() {
    const store = storage();
    if (!store) return normalizeState({ items: memoryCart, customSets: memoryCustomSets });
    try {
      const canonical = parse(store.getItem(CART_KEY));
      if (canonical.found) {
        const clean = normalizeState(canonical.value);
        if (canonical.corrupt || JSON.stringify(clean) !== JSON.stringify(canonical.value)) {
          try { store.setItem(CART_KEY, JSON.stringify(clean)); } catch (_) { }
        }
        memoryCart = clean.items;
        memoryCustomSets = clean.customSets;
        return clean;
      }
      for (const key of LEGACY_KEYS) {
        const old = parse(store.getItem(key));
        if (!old.found) continue;
        const clean = normalizeState(legacyItems(old.value));
        writeState(clean);
        return clean;
      }
      memoryCart = [];
      memoryCustomSets = [];
      return { items: [], customSets: [] };
    } catch (_) {
      return normalizeState({ items: memoryCart, customSets: memoryCustomSets });
    }
  }

  function getCart() { return getState().items; }
  function getCustomSets() { return getState().customSets; }
  function writeCart(value) {
    const state = getState();
    if (value && !Array.isArray(value) && typeof value === 'object') return writeState(value);
    return writeState({ items: value, customSets: state.customSets }).items;
  }

  function setQty(idValue, qtyValue) {
    const id = positiveId(idValue);
    if (!id || !Number.isSafeInteger(qtyValue) || qtyValue < 0) return false;
    const state = getState();
    const items = state.items;
    const index = items.findIndex(row => row.id === id);
    if (qtyValue === 0) {
      if (index >= 0) items.splice(index, 1);
    } else {
      const known = catalog();
      if (known.size && !known.has(id)) return false;
      const qty = Math.min(MAX_QTY, qtyValue);
      if (index >= 0) items[index].qty = qty;
      else if (items.length < MAX_LINES) items.push({ id, qty });
      else return false;
    }
    writeState({ items, customSets: state.customSets });
    return true;
  }

  function add(idValue, qtyValue = 1) {
    const id = positiveId(idValue);
    if (!id || !Number.isSafeInteger(qtyValue) || qtyValue < 1) return false;
    const items = getCart();
    const current = items.find(row => row.id === id);
    const next = Math.min(MAX_QTY, (current ? current.qty : 0) + Math.min(MAX_QTY, qtyValue));
    return setQty(id, next);
  }

  function adjust(idValue, delta) {
    if (!Number.isSafeInteger(delta) || Math.abs(delta) !== 1) return false;
    const id = positiveId(idValue);
    const current = getCart().find(row => row.id === id);
    if (!current) return false;
    return setQty(id, Math.max(0, current.qty + delta));
  }

  function addCustomSet(detail) {
    if (!detail || typeof detail !== 'object') return false;
    const item = normalizeCustomSets([{ ...detail, qty: detail.qty ?? 1 }])[0];
    if (!item) return false;
    const state = getState();
    const existing = state.customSets.find(row => row.key === item.key);
    const usedSetSlots = state.customSets.reduce((sum, row) => sum + row.qty, 0);
    if (existing) {
      const nextQty = existing.qty + item.qty;
      if (usedSetSlots - existing.qty + nextQty > MAX_CUSTOM_SETS) return false;
      existing.qty = nextQty;
    } else if (state.items.length + state.customSets.length >= MAX_LINES || usedSetSlots + item.qty > MAX_CUSTOM_SETS) return false;
    else state.customSets.push(item);
    writeState(state);
    return true;
  }

  function setCustomSetQty(key, qtyValue) {
    if (typeof key !== 'string' || !key || !Number.isSafeInteger(qtyValue) || qtyValue < 0) return false;
    const state = getState();
    const index = state.customSets.findIndex(row => row.key === key);
    if (index < 0) return false;
    if (qtyValue === 0) state.customSets.splice(index, 1);
    else {
      const otherSetSlots = state.customSets.reduce((sum, row, rowIndex) => sum + (rowIndex === index ? 0 : row.qty), 0);
      if (otherSetSlots + qtyValue > MAX_CUSTOM_SETS) return false;
      state.customSets[index].qty = qtyValue;
    }
    writeState(state);
    return true;
  }

  function removeCustomSet(key) { return setCustomSetQty(key, 0); }
  function clear() { writeState({ items: [], customSets: [] }); }

  function getFavorites() {
    const store = storage();
    if (!store) return memoryFavorites.slice();
    try {
      const raw = parse(store.getItem(FAVORITES_KEY));
      const source = raw.found ? raw.value : parse(store.getItem('tokyo-favorites')).value;
      const known = catalog();
      const ids = Array.isArray(source) ? source.map(positiveId).filter(id => id && (!known.size || known.has(id))) : [];
      const clean = [...new Set(ids)];
      memoryFavorites = clean;
      if (!raw.found || raw.corrupt || JSON.stringify(clean) !== JSON.stringify(source)) {
        try { store.setItem(FAVORITES_KEY, JSON.stringify(clean)); store.removeItem('tokyo-favorites'); } catch (_) { }
      }
      return clean;
    } catch (_) { return memoryFavorites.slice(); }
  }

  function setFavorites(value) {
    const known = catalog();
    const ids = Array.isArray(value) ? value.map(positiveId).filter(id => id && (!known.size || known.has(id))) : [];
    memoryFavorites = [...new Set(ids)];
    const store = storage();
    if (store) {
      try { store.setItem(FAVORITES_KEY, JSON.stringify(memoryFavorites)); store.removeItem('tokyo-favorites'); } catch (_) { }
    }
    emit('tokyo-favorites-updated');
    return memoryFavorites.slice();
  }

  function toggleFavorite(idValue) {
    const id = positiveId(idValue);
    if (!id) return getFavorites();
    const current = getFavorites();
    return setFavorites(current.includes(id) ? current.filter(value => value !== id) : current.concat(id));
  }

  async function beginCheckout() {
    const now = Date.now();
    const store = storage();
    const token = String(now) + '-' + Math.random().toString(36).slice(2);
    const locks = global.navigator && global.navigator.locks;
    if (locks && typeof locks.request === 'function') {
      let decide;
      const acquired = new Promise(resolve => { decide = resolve; });
      try {
        locks.request('tokyo-premium-order-submit', { ifAvailable: true }, lock => {
          if (!lock) { decide(false); return; }
          decide(true);
          return new Promise(resolve => { webLockRelease = resolve; });
        }).catch(() => decide(false));
        if (await acquired) { ownLock = 'web:' + token; return true; }
        return false;
      } catch (_) { /* Older browsers fall back to a verified localStorage lock. */ }
    }
    if (store) {
      try {
        const existing = parse(store.getItem(CHECKOUT_LOCK_KEY)).value;
        if (existing && Number(existing.expires) > now) return false;
        store.setItem(CHECKOUT_LOCK_KEY, JSON.stringify({ token, expires: now + 120000 }));
        await new Promise(resolve => global.setTimeout(resolve, 20));
        const verified = parse(store.getItem(CHECKOUT_LOCK_KEY)).value;
        if (!verified || verified.token !== token) return false;
      } catch (_) {
        if (memoryLockUntil > now) return false;
        memoryLockUntil = now + 120000;
      }
    } else {
      if (memoryLockUntil > now) return false;
      memoryLockUntil = now + 120000;
    }
    ownLock = token;
    return true;
  }

  function finishCheckout() {
    if (webLockRelease) { webLockRelease(); webLockRelease = null; }
    const store = storage();
    if (store && ownLock) {
      try {
        const current = parse(store.getItem(CHECKOUT_LOCK_KEY)).value;
        if (current && current.token === ownLock) store.removeItem(CHECKOUT_LOCK_KEY);
      } catch (_) { }
    }
    ownLock = '';
    memoryLockUntil = 0;
  }

  function checkoutRequestKey() {
    const fingerprint = JSON.stringify(orderPayload());
    const store = storage();
    if (store) {
      try {
        const current = parse(store.getItem(CHECKOUT_REQUEST_KEY)).value;
        if (current && current.fingerprint === fingerprint && typeof current.key === 'string') return current.key;
      } catch (_) { }
    }
    if (memoryRequest && memoryRequest.fingerprint === fingerprint) return memoryRequest.key;
    const key = 'tokyo-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 18);
    memoryRequest = { fingerprint, key };
    if (store) {
      try { store.setItem(CHECKOUT_REQUEST_KEY, JSON.stringify(memoryRequest)); } catch (_) { }
    }
    return key;
  }

  function clearCheckoutRequestKey() {
    memoryRequest = null;
    const store = storage();
    if (store) { try { store.removeItem(CHECKOUT_REQUEST_KEY); } catch (_) { } }
  }

  function customSetSnapshot() {
    const known = catalog();
    return getCustomSets().flatMap(row => {
      const unitPrice = row.items.reduce((sum, item) => sum + Number(known.get(item.id)?.price || 0) * item.qty, 0);
      const lineTotal = unitPrice * row.qty;
      if (!Number.isSafeInteger(unitPrice) || unitPrice < 0 || !Number.isSafeInteger(lineTotal)) return [];
      return [{ ...row, variant: row.setType, unitPrice, lineTotal, priceIsEstimate: true }];
    });
  }
  function orderPayload() {
    return {
      items: getCart(),
      custom_sets: getCustomSets().flatMap(row => Array.from({ length: row.qty }, () => ({
        variant: row.setType,
        items: row.items.map(item => ({ id: item.id, qty: item.qty }))
      })))
    };
  }
  function snapshotAll() { return { items: snapshot(), customSets: customSetSnapshot() }; }
  function count() {
    const dishes = getCart().reduce((sum, row) => sum + row.qty, 0);
    const setItems = customSetSnapshot().reduce((sum, row) => sum + row.items.reduce((n, item) => n + item.qty, 0) * row.qty, 0);
    return dishes + setItems;
  }
  function snapshot() {
    const known = catalog();
    return getCart().flatMap(row => {
      const dish = known.get(row.id);
      const unitPrice = Number(dish && dish.price);
      const lineTotal = unitPrice * row.qty;
      if (!dish || !Number.isSafeInteger(unitPrice) || unitPrice < 0 || !Number.isSafeInteger(lineTotal)) return [];
      return [{ id: row.id, name: String(dish.name || ''), qty: row.qty, unitPrice, lineTotal }];
    });
  }
  function total() {
    return snapshot().reduce((sum, row) => sum + row.lineTotal, 0)
      + customSetSnapshot().reduce((sum, row) => sum + row.lineTotal, 0);
  }

  global.TokyoCart = Object.freeze({
    key: CART_KEY, maxQty: MAX_QTY, maxCustomSets: MAX_CUSTOM_SETS, get: getCart, set: writeCart, add, setQty, adjust, remove: id => setQty(id, 0),
    getCustomSets, addCustomSet, setCustomSetQty, adjustCustomSet: (key, delta) => { const row = getCustomSets().find(item => item.key === key); return row && Number.isSafeInteger(delta) && Math.abs(delta) === 1 ? setCustomSetQty(key, Math.max(0, row.qty + delta)) : false; }, removeCustomSet, customSetSnapshot, orderPayload,
    clear, count, total, snapshot, snapshotAll, getFavorites, setFavorites, toggleFavorite, beginCheckout, finishCheckout, checkoutRequestKey, clearCheckoutRequestKey,
    catalog: () => Array.from(catalog().values())
  });

  global.addEventListener && global.addEventListener('storage', event => {
    if (event.key === CART_KEY || LEGACY_KEYS.includes(event.key)) {
      const state = normalizeState(parse(event.newValue).value);
      memoryCart = state.items;
      memoryCustomSets = state.customSets;
      clearCheckoutRequestKey();
      emitCartUpdated();
    } else if (event.key === FAVORITES_KEY || event.key === 'tokyo-favorites') {
      memoryFavorites = [];
      getFavorites();
      emit('tokyo-favorites-updated');
    }
  });

  global.document && global.document.addEventListener('tokyo:custom-set:add', event => {
    if (addCustomSet(event.detail)) event.preventDefault();
  });

  getCart();
  getFavorites();
})(window);
