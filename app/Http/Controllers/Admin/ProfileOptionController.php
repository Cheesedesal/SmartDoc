<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Office;
use App\Models\Position;
use App\Models\Section;
use App\Models\StudentYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileOptionController extends Controller
{
    private function definition(string $type): array
    {
        return match ($type) {
            'offices' => [
                'model' => Office::class,
                'title' => 'Office management',
                'subtitle' => 'Manage offices assigned to staff and administrators.',
                'columns' => ['code' => 'Code', 'name' => 'Office', 'users_count' => 'Users'],
                'fields' => ['code' => ['label' => 'Office code', 'type' => 'text', 'max' => 30], 'name' => ['label' => 'Office name', 'type' => 'text', 'max' => 150], 'status' => $this->statusField()],
            ],
            'positions' => [
                'model' => Position::class,
                'title' => 'Position management',
                'subtitle' => 'Manage staff and administrator position options.',
                'columns' => ['name' => 'Position', 'role' => 'Role', 'users_count' => 'Users'],
                'fields' => ['name' => ['label' => 'Position name', 'type' => 'text', 'max' => 100], 'role' => ['label' => 'Applies to', 'type' => 'select', 'options' => ['both' => 'Staff and Admin', 'staff' => 'Staff only', 'admin' => 'Admin only']], 'status' => $this->statusField()],
            ],
            'courses' => [
                'model' => Course::class,
                'title' => 'Course management',
                'subtitle' => 'Manage student courses and programs.',
                'columns' => ['code' => 'Code', 'name' => 'Course / program', 'users_count' => 'Students'],
                'fields' => ['code' => ['label' => 'Course code', 'type' => 'text', 'max' => 30], 'name' => ['label' => 'Course / program name', 'type' => 'text', 'max' => 150], 'status' => $this->statusField()],
            ],
            'years' => [
                'model' => StudentYear::class,
                'title' => 'Year-level management',
                'subtitle' => 'Set valid year levels for each course.',
                'columns' => ['course.name' => 'Course', 'year_level' => 'Year level', 'users_count' => 'Students'],
                'fields' => ['course_id' => ['label' => 'Course / program', 'type' => 'relation', 'relation' => Course::class], 'year_level' => ['label' => 'Year level', 'type' => 'number', 'min' => 1, 'max' => 99], 'status' => $this->statusField()],
            ],
            'sections' => [
                'model' => Section::class,
                'title' => 'Section management',
                'subtitle' => 'Set valid sections for each course and year level.',
                'columns' => ['studentYear.course.name' => 'Course', 'studentYear.year_level' => 'Year', 'name' => 'Section', 'users_count' => 'Students'],
                'fields' => ['student_year_id' => ['label' => 'Course and year level', 'type' => 'relation', 'relation' => StudentYear::class], 'name' => ['label' => 'Section', 'type' => 'text', 'max' => 50], 'status' => $this->statusField()],
            ],
            default => abort(404),
        };
    }

    public function index(Request $request, string $type): View
    {
        $definition = $this->definition($type);
        $model = $definition['model'];
        $query = $model::query()
            ->when($request->boolean('archived'), fn ($query) => $query->archived(), fn ($query) => $query->unarchived())
            ->withCount('users');

        if ($type === 'years') {
            $query->with('course');
        } elseif ($type === 'sections') {
            $query->with('studentYear.course');
        }

        $records = $query->when($request->filled('search'), function ($query) use ($request, $type) {
            $search = '%'.$request->string('search')->trim().'%';
            match ($type) {
                'offices' => $query->where(fn ($q) => $q->where('code', 'like', $search)->orWhere('name', 'like', $search)),
                'positions', 'sections' => $query->where('name', 'like', $search),
                'courses' => $query->where(fn ($q) => $q->where('code', 'like', $search)->orWhere('name', 'like', $search)),
                'years' => $query->whereHas('course', fn ($q) => $q->where('name', 'like', $search)),
                default => null,
            };
        })->orderBy($type === 'years' ? 'year_level' : ($type === 'offices' || $type === 'courses' ? 'name' : ($type === 'positions' ? 'name' : 'name')))
            ->paginate(20)
            ->withQueryString();

        $relations = [];
        foreach ($definition['fields'] as $field => $config) {
            if (($config['type'] ?? null) === 'relation') {
                $relations[$field] = $config['relation']::unarchived()->where('status', 'active')
                    ->when($field === 'student_year_id', fn ($query) => $query->with('course'))
                    ->orderBy($field === 'student_year_id' ? 'year_level' : 'name')
                    ->get();
            }
        }

        return view('admin.profile-options.index', compact('type', 'definition', 'records', 'relations'));
    }

    public function store(Request $request, string $type): RedirectResponse
    {
        $definition = $this->definition($type);
        $data = $request->validate($this->rules($type));
        $definition['model']::create($data);

        return back()->with('status', $this->optionLabel($type).' option created.');
    }

    public function update(Request $request, string $type, int $id): RedirectResponse
    {
        $definition = $this->definition($type);
        $record = $definition['model']::unarchived()->findOrFail($id);
        $data = $request->validate($this->rules($type, $record));
        $record->update($data);

        return back()->with('status', $this->optionLabel($type).' option updated.');
    }

    public function archive(string $type, int $id): RedirectResponse
    {
        $definition = $this->definition($type);
        $record = $definition['model']::unarchived()->findOrFail($id);
        $record->archive();

        return back()->with('status', $this->optionLabel($type).' option archived.');
    }

    public function restore(string $type, int $id): RedirectResponse
    {
        $definition = $this->definition($type);
        $record = $definition['model']::archived()->findOrFail($id);
        $record->restoreArchive();

        return back()->with('status', 'Archived option restored.');
    }

    private function rules(string $type, mixed $record = null): array
    {
        $definition = $this->definition($type);
        $unique = fn (string $table, string $column) => Rule::unique($table, $column)->ignore($record?->id);
        $rules = match ($type) {
            'offices' => [
                'code' => ['required', 'string', 'max:30', $unique('offices', 'code')],
                'name' => ['required', 'string', 'max:150', $unique('offices', 'name')],
            ],
            'positions' => [
                'name' => ['required', 'string', 'max:100', Rule::unique('positions', 'name')->where('role', request()->input('role'))->ignore($record?->id)],
                'role' => ['required', Rule::in(['both', 'staff', 'admin'])],
            ],
            'courses' => [
                'code' => ['required', 'string', 'max:30', $unique('courses', 'code')],
                'name' => ['required', 'string', 'max:150', $unique('courses', 'name')],
            ],
            'years' => [
                'course_id' => ['required', 'integer', Rule::exists('courses', 'id')->where(fn ($q) => $q->whereNull('archived_at')->where('status', 'active'))],
                'year_level' => ['required', 'integer', 'min:1', 'max:99', Rule::unique('student_years', 'year_level')->where('course_id', request()->integer('course_id'))->ignore($record?->id)],
            ],
            'sections' => [
                'student_year_id' => ['required', 'integer', Rule::exists('student_years', 'id')->where(fn ($q) => $q->whereNull('archived_at')->where('status', 'active'))],
                'name' => ['required', 'string', 'max:50', Rule::unique('sections', 'name')->where('student_year_id', request()->integer('student_year_id'))->ignore($record?->id)],
            ],
        };
        foreach ($definition['fields'] as $field => $config) {
            if ($field === 'status') {
                $rules[$field] = ['required', Rule::in(['active', 'inactive'])];
            }
        }

        return $rules;
    }

    private function statusField(): array
    {
        return ['label' => 'Status', 'type' => 'select', 'options' => ['active' => 'Active', 'inactive' => 'Inactive']];
    }

    private function optionLabel(string $type): string
    {
        return match ($type) {
            'offices' => 'Office',
            'positions' => 'Position',
            'courses' => 'Course',
            'years' => 'Year',
            'sections' => 'Section',
        };
    }
}
