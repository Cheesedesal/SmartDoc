<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatbot_inquiries', function (Blueprint $table) {
            $table->id();
            $table->string('matched_intent', 80)->nullable()->index();
            $table->string('suggested_intent', 80)->nullable()->index();
            $table->string('suggested_question', 255)->nullable();
            $table->string('locale', 10)->nullable();
            $table->decimal('confidence', 4, 3)->nullable();
            $table->string('outcome', 20)->index();
            $table->timestamps();

            $table->index(['suggested_intent', 'locale', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_inquiries');
    }
};
