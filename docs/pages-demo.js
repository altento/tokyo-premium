const applyBookingFormat = () => {
  const format = new URLSearchParams(location.search).get('format');
  const select = document.querySelector('#booking-format');
  if (select && ['Начало', 'Сезонный', 'Премиальный'].includes(format || '')) select.value = format;
};
applyBookingFormat();
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', applyBookingFormat, { once: true });

document.addEventListener('submit', async event => {
  const form = event.target;
  if (!(form instanceof HTMLFormElement) || !form.matches('form[data-api]')) return;
  event.preventDefault();
  event.stopImmediatePropagation();

  const status = form.querySelector('.form-status');
  if (form.dataset.submitting === 'true') return;
  const data = Object.fromEntries(new FormData(form).entries());
  const type = form.dataset.api;
  const storageKey = type === 'order' ? 'tokyo-demo-orders'
    : type === 'booking' ? 'tokyo-demo-bookings'
      : type === 'review' ? 'tokyo-demo-reviews' : 'tokyo-demo-contacts';
  let rows = [];
  try { rows = JSON.parse(localStorage.getItem(storageKey) || '[]'); if (!Array.isArray(rows)) rows = []; } catch (_) { rows = []; }

  const cart = window.TokyoCart;
  if (type === 'order') {
    if (!cart || !(cart.get().length || cart.getCustomSets().length)) {
      status.textContent = 'Добавьте блюда или соберите сет перед подтверждением.';
      status.className = 'form-status error';
      return;
    }
    if (!await cart.beginCheckout()) {
      status.textContent = 'Оформление уже выполняется. Подождите завершения запроса.';
      status.className = 'form-status error';
      return;
    }
    form.dataset.submitting = 'true';
    const submit = form.querySelector('button[type="submit"]');
    if (submit) submit.disabled = true;
    const snapshot = cart.snapshotAll();
    const payload = cart.orderPayload();
    const customSets = snapshot.customSets.map(set => ({
      ...set,
      variant: set.variant || set.setType,
      items: set.items.map(item => ({ id: item.id, qty: item.qty }))
    }));
    const subtotal = cart.total();
    const promoCode = String(data.promo_code || '').trim().toUpperCase();
    let discountTotal = 0;
    if (promoCode === 'DEMO10') discountTotal = Math.min(5000, Math.floor(subtotal * 0.10));
    else if (promoCode === 'TOKYOHOUR') {
      const parts = new Intl.DateTimeFormat('en-GB', { timeZone: 'Asia/Qyzylorda', weekday: 'short', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }).formatToParts(new Date());
      const local = Object.fromEntries(parts.map(part => [part.type, part.value]));
      if (!['Mon','Tue','Wed','Thu','Fri'].includes(local.weekday) || Number(local.hour) < 14 || Number(local.hour) >= 17) {
        cart.finishCheckout(); form.dataset.submitting = 'false'; if (submit) submit.disabled = false;
        status.textContent = 'TOKYOHOUR действует только по будням с 14:00 до 17:00 по времени Asia/Qyzylorda.'; status.className = 'form-status error'; return;
      }
      discountTotal = Math.min(4000, Math.floor(subtotal * 0.15));
    } else if (promoCode) {
      cart.finishCheckout(); form.dataset.submitting = 'false'; if (submit) submit.disabled = false;
      status.textContent = 'В демоверсии доступны промокоды DEMO10 и TOKYOHOUR.'; status.className = 'form-status error'; return;
    }
    const validDishes = snapshot.items.every(row => Number.isSafeInteger(row.id) && row.id > 0 && Number.isSafeInteger(row.qty) && row.qty > 0 && row.qty <= 20 && Number.isSafeInteger(row.unitPrice) && row.unitPrice >= 0 && Number.isSafeInteger(row.lineTotal));
    const validSets = customSets.every(set => typeof set.variant === 'string' && set.items.length >= 3 && set.items.length <= 8 && Number.isSafeInteger(set.qty) && set.qty > 0 && set.qty <= 3 && Number.isSafeInteger(set.unitPrice) && set.unitPrice >= 0 && Number.isSafeInteger(set.lineTotal) && set.items.every(item => Number.isSafeInteger(item.id) && item.id > 0 && Number.isSafeInteger(item.qty) && item.qty > 0 && item.qty <= 4));
    if ((!snapshot.items.length && !customSets.length) || !validDishes || !validSets || !Number.isSafeInteger(subtotal) || subtotal <= 0) {
      cart.finishCheckout();
      form.dataset.submitting = 'false';
      if (submit) submit.disabled = false;
      status.textContent = 'Корзина пуста или содержит недоступное блюдо.';
      status.className = 'form-status error';
      return;
    }
    rows.unshift({
      name: data.name,
      date: new Date().toLocaleString('ru-RU'),
      orderId: `DEMO-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
      subtotal,
      discountTotal,
      promoCode,
      total: subtotal - discountTotal,
      count: cart.count(),
      fulfillment: data.fulfillment || 'delivery',
      items: snapshot.items,
      customSets,
      custom_sets: payload.custom_sets
    });
  } else if (type === 'booking') {
    rows.unshift({ name: data.name, date: data.date, time: data.time, guests: data.guests, zone: data.zone, table: data.table, format: data.format || '' });
  } else if (type === 'review') {
    if (String(data.body || '').trim().length < 20) {
      status.textContent = 'Добавьте отзыв не короче 20 символов.';
      status.className = 'form-status error';
      return;
    }
    rows.unshift({ name: data.name, theme: data.theme || '', rating: data.rating, text: data.body, date: new Date().toLocaleDateString('ru-RU') });
  } else {
    rows.unshift({ name: data.name, email: data.email, subject: data.subject, message: data.message, date: new Date().toLocaleString('ru-RU') });
  }

  try { localStorage.setItem(storageKey, JSON.stringify(rows.slice(0, 30))); }
  catch (_) {
    if (type === 'order') cart?.finishCheckout();
    form.dataset.submitting = 'false';
    const submit = form.querySelector('button[type="submit"]');
    if (submit) submit.disabled = false;
    status.textContent = 'Не удалось сохранить демо-запись в этом браузере.';
    status.className = 'form-status error';
    return;
  }
  if (type === 'order') {
    cart.clearCheckoutRequestKey();
    cart.clear();
    cart.finishCheckout();
  }
  const successMessage = type === 'review'
    ? 'Спасибо. Отзыв сохранён в этом браузере и не публикуется.'
    : type === 'contact'
      ? 'Сообщение сохранено только в этом браузере. Оно не отправлено ресторану.'
      : type === 'booking'
        ? 'Демо-заявка сохранена только в этом браузере. Email не отправляется, бронь не подтверждена.'
        : 'Готово: демо-запись сохранена только в этом браузере. Это не подтверждённая бронь и не отправленный заказ.';
  if (type === 'order' && window.TokyoCheckout) window.TokyoCheckout.reset(form);
  else form.reset();
  status.textContent = successMessage;
  status.className = 'form-status success';
  form.dataset.submitting = 'false';
  const submit = form.querySelector('button[type="submit"]');
  if (submit) submit.disabled = type === 'order' && !(cart?.get().length || cart?.getCustomSets().length);
  document.dispatchEvent(new Event('tokyo-demo-data-updated'));
}, true);
