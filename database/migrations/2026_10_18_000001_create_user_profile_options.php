<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offices', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 150)->unique();
            $table->string('status', 20)->default('active');
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('role', 20)->default('both');
            $table->string('status', 20)->default('active');
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['name', 'role']);
        });

        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 150)->unique();
            $table->string('status', 20)->default('active');
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('student_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year_level');
            $table->string('status', 20)->default('active');
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['course_id', 'year_level']);
        });

        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_year_id')->constrained('student_years')->cascadeOnDelete();
            $table->string('name', 50);
            $table->string('status', 20)->default('active');
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['student_year_id', 'name']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('office_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('student_year_id')->nullable()->constrained('student_years')->nullOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
        });

        $now = now();
        $legacyOfficeIds = DB::table('users')
            ->whereNotNull('department_id')
            ->distinct()
            ->pluck('department_id');
        foreach (DB::table('departments')->whereIn('id', $legacyOfficeIds)->get() as $department) {
            $officeId = DB::table('offices')->insertGetId([
                'code' => $department->code,
                'name' => $department->name,
                'status' => $department->status,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('users')->where('department_id', $department->id)->update(['office_id' => $officeId]);
        }

        foreach (DB::table('users')->whereNotNull('position')->where('position', '!=', '')->select('position', 'role')->distinct()->get() as $user) {
            $positionId = DB::table('positions')->insertGetId([
                'name' => $user->position,
                'role' => in_array($user->role, ['staff', 'admin'], true) ? $user->role : 'both',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('users')
                ->where('position', $user->position)
                ->where('role', $user->role)
                ->update(['position_id' => $positionId]);
        }

        foreach (DB::table('users')->whereNotNull('course')->where('course', '!=', '')->select('course')->distinct()->get() as $user) {
            $courseId = DB::table('courses')->insertGetId([
                'code' => 'LEGACY-'.strtoupper(substr(sha1($user->course), 0, 12)),
                'name' => $user->course,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('users')->where('course', $user->course)->update(['course_id' => $courseId]);
        }

        foreach (DB::table('users')
            ->whereNotNull('course_id')
            ->whereNotNull('year')
            ->select('course_id', 'year')
            ->distinct()
            ->get() as $user) {
            $yearId = DB::table('student_years')->insertGetId([
                'course_id' => $user->course_id,
                'year_level' => $user->year,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('users')
                ->where('course_id', $user->course_id)
                ->where('year', $user->year)
                ->update(['student_year_id' => $yearId]);
        }

        foreach (DB::table('users')
            ->whereNotNull('student_year_id')
            ->whereNotNull('section')
            ->where('section', '!=', '')
            ->select('student_year_id', 'section')
            ->distinct()
            ->get() as $user) {
            $sectionId = DB::table('sections')->insertGetId([
                'student_year_id' => $user->student_year_id,
                'name' => $user->section,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('users')
                ->where('student_year_id', $user->student_year_id)
                ->where('section', $user->section)
                ->update(['section_id' => $sectionId]);
        }

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->string('subject_label', 255)->nullable()->after('subject_id');
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropColumn('subject_label');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['office_id']);
            $table->dropForeign(['position_id']);
            $table->dropForeign(['course_id']);
            $table->dropForeign(['student_year_id']);
            $table->dropForeign(['section_id']);
            $table->dropColumn(['office_id', 'position_id', 'course_id', 'student_year_id', 'section_id']);
        });

        Schema::dropIfExists('sections');
        Schema::dropIfExists('student_years');
        Schema::dropIfExists('courses');
        Schema::dropIfExists('positions');
        Schema::dropIfExists('offices');
    }
};
