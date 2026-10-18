<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Additive migration for the overhaul:
 *  - online payment details on a request
 *  - AI automation bookkeeping (remarks, confidence, stage clock) + audit log table
 *  - archive / cancel support (single-request rule)
 *
 * Nothing is dropped or rewritten. Every new column is nullable or has a default,
 * so existing rows stay valid. The one data statement at the end only sets the
 * NEW `is_archived` flag on requests that were already Completed, so students who
 * finished a request before this change are not blocked by the single-request rule.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_requests', function (Blueprint $table) {
            // Online payment
            $table->string('payment_method', 30)->nullable();
            $table->string('payment_reference', 60)->nullable()->unique();
            $table->decimal('payment_amount', 10, 2)->default(0);
            $table->string('payment_status', 20)->default('Not Required')->index();
            $table->string('payment_proof_path')->nullable();      // private "local" disk
            $table->date('payment_paid_on')->nullable();
            $table->timestamp('payment_verified_at')->nullable();
            $table->json('payment_metadata')->nullable();

            // AI automation
            $table->text('ai_remarks')->nullable();
            $table->unsignedTinyInteger('ai_confidence')->nullable();
            $table->timestamp('ai_checked_at')->nullable();
            $table->timestamp('stage_updated_at')->nullable();

            // Archive / cancel
            $table->boolean('is_archived')->default(false)->index();
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
        });

        Schema::create('request_ai_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_request_id')->constrained()->cascadeOnDelete();
            $table->string('action', 40);                 // verified | held | stage_advanced | ready_for_pickup
            $table->string('from_stage', 30)->nullable();
            $table->string('to_stage', 30)->nullable();
            $table->unsignedTinyInteger('confidence')->nullable();
            $table->text('details')->nullable();
            $table->timestamps();

            $table->index('document_request_id');
        });

        DB::table('document_requests')
            ->where('status', 'Completed')
            ->update(['is_archived' => true, 'archived_at' => now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('request_ai_logs');

        Schema::table('document_requests', function (Blueprint $table) {
            $table->dropUnique(['payment_reference']);
            $table->dropIndex(['payment_status']);
            $table->dropIndex(['is_archived']);

            $table->dropColumn([
                'payment_method', 'payment_reference', 'payment_amount', 'payment_status',
                'payment_proof_path', 'payment_paid_on', 'payment_verified_at', 'payment_metadata',
                'ai_remarks', 'ai_confidence', 'ai_checked_at', 'stage_updated_at',
                'is_archived', 'archived_at', 'cancelled_at', 'cancel_reason',
            ]);
        });
    }
};
