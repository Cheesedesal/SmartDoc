<?php

namespace App\Http\Controllers;

use App\Models\DocumentRequest;
use App\Models\UploadedRequirement;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams private uploads. Files live on a non-public disk, so this is the only way
 * to read them, and only the owning student or staff/admin may (DocumentRequestPolicy::view).
 */
class RequestFileController extends Controller
{
    public function requirement(DocumentRequest $documentRequest, UploadedRequirement $upload): StreamedResponse
    {
        $this->authorize('view', $documentRequest);

        // The upload must belong to the request in the URL (no cross-request access).
        abort_unless((int) $upload->document_request_id === (int) $documentRequest->id, 404);

        return $this->serve($upload->path, $upload->original_name);
    }

    public function paymentProof(DocumentRequest $documentRequest): StreamedResponse
    {
        $this->authorize('view', $documentRequest);

        abort_if(empty($documentRequest->payment_proof_path), 404);

        return $this->serve(
            $documentRequest->payment_proof_path,
            'payment-proof-'.$documentRequest->reference
        );
    }

    private function serve(?string $path, string $suggestedName): StreamedResponse
    {
        abort_if($path === null || $path === '' || str_contains($path, '..'), 404);

        $disk = Storage::disk(config('smartdoc.uploads.disk'));
        abort_unless($disk->exists($path), 404);

        // The client-supplied name is only a hint; strip it down to safe characters.
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', basename($suggestedName)) ?: 'file';
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        if ($extension !== '' && ! str_ends_with(strtolower($name), '.'.strtolower($extension))) {
            $name .= '.'.$extension;
        }

        return $disk->download($path, $name, [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
