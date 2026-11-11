<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_logs', function (Blueprint $t) {
            $t->unique('attendance_correction_request_id', 'approval_correction_unique');
            $t->unique('leave_request_id', 'approval_leave_unique');
        });
    }

    public function down(): void
    {
        Schema::table('approval_logs', function (Blueprint $t) {
            $t->dropUnique('approval_correction_unique');
            $t->dropUnique('approval_leave_unique');
        });
    }
};
