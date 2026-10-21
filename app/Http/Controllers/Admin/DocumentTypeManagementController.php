<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DocumentRequirement;
use App\Models\DocumentType;
use App\Services\DocumentProcessingEstimator;
use App\Services\PortalNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DocumentTypeManagementController extends Controller
{
    public function index(Request $request, DocumentProcessingEstimator $estimator): View
    {
        $showArchived = $request->boolean('archived');
        $showArchivedRequirements = $request->boolean('archived_requirements');
        $documentTypes = DocumentType::query()
            ->when(! $showArchivedRequirements, fn ($query) => $showArchived ? $query->archived() : $query->unarchived())
            ->with(['requirements' => fn ($query) => $query
                ->when($showArchivedRequirements, fn ($query) => $query->archived(), fn ($query) => $query->unarchived())
                ->withCount('uploads')
                ->orderBy('name')])
            ->withCount(['requirements' => fn ($query) => $query->unarchived(), 'requests'])
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->string('search')->trim()->toString().'%'))
            ->when($request->filled('active'), fn ($query) => $query->where('is_active', $request->boolean('active')))
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();
        $processingEstimates = $documentTypes->getCollection()->mapWithKeys(
            fn (DocumentType $type) => [$type->id => $estimator->estimate($type->id)]
        );

        return view('admin.document-types.index', compact('documentTypes', 'processingEstimates'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules());
        $data['is_active'] = $request->boolean('is_active');
        $documentType = DocumentType::create($data);
        if ($documentType->is_active) {
            app(PortalNotifier::class)->documentTypeActivated($documentType);
        }

        return back()->with('status', 'Document type created.');
    }

    public function update(Request $request, DocumentType $documentType): RedirectResponse
    {
        abort_if($documentType->archived_at !== null, 409, 'Restore the document type before editing it.');

        $wasActive = $documentType->is_active;
        $data = $request->validate($this->rules($documentType));
        $data['is_active'] = $request->boolean('is_active');
        $documentType->update($data);
        if (! $wasActive && $documentType->is_active) {
            app(PortalNotifier::class)->documentTypeActivated($documentType);
        }

        return back()->with('status', 'Document type updated.');
    }

    public function archive(DocumentType $documentType): RedirectResponse
    {
        DB::transaction(function () use ($documentType) {
            $documentType->update(['is_active' => false]);
            $documentType->archive();
        });

        return back()->with('status', 'Document type archived and deactivated. Existing request history is preserved.');
    }

    public function restore(DocumentType $documentType): RedirectResponse
    {
        $documentType->restoreArchive();

        return back()->with('status', 'Document type restored. It remains inactive until reactivated.');
    }

    public function bulkArchive(Request $request): RedirectResponse
    {
        $types = $this->selectedTypes($request, false);
        DB::transaction(function () use ($types) {
            foreach ($types as $type) {
                $type->update(['is_active' => false]);
                $type->archive();
            }
        });

        return back()->with('status', $types->count().' document type(s) archived.');
    }

    public function bulkRestore(Request $request): RedirectResponse
    {
        $types = $this->selectedTypes($request, true);
        DB::transaction(fn () => $types->each(fn (DocumentType $type) => $type->restoreArchive()));

        return back()->with('status', $types->count().' document type(s) restored. They remain inactive until reactivated.');
    }

    public function storeRequirement(Request $request, DocumentType $documentType): RedirectResponse
    {
        abort_if($documentType->archived_at !== null, 409, 'Restore the document type before changing its requirements.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('document_requirements', 'name')->where('document_type_id', $documentType->id)],
            'description' => ['required', 'string', 'max:150'],
            'is_required' => ['required', 'boolean'],
        ]);
        $documentType->requirements()->create($data);

        return back()->with('status', 'Requirement added to '.$documentType->name.'.');
    }

    public function updateRequirement(Request $request, DocumentType $documentType, DocumentRequirement $requirement): RedirectResponse
    {
        abort_if($requirement->archived_at !== null || $documentType->archived_at !== null, 409, 'Restore this record before editing it.');

        abort_unless($requirement->document_type_id === $documentType->id, 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('document_requirements', 'name')->where('document_type_id', $documentType->id)->ignore($requirement->id)],
            'description' => ['required', 'string', 'max:150'],
            'is_required' => ['required', 'boolean'],
        ]);
        $requirement->update($data);

        return back()->with('status', 'Requirement updated.');
    }

    public function archiveRequirement(DocumentType $documentType, DocumentRequirement $requirement): RedirectResponse
    {
        abort_unless($requirement->document_type_id === $documentType->id, 404);
        $requirement->archive();

        return back()->with('status', 'Requirement archived. Existing uploads and request history are preserved.');
    }

    public function restoreRequirement(DocumentType $documentType, DocumentRequirement $requirement): RedirectResponse
    {
        abort_unless($requirement->document_type_id === $documentType->id, 404);
        $requirement->restoreArchive();

        return back()->with('status', 'Requirement restored.');
    }

    public function bulkArchiveRequirements(Request $request): RedirectResponse
    {
        $requirements = $this->selectedRequirements($request, false);
        DB::transaction(fn () => $requirements->each(fn (DocumentRequirement $requirement) => $requirement->archive()));

        return back()->with('status', $requirements->count().' requirement(s) archived.');
    }

    public function bulkRestoreRequirements(Request $request): RedirectResponse
    {
        $requirements = $this->selectedRequirements($request, true);
        DB::transaction(fn () => $requirements->each(fn (DocumentRequirement $requirement) => $requirement->restoreArchive()));

        return back()->with('status', $requirements->count().' requirement(s) restored.');
    }

    private function selectedTypes(Request $request, bool $archived)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'distinct'],
        ]);
        $query = DocumentType::whereIn('id', $data['ids']);
        $archived ? $query->archived() : $query->unarchived();
        $types = $query->get();
        if ($types->count() !== count($data['ids'])) {
            throw ValidationException::withMessages(['ids' => 'Some selected document types are no longer available for this action.']);
        }

        return $types;
    }

    private function selectedRequirements(Request $request, bool $archived)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'distinct'],
        ]);
        $query = DocumentRequirement::whereIn('id', $data['ids']);
        $archived ? $query->archived() : $query->unarchived();
        $requirements = $query->get();
        if ($requirements->count() !== count($data['ids'])) {
            throw ValidationException::withMessages(['ids' => 'Some selected requirements are no longer available for this action.']);
        }

        return $requirements;
    }

    private function rules(?DocumentType $documentType = null): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('document_types', 'name')->ignore($documentType?->id)],
            'description' => ['required', 'string', 'max:5000'],
            'processing_days' => ['required', 'integer', 'min:1', 'max:365'],
            'fee' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
