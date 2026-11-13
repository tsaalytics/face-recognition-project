<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\FaceEnrollmentRequest;
use App\Models\Employee;
use App\Models\FaceEnrollmentAudit;
use App\Services\FaceEnrollmentService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class FaceEnrollmentController extends Controller
{
    public function show(Employee $employee)
    {
        Gate::authorize('manageFace', $employee);

        return response()->view('hr.faces.show', ['employee' => $employee,
            'sampleCount' => $employee->faceEmbeddings()->count(),
            'audits' => FaceEnrollmentAudit::where('employee_id', $employee->id)->latest('id')->paginate(10)])
            ->header('Cache-Control', 'private, no-store');
    }

    public function store(FaceEnrollmentRequest $request, Employee $employee, FaceEnrollmentService $service)
    {
        abort_unless($request->isJson(), 415);
        try {
            $service->enroll($request->user(), $employee, $request->validated());
        } catch (ValidationException|HttpExceptionInterface|AuthorizationException $e) {
            throw $e;
        } catch (\Throwable) {
            // Avoid SQL bindings/encrypted vectors or API payloads appearing in exception logs.
            return response()->json(['message' => 'Enrollment gagal disimpan. Muat ulang halaman dan periksa status sebelum mencoba kembali.'], 503)->header('Cache-Control', 'no-store');
        }

        return response()->json(['message' => 'Enrollment berhasil disimpan.', 'version' => $employee->fresh()->face_enrollment_version])->header('Cache-Control', 'no-store');
    }

    public function destroy(Request $request, Employee $employee, FaceEnrollmentService $service)
    {
        Gate::authorize('manageFace', $employee);
        $data = $request->validate(['confirmation' => ['required', 'accepted'], 'enrollment_version' => ['required', 'integer', 'min:0']]);
        $service->delete($request->user(), $employee, (int) $data['enrollment_version']);

        return redirect()->route('hr.faces.show', $employee)->with('success', 'Enrollment telah dihapus.');
    }
}
