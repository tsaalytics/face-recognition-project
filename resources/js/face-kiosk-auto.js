import { createKioskDetector } from './kiosk-detector.js';
import { KioskScanGate, frameQuality } from './kiosk-scan-gate.js';

export function setupFaceKiosk(form, browser = window, options = {}) {
    const find = (s) => form.querySelector(s), doc = browser.document;
    const video = find('video'), canvas = find('canvas'), status = find('[data-status]'), result = doc.querySelector('[data-result]');
    const toggle = find('[data-kiosk-toggle]'), retry = find('[data-kiosk-retry]') || { hidden: true };
    const popup = doc.querySelector('[data-result-dialog]'), popupRetry = doc.querySelector('[data-result-retry]');
    let popupTimer;
    function showResult(message, success, kind = 'face', late = false) {
        browser.clearTimeout(popupTimer);
        result.textContent = message; result.dataset.tone = success ? 'success' : 'error';
        popup.dataset.tone = result.dataset.tone; popup.dataset.retry = kind;
        doc.querySelector('[data-result-title]').textContent = success ? 'Berhasil Absen' : 'Absensi Gagal';
        doc.querySelector('[data-result-icon]').textContent = success ? '✓' : '×';
        doc.querySelector('[data-result-badge]').hidden = !late;
        popupRetry.hidden = success; popupRetry.textContent = kind === 'reload' ? 'Muat Ulang' : (kind === 'resume' ? 'Lanjutkan Kiosk' : 'Coba Lagi');
        if (!popup.open) popup.showModal();
        if (success) popupTimer = browser.setTimeout(() => popup.close(), 4500);
    }
    const mono = options.now || (() => browser.performance.now());
    const qualityCanvas = doc.createElement('canvas');
    const gate = new KioskScanGate({ stableMs: Number(form.dataset.stableMs), cooldownMs: Number(form.dataset.cooldownMs), absenceMs: Number(form.dataset.absenceMs) });
    let paused = form.dataset.paused === '1', stream = null, detector = null, epoch = 0, busy = false, opening = false, resumeAfterFlight = false;
    let loopTimer, syncTimer, clockTimer, requestTimer, aborter;
    let context = { mode: form.dataset.mode, server_time: form.dataset.serverTime, expires: Number(form.dataset.expires) }, syncedAt = mono();
    const active = () => !paused && !doc.hidden;
    const serverTime = () => Date.parse(context.server_time) + mono() - syncedAt;
    const csrf = () => find('[name="_token"]').value;
    const label = () => { if (toggle) toggle.textContent = paused ? 'Lanjutkan Kiosk' : 'Jeda Kiosk'; };
    const showClock = () => {
        find('[data-kiosk-time]').textContent = new Intl.DateTimeFormat('id-ID', { timeZone: 'Asia/Jakarta', dateStyle: 'medium', timeStyle: 'medium' }).format(new Date(serverTime())) + ' WIB';
        find('[data-kiosk-mode]').textContent = { check_in: 'Check In', check_out: 'Check Out', closed: 'Closed' }[context.mode] || 'Memeriksa jadwal';
    };
    function halt() {
        epoch++; opening = false; resumeAfterFlight = false;
        gate.box = null; gate.stableSince = null; gate.absentSince = null;
        [loopTimer, syncTimer, clockTimer, requestTimer].forEach((t) => browser.clearTimeout(t));
        aborter?.abort(); aborter = null;
        stream?.getTracks().forEach((t) => t.stop()); stream = null; video.srcObject = null;
        detector?.close(); detector = null;
        canvas.width = 0; canvas.height = 0; qualityCanvas.width = 0; qualityCanvas.height = 0;
        find('[data-camera-state]').textContent = 'Camera Inactive';
        result.textContent = '';
        browser.clearTimeout(popupTimer); if (popup.open) popup.close();
    }
    async function sessionRequest(url, method = 'GET') {
        const response = await browser.fetch(url, { method, credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() } });
        if (!response.ok) throw new Error('Akses perangkat atau sesi kiosk tidak tersedia. Muat ulang halaman untuk aktivasi ulang.');
        return response.json();
    }
    async function sync(ticket) {
        let body = await sessionRequest(form.dataset.statusUrl);
        if (ticket !== epoch || !active()) return false;
        if (body.paused) { paused = true; halt(); label(); status.textContent = 'Kiosk dijeda oleh HR.'; showResult(status.textContent, false, 'resume'); return false; }
        if (Number(body.expires) * 1000 - Date.parse(body.server_time) < 120000) body = await sessionRequest(form.dataset.startUrl, 'POST');
        if (ticket !== epoch || !active()) return false;
        context = body; syncedAt = mono(); showClock();
        syncTimer = browser.setTimeout(async () => {
            try { await sync(ticket); } catch (error) { if (ticket === epoch) { halt(); status.textContent = error.message; showResult(error.message, false, 'reload'); } }
        }, 15000);
        return true;
    }
    function clock(ticket) {
        if (ticket !== epoch || !active()) return;
        showClock();
        if (serverTime() >= Number(context.expires) * 1000) { halt(); status.textContent = 'Sesi kiosk berakhir. Muat ulang halaman.'; showResult(status.textContent, false, 'reload'); return; }
        clockTimer = browser.setTimeout(() => clock(ticket), 1000);
    }
    async function recognize(ticket) {
        if (busy || ticket !== epoch || !active()) return;
        busy = true; gate.complete(mono()); status.textContent = 'Memproses recognition…'; result.textContent = '';
        let sample = null, extraCooldown = 0;
        aborter = new browser.AbortController();
        requestTimer = browser.setTimeout(() => aborter?.abort(), 35000);
        try {
            const ratio = Math.min(1, 960 / video.videoWidth, 720 / video.videoHeight);
            canvas.width = Math.round(video.videoWidth * ratio); canvas.height = Math.round(video.videoHeight * ratio);
            canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
            sample = canvas.toDataURL('image/jpeg', .85);
            canvas.width = 0; canvas.height = 0;
            if (!sample.startsWith('data:image/jpeg;base64,') || sample.length > 1400000) throw new Error('Frame kamera tidak valid.');
            const response = await browser.fetch(form.action, { method: 'POST', credentials: 'same-origin', signal: aborter.signal,
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
                body: JSON.stringify({ samples: [sample] }) });
            sample = null;
            let body = {}; try { body = await response.json(); } catch { /* Proxy or maintenance page. */ }
            if (ticket !== epoch || !active()) return;
            if ([401, 403, 419].includes(response.status)) { halt(); status.textContent = 'Akses kiosk berakhir. Muat ulang halaman dan aktifkan kembali perangkat.'; showResult(status.textContent, false, 'reload'); return; }
            if (response.status === 429) extraCooldown = Math.max(6500, Number(response.headers?.get('Retry-After') || 60) * 1000);
            showResult(response.ok ? (body.message || 'Absensi berhasil dicatat.')
                : (Object.values(body.errors || {}).flat()[0] || body.message || 'Sistem pengenalan wajah sedang mengalami gangguan.'), response.ok, 'face', response.ok && body.type === 'check_in' && body.status === 'late');
        } catch {
            if (ticket === epoch && active()) showResult('Proses terputus. Periksa riwayat absensi sebelum mencoba ulang.', false);
        } finally {
            sample = null; canvas.width = 0; canvas.height = 0; busy = false;
            browser.clearTimeout(requestTimer); aborter = null;
            gate.complete(mono(), extraCooldown);
            if (resumeAfterFlight && active()) { resumeAfterFlight = false; browser.setTimeout(start, 0); }
        }
    }
    async function tick(ticket) {
        if (ticket !== epoch || !active() || !stream || !detector) return;
        try {
            if (context.mode === 'closed') status.textContent = 'Absensi belum tersedia. Silakan ikuti jadwal yang ditentukan.';
            else if (popup.open) status.textContent = 'Lihat hasil absensi pada popup.';
            else if (!busy && video.videoWidth && video.videoHeight && video.readyState >= 2) {
                const detected = detector.detectForVideo(video, mono()).detections;
                const faces = detected.map(({ boundingBox: b }) => ({ x: b.originX / video.videoWidth, y: b.originY / video.videoHeight, width: b.width / video.videoWidth, height: b.height / video.videoHeight }));
                const quality = faces.length !== 1 || (options.quality || frameQuality)(video, qualityCanvas, faces[0]);
                const decision = gate.observe(faces, mono(), quality);
                status.textContent = decision.message;
                if (decision.ready) await recognize(ticket);
            }
        } catch { halt(); status.textContent = 'Detektor wajah tidak tersedia. Periksa aset lokal lalu coba lagi.'; retry.hidden = false; showResult(status.textContent, false, 'camera'); }
        if (ticket === epoch && active() && stream) loopTimer = browser.setTimeout(() => tick(ticket), Number(form.dataset.pollMs) || 250);
    }
    async function start() {
        if (!active() || opening || stream || busy) return;
        opening = true; const ticket = ++epoch; retry.hidden = true; status.textContent = 'Menyiapkan kamera dan detektor lokal…';
        try {
            if (!browser.isSecureContext || !browser.navigator.mediaDevices?.getUserMedia) throw new Error('Kamera memerlukan HTTPS atau localhost dan browser yang mendukung webcam.');
            if (!await sync(ticket)) return;
            const next = await browser.navigator.mediaDevices.getUserMedia({ video: { width: { ideal: 960 }, height: { ideal: 720 }, facingMode: 'user' }, audio: false });
            if (ticket !== epoch || !active()) { next.getTracks().forEach((t) => t.stop()); return; }
            stream = next; video.srcObject = next; await video.play();
            next.getVideoTracks?.()[0]?.addEventListener('ended', () => { if (stream === next) { halt(); status.textContent = 'Kamera terputus atau izin dicabut.'; showResult(status.textContent, false, 'camera'); } });
            if (ticket !== epoch) return;
            const loaded = await (options.detectorFactory || createKioskDetector)(form.dataset.detectorBase);
            if (ticket !== epoch || !active()) { loaded.close(); return; }
            detector = loaded;
            find('[data-camera-state]').textContent = 'Camera Active';
            clock(ticket); await tick(ticket);
        } catch (error) {
            if (ticket === epoch) { halt(); status.textContent = error.name === 'NotAllowedError' ? 'Izin kamera ditolak. Izinkan kamera di browser, lalu coba lagi.' : (error.message || 'Kamera tidak dapat diakses.'); retry.hidden = false; showResult(status.textContent, false, 'camera'); }
        } finally { if (ticket === epoch) opening = false; }
    }
    async function changePaused() {
        if (toggle) toggle.disabled = true;
        try {
            if (paused) { await sessionRequest(form.dataset.startUrl, 'POST'); paused = false; label(); if (busy) resumeAfterFlight = true; else await start(); }
            else { paused = true; halt(); label(); status.textContent = 'Kiosk dijeda. Kamera dan scanning berhenti.'; await sessionRequest(form.dataset.stopUrl, 'POST'); }
        } catch (error) { status.textContent = error.message; retry.hidden = false; }
        finally { if (toggle) toggle.disabled = false; }
    }
    toggle?.addEventListener('click', changePaused);
    retry.addEventListener?.('click', start);
    doc.querySelector('[data-result-close]').addEventListener('click', () => popup.close());
    popup.addEventListener('close', () => browser.clearTimeout(popupTimer));
    popupRetry.addEventListener('click', () => {
        const kind = popup.dataset.retry;
        popup.close();
        if (kind === 'reload') browser.location.reload();
        else if (kind === 'resume') changePaused();
        else if (kind === 'camera') start();
        else { gate.retry(); status.textContent = 'Siap mencoba lagi. Tahan posisi wajah; cooldown tetap berlaku.'; }
    });
    form.addEventListener('submit', (event) => event.preventDefault());
    doc.addEventListener('visibilitychange', () => { if (doc.hidden) halt(); else if (!paused) { if (busy) resumeAfterFlight = true; else start(); } });
    browser.addEventListener('pagehide', halt);
    browser.addEventListener('pageshow', () => { if (!paused) { if (busy) resumeAfterFlight = true; else start(); } });
    label(); showClock();
    if (paused) { status.textContent = 'Kiosk dijeda oleh HR.'; showResult(status.textContent, false, 'resume'); }
    const ready = paused ? Promise.resolve() : start();
    return { ready, halt };
}
