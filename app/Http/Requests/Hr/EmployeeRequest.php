<?php

namespace App\Http\Requests\Hr;

use App\Models\Employee;
use App\Models\User;
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
            'date_of_birth' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'], 'home_address' => ['required', 'string', 'max:3000'],
            'name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', Rule::unique('employees')->ignore($this->route('employee'))],
            'phone' => ['required', 'string', 'max:30'], 'position' => ['required', 'string', 'max:255'],
            'division_id' => ['required', 'integer', 'exists:divisions,id'], 'is_active' => ['required', 'boolean'],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->whereIn('role', ['employee', 'hr'])->where(fn ($q) => $q->where('is_active', true)->when($this->route('employee')?->user_id, fn ($q, $id) => $q->orWhere('id', $id))), Rule::unique('employees', 'user_id')->ignore($this->route('employee'))],
        ];
    }

    public function after(): array
    {
        return [function ($validator) {
            $employee = $this->route('employee');
            if ($this->filled('email') && User::where('email', $this->input('email'))->when(($this->input('user_id') ?: $employee?->user_id), fn ($q, $id) => $q->where('id', '!=', $id))->exists()) {
                $validator->errors()->add('email', 'Email sudah digunakan akun lain.');
            }

        }];
    }
}
