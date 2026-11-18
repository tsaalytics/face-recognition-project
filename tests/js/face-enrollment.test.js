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
    let stopped = 0, cameraCalls = 0, fetchCalls = [], urls = [], reloaded = 0;
    const stream = { getTracks: () => [{ stop() { stopped++; } }] };
    const form = { action: '/hr/employees/1/face', dataset: { version: '2' },
        querySelector(s) { if (s.includes('_token')) return { value: 'csrf' }; return els[s.replace('[data-face-', '').replace(']', '')]; },
        addEventListener(name, fn) { this[name] = fn; } };
    const browser = { isSecureContext: true, navigator: { mediaDevices: { getUserMedia: async () => { cameraCalls++; if (options.denied) throw Error(); return options.pending ? options.pending : stream; } } },
        addEventListener(name, fn) { listeners.set(name, fn); }, location: { reload() { reloaded++; } },
        fetch: async (url, request) => { urls.push(url); fetchCalls.push(request); if (options.network) throw Error(); return options.handler ? options.handler(url, request) : (options.response || { ok: true }); } };
    let profile = null;
    if (options.integrated) {
        const fields = { '[name="_token"]': { value: 'csrf' }, '[name="_method"]': { value: 'POST' }, '#business_id': { value: '' } };
        profile = { action: '/hr/employees', reportValidity: () => !options.invalidProfile, querySelector: (s) => fields[s] };
        form.dataset.profileForm = 'employee-profile'; form.dataset.profilePersisted = options.persisted ? '1' : '0';
        browser.document = { getElementById: () => profile, querySelector: () => make() };
        browser.FormData = class { constructor(source) { this.source = source; } };
        browser.history = { replaceState() {} };
    }
    setupFaceEnrollment(form, browser);
    return { els, form, browser, profile, stream, listeners, stats: () => ({ stopped, cameraCalls, fetchCalls, urls, reloaded }) };
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

const savedProfile = { update_url: '/hr/employees/7', edit_url: '/hr/employees/7/edit', enrollment_url: '/hr/employees/7/face', business_id: '123420000517', enrollment_version: 0 };

test('integrated creation saves profile before AI and keeps retry bound to the saved employee after AI failure', async () => {
    const f = fixture({ integrated: true, handler: async (url) => url.endsWith('/face')
        ? { ok: false, status: 503, json: async () => ({ message: 'AI tidak tersedia' }) }
        : { ok: true, json: async () => savedProfile } });
    await captureThree(f);
    await f.form.submit({ preventDefault() {} });
    assert.deepEqual(f.stats().urls, ['/hr/employees', '/hr/employees/7/face']);
    assert.equal(f.profile.action, '/hr/employees/7');
    assert.equal(f.profile.querySelector('[name="_method"]').value, 'PUT');
    assert.equal(f.profile.querySelector('#business_id').value, savedProfile.business_id);
    assert.equal(f.form.dataset.profilePersisted, '1');
    assert.equal(f.els.error.textContent, 'AI tidak tersedia');
    assert.equal(f.profile.inert, false);
    assert.equal(f.stats().reloaded, 0);
    await captureThree(f);
    await f.form.submit({ preventDefault() {} });
    assert.equal(f.stats().urls[2], '/hr/employees/7');
});

test('invalid profile or server validation prevents any enrollment request', async () => {
    const invalid = fixture({ integrated: true, invalidProfile: true });
    await captureThree(invalid); await invalid.form.submit({ preventDefault() {} });
    assert.equal(invalid.stats().fetchCalls.length, 0);
    const denied = fixture({ integrated: true, handler: async () => ({ ok: false, status: 422, json: async () => ({ errors: { email: ['Email sudah digunakan'] } }) }) });
    await captureThree(denied); await denied.form.submit({ preventDefault() {} });
    assert.deepEqual(denied.stats().urls, ['/hr/employees']);
    assert.match(denied.els.error.textContent, /Email sudah digunakan/);
    assert.equal(denied.profile.action, '/hr/employees');
});

test('saving an existing profile never upgrades a stale enrollment version before re-enrolling', async () => {
    const f = fixture({ integrated: true, persisted: true, handler: async (url) => url.endsWith('/face')
        ? { ok: false, status: 409 }
        : { ok: true, json: async () => ({ ...savedProfile, enrollment_version: 9 }) } });
    await captureThree(f); await f.form.submit({ preventDefault() {} });
    assert.equal(JSON.parse(f.stats().fetchCalls[1].body).enrollment_version, 2);
    assert.match(f.els.error.textContent, /Enrollment telah berubah/);
});
