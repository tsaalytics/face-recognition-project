<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MonitorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role === Role::Admin && $this->user()->is_active;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'division_id' => ['nullable', 'integer', 'exists:divisions,id'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('from'), 'after_or_equal:from')],
            'status' => ['nullable', Rule::in(['present', 'late', 'leave', 'absent'])],
            'role' => ['nullable', Rule::in(['admin', 'hr', 'employee'])],
            'active' => ['nullable', Rule::in(['0', '1'])],
        ];
    }
}
