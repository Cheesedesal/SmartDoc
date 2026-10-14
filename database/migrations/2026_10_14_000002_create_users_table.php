<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('school_id', 30)->nullable()->unique();
            $table->string('firstname')->nullable();
            $table->string('surname')->nullable();

            $table->foreignId('department_id')
                ->nullable()
                ->constrained('departments')
                ->nullOnDelete();

            $table->string('course', 100)->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('section', 50)->nullable();
            $table->string('position', 100)->nullable();

            $table->string('role', 20)->default('student')->index();
            $table->string('status', 20)->default('activated')->index();
        });

        // Preserve the existing account, email, and password hash.
        DB::table('users')
            ->where('id', 1)
            ->update([
                'school_id' => 'ADMIN001',
                'firstname' => 'Administrator',
                'surname' => '',
                'role' => 'admin',
                'status' => 'activated',
            ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropUnique(['school_id']);
            $table->dropIndex(['role']);
            $table->dropIndex(['status']);

            $table->dropColumn([
                'school_id',
                'firstname',
                'surname',
                'department_id',
                'course',
                'year',
                'section',
                'position',
                'role',
                'status',
            ]);
        });
    }
};
