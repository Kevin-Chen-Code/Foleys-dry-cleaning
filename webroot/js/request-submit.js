document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-request-form]').forEach((form) => {
    const button = form.querySelector('[data-request-submit]');
    if (!button) return;
    const label = button.textContent;
    let submitting = false;
    form.addEventListener('submit', (event) => {
      if (submitting) {
        event.preventDefault();
        return;
      }
      if (event.defaultPrevented) return;
      // Leave validation failures editable; lock only a valid submission.
      // The closed Add item dialog may contain an abandoned invalid quantity.
      // Validate the actual request fields, not that temporary dialog input.
      if (![...form.querySelectorAll('input[required]')].every((input) => input.reportValidity())) {
        event.preventDefault();
        return;
      }
      submitting = true;
      button.disabled = true;
      button.textContent = 'Submitting…';
      form.setAttribute('aria-busy', 'true');
    });
    // Browsers may restore the page (and its disabled button) from history.
    window.addEventListener('pageshow', () => {
      submitting = false;
      button.disabled = false;
      button.textContent = label;
      form.removeAttribute('aria-busy');
    });
  });
});
