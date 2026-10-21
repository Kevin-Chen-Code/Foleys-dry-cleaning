const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

function setup(fetch) {
    const events = {};
    const status = {hidden: true, style: {}, textContent: ''};
    const button = {disabled: false};
    const calls = {clicks: 0, reloads: 0, revoked: 0, prevented: 0};
    const timers = [];
    const form = {action: '/admin/weekly-report.xlsx', querySelector: () => button, addEventListener: (name, handler) => { events[name] = handler; }};
    const context = {
        document: {
            querySelector: (selector) => selector === '[data-report-download]' ? form : status,
            createElement: () => ({click: () => { calls.clicks++; }, remove() {}}),
            body: {appendChild() {}},
        },
        window: {location: {reload: () => { calls.reloads++; }}},
        URL: {createObjectURL: () => 'blob:test', revokeObjectURL: () => { calls.revoked++; }},
        FormData: class {constructor(source) {this.source = source;}},
        fetch, setTimeout: (handler) => timers.push(handler),
    };
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../webroot/js/report-download.js'), 'utf8'), context);
    return {status, button, calls, timers, form, submit: () => events.submit({preventDefault: () => { calls.prevented++; }})};
}

test('Downloads via POST with form token, then refreshes the report', async () => {
    let request;
    const ui = setup(async (url, options) => {
        request = {url, options};
        return {ok: true, headers: {get: () => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'}, blob: async () => ({})};
    });
    await ui.submit();
    assert.equal(request.url, ui.form.action);
    assert.equal(request.options.method, 'POST');
    assert.equal(request.options.credentials, 'same-origin');
    assert.equal(request.options.body.source, ui.form);
    assert.equal(ui.calls.clicks, 1);
    assert.equal(ui.calls.reloads, 0);
    assert.equal(ui.button.disabled, true);
    ui.timers.forEach(handler => handler());
    assert.equal(ui.calls.reloads, 1);
    assert.equal(ui.calls.revoked, 1);
});

test('Ignores repeat clicks while the first download is pending', async () => {
    let finish;
    let requests = 0;
    const ui = setup(() => {requests++; return new Promise(resolve => {finish = resolve;});});
    const first = ui.submit();
    await ui.submit();
    assert.equal(requests, 1);
    finish({ok: false});
    await first;
    assert.equal(ui.button.disabled, false);
});

test('HTTP, login-redirect, network and body failures show an error without refreshing', async () => {
    for (const response of [
        async () => ({ok: false}),
        async () => ({ok: true, headers: {get: () => 'text/html'}}),
        async () => {throw new Error('Network failure');},
        async () => ({ok: true, headers: {get: () => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'}, blob: async () => {throw new Error('Interrupted body');}}),
    ]) {
        const ui = setup(response);
        await ui.submit();
        assert.equal(ui.calls.clicks, 0);
        assert.equal(ui.calls.reloads, 0);
        assert.equal(ui.timers.length, 0);
        assert.equal(ui.button.disabled, false);
        assert.equal(ui.status.hidden, false);
        assert.equal(ui.status.style.color, '#b42318');
        assert.match(ui.status.textContent, /could not be completed/);
        assert.match(ui.status.textContent, /Requests remain saved/);
    }
});
