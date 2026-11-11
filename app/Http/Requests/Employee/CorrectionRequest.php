<?php

namespace App\Http\Requests\Employee;

use App\Models\AttendanceCorrectionRequest;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', AttendanceCorrectionRequest::class);
    }

    public function rules(): array
    {
        return [
            'attendance_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'time' => ['required', 'date_format:H:i'], 'type' => ['required', Rule::in(['check_in', 'check_out'])],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=1,min_height=1,max_width=4096,max_height=4096'],
            'captured_at' => ['required', 'date', function ($attribute, $value, $fail) {
                try {
                    $time = Carbon::parse($value);
                    if ($time->gt(now()->addMinutes(5)) || $time->lt(now()->subMinutes(15))) {
                        $fail('Waktu foto tidak valid atau lebih dari 15 menit. Ambil ulang foto dan periksa jam perangkat.');
                    }
                } catch (\Throwable) {
                    $fail('Waktu pengambilan foto tidak valid.');
                }
            }],
            'latitude' => ['required', 'numeric', 'between:-90,90'], 'longitude' => ['required', 'numeric', 'between:-180,180'],
            'privacy_consent' => ['required', 'accepted'],
        ];
    }
}
