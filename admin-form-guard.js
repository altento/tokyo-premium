(() => {
  const forms = [...document.forms].filter((form) => form.method.toLowerCase() === 'post');
  const controlsFor = (form) => [...form.elements].filter((control) => {
    if (!control.name || control.disabled || control.type === 'hidden') return false;
    return !['submit', 'button', 'reset', 'image'].includes(control.type);
  });

  const valueOf = (control) => {
    if (control instanceof HTMLInputElement) {
      if (control.type === 'checkbox' || control.type === 'radio') return control.checked;
      if (control.type === 'file') return [...control.files].map((file) => `${file.name}:${file.size}:${file.lastModified}`);
    }
    if (control instanceof HTMLSelectElement && control.multiple) {
      return [...control.selectedOptions].map((option) => option.value);
    }
    return control.value;
  };

  const snapshot = (form) => JSON.stringify(controlsFor(form).map((control) => [control.name, control.type, valueOf(control)]));
  const initial = new Map(forms.map((form) => [form, snapshot(form)]));
  const submitting = new WeakSet();

  for (const form of forms) form.addEventListener('submit', () => submitting.add(form));

  window.addEventListener('beforeunload', (event) => {
    const hasUnsavedChanges = forms.some((form) => !submitting.has(form) && snapshot(form) !== initial.get(form));
    if (!hasUnsavedChanges) return;
    event.preventDefault();
    event.returnValue = '';
  });
})();
