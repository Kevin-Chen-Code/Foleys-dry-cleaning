const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

function setup() {
  const handlers = {};
  const attributes = {};
  const button = {disabled: false, textContent: 'Submit request'};
  const form = {
    valid: true,
    querySelector: () => button,
    querySelectorAll() { return [{reportValidity: () => this.valid}]; },
    addEventListener: (name, handler) => { handlers[name] = handler; },
    setAttribute: (name, value) => { attributes[name] = value; },
    removeAttribute: (name) => { delete attributes[name]; },
  };
  vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../webroot/js/request-submit.js'), 'utf8'), {
    document: {addEventListener: (_, ready) => ready(), querySelectorAll: () => [form]},
    window: {addEventListener: (name, handler) => { handlers[name] = handler; }},
  });
  return {form, button, attributes, handlers, submit(prevented = false) {
    const event = {defaultPrevented: prevented, preventDefault() { this.defaultPrevented = true; }};
    handlers.submit(event);
    return event;
  }};
}

test('Allows first POST and blocks repeat submissions including Enter while pending', () => {
  const ui = setup();
  assert.equal(ui.submit().defaultPrevented, false);
  assert.equal(ui.button.disabled, true);
  assert.equal(ui.button.textContent, 'Submitting…');
  assert.equal(ui.attributes['aria-busy'], 'true');
  assert.equal(ui.submit().defaultPrevented, true);
});

test('Invalid inputs remain editable and can be corrected before submitting', () => {
  const ui = setup();
  ui.form.valid = false;
  assert.equal(ui.submit().defaultPrevented, true);
  assert.equal(ui.button.disabled, false);
  ui.form.valid = true;
  assert.equal(ui.submit().defaultPrevented, false);
  assert.equal(ui.button.disabled, true);
});

test('Submission already cancelled by another handler does not lock the form', () => {
  const ui = setup();
  ui.submit(true);
  assert.equal(ui.button.disabled, false);
  assert.equal(ui.submit().defaultPrevented, false);
});

test('Returning through browser history restores the button', () => {
  const ui = setup();
  ui.submit();
  ui.handlers.pageshow();
  assert.equal(ui.button.disabled, false);
  assert.equal(ui.button.textContent, 'Submit request');
  assert.equal(ui.attributes['aria-busy'], undefined);
  assert.equal(ui.submit().defaultPrevented, false);
});
