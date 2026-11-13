<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $t) {
            $t->string('business_id', 12)->nullable()->unique();
            $t->date('date_of_birth')->nullable();
            $t->text('home_address')->nullable();
        });
        Schema::table('users', function (Blueprint $t) {
            $t->string('account_status', 30)->default('active')->index();
            $t->timestamp('activated_at')->nullable();
            $t->timestamp('invitation_sent_at')->nullable();
        });
        DB::table('users')->where('is_active', false)->update(['account_status' => 'disabled']);
        DB::table('users')->update(['activated_at' => DB::raw('created_at')]);
        // Old requested times are retained for history. New approvals use submitted_at.
        Schema::table('attendance_records', fn (Blueprint $t) => $t->string('source', 40)->change());
    }

    public function down(): void
    {
        // Keep source as string: reverting it to enum would truncate new source values.
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['account_status', 'activated_at', 'invitation_sent_at']));
        Schema::table('employees', function (Blueprint $t) {
            $t->dropUnique(['business_id']);
            $t->dropColumn(['business_id', 'date_of_birth', 'home_address']);
        });
    }
};
