<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Model;

class ApprovalLog extends Model
{
    protected static function booted(): void
    {
        static::creating(function (ApprovalLog $log) {
            if ((bool) $log->attendance_correction_request_id === (bool) $log->leave_request_id) {
                throw new \LogicException('Log harus merujuk tepat satu permohonan.');
            }
            $reviewer = User::find($log->reviewed_by);
            if (! $reviewer?->is_active || $reviewer->role !== Role::Hr) {
                throw new \LogicException('Reviewer harus HR aktif.');
            }
        });
        static::updating(fn () => throw new \LogicException('Approval log bersifat immutable.'));
        static::deleting(fn () => throw new \LogicException('Approval log bersifat immutable.'));
    }

    protected $fillable = [];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function attendanceCorrectionRequest()
    {
        return $this->belongsTo(AttendanceCorrectionRequest::class);
    }

    public function leaveRequest()
    {
        return $this->belongsTo(LeaveRequest::class);
    }
}
