document.addEventListener('DOMContentLoaded', () => {
  const grid = document.querySelector('#review-grid');
  if (!grid) return;
  if (grid.dataset.reviewEnhancements === 'ready') return;
  grid.dataset.reviewEnhancements = 'ready';
  const filterButtons = [...document.querySelectorAll('[data-review-filter]')];
  const sort = document.querySelector('#review-sort');
  const more = document.querySelector('#review-more');
  let filter = 'all';
  let limit = 6;

  function render() {
    const cards = [...grid.querySelectorAll('[data-review-card]')];
    const filtered = cards.filter(card => filter === 'all' || card.dataset.theme === filter);
    filtered.sort((a, b) => {
      if (sort?.value === 'highest') return Number(b.dataset.rating) - Number(a.dataset.rating);
      if (sort?.value === 'lowest') return Number(a.dataset.rating) - Number(b.dataset.rating);
      return b.dataset.date.localeCompare(a.dataset.date);
    });
    cards.forEach(card => { card.hidden = true; });
    filtered.forEach((card, index) => {
      card.hidden = index >= limit;
      grid.append(card);
    });
    if (more) more.hidden = filtered.length <= limit;
  }

  filterButtons.forEach(button => button.addEventListener('click', () => {
    filter = button.dataset.reviewFilter;
    limit = 6;
    filterButtons.forEach(item => item.classList.toggle('active', item === button));
    render();
  }));
  sort?.addEventListener('change', render);
  more?.addEventListener('click', () => { limit += 6; render(); });
  render();

  // Browser-only entries are presented in a separate region and never affect
  // the published review cards or their aggregate rating.
  const localKey = 'tokyo-demo-reviews';
  const localHost = document.createElement('section');
  localHost.className = 'local-review-panel';
  localHost.id = 'local-reviews';
  localHost.setAttribute('aria-labelledby', 'local-reviews-title');
  const submitBand = document.querySelector('.review-submit-band');
  if (submitBand) submitBand.before(localHost);

  function readLocalReviews() {
    try {
      const value = JSON.parse(localStorage.getItem(localKey) || '[]');
      return Array.isArray(value) ? value.slice(0, 30) : [];
    } catch (_) { return []; }
  }

  function renderLocalReviews() {
    if (!submitBand) return;
    const rows = readLocalReviews().filter(row => row && typeof row === 'object');
    localHost.replaceChildren();
    localHost.hidden = rows.length === 0;
    if (!rows.length) return;

    const heading = document.createElement('div');
    heading.className = 'local-review-heading';
    const title = document.createElement('h2');
    title.id = 'local-reviews-title';
    title.textContent = 'Мои отзывы в этом браузере';
    const disclosure = document.createElement('p');
    disclosure.textContent = 'Локальные демо-записи видны только здесь, не публикуются и не меняют общую оценку.';
    const clear = document.createElement('button');
    clear.type = 'button';
    clear.className = 'text-button';
    clear.textContent = 'Очистить мои отзывы';
    clear.addEventListener('click', () => {
      try { localStorage.removeItem(localKey); } catch (_) { /* storage may be unavailable */ }
      renderLocalReviews();
    });
    heading.append(title, disclosure, clear);

    const list = document.createElement('div');
    list.className = 'local-review-list';
    rows.forEach(row => {
      const card = document.createElement('article');
      card.className = 'local-review-card';
      const meta = document.createElement('div');
      meta.className = 'local-review-meta';
      const author = document.createElement('strong');
      author.textContent = String(row.name || 'Гость');
      const theme = document.createElement('span');
      theme.textContent = ['Кухня', 'Сервис', 'Атмосфера', 'Омакасе'].includes(row.theme) ? row.theme : 'Демо';
      const date = document.createElement('time');
      date.textContent = String(row.date || 'Только что');
      meta.append(author, theme, date);

      const rating = document.createElement('p');
      rating.className = 'local-review-rating';
      const score = Number(row.rating);
      rating.textContent = `Оценка: ${Number.isInteger(score) && score >= 1 && score <= 5 ? `${'★'.repeat(score)}${'☆'.repeat(5-score)} (${score}/5)` : 'не указана'}`;
      const body = document.createElement('p');
      body.className = 'local-review-body';
      body.textContent = String(row.text || row.body || '');
      const note = document.createElement('small');
      note.textContent = 'Только локальная демозапись';
      card.append(meta, rating, body, note);
      list.append(card);
    });
    localHost.append(heading, list);
  }

  renderLocalReviews();
  document.addEventListener('tokyo-demo-data-updated', renderLocalReviews);
  window.addEventListener('storage', event => {
    if (event.key === localKey || event.key === null) renderLocalReviews();
  });
});
