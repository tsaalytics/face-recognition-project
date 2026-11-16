export function setupFaceKiosk(form, browser = window) {
    const find = (selector) => form.querySelector(selector);
    const consent = find('[data-consent]'), video = find('video'), canvas = find('canvas');
    const start = find('[data-start]'), scan = find('[data-scan]'), status = find('[data-status]');
    let stream = null, generation = 0, busy = false, expired = false;
    const stop = () => {
        generation++;
        stream?.getTracks().forEach((track) => track.stop());
        stream = null; video.srcObject = null; canvas.width = 0; canvas.height = 0;
        scan.disabled = true;
    };
    const refresh = () => {
        start.disabled = busy || expired || !consent.checked;
        scan.disabled = busy || expired || !consent.checked || !stream;
        consent.disabled = busy;
        find('[name="type"]').disabled = busy;
    };
    consent.addEventListener('change', () => { stop(); refresh(); });
    browser.addEventListener('pagehide', stop);
    browser.setTimeout(() => { expired = true; stop(); refresh(); status.textContent = 'Sesi kiosk berakhir. Muat ulang untuk mengaktifkan kembali.'; }, Math.max(0, Number(form.dataset.expires) * 1000 - Date.now()));
    start.addEventListener('click', async () => {
        if (busy || expired || !consent.checked) return;
        stop(); const ticket = generation; start.disabled = true;
        try {
            if (!browser.isSecureContext || !browser.navigator.mediaDevices?.getUserMedia) throw new Error();
            const next = await browser.navigator.mediaDevices.getUserMedia({ video: { width: { ideal: 960 }, height: { ideal: 720 }, facingMode: 'user' }, audio: false });
            if (ticket !== generation || expired || !consent.checked) { next.getTracks().forEach((track) => track.stop()); return; }
            stream = next; video.srcObject = next; await video.play();
            status.textContent = 'Satu orang di depan kamera. Pilih jenis absensi lalu scan.';
        } catch { stop(); status.textContent = 'Kamera tidak tersedia. Periksa izin dan gunakan HTTPS atau localhost.'; }
        finally { refresh(); }
    });
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (busy || expired || !consent.checked || !stream || !video.videoWidth || !video.videoHeight) return;
        busy = true; refresh();
        let sample = null;
        try {
            const ratio = Math.min(1, 960 / video.videoWidth, 720 / video.videoHeight);
            canvas.width = Math.round(video.videoWidth * ratio); canvas.height = Math.round(video.videoHeight * ratio);
            canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
            sample = canvas.toDataURL('image/jpeg', 0.85);
            stop();
            const response = await browser.fetch(form.action, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': find('[name="_token"]').value }, body: JSON.stringify({ consent: true, type: find('[name="type"]').value, samples: [sample] }) });
            sample = null;
            const body = await response.json();
            status.textContent = response.ok ? `${body.message} ${body.employee} / ${body.type} / ${body.time}` : (Object.values(body.errors || {}).flat()[0] || body.message || 'Scan gagal.');
        } catch { status.textContent = 'Proses terputus. Periksa riwayat absensi sebelum mencoba ulang.'; }
        finally { sample = null; stop(); busy = false; consent.checked = false; refresh(); }
    });
    refresh();
}
