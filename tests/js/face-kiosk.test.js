import test from 'node:test';
import assert from 'node:assert/strict';
import { setupFaceKiosk } from '../../resources/js/face-kiosk.js';
import { KioskScanGate, frameQuality } from '../../resources/js/kiosk-scan-gate.js';

const face = { x: .3, y: .2, width: .3, height: .4 };
const detection = { boundingBox: { originX: 192, originY: 96, width: 192, height: 192 } };
function fixture(options = {}) {
    let now = 0, timerId = 0, stopped = 0, cameraCalls = 0, closed = 0, detections = [detection];
    const timers = new Map(), scans = [];
    const make = () => ({ textContent: '', hidden: false, disabled: false, open: false, value: 'csrf', dataset: {},
        showModal() { this.open = true; }, close() { this.open = false; this.onClose?.(); },
        addEventListener(name, fn) { if (name === 'close') this.onClose = fn; else this[name] = fn; } });
    const keys = ['video', 'canvas', 'status', 'result', 'kiosk-toggle', 'kiosk-retry', 'kiosk-time', 'kiosk-mode', 'camera-state', 'token', 'result-dialog', 'result-retry', 'result-close', 'result-title', 'result-icon', 'result-badge'];
    const els = Object.fromEntries(keys.map(k => [k, make()]));
    els.video.videoWidth = 640; els.video.videoHeight = 480; els.video.readyState = 4; els.video.play = async () => {};
    els.canvas.getContext = () => ({ drawImage() {} }); els.canvas.toDataURL = () => 'data:image/jpeg;base64,SYNTHETIC';
    const stream = { getTracks: () => [{ stop() { stopped++; } }] };
    const form = { action: '/scan', dataset: { expires: '1794782100', paused: options.paused ? '1' : '0', mode: 'check_in', serverTime: '2026-11-16T08:00:00+07:00',
        statusUrl: '/status', startUrl: '/start', stopUrl: '/stop', detectorBase: '/vendor/kiosk-face-detector', pollMs: '250', stableMs: '1000', cooldownMs: '6500', absenceMs: '1200' },
        querySelector(s) { if (s.includes('_token')) return els.token; return els[s.replace('[data-', '').replace(']', '')]; },
        addEventListener(name, fn) { this[name] = fn; } };
    const context = () => ({ mode: options.mode || 'check_in', paused: false, expires: Date.parse('2026-11-16T08:15:00+07:00') / 1000, server_time: '2026-11-16T08:00:00+07:00' });
    const browser = { document: { hidden: false, createElement: make, querySelector: (s) => form.querySelector(s), addEventListener(name, fn) { this[name] = fn; } },
        isSecureContext: true, performance: { now: () => now }, AbortController,
        navigator: { mediaDevices: { getUserMedia: async () => { cameraCalls++; if (options.denied) { const e = new Error(); e.name = 'NotAllowedError'; throw e; } return options.camera || stream; } } },
        addEventListener(name, fn) { this[name] = fn; },
        setTimeout(fn, ms) { const id = ++timerId; timers.set(id, { fn, ms, at: now }); return id; }, clearTimeout(id) { timers.delete(id); },
        fetch: async (url, request) => {
            if (url !== '/scan') return { ok: true, json: async () => context() };
            scans.push(request);
            if (options.network) throw new TypeError('Network failed');
            return options.pending || { ok: !options.failed, status: options.failed ? 422 : 200, json: async () => options.failed ? { message: 'Unknown face' } : { message: 'OK', employee: 'TEST', type: 'check_in', time: '08:00' } };
        } };
    const api = setupFaceKiosk(form, browser, { detectorFactory: async () => ({ detectForVideo: () => ({ detections }), close() { closed++; } }), quality: () => true });
    async function tick(at) {
        now = at;
        for (const [id, timer] of timers) if (timer.ms === 4500 && timer.at + timer.ms <= now) { timers.delete(id); timer.fn(); }
        const item = [...timers].find(([, timer]) => timer.ms === 250);
        assert.ok(item, 'a scan poll should be scheduled'); timers.delete(item[0]);
        return item[1].fn();
    }
    return { api, els, browser, form, stream, tick, timers, setFaces(v) { detections = v; }, stats: () => ({ stopped, cameraCalls, closed, scans }) };
}

test('kiosk automatically opens camera and one stable face sends exactly one transient frame', async () => {
    const f = fixture(); await f.api.ready;
    assert.equal(f.stats().cameraCalls, 1); assert.equal(f.stats().scans.length, 0);
    await f.tick(500); await f.tick(1000);
    const body = JSON.parse(f.stats().scans[0].body);
    assert.equal(body.samples.length, 1); assert.equal('consent' in body, false);
    assert.equal(f.els['result-dialog'].open, true);
    assert.equal(f.els['result-title'].textContent, 'Berhasil Absen');
    assert.equal('type' in body, false); assert.equal('employee_id' in body, false); assert.equal('timestamp' in body, false);
    assert.equal(f.els.canvas.width, 0);
    await f.tick(8000); await f.tick(12000);
    assert.equal(f.stats().scans.length, 1, 'remaining in frame must not cause another request');
    f.setFaces([]); await f.tick(13000); await f.tick(14500);
    f.setFaces([detection]); await f.tick(15000); await f.tick(16000);
    assert.equal(f.stats().scans.length, 2);
});

