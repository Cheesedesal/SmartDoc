<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['users', 'departments', 'document_types', 'document_requirements', 'announcements', 'chatbot_faqs'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->timestamp('archived_at')->nullable()->index();
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('inactive_since')->nullable()->index();
        });

        DB::table('users')
            ->where('status', 'deactivated')
            ->whereNull('inactive_since')
            ->update(['inactive_since' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['archived_at', 'inactive_since']);
        });

        foreach (['chatbot_faqs', 'announcements', 'document_requirements', 'document_types', 'departments'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('archived_at');
            });
        }
    }
};
