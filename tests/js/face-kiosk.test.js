import test from 'node:test';
import assert from 'node:assert/strict';
import { setupFaceKiosk } from '../../resources/js/face-kiosk.js';

function fixture(pending) {
    const make = () => ({ checked: false, disabled: false, value: 'check_in', addEventListener(name, fn) { this[name] = fn; } });
    const els = Object.fromEntries(['consent', 'video', 'canvas', 'start', 'scan', 'status', 'type', 'token'].map(name => [name, make()]));
    els.video.videoWidth = 640; els.video.videoHeight = 480; els.video.play = async () => {};
    els.canvas.getContext = () => ({ drawImage() {} }); els.canvas.toDataURL = () => 'data:image/jpeg;base64,SYNTHETIC';
    let stopped = 0, calls = 0, request;
    const stream = { getTracks: () => [{ stop() { stopped++; } }] };
    const form = { action: '/hr/kiosk/scan', dataset: { expires: String(Date.now() / 1000 + 900) }, querySelector(s) {
        if (s.includes('_token')) return els.token;
        if (s.includes('name="type"')) return els.type;
        return els[s.replace('[data-', '').replace(']', '')];
    }, addEventListener(name, fn) { this[name] = fn; } };
    const browser = { isSecureContext: true, navigator: { mediaDevices: { getUserMedia: async () => { calls++; return pending || stream; } } }, addEventListener(name, fn) { this[name] = fn; }, setTimeout(fn) { this.expire = fn; }, fetch: async (url, data) => { request = data; return { ok: true, json: async () => ({ message: 'OK', employee: 'TEST', type: 'check_in', time: '08:00' }) }; } };
    setupFaceKiosk(form, browser);
    return { els, form, browser, stream, stats: () => ({ stopped, calls, request }) };
}

test('kiosk requires consent and clears camera and consent after one scan', async () => {
    const f = fixture(); await f.els.start.click(); assert.equal(f.stats().calls, 0);
    f.els.consent.checked = true; await f.els.start.click();
    await f.form.submit({ preventDefault() {} });
    assert.equal(JSON.parse(f.stats().request.body).samples.length, 1);
    assert.equal(f.els.consent.checked, false); assert.equal(f.els.scan.disabled, true);
    assert.equal(f.els.canvas.width, 0); assert.equal(f.stats().stopped, 1);
});

test('kiosk stops late camera grants after consent withdrawal or session expiry', async () => {
    for (const expire of [false, true]) {
        let resolve; const f = fixture(new Promise(r => { resolve = r; }));
        f.els.consent.checked = true; const opening = f.els.start.click();
        if (expire) f.browser.expire(); else { f.els.consent.checked = false; f.els.consent.change(); }
        resolve(f.stream); await opening;
        assert.equal(f.stats().stopped, 1); assert.equal(f.els.scan.disabled, true);
    }
});
