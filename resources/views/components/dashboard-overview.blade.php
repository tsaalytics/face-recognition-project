@props(['employees', 'present', 'late', 'absent', 'year', 'monthly', 'notifications'])
<section class="dashboard-overview" aria-labelledby="overview-title">
<h1 id="overview-title">Overview</h1>
<div class="dashboard-stats">
@foreach([
 ['Jumlah Karyawan', $employees, 'users', 'purple', 'Total karyawan'],
 ['Hadir Hari Ini', $present, 'calendar', 'green', 'Sudah check-in hari ini'],
 ['Terlambat', $late, 'clock', 'orange', 'Check-in melewati jadwal'],
 ['Belum Hadir', $absent, 'close', 'red', 'Karyawan aktif belum check-in'],
] as [$label, $count, $icon, $color, $note])
<section class="stat-card">
<div class="stat-heading"><span class="dashboard-icon tone-{{ $color }}"><x-icon :name="$icon"/></span><h2>{{ $label }}</h2></div>
<p class="stat-value">{{ number_format($count) }}</p>
<p class="stat-note tone-{{ $color }}">{{ $note }}</p>
</section>
@endforeach
</div>
<div class="dashboard-panels">
<section class="dashboard-panel attendance-panel" aria-labelledby="attendance-chart-title">
<div class="panel-heading"><h2 id="attendance-chart-title">Jumlah Kehadiran per Bulan | {{ $year }}</h2><span class="chart-year">Tahun {{ $year }}</span></div>
@php
 $ceiling = max(5, (int) (ceil(max($monthly) / 5) * 5));
@endphp
<div class="attendance-chart">
<div class="chart-axis" aria-hidden="true">@foreach([1, .75, .5, .25, 0] as $tick)<span>{{ round($ceiling * $tick) }}</span>@endforeach</div>
<div class="chart-bars">
@foreach(['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'] as $index => $month)
<div class="chart-column"><div class="chart-track"><div class="chart-bar" style="height: {{ $monthly[$index + 1] / $ceiling * 100 }}%" title="{{ $month }}: {{ $monthly[$index + 1] }} kehadiran"><span class="sr-only">{{ $month }}: {{ $monthly[$index + 1] }} kehadiran</span></div></div><span class="chart-month" aria-hidden="true">{{ $month }}</span></div>
@endforeach
</div>
</div>
<p class="chart-caption">Jumlah check-in unik per karyawan per hari. {{ $year }} · Asia/Jakarta</p>
@if(array_sum($monthly) === 0)<p class="chart-caption">Belum ada data kehadiran tahun ini.</p>@endif
</section>
<section class="dashboard-panel notification-panel" aria-labelledby="notification-title">
<div class="panel-heading"><h2 id="notification-title"><span class="dashboard-icon tone-purple"><x-icon name="bell"/></span>Notifikasi</h2></div>
<div class="notification-scroll" role="region" aria-label="Notifikasi terbaru" tabindex="0">
<table class="notification-table"><thead><tr><th scope="col">Nama</th><th scope="col">Jenis</th><th scope="col">Status</th></tr></thead><tbody>
@forelse($notifications as $notification)
@php
 $status = $notification['status'];
 $statusLabel = ['pending'=>'Menunggu', 'approved'=>'Disetujui', 'rejected'=>'Ditolak', 'present'=>'Hadir', 'late'=>'Terlambat', 'absent'=>'Absen', 'leave'=>'Izin'][$status] ?? $status;
 $tone = in_array($status, ['approved', 'present']) ? 'green' : (in_array($status, ['rejected', 'absent']) ? 'red' : 'orange');
@endphp
<tr><td><a href="{{ $notification['url'] }}">{{ $notification['name'] }}</a></td><td>{{ $notification['type'] }}</td><td><span class="notification-badge tone-{{ $tone }}">{{ $statusLabel }}</span></td></tr>
@empty
<tr><td colspan="3" class="notification-empty">Belum ada notifikasi.</td></tr>
@endforelse
</tbody></table>
</div>
</section>
</div>
</section>
