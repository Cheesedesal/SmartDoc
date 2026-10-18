<?php

namespace App\Services;

use App\Enums\RequestStatus;
use App\Models\DocumentRequest;
use App\Models\DocumentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\Rule;

/**
 * Single source of truth for the request-record history: which requests belong to it, how the
 * filters are validated and applied, and how the report summary is counted. The records page,
 * the printable report and the CSV export all go through this class, so they can never disagree.
 *
 * Scope: finished requests only (Completed, plus legacy Rejected rows). Open and cancelled
 * requests never appear, whatever the filters say.
 */
class RequestRecordFilter
{
    /**
     * Validate raw query-string input. Invalid fields are dropped from the returned filters and
     * reported in the message bag, so callers decide whether to show the errors or refuse.
     *
     * @param  array<string, mixed>  $input
     * @return array{filters: array<string, string>, errors: MessageBag}
     */
    public function resolve(array $input): array
    {
        $validator = Validator::make($input, [
            'search' => ['nullable', 'string', 'max:100'],
            'document_type_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', Rule::in(RequestStatus::recordValues())],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);

        $valid = $validator->valid();

        return [
            'filters' => [
                'search' => trim((string) ($valid['search'] ?? '')),
                'document_type_id' => (string) ($valid['document_type_id'] ?? ''),
                'status' => (string) ($valid['status'] ?? ''),
                'date_from' => (string) ($valid['date_from'] ?? ''),
                'date_to' => (string) ($valid['date_to'] ?? ''),
            ],
            'errors' => $validator->errors(),
        ];
    }

    /**
     * The filtered history. No ordering and no eager loading, so it can be reused for counting;
     * callers add `with()` and ordering when they need rows.
     *
     * @param  array<string, string>  $filters  as returned by resolve()
     * @return Builder<DocumentRequest>
     */
    public function query(array $filters): Builder
    {
        $query = DocumentRequest::query()->records();

        $search = $filters['search'] ?? '';

        if ($search !== '') {
            $query->where(function ($w) use ($search) {
                // Every word must match the student's first name, surname or school ID.
                $w->whereHas('student', function ($s) use ($search) {
                    foreach (preg_split('/\s+/', $search) ?: [] as $term) {
                        $like = '%'.addcslashes($term, '%_\\').'%';

                        $s->where(fn ($x) => $x
                            ->where('firstname', 'like', $like)
                            ->orWhere('surname', 'like', $like)
                            ->orWhere('school_id', 'like', $like));
                    }
                });

                // "REQ-007", "req007" or "7" match request #7 (same rule as the request queue).
                if (preg_match('/^(?:REQ-?)?0*(\d+)$/i', $search, $m)) {
                    $w->orWhere('id', (int) $m[1]);
                }
            });
        }

        if (($filters['document_type_id'] ?? '') !== '') {
            $query->where('document_type_id', (int) $filters['document_type_id']);
        }

        if (($filters['status'] ?? '') !== '') {
            $query->where('status', $filters['status']);
        }

        // The reference filters on the date the request was made (requested_at == created_at).
        if (($filters['date_from'] ?? '') !== '') {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (($filters['date_to'] ?? '') !== '') {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query;
    }

    /**
     * Counts for the filtered history, grouped by document type and status in one query. Every
     * request has exactly one type and one status, so nothing can be counted twice.
     *
     * @param  array<string, string>  $filters
     * @return array{total: int, completed: int, rejected: int, by_type: array<int, array{name: string, completed: int, rejected: int, total: int}>}
     */
    public function summary(array $filters): array
    {
        // toBase(): plain rows, so the status column is not cast to an enum while grouping.
        $rows = $this->query($filters)
            ->toBase()
            ->selectRaw('document_type_id, status, count(*) as total')
            ->groupBy('document_type_id', 'status')
            ->get();

        $names = DocumentType::query()
            ->whereIn('id', $rows->pluck('document_type_id')->unique()->all())
            ->pluck('name', 'id');

        $byType = [];
        $completed = 0;
        $rejected = 0;

        foreach ($rows as $row) {
            $typeId = (int) $row->document_type_id;
            $count = (int) $row->total;

            $byType[$typeId] ??= [
                'name' => (string) ($names[$typeId] ?? 'Unknown document'),
                'completed' => 0,
                'rejected' => 0,
                'total' => 0,
            ];

            if ($row->status === RequestStatus::Completed->value) {
                $byType[$typeId]['completed'] += $count;
                $completed += $count;
            } elseif ($row->status === RequestStatus::Rejected->value) {
                $byType[$typeId]['rejected'] += $count;
                $rejected += $count;
            }

            $byType[$typeId]['total'] = $byType[$typeId]['completed'] + $byType[$typeId]['rejected'];
        }

        $byType = array_values($byType);

        usort($byType, fn ($a, $b) => [$b['total'], $a['name']] <=> [$a['total'], $b['name']]);

        return [
            'total' => $completed + $rejected,
            'completed' => $completed,
            'rejected' => $rejected,
            'by_type' => $byType,
        ];
    }

    /**
     * Human-readable list of the filters in force, for the printed report header.
     *
     * @param  array<string, string>  $filters
     * @return array<int, string>
     */
    public function describe(array $filters): array
    {
        $parts = [];

        if (($filters['search'] ?? '') !== '') {
            $parts[] = 'Search: '.$filters['search'];
        }

        if (($filters['document_type_id'] ?? '') !== '') {
            $name = DocumentType::query()->whereKey((int) $filters['document_type_id'])->value('name');
            $parts[] = 'Document: '.($name ?? 'Unknown document');
        }

        if (($filters['status'] ?? '') !== '') {
            $parts[] = 'Status: '.$filters['status'];
        }

        if (($filters['date_from'] ?? '') !== '') {
            $parts[] = 'Requested from: '.$filters['date_from'];
        }

        if (($filters['date_to'] ?? '') !== '') {
            $parts[] = 'Requested to: '.$filters['date_to'];
        }

        return $parts;
    }
}
