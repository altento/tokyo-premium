(() => {
  const rewardLimits = {
    percent: { min: 1, max: 100, hint: 'Процентная скидка: от 1 до 100 %.' },
    fixed: { min: 1, max: 10000000, hint: 'Фиксированная скидка: от 1 до 10 000 000 ₸.' },
    set_price: { min: 1, max: 10000000, hint: 'Цена сета: от 1 до 10 000 000 ₸.' },
    bonus_points: { min: 1, max: 1000000, hint: 'Бонусы: от 1 до 1 000 000 баллов.' },
    none: { min: 0, max: 0, hint: 'Для этого типа награды числовое значение не используется.' },
    gift_dish: { min: 0, max: 0, hint: 'Количество подарков задаётся запасом и лимитом ниже.' },
    free_delivery: { min: 0, max: 0, hint: 'Стоимость доставки должна быть настроена в основной админке.' },
    access: { min: 0, max: 0, hint: 'Доступ определяется правилом Secret Menu.' }
  };

  document.querySelectorAll('form').forEach((form) => {
    const type = form.querySelector('select[name="reward_type"]');
    const value = form.querySelector('input[name="reward_value"]');
    if (!type || !value) return;
    let hint = form.querySelector('[data-reward-value-hint]');
    if (!hint) {
      hint = document.createElement('small');
      hint.dataset.rewardValueHint = '';
      hint.className = 'tp20-admin-reward-hint';
      value.insertAdjacentElement('afterend', hint);
    }
    const updateRewardLimit = () => {
      const limits = rewardLimits[type.value] || rewardLimits.none;
      value.min = String(limits.min);
      value.max = String(limits.max);
      value.setCustomValidity('');
      hint.textContent = limits.hint;
    };
    type.addEventListener('change', updateRewardLimit);
    updateRewardLimit();
  });

  const input = document.getElementById('marketing-admin-search');
  const clearButton = document.getElementById('marketing-admin-search-clear');
  const status = document.getElementById('marketing-admin-search-status');
  const copyStatus = document.getElementById('promo-copy-status');
  if (copyStatus) {
    document.addEventListener('click', async (event) => {
      const target = event.target;
      const button = target instanceof Element ? target.closest('[data-copy-code]') : null;
      if (!(button instanceof HTMLButtonElement) || button.disabled) return;
      const code = button.dataset.copyCode || '';
      if (!code) return;

      button.disabled = true;
      button.setAttribute('aria-busy', 'true');
      let copied = false;
      try {
        if (navigator.clipboard && window.isSecureContext) {
          await navigator.clipboard.writeText(code);
          copied = true;
        }
      } catch {}

      if (!copied) {
        const fallback = document.createElement('textarea');
        fallback.value = code;
        fallback.readOnly = true;
        fallback.tabIndex = -1;
        fallback.setAttribute('aria-hidden', 'true');
        fallback.style.position = 'fixed';
        fallback.style.left = '-9999px';
        fallback.style.top = '0';
        document.body.append(fallback);
        fallback.select();
        try { copied = document.execCommand('copy'); } catch {}
        fallback.remove();
        button.focus({ preventScroll: true });
      }

      if (copied) {
        button.textContent = 'Скопировано';
        copyStatus.textContent = `Промокод ${code} скопирован.`;
      } else {
        button.textContent = 'Скопировать';
        copyStatus.textContent = `Не удалось скопировать автоматически. Скопируйте код вручную: ${code}.`;
      }
      window.setTimeout(() => {
        button.textContent = 'Скопировать';
        button.disabled = false;
        button.removeAttribute('aria-busy');
      }, copied ? 1600 : 0);
    });
  }
  if (!input || !clearButton || !status) return;

  const records = Array.from(document.querySelectorAll('.tp20-admin-list > article, .tp20-admin-list > details, .tp20-admin-performance-row, .tp20-admin-ledger-row'));
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
      status.textContent = 'Совпадений в загруженных списках нет. Пользователей, приглашения, сертификаты и журнал можно искать отдельными фильтрами ниже.';
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

  document.addEventListener('keydown', (event) => {
    if (event.isComposing || event.altKey || event.ctrlKey || event.metaKey) return;
    const target = event.target;
    const isEditing = target instanceof HTMLElement
      && (target.isContentEditable || target.closest('input, textarea, select, [contenteditable="true"]'));

    if (event.key === '/' && !isEditing) {
      event.preventDefault();
      input.focus({ preventScroll: true });
      input.scrollIntoView({ block: 'center', behavior: 'smooth' });
      return;
    }

    if (event.key === 'Escape' && document.activeElement === input && input.value !== '') {
      input.value = '';
      filterRecords();
    }
  });
})();
