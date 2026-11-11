<?php

namespace App\Http\Requests\Hr;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('employee') ? $this->user()->can('update', $this->route('employee')) : $this->user()->can('create', Employee::class);
    }

    public function rules(): array
    {
        return [
            'employee_number' => ['required', 'string', 'max:50', Rule::unique('employees')->ignore($this->route('employee'))],
            'name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', Rule::unique('employees')->ignore($this->route('employee'))],
            'phone' => ['nullable', 'string', 'max:30'], 'position' => ['required', 'string', 'max:255'],
            'division_id' => ['required', 'integer', 'exists:divisions,id'], 'is_active' => ['required', 'boolean'],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', 'employee')->where(fn ($q) => $q->where('is_active', true)->when($this->route('employee')?->user_id, fn ($q, $id) => $q->orWhere('id', $id))), Rule::unique('employees', 'user_id')->ignore($this->route('employee'))],
        ];
    }
}
