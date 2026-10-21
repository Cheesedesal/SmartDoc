<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AnnouncementStatus;
use App\Enums\Audience;
use App\Enums\ChatbotFaqStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\ChatbotFaq;
use App\Models\Course;
use App\Models\Department;
use App\Models\DocumentRequirement;
use App\Models\DocumentType;
use App\Models\Office;
use App\Models\Position;
use App\Models\Section;
use App\Models\StudentYear;
use App\Models\User;
use App\Services\PortalNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use ZipArchive;

class AdminDataImportController extends Controller
{
    private static function definitions(): array
    {
        return [
            'users' => [
                'label' => 'User accounts',
                'index_route' => 'admin.users.index',
                'headers' => ['firstname', 'surname', 'school_id', 'email', 'role', 'department_code', 'office_code', 'position', 'course_code', 'year', 'section', 'status'],
                'rules' => [
                    'firstname' => ['required', 'string', 'max:255'],
                    'surname' => ['required', 'string', 'max:255'],
                    'school_id' => ['required', 'string', 'max:30', 'unique:users,school_id'],
                    'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                    'role' => ['required', Rule::in(['student', 'staff', 'admin'])],
                    'department_code' => ['nullable', 'string', Rule::exists('departments', 'code')->where('status', 'active')->whereNull('archived_at')],
                    'office_code' => ['nullable', 'string', Rule::exists('offices', 'code')->where('status', 'active')->whereNull('archived_at')],
                    'position' => ['nullable', 'string', 'max:100'],
                    'course_code' => ['nullable', 'string', Rule::exists('courses', 'code')->where('status', 'active')->whereNull('archived_at')],
                    'year' => ['nullable', 'integer', 'min:1', 'max:99'],
                    'section' => ['nullable', 'string', 'max:50'],
                    'status' => ['required', Rule::in(['activated', 'deactivated'])],
                ],
            ],
            'offices' => [
                'label' => 'Offices',
                'index_route' => 'admin.profile-options.index',
                'index_route_params' => ['type' => 'offices'],
                'headers' => ['code', 'name', 'status'],
                'rules' => [
                    'code' => ['required', 'string', 'max:30', 'unique:offices,code'],
                    'name' => ['required', 'string', 'max:150', 'unique:offices,name'],
                    'status' => ['required', Rule::in(['active', 'inactive'])],
                ],
            ],
            'positions' => [
                'label' => 'Positions',
                'index_route' => 'admin.profile-options.index',
                'index_route_params' => ['type' => 'positions'],
                'headers' => ['name', 'role', 'status'],
                'rules' => [
                    'name' => ['required', 'string', 'max:100'],
                    'role' => ['required', Rule::in(['both', 'staff', 'admin'])],
                    'status' => ['required', Rule::in(['active', 'inactive'])],
                ],
            ],
            'courses' => [
                'label' => 'Courses',
                'index_route' => 'admin.profile-options.index',
                'index_route_params' => ['type' => 'courses'],
                'headers' => ['code', 'name', 'status'],
                'rules' => [
                    'code' => ['required', 'string', 'max:30', 'unique:courses,code'],
                    'name' => ['required', 'string', 'max:150', 'unique:courses,name'],
                    'status' => ['required', Rule::in(['active', 'inactive'])],
                ],
            ],
            'years' => [
                'label' => 'Year levels',
                'index_route' => 'admin.profile-options.index',
                'index_route_params' => ['type' => 'years'],
                'headers' => ['course_code', 'year_level', 'status'],
                'rules' => [
                    'course_code' => ['required', 'string', Rule::exists('courses', 'code')->where('status', 'active')->whereNull('archived_at')],
                    'year_level' => ['required', 'integer', 'min:1', 'max:99'],
                    'status' => ['required', Rule::in(['active', 'inactive'])],
                ],
            ],
            'sections' => [
                'label' => 'Sections',
                'index_route' => 'admin.profile-options.index',
                'index_route_params' => ['type' => 'sections'],
                'headers' => ['course_code', 'year_level', 'name', 'status'],
                'rules' => [
                    'course_code' => ['required', 'string', Rule::exists('courses', 'code')->where('status', 'active')->whereNull('archived_at')],
                    'year_level' => ['required', 'integer', 'min:1', 'max:99'],
                    'name' => ['required', 'string', 'max:50'],
                    'status' => ['required', Rule::in(['active', 'inactive'])],
                ],
            ],
            'departments' => [
                'label' => 'Departments',
                'index_route' => 'admin.departments.index',
                'headers' => ['code', 'name', 'status'],
                'rules' => [
                    'code' => ['required', 'string', 'max:20', 'unique:departments,code'],
                    'name' => ['required', 'string', 'max:100', 'unique:departments,name'],
                    'status' => ['required', Rule::in(['active', 'inactive'])],
                ],
            ],
            'document-types' => [
                'label' => 'Document types',
                'index_route' => 'admin.document-types.index',
                'headers' => ['name', 'description', 'processing_days', 'fee', 'is_active'],
                'rules' => [
                    'name' => ['required', 'string', 'max:150', 'unique:document_types,name'],
                    'description' => ['required', 'string', 'max:2000'],
                    'processing_days' => ['required', 'integer', 'min:1', 'max:365'],
                    'fee' => ['required', 'numeric', 'min:0', 'max:999999.99'],
                    'is_active' => ['required', Rule::in(['0', '1'])],
                ],
            ],
            'announcements' => [
                'label' => 'Announcements',
                'index_route' => 'admin.announcements.index',
                'headers' => ['title', 'message', 'audience', 'status', 'expires_at'],
                'rules' => [
                    'title' => ['required', 'string', 'max:200', 'unique:announcements,title'],
                    'message' => ['required', 'string', 'max:20000'],
                    'audience' => ['required', Rule::enum(Audience::class)],
                    'status' => ['required', Rule::in(['Draft', 'Published'])],
                    'expires_at' => ['nullable', 'date', 'after_or_equal:today'],
                ],
            ],
            'faqs' => [
                'label' => 'FAQ drafts',
                'index_route' => 'admin.chatbot-faqs.index',
                'headers' => ['intent', 'category', 'canonical_question', 'answer', 'locale', 'question_examples'],
                'rules' => [
                    'intent' => ['required', 'regex:/^[a-z][a-z0-9_]{2,79}$/'],
                    'category' => ['required', 'string', 'max:100'],
                    'canonical_question' => ['required', 'string', 'max:255'],
                    'answer' => ['required', 'string', 'max:5000'],
                    'locale' => ['required', Rule::in(['fil', 'en'])],
                    'question_examples' => ['nullable', 'string', 'max:5000'],
                ],
            ],
            'requirements' => [
                'label' => 'Document requirements',
                'index_route' => 'admin.document-types.index',
                'headers' => ['document_type', 'name', 'description', 'is_required'],
                'rules' => [
                    'document_type' => ['required', 'string', 'exists:document_types,name'],
                    'name' => ['required', 'string', 'max:150'],
                    'description' => ['required', 'string', 'max:150'],
                    'is_required' => ['required', Rule::in(['0', '1'])],
                ],
            ],
        ];
    }

