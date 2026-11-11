<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    protected $fillable = ['employee_id', 'type', 'start_date', 'end_date', 'reason', 'attachment_path', 'submitted_at'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'submitted_at' => 'datetime'];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function approvalLogs()
    {
        return $this->hasMany(ApprovalLog::class);
    }
}
