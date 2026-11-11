<?php

namespace App\Http\Requests\Hr;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->is_active && $this->user()->role === Role::Hr;
    }

    public function rules(): array
    {
        return ['decision' => ['required', Rule::in(['approved', 'rejected'])], 'notes' => ['nullable', 'string', 'max:2000', Rule::requiredIf($this->input('decision') === 'rejected')]];
    }
}
