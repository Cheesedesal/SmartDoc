<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();          // the student
            $table->foreignId('document_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('current_processed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('status', 30)->default('Pending')->index();
            $table->string('current_stage', 30)->default('Request Received');
            $table->string('claiming_method', 20)->default('Pick-up');
            $table->string('purpose')->nullable();
            $table->text('requester_remarks')->nullable();
            $table->text('staff_remarks')->nullable();
            $table->date('estimated_completion_date')->nullable();   // legacy: NOT NULL, held '0000-00-00'

            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();   // created_at == legacy requested_at
        });

        Schema::create('uploaded_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_requirement_id')->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->string('path');                      // relative to the private "local" disk
            $table->boolean('is_verified')->default(false);
            $table->timestamps();

            $table->unique(['document_request_id', 'document_requirement_id'], 'uploaded_req_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uploaded_requirements');
        Schema::dropIfExists('document_requests');
    }
};
