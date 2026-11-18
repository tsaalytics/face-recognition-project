@props(['employee', 'sampleCount' => 0, 'enrolledAt' => null, 'audits' => null])
<section id="face-enrollment" class="profile-enrollment" aria-labelledby="enrollment-title">
    <div class="profile-section-heading"><div><h2 id="enrollment-title">Face Recognition Enrollment <span>(Optional)</span></h2><p>Pendaftaran wajah opsional. Profil tetap dapat disimpan tanpa enrollment.</p></div><span class="employee-status {{ $sampleCount ? 'employee-status-active' : 'employee-status-inactive' }}">{{ $sampleCount ? 'Enrolled' : 'Not Enrolled' }}</span></div>
    @if($sampleCount)<p class="profile-help">Enrollment: {{ $enrolledAt ? \Carbon\Carbon::parse($enrolledAt)->format('d M Y H:i').' WIB' : '-' }} · Model: InsightFace buffalo_m. Jika Re-enroll gagal, enrollment lama tetap dipertahankan.</p>@endif
    @if(!$employee->exists || ($employee->is_active && $employee->business_id))
    <form method="POST" action="{{ $employee->exists ? route('hr.faces.store', $employee) : route('hr.employees.store') }}" data-face-enrollment data-profile-form="employee-profile" data-profile-persisted="{{ $employee->exists ? '1' : '0' }}" data-version="{{ $employee->face_enrollment_version ?? 0 }}" data-submit-label="{{ $sampleCount ? 'Simpan dan Re-enroll Face' : 'Simpan dan Daftarkan Wajah' }}">
        @csrf
        <label class="profile-consent"><input type="checkbox" data-face-consent> Saya sebagai HR menyatakan karyawan telah menerima penjelasan dan menyetujui pengambilan serta penyimpanan embedding untuk demo ini (biometric-demo-v1).</label>
        <p class="profile-help">Ambil 3–5 sampel dengan variasi ringan. Foto diproses sementara tanpa disimpan permanen. Embedding terenkripsi disimpan hingga diganti/dihapus; persetujuan dapat ditarik melalui HR.</p>
        <div class="profile-enrollment-grid">
            <div class="profile-camera"><video data-face-video autoplay muted playsinline aria-label="Preview kamera enrollment"></video><span class="profile-camera-guide" aria-hidden="true"></span></div>
            <div class="profile-camera-controls">
                <h3>{{ $sampleCount ? 'Perbarui pendaftaran wajah' : 'Daftarkan wajah karyawan' }}</h3>
                <p data-face-status role="status">Belum ada sampel. Kamera belum aktif.</p>
                <div class="profile-camera-buttons"><button type="button" class="list-control" data-face-start>{{ $sampleCount ? 'Re-enroll Face' : 'Register Face' }}</button><button type="button" class="list-control" data-face-capture disabled>Ambil Sampel</button><button type="button" class="list-control" data-face-clear>Ulangi Sampel</button></div>
                <button type="submit" class="list-control list-primary" data-face-submit disabled>{{ $sampleCount ? 'Simpan dan Re-enroll Face' : 'Simpan dan Daftarkan Wajah' }}</button>
                <p class="profile-help">Profil disimpan lebih dahulu, kemudian enrollment diproses. Kamera memerlukan HTTPS atau localhost.</p>
            </div>
        </div>
        <canvas data-face-canvas hidden></canvas>
        <div data-face-error role="alert" tabindex="-1" class="hidden profile-enrollment-error"></div>
        <noscript><p>Aktifkan JavaScript untuk enrollment. Anda tetap dapat menyimpan profil tanpa kamera.</p></noscript>
    </form>
    @else<p class="profile-help">Aktifkan dan simpan profil terlebih dahulu sebelum melakukan enrollment.</p>@endif
    @if($sampleCount)
    @can('manageFace', $employee)
    <details class="profile-delete"><summary>Hapus enrollment</summary>
        <form method="POST" action="{{ route('hr.faces.destroy', $employee) }}" data-submit-form>
            @csrf @method('DELETE')<input type="hidden" name="enrollment_version" value="{{ $employee->face_enrollment_version }}">
            <label class="profile-consent"><input type="checkbox" name="confirmation" value="1" required> Hapus seluruh embedding aktif karyawan ini. Audit tetap disimpan; pemulihan memerlukan enrollment baru.</label>
            <button type="submit" class="list-control" data-submit-button>Delete Enrollment</button>
        </form>
    </details>
    @endcan
    @endif
    @if($audits && $audits->total())
    <details class="profile-account-options"><summary>Riwayat perubahan enrollment</summary>
        <div class="table-panel" role="region" aria-label="Riwayat enrollment" tabindex="0"><table class="data-table"><thead><tr><th scope="col">Waktu</th><th scope="col">Aksi</th><th scope="col">Petugas HR</th></tr></thead><tbody>
        @foreach($audits as $audit)<tr><td>{{ $audit->occurred_at->format('d M Y H:i') }} WIB</td><td>{{ ['enroll'=>'Register Face', 're_enroll'=>'Re-enroll Face', 'delete'=>'Delete Enrollment'][$audit->action] ?? $audit->action }}</td><td>#{{ $audit->actor_id }}</td></tr>@endforeach
        </tbody></table></div><x-list-pagination :paginator="$audits"/>
    </details>
    @endif
</section>
