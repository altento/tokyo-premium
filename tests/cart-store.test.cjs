const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const source = fs.readFileSync(require('node:path').join(__dirname, '..', 'cart-store.js'), 'utf8');

function createStore(initial = {}) {
  const values = new Map(Object.entries(initial));
  const listeners = new Map();
  const customEvents = [];
  const cartUpdates = [];
  const localStorage = {
    getItem(key) { return values.has(key) ? values.get(key) : null; },
    setItem(key, value) { values.set(key, String(value)); },
    removeItem(key) { values.delete(key); }
  };
  const window = {
    localStorage,
    DISHES: [
      { id: 1, name: 'Nigiri', price: 1200 }, { id: 2, name: 'Ramen', price: 4800 },
      { id: 3, name: 'Miso', price: 700 }, { id: 4, name: 'Tuna', price: 2200 },
      { id: 5, name: 'Matcha', price: 900 }, { id: 6, name: 'Gyoza', price: 1500 },
      { id: 7, name: 'Sashimi', price: 3400 }, { id: 8, name: 'Tea', price: 500 }
    ],
    document: { addEventListener(type, listener) { listeners.set(`document:${type}`, listener); }, dispatchEvent(event) { customEvents.push(event.type); if (event.type === 'tokyo-cart-updated') cartUpdates.push(event.detail); return listeners.get(`document:${event.type}`)?.(event); } },
    CustomEvent: class CustomEvent { constructor(type, options = {}) { this.type = type; this.detail = options.detail; this.defaultPrevented = false; } preventDefault() { this.defaultPrevented = true; } },
    setTimeout,
    addEventListener(type, listener) { listeners.set(type, listener); }
  };
  vm.runInNewContext(source, { window, Date, Math, JSON, Number, Map, Set, Array, Object, String, RegExp });
  return { api: window.TokyoCart, values, listeners, customEvents, cartUpdates, window };
}

test('corrupt, fractional, unknown, negative and oversized cart rows are sanitized', () => {
  const { api, values } = createStore({ 'tokyo-cart-v1': '{broken json' });
  assert.deepEqual(JSON.parse(JSON.stringify(api.get())), []);
  assert.equal(values.get('tokyo-cart-v1'), '{"items":[],"customSets":[]}');
  values.set('tokyo-cart-v1', JSON.stringify([
    { id: 1, qty: 2 }, { id: 1, qty: 50 }, { id: 2, qty: 1.5 },
    { id: -3, qty: 2 }, { id: 999, qty: 1 }, { id: 2, qty: 1000000000 }
  ]));
  assert.deepEqual(JSON.parse(JSON.stringify(api.get())), [{ id: 1, qty: 20 }, { id: 2, qty: 20 }]);
});

test('legacy array and object cart formats migrate to one canonical key', () => {
  const arrayCart = createStore({ 'tokyo-cart': JSON.stringify([{ id: 1, qty: 3 }]) });
  assert.deepEqual(JSON.parse(JSON.stringify(arrayCart.api.get())), [{ id: 1, qty: 3 }]);
  assert.equal(arrayCart.values.get('tokyo-cart-v1'), '{"items":[{"id":1,"qty":3}],"customSets":[]}');
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
  assert.deepEqual(JSON.parse(values.get('tokyo-cart-v1')), { items: [{ id: 2, qty: 5 }, { id: 1, qty: 1 }], customSets: [] });
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
  api.addCustomSet({ setType: 'signature', items: [{ id: 1, qty: 1 }, { id: 2, qty: 1 }, { id: 3, qty: 1 }] });
  const third = api.checkoutRequestKey();
  assert.notEqual(third, second);
  assert.equal(api.count(), 6);
  api.clearCheckoutRequestKey();
  assert.equal(values.has('tokyo-order-request-key'), false);
});

test('custom sets use the server variant contract and show estimates from the live catalog only', () => {
  const { api, values, cartUpdates } = createStore();
  const detail = { setType: 'signature', name: 'Tokyo Signature', estimatedTotal: 1, items: [{ id: 1, qty: 1 }, { id: 2, qty: 1 }, { id: 3, qty: 2 }] };
  assert.equal(api.addCustomSet(detail), true);
  const rows = JSON.parse(JSON.stringify(api.customSetSnapshot()));
  assert.equal(rows.length, 1);
  assert.equal(rows[0].unitPrice, 7400);
  assert.equal(rows[0].lineTotal, 7400);
  assert.equal(rows[0].priceIsEstimate, true);
  assert.equal(api.total(), 7400);
  assert.deepEqual(JSON.parse(JSON.stringify(api.orderPayload())), { items: [], custom_sets: [{ variant: 'signature', items: [{ id: 1, qty: 1 }, { id: 2, qty: 1 }, { id: 3, qty: 2 }] }] });
  assert.equal(JSON.parse(values.get('tokyo-cart-v1')).customSets[0].setType, 'signature');
  assert.equal(JSON.stringify(api.orderPayload()).includes('estimatedTotal'), false);
  assert.deepEqual(JSON.parse(JSON.stringify(cartUpdates.at(-1).orderPayload)), JSON.parse(JSON.stringify(api.orderPayload())));
  assert.equal(cartUpdates.at(-1).estimatedTotal, 7400);
  assert.equal(cartUpdates.at(-1).snapshot.customSets[0].priceIsEstimate, true);
});

