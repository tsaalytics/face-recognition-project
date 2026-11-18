<x-layout title="Perangkat Kiosk">
<div class="compact-list">
<x-list-header title="Perangkat Kiosk" breadcrumb="Perangkat Kiosk" :dashboard="route('hr.home')"><x-per-page :paginator="$devices" :action="route('hr.kiosk-devices.index')"/></x-list-header>
@if(session('kiosk_pairing_code'))<div class="kiosk-pairing-code" role="status"><p>Kode aktivasi · berlaku {{ config('kiosk.pairing_minutes') }} menit · hanya ditampilkan sekali</p><strong>{{ decrypt(session('kiosk_pairing_code')) }}</strong><p>Masukkan kode ini pada perangkat absensi yang akan diaktifkan.</p></div>@endif
<form method="POST" action="{{ route('hr.kiosk-devices.store') }}" class="kiosk-device-form" data-submit-form>@csrf
    <div class="profile-field"><label for="name">Nama perangkat</label><input class="field" id="name" name="name" required maxlength="80" placeholder="Contoh: Tablet Pintu Masuk"></div>
    <button class="list-control list-primary" type="submit" data-submit-button>Terbitkan Kode Aktivasi</button>
</form>
<div class="table-panel" role="region" aria-label="Daftar perangkat kiosk" tabindex="0"><table class="data-table"><thead><tr><th scope="col">Perangkat</th><th scope="col">Status</th><th scope="col">Berlaku sampai</th><th scope="col">Aksi</th></tr></thead><tbody>
@forelse($devices as $device)<tr><td>{{ $device->name }}</td><td>{{ $device->revoked_at ? 'Dicabut' : ($device->authorized() ? 'Aktif' : ($device->activated_at ? 'Kedaluwarsa' : ($device->pairing_expires_at?->isFuture() ? 'Menunggu aktivasi' : 'Kode kedaluwarsa'))) }}</td><td>{{ $device->expires_at?->format('d M Y H:i') ?? $device->pairing_expires_at?->format('d M Y H:i') }} WIB</td><td>@if(!$device->revoked_at)<form method="POST" action="{{ route('hr.kiosk-devices.revoke', $device) }}" data-submit-form data-confirm="{{ 'Yakin ingin mencabut akses perangkat '.$device->name.'? Perangkat tidak dapat digunakan untuk absensi sampai diaktifkan ulang.' }}">@csrf<button class="reset-link" type="submit" data-submit-button>Cabut Akses</button></form>@else<span>-</span>@endif</td></tr>
@empty<tr><td colspan="4" class="empty-state">Belum ada perangkat kiosk.</td></tr>@endforelse
</tbody></table></div>
<x-list-pagination :paginator="$devices"/>
<dialog class="kiosk-result-dialog" data-confirm-dialog data-tone="error" aria-labelledby="revoke-dialog-title" aria-describedby="revoke-dialog-message">
    <div class="kiosk-popup-icon" aria-hidden="true">×</div>
    <h2 id="revoke-dialog-title">Cabut Akses Perangkat?</h2>
    <p id="revoke-dialog-message" data-confirm-message></p>
    <div class="kiosk-popup-actions">
        <button type="button" class="list-control" data-confirm-cancel autofocus>Batal</button>
        <button type="button" class="list-control list-primary" data-confirm-accept>Ya, Cabut Akses</button>
    </div>
</dialog>
</div>
</x-layout>
