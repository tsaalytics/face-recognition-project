<?php

namespace App\Http\Requests\Employee;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->is_active && $this->user()->role === Role::Employee;
    }

    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('from'), 'after_or_equal:from')],
            'status' => ['nullable', Rule::in(['pending', 'approved', 'rejected'])],
        ];
    }
}
