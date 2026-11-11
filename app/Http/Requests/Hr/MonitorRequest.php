<?php

namespace App\Http\Requests\Hr;

use App\Enums\Role;

class MonitorRequest extends \App\Http\Requests\Admin\MonitorRequest
{
    public function authorize(): bool
    {
        return $this->user()->is_active && $this->user()->role === Role::Hr;
    }
}
