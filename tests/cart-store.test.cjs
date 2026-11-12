const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const source = fs.readFileSync(require('node:path').join(__dirname, '..', 'cart-store.js'), 'utf8');

function createStore(initial = {}) {
  const values = new Map(Object.entries(initial));
  const listeners = new Map();
  const customEvents = [];
  const localStorage = {
    getItem(key) { return values.has(key) ? values.get(key) : null; },
    setItem(key, value) { values.set(key, String(value)); },
    removeItem(key) { values.delete(key); }
  };
  const window = {
    localStorage,
    DISHES: [{ id: 1, name: 'Nigiri', price: 1200 }, { id: 2, name: 'Ramen', price: 4800 }],
    document: { dispatchEvent(event) { customEvents.push(event.type); } },
    CustomEvent: class CustomEvent { constructor(type) { this.type = type; } },
    setTimeout,
    addEventListener(type, listener) { listeners.set(type, listener); }
  };
  vm.runInNewContext(source, { window, Date, Math, JSON, Number, Map, Set, Array, Object, String, RegExp });
  return { api: window.TokyoCart, values, listeners, customEvents };
}

test('corrupt, fractional, unknown, negative and oversized cart rows are sanitized', () => {
  const { api, values } = createStore({ 'tokyo-cart-v1': '{broken json' });
  assert.deepEqual(JSON.parse(JSON.stringify(api.get())), []);
  assert.equal(values.get('tokyo-cart-v1'), '[]');
  values.set('tokyo-cart-v1', JSON.stringify([
    { id: 1, qty: 2 }, { id: 1, qty: 50 }, { id: 2, qty: 1.5 },
    { id: -3, qty: 2 }, { id: 999, qty: 1 }, { id: 2, qty: 1000000000 }
  ]));
  assert.deepEqual(JSON.parse(JSON.stringify(api.get())), [{ id: 1, qty: 20 }, { id: 2, qty: 20 }]);
});

test('legacy array and object cart formats migrate to one canonical key', () => {
  const arrayCart = createStore({ 'tokyo-cart': JSON.stringify([{ id: 1, qty: 3 }]) });
  assert.deepEqual(JSON.parse(JSON.stringify(arrayCart.api.get())), [{ id: 1, qty: 3 }]);
  assert.equal(arrayCart.values.get('tokyo-cart-v1'), '[{"id":1,"qty":3}]');
  assert.equal(arrayCart.values.has('tokyo-cart'), false);

  const objectCart = createStore({ cart: JSON.stringify({ 2: { quantity: 4 } }) });
  assert.deepEqual(JSON.parse(JSON.stringify(objectCart.api.get())), [{ id: 2, qty: 4 }]);
});

test('add and quantity controls reject fractions and clamp at twenty', () => {
  const { api } = createStore();
  assert.equal(api.add(1, 1.25), false);
  assert.equal(api.add(-1, 1), false);
  assert.equal(api.add(99, 1), false);
  assert.equal(api.add(1, 100000), true);
  assert.deepEqual(JSON.parse(JSON.stringify(api.get())), [{ id: 1, qty: 20 }]);
  assert.equal(api.setQty(1, 1.5), false);
  assert.equal(api.adjust(1, 2), false);
  assert.equal(api.adjust(1, -1), true);
  assert.deepEqual(JSON.parse(JSON.stringify(api.get())), [{ id: 1, qty: 19 }]);
  assert.deepEqual(JSON.parse(JSON.stringify(api.snapshot())), [{ id: 1, name: 'Nigiri', qty: 19, unitPrice: 1200, lineTotal: 22800 }]);
});

test('each mutation rereads storage so same-page state cannot overwrite a newer tab value', () => {
  const { api, values, listeners, customEvents } = createStore();
  values.set('tokyo-cart-v1', JSON.stringify([{ id: 2, qty: 5 }]));
  listeners.get('storage')({ key: 'tokyo-cart-v1', newValue: values.get('tokyo-cart-v1') });
  assert.deepEqual(JSON.parse(JSON.stringify(api.get())), [{ id: 2, qty: 5 }]);
  api.add(1, 1);
  assert.deepEqual(JSON.parse(values.get('tokyo-cart-v1')), [{ id: 2, qty: 5 }, { id: 1, qty: 1 }]);
  assert.ok(customEvents.includes('tokyo-cart-updated'));
});

test('favorites share the API, deduplicate IDs, migrate and track cross-tab changes', () => {
  const { api, values, listeners } = createStore({ 'tokyo-favorites': '[1,1,999]' });
  assert.deepEqual(JSON.parse(JSON.stringify(api.getFavorites())), [1]);
  api.toggleFavorite(2);
  assert.deepEqual(JSON.parse(values.get('tokyo-favorites-v1')), [1, 2]);
  values.set('tokyo-favorites-v1', '[2]');
  listeners.get('storage')({ key: 'tokyo-favorites-v1', newValue: '[2]' });
  assert.deepEqual(JSON.parse(JSON.stringify(api.getFavorites())), [2]);
});

test('checkout lock prevents reentry and releases on completion', async () => {
  const { api, values } = createStore();
  assert.equal(await api.beginCheckout(), true);
  assert.equal(await api.beginCheckout(), false);
  api.finishCheckout();
  assert.equal(values.has('tokyo-order-submit-lock'), false);
  assert.equal(await api.beginCheckout(), true);
  api.finishCheckout();
});

test('order retry key stays stable for the same cart and changes when cart content changes', () => {
  const { api, values } = createStore();
  api.add(1, 2);
  const first = api.checkoutRequestKey();
  assert.match(first, /^[A-Za-z0-9._:-]{16,128}$/);
  assert.equal(api.checkoutRequestKey(), first);
  api.add(2, 1);
  const second = api.checkoutRequestKey();
  assert.notEqual(second, first);
  api.clearCheckoutRequestKey();
  assert.equal(values.has('tokyo-order-request-key'), false);
});
