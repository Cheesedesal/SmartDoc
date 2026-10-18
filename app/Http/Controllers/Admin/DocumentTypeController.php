<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DocumentRequirementRequest;
use App\Http\Requests\Admin\DocumentTypeRequest;
use App\Models\DocumentRequirement;
use App\Models\DocumentType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DocumentTypeController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'active' => (string) $request->query('active', ''),
        ];

        $query = DocumentType::query()->withCount('requirements')->orderBy('id');

        if ($filters['q'] !== '') {
            $query->where('name', 'like', '%'.addcslashes($filters['q'], '%_\\').'%');
        }
        if (in_array($filters['active'], ['1', '0'], true)) {
            $query->where('is_active', $filters['active'] === '1');
        }

        $editing = ctype_digit((string) $request->query('edit')) ? DocumentType::find((int) $request->query('edit')) : null;

        return view('admin.document-types.index', [
            'types' => $query->get(),
            'filters' => $filters,
            'editing' => $editing,
            'counts' => [
                'types' => DocumentType::count(),
                'requirements' => DocumentRequirement::count(),
                'active' => DocumentType::where('is_active', true)->count(),
            ],
        ]);
    }

    public function store(DocumentTypeRequest $request): RedirectResponse
    {
        DocumentType::create($request->validated());

        return redirect()->route('admin.document-types.index')->with('status', 'Document type added successfully.');
    }

    public function update(DocumentTypeRequest $request, DocumentType $documentType): RedirectResponse
    {
        $documentType->update($request->validated());

        return redirect()->route('admin.document-types.index')->with('status', 'Document type updated.');
    }

    public function destroy(DocumentType $documentType): RedirectResponse
    {
        // document_requests.document_type_id is RESTRICT: keep history intact, deactivate instead.
        if ($documentType->requests()->exists()) {
            return back()->withErrors(['request' => 'Requests exist for this document type, so it cannot be deleted. Set it to inactive to hide it from students.']);
        }

        $documentType->delete(); // its requirements are removed by the FK cascade

        return redirect()->route('admin.document-types.index')->with('status', 'Document type deleted.');
    }

    /** Port of documentconfigure.php: manage one document's requirements. */
    public function configure(Request $request, DocumentType $documentType): View
    {
        $editing = ctype_digit((string) $request->query('edit'))
            ? $documentType->requirements()->find((int) $request->query('edit'))
            : null;

        return view('admin.document-types.configure', [
            'documentType' => $documentType,
            'requirements' => $documentType->requirements()->orderBy('id')->get(),
            'editing' => $editing,
        ]);
    }

    public function storeRequirement(DocumentRequirementRequest $request, DocumentType $documentType): RedirectResponse
    {
        $documentType->requirements()->create($request->validated());

        return redirect()->route('admin.document-types.configure', $documentType)->with('status', 'Requirement added.');
    }

    public function updateRequirement(DocumentRequirementRequest $request, DocumentRequirement $requirement): RedirectResponse
    {
        $requirement->update($request->validated());

        return redirect()->route('admin.document-types.configure', $requirement->document_type_id)->with('status', 'Requirement updated.');
    }

    public function destroyRequirement(DocumentRequirement $requirement): RedirectResponse
    {
        // uploaded_requirements cascades from the requirement, so refuse if students already uploaded against it.
        if ($requirement->documentType->requests()->exists()
            && \App\Models\UploadedRequirement::where('document_requirement_id', $requirement->id)->exists()) {
            return back()->withErrors(['request' => 'Students have already uploaded files for this requirement, so it cannot be deleted.']);
        }

        $typeId = $requirement->document_type_id;
        $requirement->delete();

        return redirect()->route('admin.document-types.configure', $typeId)->with('status', 'Requirement deleted.');
    }
}
