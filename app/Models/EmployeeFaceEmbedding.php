<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeFaceEmbedding extends Model
{
    protected $guarded = ['*'];

    protected $hidden = ['face_embedding', 'quality'];

    protected function casts(): array
    {
        return ['face_embedding' => 'encrypted:array', 'quality' => 'array', 'enrolled_at' => 'datetime'];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
