@extends('layouts.portal')

@section('title', 'Bulk import')
@section('heading', 'Bulk import: '.$definition['label'])
@section('subtitle', 'Download the matching template, upload a completed workbook, and verify every row before import.')

@section('content')
    <section class="panel module-panel import-panel">
        <a class="secondary-button import-back" href="{{ route($definition['index_route'], $definition['index_route_params'] ?? []) }}">← Back to {{ $definition['label'] }}</a>
        <ol class="import-steps">
            <li><strong>Download template</strong><span>Keep the column names and order unchanged. The CSV template opens in Excel.</span><a class="secondary-button" href="{{ route('admin.imports.template', $type) }}">Download template</a></li>
            <li><strong>Upload Excel or CSV</strong><span>Accepted formats: .xlsx, .csv. Maximum 5 MB and 500 data rows.</span>
                <form method="POST" enctype="multipart/form-data" action="{{ route('admin.imports.preview', $type) }}" class="import-upload-form">
                    @csrf
                    <input type="file" name="file" accept=".xlsx,.csv,.txt" required>
                    <button class="primary-button" type="submit">Upload and preview</button>
                </form>
            </li>
            @if(isset($validRows) || isset($rowErrors))
                <li class="import-preview-step">
                    <strong>Review preview</strong>
                    @if (!empty($rowErrors))
                        <div class="notice notice-error"><strong>Import is blocked until the file is corrected.</strong>
                            <ul>@foreach($rowErrors as $issue)<li>Row {{ $issue['row'] }}: {{ implode(' ', $issue['errors']) }}</li>@endforeach</ul>
                        </div>
                    @elseif(!empty($validRows))
                        <p>{{ count($validRows) }} row(s) passed validation. Review the values below before continuing.</p>
                        <div class="table-wrap import-preview-table"><table class="data-table"><thead><tr>@foreach($definition['headers'] as $header)<th>{{ str_replace('_', ' ', $header) }}</th>@endforeach</tr></thead><tbody>
                            @foreach($validRows as $row)<tr>@foreach($definition['headers'] as $header)<td>{{ $row[$header] ?? '' }}</td>@endforeach</tr>@endforeach
                        </tbody></table></div>
                        <form method="POST" action="{{ route('admin.imports.commit', $type) }}" class="import-confirm-form" data-confirm="This will import {{ count($validRows) }} validated record(s). Continue?">
                            @csrf<input type="hidden" name="preview_token" value="{{ $token }}">
                            <label class="checkbox-line"><input type="checkbox" name="verified" value="1" required> I reviewed the preview and verified that the spreadsheet data is correct.</label>
                            <button class="primary-button" type="submit">Confirm and import</button>
                        </form>
                    @else
                        <p class="empty-inline">No non-empty data rows were found.</p>
                    @endif
                </li>
            @endif
        </ol>
        <aside class="notice notice-info"><strong>Template columns</strong><p>{{ implode(', ', $definition['headers']) }}</p>
            @if($type === 'users')<p>role: student/staff/admin; status: activated/deactivated. For staff/admin, use an existing department_code, office_code, and position. For students, course_code, year, and section must match active options. Imported accounts receive a random password; users should use Forgot password to set their own.</p>@endif
            @if($type === 'departments')<p>status must be active or inactive. Department codes and names must be unique.</p>@endif
            @if($type === 'offices')<p>status must be active or inactive. Office codes and names must be unique.</p>@endif
            @if($type === 'positions')<p>role must be both, staff, or admin. Position names must be unique for each role.</p>@endif
            @if($type === 'courses')<p>Course codes and names must be unique.</p>@endif
            @if($type === 'years')<p>course_code must match an existing active course. Each course can have one record per year level.</p>@endif
            @if($type === 'sections')<p>course_code and year_level must match an existing year. Section names must be unique within that course and year.</p>@endif
            @if($type === 'document-types')<p>is_active must be 1 or 0. Processing days must be 1–365; fees are paid at pickup, not through SmartDoc.</p>@endif
            @if($type === 'requirements')<p>document_type must exactly match an existing document type name. is_required must be 1 or 0.</p>@endif
            @if($type === 'announcements')<p>audience: all/admin/staff/student; status: Draft/Published; expires_at uses YYYY-MM-DD or may be blank.</p>@endif
            @if($type === 'faqs')<p>locale is fil or en. Separate question_examples with a vertical bar or new line. Imported FAQs are inactive drafts; an administrator must review and approve them before use.</p>@endif
        </aside>
    </section>
@endsection
