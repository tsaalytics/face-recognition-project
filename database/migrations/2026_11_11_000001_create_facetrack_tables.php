<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->enum('role', ['admin', 'hr', 'employee'])->default('employee');
            $t->boolean('is_active')->default(true);
        });
        Schema::create('divisions', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->timestamps();
        });
        Schema::create('employees', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $t->foreignId('division_id')->constrained()->restrictOnDelete();
            $t->string('employee_number', 50)->unique();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('phone', 30)->nullable();
            $t->string('position');
            $t->boolean('is_active')->default(true);
            $t->enum('enrollment_status', ['not_enrolled', 'enrolled'])->default('not_enrolled');
            $t->timestamps();
        });
        Schema::create('attendance_records', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained()->restrictOnDelete();
            $t->date('attendance_date');
            $t->string('session', 30)->default('regular');
            $t->dateTime('check_in_at')->nullable();
            $t->dateTime('check_out_at')->nullable();
            $t->enum('status', ['present', 'late', 'leave', 'absent']);
            $t->enum('source', ['hr_approved', 'seed_fixture']);
            $t->timestamps();
            $t->unique(['employee_id', 'attendance_date', 'session'], 'attendance_employee_date_session_unique');
        });
        Schema::create('attendance_correction_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained()->restrictOnDelete();
            $t->date('attendance_date');
            $t->string('session', 30)->default('regular');
            $t->enum('type', ['check_in', 'check_out']);
            $t->text('reason');
            $t->string('evidence_path');
            $t->dateTime('captured_at');
            $t->dateTime('requested_at');
            $t->dateTime('submitted_at');
            $t->decimal('latitude', 10, 7);
            $t->decimal('longitude', 10, 7);
            $t->boolean('is_demo')->default(false);
            $t->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $t->timestamps();
            $t->index(['employee_id', 'attendance_date', 'status'], 'correction_employee_date_status');
        });
        Schema::create('leave_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained()->restrictOnDelete();
            $t->enum('type', ['permission', 'sick', 'annual']);
            $t->date('start_date');
            $t->date('end_date');
            $t->text('reason');
            $t->string('attachment_path')->nullable();
            $t->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $t->timestamp('submitted_at');
            $t->timestamps();
        });
        Schema::create('approval_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('attendance_correction_request_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('leave_request_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('reviewed_by')->constrained('users')->restrictOnDelete();
            $t->enum('from_status', ['pending']);
            $t->enum('to_status', ['approved', 'rejected']);
            $t->text('notes')->nullable();
            $t->timestamp('reviewed_at');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['approval_logs', 'leave_requests', 'attendance_correction_requests', 'attendance_records', 'employees', 'divisions'] as $name) {
            Schema::dropIfExists($name);
        }
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['role', 'is_active']));
    }
};
