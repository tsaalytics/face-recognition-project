<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class FaceEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageFace', $this->route('employee'));
    }

    public function rules(): array
    {
        return ['consent' => ['required', 'accepted'], 'enrollment_version' => ['required', 'integer', 'min:0'],
            'samples' => ['required', 'array', 'min:3', 'max:5'], 'samples.*' => ['required', 'string', 'max:1400000']];
    }
}
