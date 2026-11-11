<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceRecord extends Model
{
    protected $fillable = ['employee_id', 'attendance_date', 'session', 'check_in_at', 'check_out_at', 'status', 'source'];

    protected function casts(): array
    {
        return ['attendance_date' => 'date', 'check_in_at' => 'datetime', 'check_out_at' => 'datetime'];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
