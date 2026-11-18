import test from 'node:test';
import assert from 'node:assert/strict';
import { setupListControls } from '../../resources/js/list-controls.js';

test('changing the row count submits the GET form once and hides the manual fallback', () => {
    let change, submissions = 0, hidden = false;
    const form = {
        querySelector: (selector) => selector === '[data-per-page-select]'
            ? { addEventListener: (_, callback) => { change = callback; } }
            : { setAttribute: (name) => { hidden = name === 'hidden'; } },
        requestSubmit: () => { submissions++; },
    };
    setupListControls({ querySelectorAll: (selector) => selector === '[data-per-page-form]' ? [form] : [] });
    assert.equal(hidden, true);
    change();
    assert.equal(submissions, 1);
});

test('filter stays open for field interaction and closes on outside click or Escape', () => {
    let click, keydown, focused = false;
    const field = {};
    const panel = {
        open: true,
        querySelector: () => ({ focus: () => { focused = true; } }),
        contains: (target) => target === field,
        addEventListener: (_, callback) => { keydown = callback; },
    };
    setupListControls({
        querySelectorAll: (selector) => selector === '[data-list-filter]' ? [panel] : [],
        addEventListener: (_, callback) => { click = callback; },
    });
    click({ target: field });
    assert.equal(panel.open, true);
    click({ target: {} });
    assert.equal(panel.open, false);
    panel.open = true;
    keydown({ key: 'Tab' });
    assert.equal(panel.open, true);
    keydown({ key: 'Escape' });
    assert.equal(panel.open, false);
    assert.equal(focused, true);
});
