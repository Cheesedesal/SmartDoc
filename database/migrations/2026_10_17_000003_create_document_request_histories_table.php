<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('uploaded_requirements', function (Blueprint $table) {
            $table->text('verification_remarks')->nullable()->after('is_verified');
        });

        Schema::create('document_request_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 60);
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->string('from_stage', 30)->nullable();
            $table->string('to_stage', 30)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['document_request_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_request_histories');

        Schema::table('uploaded_requirements', function (Blueprint $table) {
            $table->dropColumn('verification_remarks');
        });
    }
};
