<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Models\DocumentRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Rule-based checks used by the automation ("SmartDoc AI").
 *
 * It verifies file integrity/readability, payment-detail consistency and the
 * presence of every required upload. It does NOT read the content of a document
 * or screenshot; inspectFile() and checkPayment() are the seams where a vision/LLM
 * check could be added later.
 */
class RequestEvaluator
{
    private const ALLOWED_IMAGE_TYPES = [IMAGETYPE_JPEG, IMAGETYPE_PNG];

    /**
     * @return array{passed: bool, payment_ok: bool, confidence: int, issues: array<int, string>}
     */
    public function evaluate(DocumentRequest $request): array
    {
        $request->loadMissing(['documentType.requirements', 'uploads']);

        $issues = [];
        $total = 0;
        $passedChecks = 0;

        // 1. Every required requirement must have a readable upload.
        $uploads = $request->uploads->keyBy('document_requirement_id');

        foreach ($request->documentType->requirements as $requirement) {
            if (! $requirement->is_required) {
                continue;
            }

            $total++;
            $upload = $uploads->get($requirement->id);

            if ($upload === null) {
                $issues[] = "Missing required upload: {$requirement->name}.";

                continue;
            }

            $problems = $this->inspectFile($upload->path);

            if ($problems === []) {
                $passedChecks++;
            } else {
                foreach ($problems as $problem) {
                    $issues[] = "{$requirement->name}: {$problem}";
                }
            }
        }

        // 2. Payment (only when the document has a fee).
        $paymentOk = true;

        if ((float) $request->documentType->fee > 0) {
            $total++;
            $paymentProblems = $this->checkPayment($request);
            $paymentOk = $paymentProblems === [];

            if ($paymentOk) {
                $passedChecks++;
            } else {
                array_push($issues, ...$paymentProblems);
            }
        }

        $confidence = $total === 0 ? 100 : intdiv($passedChecks * 100, $total);

        return [
            'passed' => $issues === [],
            'payment_ok' => $paymentOk,
            'confidence' => $confidence,
            'issues' => $issues,
        ];
    }

    /**
     * @return array<int, string> problems found (empty = file looks fine)
     */
    public function inspectFile(?string $path): array
    {
        $disk = Storage::disk(config('smartdoc.uploads.disk'));

        if ($path === null || $path === '' || ! $disk->exists($path)) {
            return ['the file is missing from storage.'];
        }

        if ($disk->size($path) <= 0) {
            return ['the file is empty.'];
        }

        $stream = $disk->readStream($path);
        $head = is_resource($stream) ? (string) fread($stream, 8) : '';

        if (is_resource($stream)) {
            fclose($stream);
        }

        if (str_starts_with($head, '%PDF')) {
            return [];
        }

        $info = @getimagesize($disk->path($path));

        if ($info === false || ! in_array($info[2], self::ALLOWED_IMAGE_TYPES, true)) {
            return ['the file is not a valid PDF, JPG or PNG, or it is corrupted.'];
        }

        $min = (int) config('smartdoc.uploads.min_image_side');

        if ($info[0] < $min || $info[1] < $min) {
            return ["the image is too small to be readable (at least {$min}px per side)."];
        }

        return [];
    }

    /**
     * @return array<int, string>
     */
    public function checkPayment(DocumentRequest $request): array
    {
        $issues = [];

        if (PaymentMethod::tryFrom((string) $request->payment_method) === null) {
            $issues[] = 'The payment method is missing or not recognised.';
        }

        if (! preg_match('/^[A-Za-z0-9\-]{8,40}$/', (string) $request->payment_reference)) {
            $issues[] = 'The payment reference number must be 8-40 letters, digits or dashes.';
        }

        $expected = round((float) $request->documentType->fee, 2);

        if (round((float) $request->payment_amount, 2) !== $expected) {
            $issues[] = 'The amount paid does not match the document fee.';
        }

        $paidOn = $request->payment_paid_on;

        if ($paidOn === null) {
            $issues[] = 'The payment date is missing.';
        } elseif ($paidOn->isFuture()) {
            $issues[] = 'The payment date is in the future.';
        } elseif ($paidOn->lt(Carbon::today()->subDays((int) config('smartdoc.payment.max_age_days')))) {
            $issues[] = 'The payment is too old to be accepted for this request.';
        }

        foreach ($this->inspectFile($request->payment_proof_path) as $problem) {
            $issues[] = "Payment proof: {$problem}";
        }

        return $issues;
    }
}