    public function show(string $type): View
    {
        $definitions = self::definitions();
        abort_unless(isset($definitions[$type]), 404);

        return view('admin.imports.show', [
            'type' => $type,
            'definition' => $definitions[$type],
            'validRows' => null,
            'rowErrors' => null,
            'token' => null,
        ]);
    }

    private function duplicateKeys(string $type, array $data): array
    {
        $uniqueGroups = match ($type) {
            'users' => [['school_id'], ['email']],
            'departments' => [['code'], ['name']],
            'offices' => [['code'], ['name']],
            'positions' => [['name', 'role']],
            'courses' => [['code'], ['name']],
            'years' => [['course_code', 'year_level']],
            'sections' => [['course_code', 'year_level', 'name']],
            'document-types' => [['name']],
            'announcements' => [['title']],
            'faqs' => [['intent', 'locale']],
            'requirements' => [['document_type', 'name']],
            default => [],
        };

        return array_map(
            fn ($fields) => $type.':'.implode('|', array_map(fn ($field) => mb_strtolower($data[$field] ?? ''), $fields)),
            $uniqueGroups
        );
    }

    private function createAnnouncement(array $row, Request $request): void
    {
        $announcement = Announcement::create([
            ...$row,
            'created_by' => $request->user()->id,
            'published_at' => $row['status'] === AnnouncementStatus::Published->value ? now() : null,
            'expires_at' => $row['expires_at'] ?: null,
        ]);
        if ($announcement->status === AnnouncementStatus::Published) {
            app(PortalNotifier::class)->announcementPublished($announcement);
        }
    }

