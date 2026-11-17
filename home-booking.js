(() => {
  const form = document.querySelector('[data-home-booking-form]');
  if (!form) return;

  const contacts = form.querySelector('[data-home-booking-contact-fields]');
  const buttonLabel = form.querySelector('[data-home-booking-button-label]');
  const note = form.querySelector('[data-home-booking-note]');
  const status = form.querySelector('.form-status');
  const contactControls = [...(contacts?.querySelectorAll('input, select, textarea') || [])];
  if (!contacts || !buttonLabel || !contactControls.length) return;

  const setStep = (step) => {
    const askingForContacts = step === 'contacts';
    form.dataset.homeBookingStep = step;
    contacts.hidden = !askingForContacts;
    contactControls.forEach((control) => { control.disabled = !askingForContacts; });
    note.hidden = !askingForContacts;
    buttonLabel.textContent = askingForContacts ? 'Отправить запрос' : 'Продолжить';
  };

  setStep('visit');

  document.addEventListener('submit', (event) => {
    if (event.target !== form || form.dataset.homeBookingStep !== 'visit') return;
    event.preventDefault();
    event.stopImmediatePropagation();
    setStep('contacts');
    form.querySelector('#guest-name')?.focus({ preventScroll: true });
  }, true);

  form.addEventListener('reset', () => {
    window.setTimeout(() => {
      setStep('visit');
      if (status) status.className = 'form-status';
    }, 0);
  });
})();
