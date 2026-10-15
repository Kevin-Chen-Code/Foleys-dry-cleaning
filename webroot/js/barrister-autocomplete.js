document.addEventListener('DOMContentLoaded', () => {
  const picker = document.querySelector('[data-barrister-picker]');
  if (!picker) return;
  const name = picker.querySelector('[data-barrister-name]');
  const email = picker.closest('form').querySelector('[name="email"]');
  const list = picker.querySelector('[role="listbox"]');
  const status = document.querySelector('[data-barrister-status]');
  let timer;
  let pending;
  let revision = 0;
  let matches = [];
  let active = -1;
  let selectedName = '';
  let filledEmail = '';

  const close = () => {
    list.hidden = true;
    name.setAttribute('aria-expanded', 'false');
    name.removeAttribute('aria-activedescendant');
    active = -1;
  };
  const cancel = () => {
    clearTimeout(timer);
    pending?.abort();
    revision++;
    close();
  };
  const choose = (entry) => {
    cancel();
    name.value = entry.name;
    email.value = entry.email;
    selectedName = entry.name;
    filledEmail = entry.email;
    status.textContent = 'Email address filled in successfully filled in.';
    email.dispatchEvent(new Event('input', {bubbles: true}));
    email.dispatchEvent(new Event('change', {bubbles: true}));
    name.focus();
  };
  const highlight = (index) => {
    active = index;
    [...list.children].forEach((option, position) => {
      option.setAttribute('aria-selected', String(position === active));
    });
    name.setAttribute('aria-activedescendant', list.children[active].id);
    list.children[active].scrollIntoView({block: 'nearest'});
  };
  name.addEventListener('input', () => {
    cancel();
    if (selectedName && name.value !== selectedName) {
      if (email.value === filledEmail) email.value = '';
      selectedName = '';
      filledEmail = '';
    }
    const query = name.value.trim();
    if (query.length < 2) {
      status.textContent = 'Type at least two letters to see suggested names.';
      return;
    }
    const current = revision;
    timer = setTimeout(async () => {
      pending = new AbortController();
      try {
        const url = new URL(picker.dataset.suggestionsUrl, window.location.href);
        url.searchParams.set('q', query);
        const response = await fetch(url, {signal: pending.signal, headers: {Accept: 'application/json'}});
        if (!response.ok) throw new Error('Suggestions unavailable');
        const data = await response.json();
        if (current !== revision || document.activeElement !== name) return;
        matches = data.suggestions;
        list.replaceChildren();
        matches.forEach((entry, index) => {
          const option = document.createElement('li');
          option.id = `barrister-suggestion-${index}`;
          option.setAttribute('role', 'option');
          option.setAttribute('aria-selected', 'false');
          const displayName = document.createElement('span');
          displayName.className = 'suggestion-name';
          displayName.textContent = entry.name;
          const displayEmail = document.createElement('small');
          displayEmail.textContent = entry.email;
          option.append(displayName, displayEmail);
          option.addEventListener('pointerdown', (event) => {
            event.preventDefault();
            choose(entry);
          });
          list.append(option);
        });
        list.hidden = matches.length === 0;
        name.setAttribute('aria-expanded', String(matches.length > 0));
        status.textContent = matches.length
          ? 'Select your name, or use the arrow keys and Enter. You can also enter your details manually.'
          : 'No matching names found. Please enter your name and email address manually.';
      } catch (error) {
        if (error.name === 'AbortError' || current !== revision) return;
        close();
        status.textContent = 'Suggestions are unavailable. Please enter your name and email address manually.';
      }
    }, 180);
  });
  name.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' || event.key === 'Tab') {
      cancel();
    } else if (!list.hidden && (event.key === 'ArrowDown' || event.key === 'ArrowUp')) {
      event.preventDefault();
      highlight(event.key === 'ArrowDown' ? (active + 1) % matches.length : (active <= 0 ? matches.length - 1 : active - 1));
    } else if (!list.hidden && event.key === 'Enter' && active >= 0) {
      event.preventDefault();
      choose(matches[active]);
    }
  });
  name.addEventListener('blur', cancel);
  document.addEventListener('pointerdown', (event) => {
    if (!picker.contains(event.target)) cancel();
  });
});
