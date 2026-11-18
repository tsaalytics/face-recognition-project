<?php

namespace App\Http\Requests\Hr;

use Illuminate\Validation\Rule;

class AttendanceIndexRequest extends MonitorRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'per_page' => ['sometimes', 'integer', Rule::in([5, 10, 25, 50])],
        ]);
    }
}