    private function validateRequirementName(string $type, array $data, $validator): void
    {
        if ($type === 'requirements' && isset($data['document_type'], $data['name'])) {
            $documentType = DocumentType::where('name', $data['document_type'])->first();
            if ($documentType && $documentType->requirements()->where('name', $data['name'])->exists()) {
                $validator->errors()->add('name', 'This requirement already exists for the selected document type.');
            }
        }
        if ($type === 'faqs' && isset($data['intent'], $data['locale'])
            && ChatbotFaq::where('intent', $data['intent'])->where('locale', $data['locale'])->exists()) {
            $validator->errors()->add('intent', 'An FAQ already exists for this intent and language.');
        }
    }

    public function template(string $type)
    {
        $definitions = self::definitions();
        abort_unless(isset($definitions[$type]), 404);
        $headers = $definitions[$type]['headers'];

        return response()->streamDownload(function () use ($headers) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, $headers);
            fclose($output);
        }, 'smartdoc-'.$type.'-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function preview(Request $request, string $type): View
    {
        $definitions = self::definitions();
        abort_unless(isset($definitions[$type]), 404);
        $request->validate([
            'file' => ['required', 'file', 'max:5120', 'mimes:csv,txt,xlsx'],
        ]);

        $rows = $this->readRows($request->file('file')->getRealPath(), $request->file('file')->getClientOriginalExtension());
        $headers = $definitions[$type]['headers'];
        if (count($rows) > 501) {
            return $this->previewView($type, [], [[
                'row' => 1,
                'errors' => ['The file exceeds the 500 data-row limit. Split it into smaller files.'],
            ]], null);
        }
        if (! $rows || array_map(fn ($header) => strtolower(trim($header)), $rows[0]) !== $headers) {
            return $this->previewView($type, [], [[
                'row' => 1,
                'errors' => ['The first row must exactly match the downloaded template columns and order.'],
            ]], null);
        }

        $validRows = [];
        $rowErrors = [];
        $seen = [];
        foreach (array_slice($rows, 1, 500, true) as $index => $values) {
            if (count(array_filter($values, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }
            $values = array_pad(array_slice($values, 0, count($headers)), count($headers), '');
            $values = array_map(fn ($value) => trim((string) $value), $values);
            $data = array_combine($headers, array_map(fn ($value) => $value === '' ? null : $value, $values));
            $validator = Validator::make($data, $definitions[$type]['rules']);
            $this->validateRoleRequirements($type, $data, $validator);
            $this->validateRequirementName($type, $data, $validator);
            foreach ($this->duplicateKeys($type, $data) as $duplicateKey) {
                if (isset($seen[$duplicateKey])) {
                    $validator->errors()->add('row', 'This record duplicates an earlier row in the same file.');
                }
                $seen[$duplicateKey] = true;
            }
            if ($validator->fails()) {
                $rowErrors[] = ['row' => $index + 1, 'errors' => $validator->errors()->all()];
            } else {
                $validRows[] = $data;
            }
        }

        $token = null;
        if ($validRows && ! $rowErrors) {
            $token = Str::random(48);
            session(['admin_import_preview' => ['token' => $token, 'type' => $type, 'rows' => $validRows]]);
        } else {
            session()->forget('admin_import_preview');
        }

        return $this->previewView($type, $validRows, $rowErrors, $token);
    }

    public function import(Request $request, string $type): RedirectResponse
    {
        $definitions = self::definitions();
        abort_unless(isset($definitions[$type]), 404);
        $request->validate([
            'preview_token' => ['required', 'string'],
            'verified' => ['accepted'],
        ]);
        $preview = session('admin_import_preview');
        abort_unless(
            is_array($preview)
            && hash_equals($preview['token'] ?? '', $request->string('preview_token')->toString())
            && ($preview['type'] ?? null) === $type
            && ! empty($preview['rows']),
            419,
            'This import preview expired. Upload and review the file again.'
        );

        $seen = [];
        foreach ($preview['rows'] as $index => $row) {
            $validator = Validator::make($row, $definitions[$type]['rules']);
            $this->validateRoleRequirements($type, $row, $validator);
            $this->validateRequirementName($type, $row, $validator);
            foreach ($this->duplicateKeys($type, $row) as $duplicateKey) {
                if (isset($seen[$duplicateKey])) {
                    $validator->errors()->add('row', 'This record duplicates another row in the import.');
                }
                $seen[$duplicateKey] = true;
            }
            if ($validator->fails()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'import' => 'Import validation changed after preview. Please upload and review the file again (row '.($index + 2).': '.implode(' ', $validator->errors()->all()).').',
                ]);
            }
        }

        DB::transaction(function () use ($preview, $type, $request) {
            foreach ($preview['rows'] as $row) {
                $this->createRecord($type, $row, $request);
            }
        });
        session()->forget('admin_import_preview');

        return redirect()->route($definitions[$type]['index_route'], $definitions[$type]['index_route_params'] ?? [])
            ->with('status', count($preview['rows']).' '.strtolower($definitions[$type]['label']).' imported.');
    }

