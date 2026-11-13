<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'role' => Role::class, 'is_active' => 'boolean', 'activated_at' => 'datetime', 'invitation_sent_at' => 'datetime'];
    }

    public function employee()
    {
        return $this->hasOne(Employee::class);
    }

    public function approvalLogs()
    {
        return $this->hasMany(ApprovalLog::class, 'reviewed_by');
    }
}