test('custom set quantity, removal and server three-set limit stay consistent', () => {
  const { api } = createStore();
  const first = { setType: 'signature', items: [{ id: 1, qty: 1 }, { id: 2, qty: 1 }, { id: 3, qty: 1 }] };
  const second = { setType: 'signature', items: [{ id: 4, qty: 1 }, { id: 5, qty: 1 }, { id: 6, qty: 1 }] };
  assert.equal(api.addCustomSet(first), true);
  const key = api.getCustomSets()[0].key;
  assert.equal(api.adjustCustomSet(key, 1), true);
  assert.equal(api.getCustomSets()[0].qty, 2);
  assert.equal(api.addCustomSet(second), true);
  assert.equal(api.addCustomSet(second), false);
  assert.equal(api.setCustomSetQty(key, 3), false);
  assert.equal(api.orderPayload().custom_sets.length, 3);
  assert.equal(api.removeCustomSet(key), true);
  assert.equal(api.getCustomSets().length, 1);
  assert.equal(api.getCustomSets()[0].qty, 1);
});

test('custom set validation rejects malformed, unknown and unsupported compositions', () => {
  const { api } = createStore();
  assert.equal(api.addCustomSet({ setType: 'signature', items: [{ id: 1, qty: 1 }, { id: 2, qty: 1 }] }), false);
  assert.equal(api.addCustomSet({ setType: 'signature', items: [{ id: 1, qty: 5 }, { id: 2, qty: 1 }, { id: 3, qty: 1 }] }), false);
  assert.equal(api.addCustomSet({ setType: 'signature', items: [{ id: 1, qty: 1 }, { id: 2, qty: 1 }, { id: 999, qty: 1 }] }), false);
  assert.equal(api.addCustomSet({ setType: 'signature', qty: -1, items: [{ id: 1, qty: 1 }, { id: 2, qty: 1 }, { id: 3, qty: 1 }] }), false);
  assert.deepEqual(JSON.parse(JSON.stringify(api.getCustomSets())), []);
});

test('clear removes dish and custom set lines; re-adding does not restore old custom set state', () => {
  const { api, values } = createStore();
  const detail = { setType: 'signature', items: [{ id: 1, qty: 1 }, { id: 2, qty: 1 }, { id: 3, qty: 1 }] };
  api.add(8, 2); api.addCustomSet(detail); api.clear();
  assert.deepEqual(JSON.parse(JSON.stringify(api.get())), []);
  assert.deepEqual(JSON.parse(JSON.stringify(api.getCustomSets())), []);
  assert.equal(api.add(4, 1), true);
  assert.deepEqual(JSON.parse(JSON.stringify(api.get())), [{ id: 4, qty: 1 }]);
  assert.deepEqual(JSON.parse(JSON.stringify(api.getCustomSets())), []);
  assert.deepEqual(JSON.parse(values.get('tokyo-cart-v1')), { items: [{ id: 4, qty: 1 }], customSets: [] });
});

test('custom set add event is handled once and cross-tab storage updates both line types', () => {
  const { api, values, listeners, window } = createStore();
  const detail = { setType: 'signature', items: [{ id: 1, qty: 1 }, { id: 2, qty: 1 }, { id: 3, qty: 1 }] };
  const event = new window.CustomEvent('tokyo:custom-set:add', { cancelable: true, detail });
  window.document.dispatchEvent(event);
  assert.equal(event.defaultPrevented, true);
  assert.equal(api.getCustomSets().length, 1);
  values.set('tokyo-cart-v1', JSON.stringify({ items: [{ id: 4, qty: 2 }], customSets: [{ ...detail, qty: 2 }] }));
  listeners.get('storage')({ key: 'tokyo-cart-v1', newValue: values.get('tokyo-cart-v1') });
  assert.deepEqual(JSON.parse(JSON.stringify(api.get())), [{ id: 4, qty: 2 }]);
  assert.equal(api.getCustomSets()[0].qty, 2);
  assert.equal(api.orderPayload().custom_sets.length, 2);
});
