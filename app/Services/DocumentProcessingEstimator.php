<?php

namespace App\Services;

use App\Models\DocumentRequest;
use Carbon\Carbon;

class DocumentProcessingEstimator
{
    /**
     * @return array{days: int|null, sample_size: int}
     */
    public function estimate(int $documentTypeId): array
    {
        $durations = DocumentRequest::query()
            ->where('document_type_id', $documentTypeId)
            ->whereNotNull('completed_at')
            ->orderByDesc('completed_at')
            ->limit(100)
            ->get(['created_at', 'completed_at'])
            ->map(static fn (DocumentRequest $request) => max(
                1,
                Carbon::parse($request->created_at)->diffInWeekdays(Carbon::parse($request->completed_at))
            ))
            ->sort()
            ->values();

        if ($durations->count() < 3) {
            return ['days' => null, 'sample_size' => $durations->count()];
        }

        $middle = intdiv($durations->count(), 2);
        $median = $durations->count() % 2 === 1
            ? $durations[$middle]
            : (int) round(($durations[$middle - 1] + $durations[$middle]) / 2);

        return ['days' => $median, 'sample_size' => $durations->count()];
    }
}