test('multiple faces and closed hours never submit recognition frames', async () => {
    const f = fixture(); await f.api.ready; f.setFaces([detection, detection]); await f.tick(2000); await f.tick(4000);
    assert.equal(f.stats().scans.length, 0);
    assert.match(f.els.status.textContent, /hanya satu orang/);
    const closed = fixture({ mode: 'closed' }); await closed.api.ready; await closed.tick(2000);
    assert.equal(closed.stats().scans.length, 0);
});

test('cooldown after failure still requires a full face departure and then resumes', async () => {
    const f = fixture({ failed: true }); await f.api.ready; await f.tick(1000);
    assert.equal(f.stats().scans.length, 1); assert.equal(f.els.result.textContent, 'Unknown face');
    f.els['result-close'].click();
    await f.tick(10000); assert.equal(f.stats().scans.length, 1);
    f.setFaces([]); await f.tick(11000); await f.tick(12500);
    f.setFaces([detection]); await f.tick(13000); await f.tick(14000);
    assert.equal(f.stats().scans.length, 2);
});

test('recognition requests never overlap and hiding a page stops tracks and suppresses late results', async () => {
    let finish; const pending = new Promise(r => { finish = r; });
    const f = fixture({ pending }); await f.api.ready;
    const processing = f.tick(1000);
    assert.equal(f.stats().scans.length, 1);
    assert.equal([...f.timers.values()].some(t => t.ms === 250), false);
    f.browser.document.hidden = true; f.browser.document.visibilitychange();
    assert.equal(f.stats().stopped, 1);
    finish({ ok: true, status: 200, json: async () => ({ message: 'SHOULD NOT APPEAR' }) }); await processing;
    assert.equal(f.els.result.textContent, ''); assert.equal(f.els.video.srcObject, null);
});

test('late permission grant after page hide stops the unused stream', async () => {
    let grant; const camera = new Promise(r => { grant = r; });
    const f = fixture({ camera });
    await new Promise(resolve => setImmediate(resolve));
    f.browser.document.hidden = true; f.browser.document.visibilitychange();
    grant(f.stream); await f.api.ready;
    assert.equal(f.stats().stopped, 1); assert.equal(f.stats().scans.length, 0);
});

test('denied camera has an explicit retry control; a paused kiosk never opens camera automatically', async () => {
    const f = fixture({ denied: true }); await f.api.ready;
    assert.equal(f.els['kiosk-retry'].hidden, false); assert.match(f.els.status.textContent, /Izin kamera ditolak/);
    const paused = fixture({ paused: true }); await paused.api.ready;
    assert.equal(paused.stats().cameraCalls, 0);
});

test('network uncertainty does not retry until the face has left', async () => {
    const f = fixture({ network: true }); await f.api.ready; await f.tick(1000); await f.tick(10000);
    assert.match(f.els.result.textContent, /Periksa riwayat/); assert.equal(f.stats().scans.length, 1);
});

test('gate rejects moving, small, dark and multiple faces and ignores brief absence', () => {
    const gate = new KioskScanGate();
    assert.equal(gate.observe([face], 0).ready, false);
    assert.equal(gate.observe([{ ...face, x: .5 }], 1000).ready, false);
    assert.equal(gate.observe([face, face], 2000).ready, false);
    assert.equal(gate.observe([{ ...face, width: .1 }], 3000).ready, false);
    assert.equal(gate.observe([face], 4000, false).ready, false);
    assert.equal(gate.observe([face], 5000).ready, false);
    assert.equal(gate.observe([face], 6100).ready, true);
    gate.complete(6100); gate.observe([], 15000); gate.observe([face], 15500);
    assert.equal(gate.observe([face], 18000).ready, false);
});

test('exposure check clears its scratch pixels and rejects flat frames', () => {
    const canvas = { getContext: () => ({ drawImage() {}, getImageData: () => ({ data: new Uint8ClampedArray(96 * 96 * 4).fill(100) }) }) };
    assert.equal(frameQuality({ videoWidth: 640, videoHeight: 480 }, canvas, face), false);
    assert.equal(canvas.width, 0); assert.equal(canvas.height, 0);
});

test('failure popup retry re-arms the same face but cannot bypass cooldown', async () => {
    const f = fixture({ failed: true }); await f.api.ready; await f.tick(1000);
    assert.equal(f.els['result-dialog'].open, true);
    assert.equal(f.els['result-title'].textContent, 'Absensi Gagal');
    f.els['result-retry'].click();
    assert.equal(f.els['result-dialog'].open, false);
    await f.tick(2000); assert.equal(f.stats().scans.length, 1);
    await f.tick(8000); await f.tick(9100);
    assert.equal(f.stats().scans.length, 2);
});

test('pause stops the camera, and resume starts automatically', async () => {
    const f = fixture(); await f.api.ready;
    await f.els['kiosk-toggle'].click();
    assert.equal(f.stats().stopped, 1);
    await f.els['kiosk-toggle'].click();
    assert.equal(f.stats().cameraCalls, 2); assert.equal(f.stats().scans.length, 0);
});
