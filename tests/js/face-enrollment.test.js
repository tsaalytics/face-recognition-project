import test from 'node:test';
import assert from 'node:assert/strict';
import { setupFaceEnrollment } from '../../resources/js/face-enrollment.js';

function fixture(options = {}) {
    const listeners = new Map();
    const make = () => ({ checked: false, disabled: false, textContent: '', value: 'csrf', width: 0, height: 0,
        classList: { add() {}, remove() {} }, focus() {},
        addEventListener(name, fn) { this[name] = fn; } });
    const names = ['consent', 'video', 'canvas', 'start', 'capture', 'clear', 'submit', 'status', 'error'];
    const els = Object.fromEntries(names.map(n => [n, make()]));
    els.video.videoWidth = 640; els.video.videoHeight = 480; els.video.play = async () => {};
    els.canvas.getContext = () => ({ drawImage() {} });
    els.canvas.toDataURL = () => 'data:image/jpeg;base64,SYNTHETIC';
    let stopped = 0, cameraCalls = 0, fetchCalls = [], reloaded = 0;
    const stream = { getTracks: () => [{ stop() { stopped++; } }] };
    const form = { action: '/hr/employees/1/face', dataset: { version: '2' },
        querySelector(s) { if (s.includes('_token')) return { value: 'csrf' }; return els[s.replace('[data-face-', '').replace(']', '')]; },
        addEventListener(name, fn) { this[name] = fn; } };
    const browser = { isSecureContext: true, navigator: { mediaDevices: { getUserMedia: async () => { cameraCalls++; if (options.denied) throw Error(); return options.pending ? options.pending : stream; } } },
        addEventListener(name, fn) { listeners.set(name, fn); }, location: { reload() { reloaded++; } },
        fetch: async (url, request) => { fetchCalls.push(request); if (options.network) throw Error(); return options.response || { ok: true }; } };
    setupFaceEnrollment(form, browser);
    return { els, form, browser, stream, listeners, stats: () => ({ stopped, cameraCalls, fetchCalls, reloaded }) };
}

async function captureThree(f) {
    f.els.consent.checked = true; f.els.consent.change(); await f.els.start.click();
    const oldNow = Date.now; let now = 10000;
    try { Date.now = () => (now += 1100); for (let i = 0; i < 3; i++) f.els.capture.click(); }
    finally { Date.now = oldNow; }
}

test('camera requires consent/action, revocation clears samples and stops tracks', async () => {
    const f = fixture();
    assert.equal(f.stats().cameraCalls, 0); await f.els.start.click(); assert.equal(f.stats().cameraCalls, 0);
    await captureThree(f); assert.equal(f.els.submit.disabled, false);
    f.els.consent.checked = false; f.els.consent.change();
    assert.equal(f.stats().stopped, 1); assert.equal(f.els.submit.disabled, true);
    assert.equal(f.els.canvas.width, 0);
});

test('three samples submit JSON with CSRF and no employee or embedding fields', async () => {
    const f = fixture(); await captureThree(f);
    await f.form.submit({ preventDefault() {} });
    const request = f.stats().fetchCalls[0], body = JSON.parse(request.body);
    assert.equal(body.samples.length, 3); assert.equal(body.enrollment_version, 2); assert.equal(body.consent, true);
    assert.equal(request.headers['X-CSRF-TOKEN'], 'csrf'); assert.equal('employee_id' in body, false);
    assert.equal(f.stats().reloaded, 1); assert.equal(f.els.submit.disabled, true);
});

test('denied or insecure camera never fabricates samples', async () => {
    const f = fixture({ denied: true }); f.els.consent.checked = true;
    await f.els.start.click(); assert.match(f.els.error.textContent, /Kamera tidak dapat/);
    assert.equal(f.els.submit.disabled, true);
    const g = fixture(); g.browser.isSecureContext = false; g.els.consent.checked = true;
    await g.els.start.click(); assert.equal(g.stats().cameraCalls, 0);
});

test('late camera grant is stopped after consent withdrawal', async () => {
    let resolve; const pending = new Promise(r => { resolve = r; });
    const f = fixture({ pending }); f.els.consent.checked = true;
    const opening = f.els.start.click(); f.els.consent.checked = false; f.els.consent.change();
    resolve(f.stream); await opening;
    assert.equal(f.stats().stopped, 1); assert.equal(f.els.video.srcObject, null);
});

test('validation/network failure clears images and pagehide stops camera', async () => {
    for (const options of [{ network: true }, { response: { ok: false, status: 422, json: async () => ({ errors: { samples: ['Wajah blur'] } }) } }]) {
        const f = fixture(options); await captureThree(f); await f.form.submit({ preventDefault() {} });
        assert.equal(f.els.submit.disabled, true); assert.ok(f.els.error.textContent.length > 0);
    }
    const f = fixture(); await captureThree(f); f.listeners.get('pagehide')();
    assert.equal(f.stats().stopped, 1); assert.equal(f.els.submit.disabled, true);
});
