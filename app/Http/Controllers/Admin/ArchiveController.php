<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\ChatbotFaq;
use App\Models\Department;
use App\Models\DocumentRequirement;
use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ArchiveController extends Controller
{
    private const TYPES = [
        'users' => [User::class, 'User accounts'],
        'departments' => [Department::class, 'Departments'],
        'announcements' => [Announcement::class, 'Announcements'],
        'document-types' => [DocumentType::class, 'Document types'],
        'requirements' => [DocumentRequirement::class, 'Requirements'],
        'faqs' => [ChatbotFaq::class, 'FAQs'],
    ];

    public function index(Request $request): View
    {
        $type = $request->query('type', 'users');
        abort_unless(isset(self::TYPES[$type]), 404);

        [$model, $label] = self::TYPES[$type];
        $records = $model::query()->archived()->latest()->paginate(20)->withQueryString();

        return view('admin.archive.index', [
            'type' => $type,
            'label' => $label,
            'types' => self::TYPES,
            'records' => $records,
        ]);
    }

    public function restore(Request $request, string $type, int $id): RedirectResponse
    {
        abort_unless(isset(self::TYPES[$type]), 404);
        [$model] = self::TYPES[$type];
        $record = $model::query()->archived()->findOrFail($id);
        $record->restoreArchive();

        return redirect()->route('admin.archive.index', ['type' => $type])
            ->with('status', self::TYPES[$type][1].' record restored.');
    }
}
