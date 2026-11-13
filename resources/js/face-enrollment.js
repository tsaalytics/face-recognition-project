export function setupFaceEnrollment(form, browser = window) {
    const find = (s) => form.querySelector(s);
    const consent = find('[data-face-consent]'), video = find('[data-face-video]'), canvas = find('[data-face-canvas]');
    const start = find('[data-face-start]'), capture = find('[data-face-capture]'), clear = find('[data-face-clear]'), submit = find('[data-face-submit]');
    const status = find('[data-face-status]'), errors = find('[data-face-error]');
    let stream = null, samples = [], generation = 0, busy = false, lastCapture = 0;
    const error = (text) => { errors.textContent = text; errors.classList.remove('hidden'); errors.focus(); };
    const stop = () => { stream?.getTracks().forEach((t) => t.stop()); stream = null; video.srcObject = null; capture.disabled = true; };
    const refresh = () => {
        submit.disabled = busy || !consent.checked || samples.length < 3;
        capture.disabled = busy || !stream || !consent.checked || samples.length >= 5;
        start.disabled = busy || !consent.checked; clear.disabled = busy; consent.disabled = busy;
        submit.textContent = busy ? 'Memproses sampel...' : 'Simpan enrollment';
    };
    const reset = () => {
        generation++; stop(); samples = []; lastCapture = 0;
        canvas.width = 0; canvas.height = 0;
        status.textContent = 'Belum ada sampel. Kamera belum aktif.'; refresh();
    };
    consent.addEventListener('change', () => { if (!consent.checked) reset(); refresh(); });
    clear.addEventListener('click', reset);
    start.addEventListener('click', async () => {
        if (busy || !consent.checked) return;
        if (!browser.isSecureContext || !browser.navigator.mediaDevices?.getUserMedia) return error('Kamera tidak tersedia. Gunakan HTTPS atau localhost.');
        generation++; const ticket = generation; stop(); start.disabled = true;
        try {
            const next = await browser.navigator.mediaDevices.getUserMedia({ video: { width: { ideal: 960 }, height: { ideal: 720 }, facingMode: 'user' }, audio: false });
            if (ticket !== generation || !consent.checked) { next.getTracks().forEach((t) => t.stop()); return; }
            stream = next; video.srcObject = stream; await video.play();
            if (ticket !== generation || !consent.checked) return;
            status.textContent = `${samples.length}/5 sampel. Hadapkan wajah ke kamera, lalu ambil sampel dengan variasi ringan.`;
        } catch { if (ticket === generation) { stop(); error('Kamera tidak dapat diakses. Periksa izin perangkat atau hubungi Admin.'); } }
        finally { refresh(); }
    });
    capture.addEventListener('click', () => {
        if (busy || !stream || !consent.checked || samples.length >= 5) return;
        if (lastCapture && Date.now() - lastCapture < 1000) return error('Tunggu satu detik dan ubah posisi wajah sedikit.');
        if (!video.videoWidth || !video.videoHeight) return error('Kamera belum siap. Tunggu preview tampil.');
        try {
            const ratio = Math.min(1, 960 / video.videoWidth, 720 / video.videoHeight);
            canvas.width = Math.round(video.videoWidth * ratio); canvas.height = Math.round(video.videoHeight * ratio);
            canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
            const sample = canvas.toDataURL('image/jpeg', 0.85);
            if (!sample.startsWith('data:image/jpeg;base64,') || sample.length > 1400000) throw new Error();
            samples.push(sample); lastCapture = Date.now(); canvas.width = 0; canvas.height = 0;
            status.textContent = `${samples.length}/5 sampel. Minimal 3, maksimal 5; gunakan variasi posisi ringan.`;
            if (samples.length === 5) stop(); refresh();
        } catch { error('Sampel gagal diambil. Coba ulangi kamera.'); }
    });
    form.addEventListener('submit', async (event) => {
        event.preventDefault(); if (busy || !consent.checked || samples.length < 3) return;
        busy = true; stop(); refresh(); errors.classList.add('hidden');
        try {
            const response = await browser.fetch(form.action, { method: 'POST', credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': find('[name="_token"]').value },
                body: JSON.stringify({ consent: true, enrollment_version: Number(form.dataset.version), samples }) });
            if (response.ok) { samples = []; browser.location.reload(); return; }
            if (response.status === 409) { error('Enrollment telah berubah. Muat ulang halaman sebelum mencoba kembali.'); return; }
            if (response.status === 419 || response.status === 401) { error('Sesi formulir berakhir. Muat ulang halaman dan login kembali.'); return; }
            let body = null; try { body = await response.json(); } catch { /* Proxy/maintenance error. */ }
            error(body?.errors ? Object.values(body.errors).flat().join(' ') : (body?.message || 'Enrollment gagal. Periksa status sebelum mencoba kembali.'));
        } catch { error('Koneksi terputus; status penyimpanan belum pasti. Muat ulang halaman dan periksa enrollment sebelum mencoba lagi.'); }
        finally { busy = false; reset(); }
    });
    browser.addEventListener('pagehide', reset);
    browser.addEventListener('pageshow', () => { consent.checked = false; reset(); });
    refresh();
}
