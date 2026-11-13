
export function setupCorrectionForm(form, browser = window) {
    const find = (selector) => form.querySelector(selector);
    const video = find('[data-camera-video]');
    const preview = find('[data-photo-preview]');
    const canvas = find('[data-photo-canvas]');
    const cameraStatus = find('[data-camera-status]');
    const locationStatus = find('[data-location-status]');
    const errors = find('[data-correction-errors]');
    const consent = find('[data-privacy-consent]');
    const start = find('[data-start-camera]');
    const capture = find('[data-capture-photo]');
    const stop = find('[data-stop-camera]');
    const locate = find('[data-get-location]');
    const submit = find('[data-correction-submit]');
    let stream = null, photo = null, photoUrl = null, capturedAt = null;
    let coordinates = null, locatedAt = null, generation = 0, locationVersion = 0, sending = false;

    const error = (message) => {
        errors.textContent = message;
        errors.classList.remove('hidden');
        errors.focus();
    };
    const refresh = () => {
        submit.disabled = sending || !consent.checked || !photo || !coordinates;
        submit.textContent = sending ? 'Mengirim...' : 'Kirim untuk ditinjau HR';
    };
    const stopCamera = () => {
        stream?.getTracks().forEach((track) => track.stop());
        stream = null;
        video.srcObject = null;
        video.classList.add('hidden');
        capture.disabled = true;
    };
    const clearPhoto = () => {
        generation++;
        stopCamera();
        photo = null; capturedAt = null;
        if (photoUrl) browser.URL.revokeObjectURL(photoUrl);
        photoUrl = null;
        preview.removeAttribute('src');
        preview.classList.add('hidden');
        cameraStatus.textContent = 'Belum ada foto.';
        refresh();
    };
    const clearEvidence = () => {
        clearPhoto();
        locationVersion++;
        coordinates = null; locatedAt = null;
        locationStatus.textContent = 'Belum ada koordinat.';
        locate.disabled = false;
        refresh();
    };
    consent.addEventListener('change', () => {
        if (!consent.checked) clearEvidence();
        refresh();
    });
    start.addEventListener('click', async () => {
        if (!consent.checked) return error('Baca informasi privasi dan centang persetujuan terlebih dahulu.');
        clearPhoto();
        if (!browser.isSecureContext || !browser.navigator.mediaDevices?.getUserMedia) {
            return error('Kamera browser tidak tersedia. Gunakan HTTPS atau localhost dan perangkat berkamera. Hubungi HR untuk prosedur alternatif.');
        }
        const ticket = generation;
        start.disabled = true;
        cameraStatus.textContent = 'Meminta izin kamera...';
        try {
            const media = await browser.navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false });
            if (ticket !== generation || !consent.checked) {
                media.getTracks().forEach((track) => track.stop());
                return;
            }
            stream = media;
            video.srcObject = media;
            video.classList.remove('hidden');
            await video.play();
            if (ticket !== generation || !consent.checked) return;
            capture.disabled = false;
            cameraStatus.textContent = 'Kamera aktif. Tekan Ambil foto.';
        } catch (cause) {
            stopCamera();
            cameraStatus.textContent = 'Kamera belum tersedia.';
            error(cause.name === 'NotAllowedError' || cause.name === 'SecurityError'
                ? 'Izin kamera ditolak. Izinkan melalui pengaturan browser jika bersedia, atau hubungi HR untuk prosedur alternatif. Foto tidak dibuat otomatis.'
                : 'Kamera tidak dapat dibuka atau sedang dipakai aplikasi lain. Coba kembali, atau hubungi HR.');
        } finally { start.disabled = false; }
    });
    stop.addEventListener('click', () => {
        stopCamera();
        cameraStatus.textContent = photo ? 'Foto tersimpan sementara di perangkat; kamera dihentikan.' : 'Kamera dihentikan. Belum ada foto.';
    });
    capture.addEventListener('click', async () => {
        if (!stream || !consent.checked || !video.videoWidth || !video.videoHeight) {
            return error('Kamera belum siap. Buka kamera dan tunggu pratinjau.');
        }
        const ticket = generation;
        const ratio = Math.min(1, 1280 / Math.max(video.videoWidth, video.videoHeight));
        canvas.width = Math.round(video.videoWidth * ratio);
        canvas.height = Math.round(video.videoHeight * ratio);
        canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
        const taken = new Date().toISOString();
        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.85));
        if (ticket !== generation || !consent.checked) return;
        if (!blob || blob.size > 5 * 1024 * 1024) return error('Foto tidak berhasil disiapkan atau melebihi 5 MB. Ambil ulang.');
        photo = blob;
        capturedAt = taken;
        if (photoUrl) browser.URL.revokeObjectURL(photoUrl);
        photoUrl = browser.URL.createObjectURL(blob);
        preview.src = photoUrl;
        preview.classList.remove('hidden');
        stopCamera();
        cameraStatus.textContent = 'Foto diambil pada ' + new Date(taken).toLocaleString('id-ID') + ' (waktu perangkat).';
        refresh();
    });
    locate.addEventListener('click', () => {
        if (!consent.checked) return error('Baca informasi privasi dan centang persetujuan terlebih dahulu.');
        coordinates = null; locatedAt = null; refresh();
        if (!browser.isSecureContext || !browser.navigator.geolocation) {
            return error('Lokasi browser tidak tersedia. Gunakan HTTPS atau localhost. Hubungi HR untuk prosedur alternatif; tidak ada lokasi pengganti.');
        }
        const ticket = ++locationVersion;
        locate.disabled = true;
        locationStatus.textContent = 'Meminta lokasi perangkat...';
        const success = (position) => {
            if (ticket !== locationVersion || !consent.checked) return;
            locate.disabled = false;
            const { latitude, longitude, accuracy } = position.coords;
            if (!Number.isFinite(latitude) || !Number.isFinite(longitude) || latitude < -90 || latitude > 90 || longitude < -180 || longitude > 180) {
                locationStatus.textContent = 'Koordinat tidak valid.';
                return error('Perangkat memberikan koordinat tidak valid. Coba kembali atau hubungi HR.');
            }
            coordinates = { latitude, longitude }; locatedAt = Date.now();
            locationStatus.textContent = latitude.toFixed(7) + ', ' + longitude.toFixed(7) + (Number.isFinite(accuracy) ? ' / estimasi akurasi +/-' + Math.round(accuracy) + ' meter' : '');
            errors.classList.add('hidden');
            refresh();
        };
        const failed = (cause) => {
            if (ticket !== locationVersion || !consent.checked) return;
            locate.disabled = false;
            locationStatus.textContent = 'Lokasi belum diperoleh.';
            const messages = {
                1: 'Izin lokasi ditolak (kode 1). Periksa izin lokasi situs dan Windows Settings > Privacy & security > Location jika bersedia. Hubungi HR untuk alternatif; tidak ada koordinat pengganti.',
                2: 'Perangkat tidak dapat menentukan lokasi (kode 2). Periksa Location services Windows, akses lokasi untuk browser, dan koneksi internet. Jika tetap gagal, hubungi HR.',
                3: 'Pencarian lokasi melewati batas waktu (kode 3). Coba kembali dengan koneksi internet/Wi-Fi yang tersedia atau hubungi HR.',
            };
            error(messages[cause.code] || 'Lokasi tidak dapat diperoleh. Periksa izin perangkat atau hubungi HR.');
            refresh();
        };
        const requestPosition = (highAccuracy) => {
            if (ticket !== locationVersion || !consent.checked) return;
            try {
                browser.navigator.geolocation.getCurrentPosition(success, (cause) => {
                    if (ticket !== locationVersion || !consent.checked) return;
                    if (highAccuracy && [2, 3].includes(cause.code)) {
                        locationStatus.textContent = 'Akurasi tinggi belum tersedia. Mencoba lokasi standar perangkat (hingga 20 detik)...';
                        requestPosition(false);
                    } else { failed(cause); }
                }, { enableHighAccuracy: highAccuracy, maximumAge: 0, timeout: highAccuracy ? 15000 : 20000 });
            } catch { failed({code: 2}); }
        };
        requestPosition(true);
    });
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (sending) return;
        if (!form.reportValidity()) return;
        if (!consent.checked || !photo || !coordinates) return error('Persetujuan, foto langsung dari kamera, dan lokasi wajib tersedia. Hubungi HR jika tidak dapat memberikan izin.');
        if (Date.now() - Date.parse(capturedAt) > 15 * 60 * 1000 || Date.now() - locatedAt > 15 * 60 * 1000) {
            return error('Foto atau lokasi lebih dari 15 menit. Ambil ulang sebelum mengirim.');
        }
        sending = true; refresh();
        errors.classList.add('hidden');
        try {
        const data = new browser.FormData(form);
        data.set('photo', photo, photo.type === 'image/png' ? 'capture.png' : 'capture.jpg');
        data.set('captured_at', capturedAt);
        data.set('latitude', String(coordinates.latitude));
        data.set('longitude', String(coordinates.longitude));
            const response = await browser.fetch(form.action, {
                method: 'POST', body: data, credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if ([401, 419].includes(response.status) || response.redirected) throw new Error('Sesi berakhir. Masuk kembali, lalu ambil ulang foto dan lokasi.');
            if (response.status === 429) throw new Error('Terlalu banyak pengajuan. Tunggu satu menit dan periksa daftar permohonan.');
            if (response.status === 403) throw new Error('Akses pengajuan tidak tersedia. Hubungi HR untuk memeriksa profil Anda.');
            if (response.status >= 500) throw new Error('Pengiriman belum dapat dikonfirmasi. Periksa daftar permohonan sebelum mencoba kembali.');
            const result = await response.json();
            if (!response.ok) {
                error(Object.values(result.errors || {}).flat().join(String.fromCharCode(10)) || result.message || 'Pengajuan tidak dapat disimpan.');
                return;
            }
            clearEvidence();
            browser.location.assign(result.redirect);
        } catch (cause) {
            error(cause instanceof TypeError
                ? 'Koneksi terputus; pengiriman belum dapat dikonfirmasi. Periksa daftar permohonan sebelum mencoba kembali.'
                : cause.message || 'Pengiriman belum dapat dikonfirmasi. Periksa daftar permohonan.');
        } finally { sending = false; refresh(); }
    });
    browser.addEventListener('pagehide', clearEvidence);
    refresh();
}