    private function previewView(string $type, array $validRows, array $rowErrors, ?string $token): View
    {
        return view('admin.imports.show', [
            'type' => $type,
            'definition' => self::definitions()[$type],
            'validRows' => $validRows,
            'rowErrors' => $rowErrors,
            'token' => $token,
        ]);
    }

    private function validateRoleRequirements(string $type, array $data, $validator): void
    {
        if ($type === 'users' && ($data['role'] ?? null) === Role::Student->value && (
            empty($data['course_code']) || empty($data['year']) || empty($data['section'])
        )) {
            $validator->errors()->add('role', 'Students need an existing course code, year, and section.');
        } elseif ($type === 'users' && ($data['role'] ?? null) === Role::Student->value && $data['course_code'] && $data['year'] && $data['section']) {
            $year = StudentYear::whereHas('course', fn ($query) => $query->where('code', $data['course_code']))
                ->where('year_level', $data['year'])
                ->whereNull('archived_at')->where('status', 'active')->first();
            if (! $year || ! $year->sections()->where('name', $data['section'])->whereNull('archived_at')->where('status', 'active')->exists()) {
                $validator->errors()->add('section', 'The selected course, year, and section must match active profile options.');
            }
        }
        if ($type === 'users' && in_array($data['role'] ?? null, [Role::Staff->value, Role::Admin->value], true) && (
            empty($data['department_code']) || empty($data['office_code']) || empty($data['position'])
        )) {
            $validator->errors()->add('role', 'Staff and admins need an existing department_code, office_code, and position.');
        } elseif ($type === 'users' && in_array($data['role'] ?? null, [Role::Staff->value, Role::Admin->value], true) && ! empty($data['position'])) {
            if (! Position::where('name', $data['position'])->whereIn('role', ['both', $data['role']])->whereNull('archived_at')->where('status', 'active')->exists()) {
                $validator->errors()->add('position', 'The selected position must be active and assigned to this role.');
            }
        }
        if ($type === 'positions' && isset($data['name'], $data['role'])
            && Position::where('name', $data['name'])->where('role', $data['role'])->exists()) {
            $validator->errors()->add('name', 'This position and role already exist.');
        }
        if ($type === 'years' && isset($data['course_code'], $data['year_level'])) {
            $course = Course::where('code', $data['course_code'])->first();
            if ($course && StudentYear::where('course_id', $course->id)->where('year_level', $data['year_level'])->exists()) {
                $validator->errors()->add('year_level', 'This year level already exists for the selected course.');
            }
        }
        if ($type === 'sections' && isset($data['course_code'], $data['year_level'], $data['name'])) {
            $year = StudentYear::whereHas('course', fn ($query) => $query->where('code', $data['course_code']))
                ->where('year_level', $data['year_level'])->whereNull('archived_at')->where('status', 'active')->first();
            if ($year && $year->sections()->where('name', $data['name'])->exists()) {
                $validator->errors()->add('name', 'This section already exists for the selected course and year.');
            }
        }
    }

