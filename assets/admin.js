(() => {
  const picker = document.querySelector('[data-fscm-picker]');
  if (!picker || !window.fscmAdmin) return;
  const selected = JSON.parse(picker.dataset.selected || '[null,null]');
  const slots = [...picker.querySelectorAll('[data-fscm-slot]')];
  const direction = document.querySelector('[data-fscm-direction]');
  const flow = direction.querySelector('[data-fscm-flow]');
  const radios = [...direction.querySelectorAll('input[name="fscm-keep"]')];
  const reviewPanel = document.querySelector('[data-fscm-review-panel]');
  const reviewButton = direction.querySelector('[data-fscm-review]');
  const timers = [null, null];
  const requests = [0, 0];

  function element(tag, className, value) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (value !== undefined) node.textContent = value;
    return node;
  }

  function name(customer) { return `${customer.name} (#${customer.id})`; }

  function renderSelection(index) {
    const box = slots[index].querySelector('.fscm-selection');
    box.replaceChildren();
    slots[index].classList.toggle('is-selected', !!selected[index]);
    if (!selected[index]) return;
    const customer = selected[index];
    const card = element('div', 'fscm-customer');
    card.append(element('strong', '', name(customer)), element('span', '', customer.email), element('small', '', `${customer.status} · ${customer.tickets} ticket(s)`));
    const change = element('button', 'button button-secondary', 'Change');
    change.type = 'button';
    change.addEventListener('click', () => slots[index].querySelector('.fscm-search').focus());
    box.append(card, change);
  }

  function renderDirection() {
    const both = selected[0] && selected[1] && selected[0].id !== selected[1].id;
    direction.hidden = !both;
    if (!both) return;
    radios.forEach((radio, index) => {
      radio.nextElementSibling.textContent = `Keep ${name(selected[index])}`;
    });
    const keep = Number(radios.find(radio => radio.checked).value);
    flow.textContent = `${name(selected[1 - keep])} will merge into ${name(selected[keep])}.`;
  }

  function choose(index, customer) {
    selected[index] = customer;
    slots[index].querySelector('.fscm-results').hidden = true;
    renderSelection(index);
    renderDirection();
    if (reviewPanel) reviewPanel.hidden = true;
    if (index === 0 && !selected[1]) {
      const input = slots[1].querySelector('.fscm-search');
      input.value = customer.email;
      search(1, customer.email);
    }
  }

  function renderResults(index, customers, message = '') {
    const box = slots[index].querySelector('.fscm-results');
    box.replaceChildren();
    box.hidden = false;
    if (message || !customers.length) {
      box.append(element('p', 'fscm-empty', message || 'No matching customers found.'));
      return;
    }
    customers.forEach(customer => {
      const button = element('button', 'fscm-result');
      button.type = 'button';
      button.setAttribute('role', 'option');
      button.append(element('strong', '', name(customer)), element('span', '', customer.email), element('small', '', `${customer.status} · ${customer.tickets} ticket(s)`));
      button.addEventListener('click', () => choose(index, customer));
      box.append(button);
    });
  }

  async function search(index, term) {
    const serial = ++requests[index];
    if (term.trim().length < 2 && !/^\d+$/.test(term.trim())) {
      slots[index].querySelector('.fscm-results').hidden = true;
      return;
    }
    renderResults(index, [], 'Searching…');
    const url = new URL(window.fscmAdmin.ajaxUrl);
    url.searchParams.set('action', 'fscm_search');
    url.searchParams.set('nonce', window.fscmAdmin.nonce);
    url.searchParams.set('term', term.trim());
    try {
      const response = await fetch(url, { credentials: 'same-origin' });
      const result = await response.json();
      if (serial !== requests[index]) return;
      if (!response.ok || !result.success) throw new Error(result.data?.message || 'Search failed.');
      renderResults(index, result.data);
    } catch (error) {
      if (serial === requests[index]) renderResults(index, [], error.message);
    }
  }

  slots.forEach((slot, index) => {
    const input = slot.querySelector('.fscm-search');
    input.addEventListener('input', () => {
      clearTimeout(timers[index]);
      timers[index] = setTimeout(() => search(index, input.value), 250);
    });
    input.addEventListener('focus', () => {
      if (input.value.trim().length >= 2 || /^\d+$/.test(input.value.trim())) search(index, input.value);
    });
    renderSelection(index);
  });
  radios.forEach(radio => radio.addEventListener('change', () => {
    renderDirection();
    if (reviewPanel) reviewPanel.hidden = true;
  }));
  reviewButton.addEventListener('click', () => {
    const keep = Number(radios.find(radio => radio.checked).value);
    const url = new URL(picker.dataset.pageUrl, location.href);
    url.searchParams.set('target', selected[keep].id);
    url.searchParams.set('source', selected[1 - keep].id);
    location.assign(url.href);
  });
  renderDirection();
  if (selected[0] && !selected[1]) {
    const input = slots[1].querySelector('.fscm-search');
    input.value = selected[0].email;
    search(1, input.value);
  }
})();
