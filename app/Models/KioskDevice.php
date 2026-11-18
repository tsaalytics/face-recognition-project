<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class KioskDevice extends Model
{
    use HasUuids;

    protected $guarded = ['*'];
    protected $hidden = ['token_hash', 'pairing_code_hash'];

    protected function casts(): array
    {
        return ['pairing_expires_at' => 'datetime', 'activated_at' => 'datetime', 'expires_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function authorized(): bool
    {
        return $this->activated_at && $this->token_hash && ! $this->revoked_at && $this->expires_at?->isFuture();
    }
}