    private function createRecord(string $type, array $row, Request $request): void
    {
        match ($type) {
            'users' => $this->createUser($row),
            'departments' => Department::create($row),
            'offices' => Office::create($row),
            'positions' => Position::create($row),
            'courses' => Course::create($row),
            'years' => $this->createYear($row),
            'sections' => $this->createSection($row),
            'document-types' => $this->createDocumentType($row),
            'announcements' => $this->createAnnouncement($row, $request),
            'faqs' => ChatbotFaq::create([
                ...$row,
                'question_examples' => array_values(array_filter(array_map('trim', preg_split('/[\r\n|]+/', $row['question_examples'] ?? '')))),
                'status' => ChatbotFaqStatus::Draft,
                'is_active' => false,
                'is_suggested' => false,
                'created_by' => $request->user()->id,
            ]),
            'requirements' => $this->createRequirement($row),
        };
    }

    private function createDocumentType(array $row): void
    {
        $documentType = DocumentType::create([
            ...$row,
            'is_active' => $row['is_active'] === '1',
        ]);
        if ($documentType->is_active) {
            app(PortalNotifier::class)->documentTypeActivated($documentType);
        }
    }

    private function createRequirement(array $row): void
    {
        $documentType = DocumentType::where('name', $row['document_type'])->firstOrFail();
        DocumentRequirement::create([
            'document_type_id' => $documentType->id,
            'name' => $row['name'],
            'description' => $row['description'],
            'is_required' => $row['is_required'] === '1',
        ]);
    }

    private function createUser(array $row): void
    {
        $isStudent = $row['role'] === Role::Student->value;
        $department = ! $isStudent && ! empty($row['department_code'])
            ? Department::where('code', $row['department_code'])->firstOrFail()
            : null;
        $office = ! $isStudent && ! empty($row['office_code'])
            ? Office::where('code', $row['office_code'])->firstOrFail()
            : null;
        $position = ! $isStudent && ! empty($row['position'])
            ? Position::where('name', $row['position'])->whereIn('role', ['both', $row['role']])->firstOrFail()
            : null;
        $course = $isStudent ? Course::where('code', $row['course_code'])->firstOrFail() : null;
        $year = $isStudent ? StudentYear::where('course_id', $course->id)->where('year_level', $row['year'])->firstOrFail() : null;
        $section = $isStudent ? Section::where('student_year_id', $year->id)->where('name', $row['section'])->firstOrFail() : null;
        User::create([
            'firstname' => $row['firstname'],
            'surname' => $row['surname'],
            'name' => trim($row['firstname'].' '.$row['surname']),
            'school_id' => $row['school_id'],
            'email' => $row['email'],
            'role' => $row['role'],
            'status' => $row['status'],
            'department_id' => $department?->id,
            'office_id' => $office?->id,
            'position_id' => $position?->id,
            'position' => $position?->name,
            'course_id' => $course?->id,
            'student_year_id' => $year?->id,
            'section_id' => $section?->id,
            'course' => $course?->name,
            'year' => $isStudent ? ($row['year'] ?: null) : null,
            'section' => $section?->name,
            'password' => Hash::make(Str::random(48)),
        ]);
    }

    private function createYear(array $row): void
    {
        $course = Course::where('code', $row['course_code'])->firstOrFail();
        StudentYear::create(['course_id' => $course->id, 'year_level' => $row['year_level'], 'status' => $row['status']]);
    }

    private function createSection(array $row): void
    {
        $year = StudentYear::where('year_level', $row['year_level'])
            ->whereHas('course', fn ($query) => $query->where('code', $row['course_code']))
            ->firstOrFail();
        Section::create(['student_year_id' => $year->id, 'name' => $row['name'], 'status' => $row['status']]);
    }

