<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    protected $fillable = ['actor_id', 'action', 'subject_type', 'subject_id', 'subject_label', 'changes', 'ip_address'];

    protected $casts = ['changes' => 'array'];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function getRecordLabelAttribute(): string
    {
        if ($this->subject_label) {
            return $this->subject_label;
        }

        $subject = $this->subject;
        foreach (['full_name', 'name', 'title', 'reference', 'canonical_question', 'intent'] as $attribute) {
            if ($subject?->{$attribute}) {
                return (string) $subject->{$attribute};
            }
        }

        return self::subjectKind($this->subject_type).' #'.($this->subject_id ?? '—');
    }

    public function getActivityDescriptionAttribute(): string
    {
        $record = 'Record: '.$this->record_label;
        $kind = self::subjectKind($this->subject_type);
        if ($this->action === 'login') {
            return 'Logged in — '.$record;
        }
        if (in_array($this->action, ['archived', 'restored'], true)) {
            return ucfirst($this->action).' '.$kind.' — '.$record;
        }
        if ($this->action === 'created') {
            $detail = $this->subject_type === User::class && $this->subject?->role
                ? ' a new '.$this->subject->role->label().' account'
                : ' a new '.$kind;

            return 'Created'.$detail.' — '.$record;
        }

        $fields = collect($this->changes ?? [])
            ->map(fn ($change, $field) => is_array($change) && array_key_exists('from', $change) && array_key_exists('to', $change)
                ? self::fieldLabel($field).': '.self::displayValue($change['from']).' → '.self::displayValue($change['to'])
                : self::fieldLabel($field))
            ->unique()
            ->take(3)
            ->implode('; ');

        return 'Updated '.($fields !== '' ? $fields : $kind).' — '.$record;
    }

    private static function displayValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'None';
        }
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        $value = trim((string) $value);

        return mb_strlen($value) > 100 ? mb_substr($value, 0, 97).'…' : $value;
    }

    private static function subjectKind(?string $type): string
    {
        return match ($type) {
            User::class => 'user account',
            \App\Models\DocumentType::class => 'document type',
            \App\Models\DocumentRequest::class => 'document request',
            \App\Models\DocumentRequirement::class => 'requirement',
            \App\Models\Department::class => 'department',
            \App\Models\Announcement::class => 'announcement',
            \App\Models\ChatbotFaq::class => 'FAQ',
            \App\Models\Office::class => 'office',
            \App\Models\Position::class => 'position',
            \App\Models\Course::class => 'course',
            \App\Models\StudentYear::class => 'year level',
            \App\Models\Section::class => 'section',
            \App\Models\UploadedRequirement::class => 'uploaded requirement',
            default => class_basename($type ?: 'record'),
        };
    }

    private static function fieldLabel(string $field): string
    {
        return match ($field) {
            'firstname' => 'first name',
            'surname' => 'surname',
            'school_id' => 'school ID',
            'department_id' => 'department',
            'office_id' => 'office',
            'position_id' => 'position',
            'course_id' => 'course',
            'student_year_id' => 'year level',
            'section_id' => 'section',
            'is_active' => 'availability',
            default => strtolower(str_replace('_', ' ', $field)),
        };
    }
}
