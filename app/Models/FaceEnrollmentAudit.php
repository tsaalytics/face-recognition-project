<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FaceEnrollmentAudit extends Model
{
    public $timestamps = false;

    protected $guarded = ['*'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Enrollment audit is immutable.'));
        static::deleting(fn () => throw new \LogicException('Enrollment audit is immutable.'));
    }

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }
}
