document.addEventListener('DOMContentLoaded', () => {
  const grid = document.querySelector('#review-grid');
  if (!grid) return;
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
});
