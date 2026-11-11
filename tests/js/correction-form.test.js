
import test from 'node:test';
import assert from 'node:assert/strict';
import { setupCorrectionForm } from '../../resources/js/correction-form.js';

function harness({cameraError = null, locationError = null, secure = true, responses = []} = {}) {
    const elements = new Map();
    const element = () => ({
        checked: false, disabled: false, textContent: '', src: null,
        classList: { values: new Set(), add(v) { this.values.add(v); }, remove(v) { this.values.delete(v); } },
        handlers: new Map(), addEventListener(type, fn) { this.handlers.set(type, fn); },
        async fire(type) { return this.handlers.get(type)?.({preventDefault() {}}); },
        focus() {}, removeAttribute(name) { this[name] = null; },
    });
    const names = ['camera-video','photo-preview','photo-canvas','camera-status','location-status','correction-errors','privacy-consent','start-camera','capture-photo','stop-camera','get-location','correction-submit'];
    names.forEach((name) => elements.set(name, element()));
    const form = element();
    form.querySelector = (selector) => elements.get(selector.slice(6, -1));
    form.reportValidity = () => true;
    form.action = '/employee/corrections';
    const video = elements.get('camera-video');
    video.videoWidth = 640; video.videoHeight = 480; video.play = async () => {};
    const canvas = elements.get('photo-canvas');
    let draws = 0;
    canvas.getContext = () => ({drawImage() { draws++; }});
    canvas.toBlob = (done) => done(new Blob(['camera frame'], {type: 'image/jpeg'}));
    let cameraCalls = 0, locationCalls = 0, stopped = 0, assigned = null;
    let pendingLocation = null;
    const fetches = [];
    const browser = {
        isSecureContext: secure,
        navigator: {
            mediaDevices: {getUserMedia: async () => {
                cameraCalls++;
                if (cameraError) throw Object.assign(new Error('denied'), {name: cameraError});
                return {getTracks: () => [{stop() { stopped++; }}]};
            }},
            geolocation: {getCurrentPosition(ok, fail, options) {
                locationCalls++;
                pendingLocation = {ok, fail, options};
                if (locationError) fail({code: locationError});
            }},
        },
        URL: {createObjectURL: () => 'blob:test', revokeObjectURL() {}},
        FormData: class extends Map { constructor() { super(); } },
        fetch: async (url, options) => {
            fetches.push({url, ...options});
            const response = responses.shift() || {status: 201, ok: true, json: async () => ({redirect: '/employee/corrections/42'})};
            if (response instanceof Error) throw response;
            return response;
        },
        location: {assign(url) { assigned = url; }},
        addEventListener() {},
    };
    setupCorrectionForm(form, browser);
    return {
        form, browser, elements, fetches,
        get: (name) => elements.get(name),
        fire: (name, type = 'click') => elements.get(name).fire(type),
        consent: async (value = true) => { elements.get('privacy-consent').checked = value; await elements.get('privacy-consent').fire('change'); },
        grantLocation: () => pendingLocation.ok({coords: {latitude: -6.2, longitude: 106.8, accuracy: 25}}),
        state: () => ({cameraCalls, locationCalls, stopped, assigned, draws, pendingLocation}),
    };
}

test('camera/location never run before explicit consent and action', async () => {
    const h = harness();
    assert.equal(h.state().cameraCalls, 0);
    assert.equal(h.state().locationCalls, 0);
    await h.fire('start-camera'); await h.fire('get-location');
    await h.form.fire('submit');
    assert.equal(h.state().cameraCalls, 0);
    assert.equal(h.state().locationCalls, 0);
    assert.equal(h.fetches.length, 0);
    assert.equal(h.get('correction-submit').disabled, true);
});

test('camera denial explains HR alternative and cannot fabricate a photo', async () => {
    const h = harness({cameraError: 'NotAllowedError'});
    await h.consent(); await h.fire('start-camera');
    await h.fire('get-location'); h.grantLocation();
    await h.form.fire('submit');
    assert.equal(h.get('correction-submit').disabled, true);
    assert.equal(h.state().draws, 0);
    assert.equal(h.fetches.length, 0);
    // The camera explanation was displayed before submit's missing-evidence summary.
    assert.match(h.get('camera-status').textContent, /belum tersedia/);
    assert.match(h.get('correction-errors').textContent, /Hubungi HR/);
});

test('location denial never substitutes coordinates or sends evidence', async () => {
    const h = harness({locationError: 1});
    await h.consent(); await h.fire('start-camera'); await h.fire('capture-photo');
    await h.fire('get-location');
    assert.match(h.get('correction-errors').textContent, /Izin lokasi ditolak/);
    assert.equal(h.get('correction-submit').disabled, true);
    await h.form.fire('submit');
    assert.equal(h.fetches.length, 0);
    assert.ok(h.state().stopped > 0);
});

test('live capture and granted location send multipart evidence with server validation feedback', async () => {
    const h = harness({responses: [
        {status: 422, ok: false, json: async () => ({errors: {reason: ['Alasan wajib diperjelas.']}})},
    ]});
    await h.consent(); await h.fire('start-camera'); await h.fire('capture-photo');
    await h.fire('get-location'); h.grantLocation();
    assert.equal(h.get('correction-submit').disabled, false);
    assert.equal(h.state().pendingLocation.options.maximumAge, 0);
    assert.equal(h.state().pendingLocation.options.timeout, 15000);
    await h.form.fire('submit');
    assert.match(h.get('correction-errors').textContent, /Alasan wajib/);
    assert.equal(h.get('correction-submit').disabled, false);
    const sent = h.fetches[0];
    assert.equal(sent.method, 'POST');
    assert.equal(sent.body.get('latitude'), '-6.2');
    assert.equal(sent.body.get('longitude'), '106.8');
    assert.equal(sent.body.get('photo').type, 'image/jpeg');
    assert.ok(Date.parse(sent.body.get('captured_at')));
    assert.equal(sent.headers.Accept, 'application/json');
    await h.form.fire('submit');
    assert.equal(h.state().assigned, '/employee/corrections/42');
    assert.equal(h.get('correction-submit').disabled, true);
});

test('revoked consent clears evidence and ignores late location callbacks', async () => {
    const h = harness();
    await h.consent(); await h.fire('start-camera'); await h.fire('capture-photo');
    await h.fire('get-location');
    await h.consent(false);
    h.grantLocation();
    assert.equal(h.get('correction-submit').disabled, true);
    assert.equal(h.get('location-status').textContent, 'Belum ada koordinat.');
    await h.form.fire('submit');
    assert.equal(h.fetches.length, 0);
});

test('insecure context gives clear fallback without requesting sensors', async () => {
    const h = harness({secure: false});
    await h.consent(); await h.fire('start-camera');
    assert.match(h.get('correction-errors').textContent, /HTTPS atau localhost/);
    await h.fire('get-location');
    assert.equal(h.state().cameraCalls, 0);
    assert.equal(h.state().locationCalls, 0);
    assert.equal(h.fetches.length, 0);
});

test('network uncertainty tells employee to check existing requests before retry', async () => {
    const h = harness({responses: [new TypeError('Failed to fetch')]});
    await h.consent(); await h.fire('start-camera'); await h.fire('capture-photo');
    await h.fire('get-location'); h.grantLocation();
    await h.form.fire('submit');
    assert.match(h.get('correction-errors').textContent, /Periksa daftar permohonan/);
    assert.equal(h.get('correction-submit').disabled, false);
});
