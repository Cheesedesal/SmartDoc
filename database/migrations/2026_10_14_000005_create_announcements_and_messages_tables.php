<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200)->unique();
            $table->text('message');
            $table->string('audience', 20)->default('all');          // admin | staff | student | all
            $table->string('status', 20)->default('Draft')->index(); // Published | Draft | Expired
            $table->dateTime('published_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['student_id', 'staff_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('announcements');
    }
};
