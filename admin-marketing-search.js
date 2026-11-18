(() => {
  const input = document.getElementById('marketing-admin-search');
  const clearButton = document.getElementById('marketing-admin-search-clear');
  const status = document.getElementById('marketing-admin-search-status');
  if (!input || !clearButton || !status) return;

  const records = Array.from(document.querySelectorAll('.tp20-admin-list > article, .tp20-admin-list > details, .tp20-admin-ledger-row'));
  const normalize = (value) => value.trim().toLocaleLowerCase('ru');

  const filterRecords = () => {
    const query = normalize(input.value);
    let visible = 0;
    for (const record of records) {
      const match = query === '' || normalize(record.textContent || '').includes(query);
      record.hidden = !match;
      if (match) visible += 1;
    }
    if (query === '') {
      status.textContent = 'Введите запрос, чтобы отфильтровать списки.';
    } else if (visible === 0) {
      status.textContent = 'Совпадений в загруженных списках нет. Пользователей и журнал бонусов можно искать их фильтрами ниже.';
    } else {
      status.textContent = `Показано ${visible} из ${records.length} записей в загруженных списках.`;
    }
  };

  input.addEventListener('input', filterRecords);
  clearButton.addEventListener('click', () => {
    input.value = '';
    filterRecords();
    input.focus();
  });
})();
