<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('user') ? $this->user()->can('update', $this->route('user')) : $this->user()->can('create', User::class);
    }

    public function rules(): array
    {
        $rules = ['role' => ['required', Rule::in(['hr', 'employee'])], 'password' => ['prohibited'], 'password_confirmation' => ['prohibited']];
        if ($this->route('user')) {
            $rules['account_status'] = ['required', Rule::in(['pending_activation', 'active', 'disabled'])];
        } else {
            $rules['employee_id'] = ['required', 'integer', 'exists:employees,id'];
        }

        return $rules;
    }
}
