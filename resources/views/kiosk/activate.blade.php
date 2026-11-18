<x-kiosk-layout title="Aktivasi Perangkat Kiosk">
<section class="kiosk-activation">
    <img src="{{ asset('images/facetrack-ai-logo.png') }}" alt="FaceTrack AI" width="180" height="43">
    <div class="profile-card"><h1>Aktivasi Perangkat Absensi</h1><p class="profile-help">Minta kode aktivasi dari HR, lalu masukkan di tablet ini. Perangkat hanya mendapat akses setelah aktivasi berhasil.</p>
    @if($errors->any())<p class="profile-enrollment-error" role="alert">{{ $errors->first() }}</p>@endif
    <form method="POST" action="{{ route('kiosk.activate.store') }}" data-submit-form>
        @csrf
        <div class="profile-field"><label for="activation_code">Kode aktivasi</label><input id="activation_code" class="field" type="text" name="activation_code" required maxlength="24" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="XXXX-XXXX-XXXX-XXXX"></div>
        <button type="submit" class="list-control list-primary" data-submit-button>Aktifkan Perangkat</button>
    </form>
    </div>
</section>
</x-kiosk-layout>
