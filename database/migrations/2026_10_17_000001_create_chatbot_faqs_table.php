<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatbot_faqs', function (Blueprint $table) {
            $table->id();
            $table->string('intent', 80);
            $table->string('category', 100);
            $table->string('canonical_question', 255);
            $table->json('question_examples')->nullable();
            $table->text('answer');
            $table->string('locale', 10)->default('fil');
            $table->string('status', 24)->default('draft')->index();
            $table->boolean('is_active')->default(false);
            $table->boolean('is_suggested')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['intent', 'locale']);
            $table->index(['status', 'is_active', 'is_suggested']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_faqs');
    }
};
