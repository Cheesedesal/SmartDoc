<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->text('description');
            $table->unsignedInteger('processing_days');
            $table->decimal('fee', 10, 2)->default(0);   // legacy code truncated this to int
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('document_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_type_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('description', 150);
            $table->boolean('is_required')->default(true);
            $table->timestamps();

            $table->unique(['document_type_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_requirements');
        Schema::dropIfExists('document_types');
    }
};
