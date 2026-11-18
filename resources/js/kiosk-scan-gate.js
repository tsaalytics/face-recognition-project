export class KioskScanGate {
    constructor({ stableMs = 1000, cooldownMs = 6500, absenceMs = 1200 } = {}) {
        Object.assign(this, { stableMs, cooldownMs, absenceMs });
        this.reset();
    }
    reset() { this.stableSince = null; this.box = null; this.latched = false; this.absentSince = null; this.until = 0; }
    complete(now, cooldown = this.cooldownMs) { this.latched = true; this.until = now + Math.max(this.cooldownMs, cooldown); this.absentSince = null; this.stableSince = null; }
    retry() { this.latched = false; this.stableSince = null; this.box = null; this.absentSince = null; }
    observe(faces, now, quality = true) {
        if (!this.latched && now < this.until) return { ready: false, message: 'Menunggu cooldown sebelum mencoba lagi…' };
        if (!faces.length) {
            this.box = null; this.stableSince = null;
            this.absentSince ??= now;
            if (this.latched && now >= this.until && now - this.absentSince >= this.absenceMs) this.latched = false;
            return { ready: false, message: this.latched ? 'Cooldown. Menunggu area kamera kosong.' : 'Siap untuk orang berikutnya. Posisikan wajah di dalam bingkai.' };
        }
        this.absentSince = null;
        if (this.latched) return { ready: false, message: now < this.until ? 'Cooldown. Silakan tinggalkan bingkai setelah absensi.' : 'Wajah ini sudah diproses. Kosongkan bingkai sebelum orang berikutnya.' };
        if (faces.length !== 1) { this.stableSince = null; return { ready: false, message: 'Pastikan hanya satu orang berada di depan kamera.' }; }
        const b = faces[0];
        if (b.width < .16 || b.height < .18 || b.x < .02 || b.y < .02 || b.x + b.width > .98 || b.y + b.height > .98 || !quality) {
            this.stableSince = null; this.box = null;
            return { ready: false, message: 'Dekatkan wajah, perbaiki pencahayaan, dan posisikan seluruh wajah di dalam bingkai.' };
        }
        const moved = !this.box || ['x', 'y', 'width', 'height'].some((k) => Math.abs(b[k] - this.box[k]) > .04);
        if (moved) { this.stableSince = now; this.box = b; }
        this.stableSince ??= now;
        return { ready: now - this.stableSince >= this.stableMs, message: 'Wajah terdeteksi. Tahan posisi sebentar…' };
    }
}

// A lightweight exposure/texture precheck; the Python service remains authoritative for image quality.
export function frameQuality(video, canvas, face) {
    if (!face) return true;
    try {
        canvas.width = 96; canvas.height = 96;
        const ctx = canvas.getContext('2d', { willReadFrequently: true });
        ctx.drawImage(video, face.x * video.videoWidth, face.y * video.videoHeight, face.width * video.videoWidth, face.height * video.videoHeight, 0, 0, 96, 96);
        const pixels = ctx.getImageData(0, 0, 96, 96).data;
        let sum = 0, squared = 0;
        for (let i = 0; i < pixels.length; i += 4) { const light = (pixels[i] + pixels[i + 1] + pixels[i + 2]) / 3; sum += light; squared += light * light; }
        const mean = sum / (pixels.length / 4);
        return mean >= 35 && mean <= 225 && squared / (pixels.length / 4) - mean * mean >= 60;
    } finally { canvas.width = 0; canvas.height = 0; }
}
