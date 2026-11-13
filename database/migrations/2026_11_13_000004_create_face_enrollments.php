<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', fn (Blueprint $t) => $t->unsignedInteger('face_enrollment_version')->default(0));
        Schema::create('employee_face_embeddings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained()->restrictOnDelete();
            $t->text('face_embedding');
            $t->unsignedSmallInteger('embedding_dimension');
            $t->string('model_name', 30);
            $t->string('model_identifier', 100);
            $t->string('enrollment_status', 20)->default('enrolled');
            $t->json('quality');
            $t->timestamp('enrolled_at');
            $t->timestamps();
            $t->index(['employee_id', 'model_identifier']);
        });
        Schema::create('face_enrollment_audits', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained()->restrictOnDelete();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->string('action', 20);
            $t->unsignedInteger('version');
            $t->unsignedSmallInteger('sample_count');
            $t->unsignedSmallInteger('stored_count');
            $t->string('model_identifier', 100)->nullable();
            $t->string('consent_version', 30)->nullable();
            $t->timestamp('occurred_at');
            $t->unique(['employee_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('face_enrollment_audits');
        Schema::dropIfExists('employee_face_embeddings');
        Schema::table('employees', fn (Blueprint $t) => $t->dropColumn('face_enrollment_version'));
    }
};
