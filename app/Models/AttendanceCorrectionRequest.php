<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceCorrectionRequest extends Model
{
    protected $fillable = ['employee_id', 'attendance_date', 'session', 'type', 'reason', 'evidence_path', 'captured_at', 'requested_at', 'submitted_at', 'latitude', 'longitude'];

    protected function casts(): array
    {
        return ['attendance_date' => 'date', 'captured_at' => 'datetime', 'requested_at' => 'datetime', 'submitted_at' => 'datetime', 'latitude' => 'decimal:7', 'longitude' => 'decimal:7', 'is_demo' => 'boolean'];
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
