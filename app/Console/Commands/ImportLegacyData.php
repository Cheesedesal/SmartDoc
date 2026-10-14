<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ImportLegacyData extends Command
{
    protected $signature = 'smartdoc:import-legacy
                            {--uploads= : Path to the legacy SmartDoc/uploads/requirements folder}
                            {--fresh : Empty the new tables before importing}';

    protected $description = 'Copy data from the legacy smartdoc_legacy database into the new schema';

    /** Tables emptied by --fresh, children first. */
    private const TABLES = [
        'messages', 'announcements', 'uploaded_requirements', 'document_requests',
        'document_requirements', 'document_types', 'users', 'departments',
    ];

    private array $notes = [];

    public function handle(): int
    {
        $legacy = DB::connection('legacy');

        try {
            $legacy->getPdo();
        } catch (Throwable $e) {
            $this->error('Cannot reach the legacy database: '.$e->getMessage());
            $this->line('Check DB_LEGACY_DATABASE in .env and that smartdoc.sql was imported into it.');

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            Schema::withoutForeignKeyConstraints(function () {
                foreach (self::TABLES as $table) {
                    DB::table($table)->truncate();
                }
            });
        } elseif (DB::table('users')->exists() || DB::table('departments')->exists()) {
            $this->error('Target tables are not empty. Re-run with --fresh to wipe and re-import.');

            return self::FAILURE;
        }

        $counts = [];

        DB::transaction(function () use ($legacy, &$counts) {
            $counts['departments'] = $this->departments($legacy);
            $counts['users'] = $this->users($legacy);
            $counts['document_types'] = $this->documentTypes($legacy);
            $counts['document_requirements'] = $this->documentRequirements($legacy);
            $counts['document_requests'] = $this->documentRequests($legacy);
            $counts['uploaded_requirements'] = $this->uploadedRequirements($legacy);
            $counts['announcements'] = $this->announcements($legacy);
            $counts['messages'] = $this->messages($legacy);
        });

        $this->table(['Table', 'Rows imported'], collect($counts)->map(fn ($n, $t) => [$t, $n])->values()->all());

        foreach ($this->notes as $note) {
            $this->warn($note);
        }

        $this->info('Legacy import finished.');

        return self::SUCCESS;
    }

    /** '0000-00-00' and empty strings become null. */
    private function date(mixed $value): ?string
    {
        if ($value === null || $value === '' || str_starts_with((string) $value, '0000')) {
            return null;
        }

        return (string) $value;
    }

    private function departments($legacy): int
    {
        $rows = $legacy->table('departments')->orderBy('department_id')->get();

        foreach ($rows as $r) {
            DB::table('departments')->insert([
                'id' => $r->department_id,
                'code' => $r->department_code,
                'name' => $r->department_name,
                'status' => $r->status,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $rows->count();
    }

    private function users($legacy): int
    {
        $rows = $legacy->table('users')->orderBy('user_id')->get();

        foreach ($rows as $r) {
            // Legacy passwords are plaintext; leave anything that already looks bcrypt-hashed alone.
            $password = str_starts_with($r->password, '$2y$') ? $r->password : Hash::make($r->password);

            DB::table('users')->insert([
                'id' => $r->user_id,
                'school_id' => (string) $r->user_id,   // existing users keep logging in with their old ID
                'firstname' => $r->firstname,
                'surname' => $r->surname,
                'email' => $r->email,
                'department_id' => $r->department_id,
                'course' => $r->course,
                'year' => $r->year,
                'section' => $r->section,
                'position' => $r->position,
                'password' => $password,
                'role' => $r->role,
                'status' => $r->status,
                'created_at' => $r->created_at,
                'updated_at' => $r->created_at,
            ]);
        }

        if ($rows->isNotEmpty()) {
            $this->notes[] = 'Passwords were hashed. Ask users to change the old ones (e.g. "hi", "admin", "staff", "Password01").';
        }

        return $rows->count();
    }

    private function documentTypes($legacy): int
    {
        $rows = $legacy->table('document_types')->orderBy('document_type_id')->get();

        foreach ($rows as $r) {
            DB::table('document_types')->insert([
                'id' => $r->document_type_id,
                'name' => $r->document_name,
                'description' => $r->description,
                'processing_days' => $r->processing_days,
                'fee' => $r->fee,
                'is_active' => $r->is_active === 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $rows->count();
    }

    private function documentRequirements($legacy): int
    {
        $rows = $legacy->table('document_requirements')->orderBy('requirement_id')->get();

        foreach ($rows as $r) {
            DB::table('document_requirements')->insert([
                'id' => $r->requirement_id,
                'document_type_id' => $r->document_type_id,
                'name' => $r->requirement_name,
                'description' => $r->description,
                'is_required' => $r->is_required === 'required',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $rows->count();
    }

    private function documentRequests($legacy): int
    {
        $rows = $legacy->table('document_requests')->orderBy('request_id')->get();

        foreach ($rows as $r) {
            $stage = $r->current_stage;

            // Legacy rows can hold a blank stage (the enum was never validated on a rejected request).
            if ($stage === null || $stage === '') {
                $stage = $this->date($r->approved_at) ? 'For Verification' : 'Request Received';
                $this->notes[] = "Request #{$r->request_id}: blank stage set to '{$stage}'.";
            }

            if ($this->date($r->estimated_completion_date) === null) {
                $this->notes[] = "Request #{$r->request_id}: invalid estimated completion date set to NULL.";
            }

            DB::table('document_requests')->insert([
                'id' => $r->request_id,
                'user_id' => $r->user_id,
                'document_type_id' => $r->document_type_id,
                'current_processed_by' => $r->current_processed_by,
                'status' => $r->status,
                'current_stage' => $stage,
                'claiming_method' => $r->claiming_method,
                'purpose' => $r->purpose,
                'requester_remarks' => $r->requester_remarks,
                'staff_remarks' => $r->staff_remarks,
                'estimated_completion_date' => $this->date($r->estimated_completion_date),
                'approved_at' => $this->date($r->approved_at),
                'approved_by' => $r->approved_by,
                'rejected_at' => $this->date($r->rejected_at),
                'rejected_by' => $r->rejected_by,
                'rejection_reason' => $r->rejection_reason,
                'completed_at' => $this->date($r->completed_at),
                'released_at' => $this->date($r->released_at),
                'released_by' => $r->released_by,
                'created_at' => $r->requested_at,
                'updated_at' => $this->date($r->released_at) ?? $this->date($r->rejected_at) ?? $r->requested_at,
            ]);
        }

        return $rows->count();
    }

    private function uploadedRequirements($legacy): int
    {
        $rows = $legacy->table('uploaded_requirements')->orderBy('upload_id')->get();
        $source = $this->option('uploads') ? rtrim($this->option('uploads'), '/\\') : null;
        $missing = 0;

        foreach ($rows as $r) {
            $stored = basename($r->file_path);
            $path = 'requirements/'.$stored;

            if ($source) {
                $file = $source.DIRECTORY_SEPARATOR.$stored;

                if (is_file($file)) {
                    Storage::disk('local')->put($path, fopen($file, 'r'));
                } else {
                    $missing++;
                    $this->notes[] = "Upload #{$r->upload_id}: file not found at {$file}.";
                }
            }

            DB::table('uploaded_requirements')->insert([
                'id' => $r->upload_id,
                'document_request_id' => $r->request_id,
                'document_requirement_id' => $r->requirement_id,
                'original_name' => $r->file_name ?? $stored,
                'path' => $path,
                'is_verified' => $r->verified === 'Verified',
                'created_at' => $r->uploaded_at,
                'updated_at' => $r->uploaded_at,
            ]);
        }

        if (! $source) {
            $this->notes[] = 'No --uploads path given: database rows were imported but files were NOT copied.';
        } elseif ($missing === 0) {
            $this->notes[] = 'All upload files were copied to storage/app/private/requirements.';
        }

        return $rows->count();
    }

    private function announcements($legacy): int
    {
        $rows = $legacy->table('announcements')->orderBy('announcement_id')->get();

        foreach ($rows as $r) {
            DB::table('announcements')->insert([
                'id' => $r->announcement_id,
                'title' => $r->title,
                'message' => $r->message,
                'audience' => $r->audience === 'all users' ? 'all' : $r->audience,
                'status' => $r->status,
                'published_at' => $this->date($r->published_at),
                'expires_at' => $this->date($r->expiration_date),
                'created_by' => $r->created_by,
                'created_at' => $r->created_at,
                'updated_at' => $r->created_at,
            ]);
        }

        return $rows->count();
    }

    private function messages($legacy): int
    {
        $rows = $legacy->table('messages')->orderBy('message_id')->get();

        foreach ($rows as $r) {
            DB::table('messages')->insert([
                'id' => $r->message_id,
                'student_id' => $r->student_id,
                'staff_id' => $r->staff_id,
                'sender_id' => $r->user_id,
                'body' => $r->message,
                'created_at' => $r->sent_at,
                'updated_at' => $r->sent_at,
            ]);
        }

        return $rows->count();
    }
}
