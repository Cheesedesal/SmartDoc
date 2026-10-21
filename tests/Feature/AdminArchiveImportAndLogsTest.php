<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminArchiveImportAndLogsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_archive_hub_switches_categories_and_restores_records(): void
    {
        $admin = $this->createUser('admin', Role::Admin);
        $department = Department::create(['code' => 'REG', 'name' => 'Registrar', 'status' => 'active']);
        $department->archive();

        $this->actingAs($admin)
            ->get(route('admin.archive.index', ['type' => 'departments']))
            ->assertOk()
            ->assertSee('Registrar')
            ->assertSee('Announcements')
            ->assertSee('Restore');

        $this->post(route('admin.archive.restore', ['type' => 'departments', 'id' => $department->id]))
            ->assertRedirect();
        $this->assertNull($department->fresh()->archived_at);
    }

    public function test_user_management_role_selector_only_lists_the_selected_role(): void
    {
        $admin = $this->createUser('admin', Role::Admin);
        $this->createUser('student', Role::Student);
        $this->createUser('staff', Role::Staff);

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['role' => 'staff']))
            ->assertOk()
            ->assertSee('Manage Admin')
            ->assertSee('Manage Staff')
            ->assertSee('Staff Test')
            ->assertDontSee('Student Test');
    }

    public function test_admin_can_preview_and_confirm_a_department_csv_import(): void
    {
        $admin = $this->createUser('admin', Role::Admin);
        $response = $this->actingAs($admin)->post(route('admin.imports.preview', 'departments'), [
            'file' => UploadedFile::fake()->createWithContent(
                'departments.csv',
                "code,name,status\nREG,Registrar,active\n"
            ),
        ]);
        $response->assertOk()->assertSee('1 row(s) passed validation')->assertSee('Registrar');
        preg_match('/name="preview_token" value="([^"]+)"/', $response->getContent(), $matches);

        $this->post(route('admin.imports.commit', 'departments'), [
            'preview_token' => $matches[1],
            'verified' => '1',
        ])->assertRedirect(route('admin.departments.index'));

        $this->assertDatabaseHas('departments', ['code' => 'REG', 'name' => 'Registrar']);
    }

    public function test_admin_can_preview_and_confirm_an_xlsx_import(): void
    {
        $admin = $this->createUser('admin', Role::Admin);
        $path = tempnam(sys_get_temp_dir(), 'smartdoc-xlsx-');
        $archive = new \ZipArchive;
        $this->assertSame(true, $archive->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE));
        $archive->addFromString('xl/sharedStrings.xml', '<?xml version="1.0" encoding="UTF-8"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>code</t></si><si><t>name</t></si><si><t>status</t></si><si><t>REG</t></si><si><t>Registrar</t></si><si><t>active</t></si></sst>');
        $archive->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c><c r="C1" t="s"><v>2</v></c></row><row r="2"><c r="A2" t="s"><v>3</v></c><c r="B2" t="s"><v>4</v></c><c r="C2" t="s"><v>5</v></c></row></sheetData></worksheet>');
        $archive->close();
        $parser = new \ReflectionMethod(\App\Http\Controllers\Admin\AdminDataImportController::class, 'readRows');
        $parser->setAccessible(true);
        $this->assertSame([
            ['code', 'name', 'status'],
            ['REG', 'Registrar', 'active'],
        ], $parser->invoke(new \App\Http\Controllers\Admin\AdminDataImportController, $path, 'xlsx'));
        $contents = file_get_contents($path);
        unlink($path);

        $response = $this->actingAs($admin)->post(route('admin.imports.preview', 'departments'), [
            'file' => UploadedFile::fake()->createWithContent('departments.xlsx', $contents),
        ]);
        $response->assertOk()->assertSee('1 row(s) passed validation')->assertSee('Registrar');
        preg_match('/name="preview_token" value="([^"]+)"/', $response->getContent(), $matches);

        $this->post(route('admin.imports.commit', 'departments'), [
            'preview_token' => $matches[1],
            'verified' => '1',
        ])->assertRedirect();
        $this->assertDatabaseHas('departments', ['code' => 'REG', 'name' => 'Registrar']);
    }

    public function test_activity_log_role_filter_and_csv_export_use_the_same_filter(): void
    {
        $admin = $this->createUser('admin', Role::Admin);
        $staff = $this->createUser('staff', Role::Staff);
        $staff->forceFill(['firstname' => '=SUM(1,1)', 'surname' => ''])->saveQuietly();
        ActivityLog::create([
            'actor_id' => $staff->id,
            'action' => 'created',
            'subject_type' => Department::class,
            'subject_id' => 1,
            'changes' => ['name' => '=2+2'],
            'ip_address' => '=SUM(1,1)',
        ]);
        ActivityLog::create([
            'actor_id' => $admin->id,
            'action' => 'created',
            'subject_type' => User::class,
            'subject_id' => $staff->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.activity-logs.index', ['role' => 'staff']))
            ->assertOk()
            ->assertSee('SUM(1,1)')
            ->assertSee('Activity Details')
            ->assertDontSee('User #');

        $export = $this->get(route('admin.activity-logs.export', ['role' => 'staff']))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('content-disposition');
        $this->assertStringContainsString('User,"Activity Details","Date & Time"', $export->streamedContent());
        $this->assertStringContainsString("'=SUM(1,1)", $export->streamedContent());
        $this->assertStringNotContainsString('IP address', $export->streamedContent());
    }

    private function createUser(string $prefix, Role $role): User
    {
        return User::create([
            'name' => ucfirst($prefix).' Test',
            'firstname' => ucfirst($prefix),
            'surname' => 'Test',
            'school_id' => strtoupper($prefix).'-001',
            'email' => $prefix.'@example.test',
            'password' => Hash::make('secure-test-password'),
            'role' => $role,
            'status' => UserStatus::Activated,
        ]);
    }
}
