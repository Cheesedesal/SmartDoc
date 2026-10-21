<?php

namespace App\Observers;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ActivityLogObserver
{
    private const EXCLUDED = ['password', 'remember_token', 'profile_photo_path', 'path'];

    public function created(Model $model): void
    {
        $this->record($model, 'created');
    }

    public function updated(Model $model): void
    {
        $changes = [];
        foreach ($model->getChanges() as $key => $value) {
            if (in_array($key, self::EXCLUDED, true) || $key === 'updated_at') {
                continue;
            }
            $changes[$key] = ['from' => $model->getOriginal($key), 'to' => $value];
        }

        if ($changes !== []) {
            $action = 'updated';
            if (array_key_exists('archived_at', $changes)) {
                $action = $changes['archived_at']['from'] === null ? 'archived' : 'restored';
            }
            $this->record($model, $action, $this->resolveReferenceChanges($changes));
        }
    }

    public function deleted(Model $model): void
    {
        $this->record($model, 'deleted');
    }

    private function record(Model $model, string $action, array $changes = []): void
    {
        $changes = array_diff_key($changes, array_flip(self::EXCLUDED));
        ActivityLog::create([
            'actor_id' => Auth::id(),
            'action' => $action,
            'subject_type' => $model::class,
            'subject_id' => $model->getKey(),
            'subject_label' => $this->recordLabel($model),
            'changes' => $changes === [] ? null : $changes,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }

    private function recordLabel(Model $model): ?string
    {
        foreach (['full_name', 'name', 'title', 'reference', 'canonical_question', 'intent'] as $attribute) {
            if ($model->getAttribute($attribute)) {
                return (string) $model->getAttribute($attribute);
            }
        }

        return null;
    }

    private function resolveReferenceChanges(array $changes): array
    {
        $references = [
            'department_id' => ['departments', 'name'],
            'office_id' => ['offices', 'name'],
            'position_id' => ['positions', 'name'],
            'course_id' => ['courses', 'name'],
            'student_year_id' => ['student_years', 'year_level'],
            'section_id' => ['sections', 'name'],
        ];

        foreach ($references as $field => [$table, $label]) {
            if (! isset($changes[$field])) {
                continue;
            }
            foreach (['from', 'to'] as $direction) {
                $id = $changes[$field][$direction] ?? null;
                $value = $id === null ? null : DB::table($table)->where('id', $id)->value($label);
                $changes[$field][$direction] = $value === null ? 'None' : (string) $value;
            }
        }

        return $changes;
    }
}
