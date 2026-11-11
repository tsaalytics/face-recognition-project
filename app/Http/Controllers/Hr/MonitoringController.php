<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\MonitorRequest;
use App\Models\Division;
use App\Services\AdminMonitoringService;
use App\Services\HrDashboardService;

class MonitoringController extends Controller
{
    public function dashboard(HrDashboardService $service)
    {
        return view('hr.dashboard', $service->data());
    }

    public function attendance(MonitorRequest $request, AdminMonitoringService $service)
    {
        return view('hr.attendance', ['records' => $service->attendance($request->validated()), 'divisions' => Division::orderBy('name')->get()]);
    }

    public function enrollment(MonitorRequest $request, AdminMonitoringService $service)
    {
        return view('hr.enrollment', ['employees' => $service->employees($request->validated()), 'divisions' => Division::orderBy('name')->get()]);
    }

    public function export(MonitorRequest $request, AdminMonitoringService $service)
    {
        $filters = $request->validated();

        return response()->streamDownload(function () use ($service, $filters) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Tanggal', 'Sesi', 'NIK', 'Nama', 'Divisi', 'Masuk', 'Pulang', 'Status', 'Sumber'], ',', '"', '');
            foreach ($service->attendanceQuery($filters)->lazy(500) as $r) {
                $row = [$r->attendance_date->format('Y-m-d'), $r->session, $r->employee->employee_number, $r->employee->name, $r->employee->division->name, $r->check_in_at?->format('Y-m-d H:i:s') ?? '', $r->check_out_at?->format('Y-m-d H:i:s') ?? '', $r->status, $r->source === 'seed_fixture' ? 'Data demo sintetis' : 'HR Approved'];
                $row = array_map(fn ($s) => preg_match('/^[\s]*[=+@\-]/u', (string) $s) ? "'".$s : $s, $row);
                fputcsv($out, $row, ',', '"', '');
            }
            fclose($out);
        }, 'facetrack-attendance-'.today()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
