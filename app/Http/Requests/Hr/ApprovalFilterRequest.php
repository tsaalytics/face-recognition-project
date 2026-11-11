<?php

namespace App\Http\Requests\Hr;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApprovalFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->is_active && $this->user()->role === Role::Hr;
    }

    public function rules(): array
    {
        return ['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['pending', 'approved', 'rejected'])]];
    }
}