    private function readRows(string $path, string $extension): array
    {
        if (strtolower($extension) !== 'xlsx') {
            $handle = fopen($path, 'r');
            if ($handle === false) {
                throw new \RuntimeException('Unable to read uploaded import file.');
            }
            $rows = [];
            while (($row = fgetcsv($handle)) !== false) {
                $rows[] = array_map(fn ($value) => preg_replace('/^\xEF\xBB\xBF/', '', (string) $value), $row);
            }
            fclose($handle);

            return $rows;
        }

        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw \Illuminate\Validation\ValidationException::withMessages(['file' => 'The uploaded Excel file could not be opened.']);
        }
        $sharedStrings = [];
        $sharedInfo = $zip->statName('xl/sharedStrings.xml');
        if ($sharedInfo && $sharedInfo['size'] > 5 * 1024 * 1024) {
            $zip->close();
            throw \Illuminate\Validation\ValidationException::withMessages(['file' => 'The Excel workbook shared-string data exceeds the allowed size.']);
        }
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml !== false) {
            $xml = simplexml_load_string($sharedXml, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOBLANKS);
            if (! $xml) {
                $zip->close();
                throw \Illuminate\Validation\ValidationException::withMessages(['file' => 'The Excel workbook contains invalid shared-string XML.']);
            }
            $mainNamespace = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
            foreach ($xml->children($mainNamespace)->si as $item) {
                $parts = [];
                $itemNodes = $item->children($mainNamespace);
                if (isset($itemNodes->t)) {
                    $parts[] = (string) $itemNodes->t;
                } else {
                    foreach ($itemNodes->r as $run) {
                        $parts[] = (string) $run->children($mainNamespace)->t;
                    }
                }
                $sharedStrings[] = implode('', $parts);
            }
        }
        $sheetInfo = $zip->statName('xl/worksheets/sheet1.xml');
        if ($sheetInfo && $sheetInfo['size'] > 5 * 1024 * 1024) {
            $zip->close();
            throw \Illuminate\Validation\ValidationException::withMessages(['file' => 'The Excel worksheet exceeds the allowed uncompressed size.']);
        }
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if ($sheet === false) {
            throw \Illuminate\Validation\ValidationException::withMessages(['file' => 'The Excel workbook has no first worksheet.']);
        }
        $xml = simplexml_load_string($sheet, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOBLANKS);
        if (! $xml) {
            throw \Illuminate\Validation\ValidationException::withMessages(['file' => 'The Excel workbook contains invalid worksheet XML.']);
        }
        $rows = [];
        $mainNamespace = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $sheetData = $xml->children($mainNamespace)->sheetData;
        foreach ($sheetData->children($mainNamespace)->row ?? [] as $row) {
            $values = [];
            foreach ($row->children($mainNamespace)->c as $cell) {
                $attributes = $cell->attributes();
                preg_match('/^([A-Z]+)/', (string) $attributes['r'], $column);
                $columnIndex = $this->columnNumber($column[1] ?? 'A');
                $cellNodes = $cell->children($mainNamespace);
                $value = (string) ($cellNodes->v ?? '');
                if ((string) $attributes['t'] === 's') {
                    $value = $sharedStrings[(int) $value] ?? '';
                } elseif ((string) $attributes['t'] === 'inlineStr') {
                    $value = (string) ($cellNodes->is->children($mainNamespace)->t ?? '');
                }
                $values[$columnIndex] = $value;
            }
            if ($values) {
                ksort($values);
                $rowValues = array_fill(0, max(array_keys($values)) + 1, '');
                foreach ($values as $index => $value) {
                    $rowValues[$index] = $value;
                }
                $rows[] = $rowValues;
            }
        }

        return $rows;
    }

    private function columnNumber(string $letters): int
    {
        $number = 0;
        foreach (str_split($letters) as $letter) {
            $number = $number * 26 + ord($letter) - 64;
        }

        return $number - 1;
    }
}
