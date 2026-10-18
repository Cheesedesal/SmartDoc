<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Batch 3: printable request report and CSV export (port of reference requestreport.php).
 * Both endpoints are staff/admin only, read-only, and driven by the same validated filters as
 * the records page. Expected values below are derived by hand from the fixtures in each test.
 */
class RequestReportTest extends TestCase
{
    use RefreshDatabase;

    private const CSV_HEADINGS = [
        'Request ID', 'Student Name', 'Email', 'Course', 'Year', 'Section', 'Document Type',
        'Status', 'Requested At', 'Completed At', 'Rejected At', 'Rejection Reason',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['smartdoc.automation.tick_seconds' => 0]);
    }

    // ---- access control -------------------------------------------------------

    public function test_guests_are_redirected_to_login_for_the_report_and_the_export(): void
    {
        $this->get(route('request-reports.show'))->assertRedirect(route('login'));
        $this->get(route('request-reports.export'))->assertRedirect(route('login'));
    }

    public function test_students_cannot_open_the_report_or_download_the_export(): void
    {
        $student = $this->user('alice', Role::Student);
        $this->makeRequest($student, ['status' => RequestStatus::Completed]);

        $this->actingAs($student)->get(route('request-reports.show'))->assertForbidden();
        $this->actingAs($student)->get(route('request-reports.export'))->assertForbidden();

        // Valid filters do not change the answer.
        $query = ['status' => 'Completed', 'search' => 'alice'];
        $this->actingAs($student)->get(route('request-reports.show', $query))->assertForbidden();
        $this->actingAs($student)->get(route('request-reports.export', $query))->assertForbidden();
    }

    public function test_staff_and_admin_can_open_the_report_and_download_the_export(): void
    {
        $this->makeRequest($this->user('alice', Role::Student), ['status' => RequestStatus::Completed]);

        foreach ([Role::Staff, Role::Admin] as $role) {
            $viewer = $this->user('v'.$role->value, $role);

            $this->actingAs($viewer)->get(route('request-reports.show'))
                ->assertOk()
                ->assertSee('Request Record History Report')
                ->assertSee('Print / Save as PDF')
                ->assertSee('Generated')
                ->assertSee('alice Test');

            $this->actingAs($viewer)->get(route('request-reports.export'))->assertOk();
        }
    }

    public function test_the_report_endpoints_are_read_only(): void
    {
        $staff = $this->user('st1', Role::Staff);
        $this->makeRequest($this->user('alice', Role::Student), ['status' => RequestStatus::Completed]);

        foreach (['/request-reports', '/request-reports/export'] as $url) {
            $this->actingAs($staff)->post($url)->assertStatus(405);
            $this->actingAs($staff)->put($url)->assertStatus(405);
            $this->actingAs($staff)->delete($url)->assertStatus(405);
        }

        $this->assertSame(1, DocumentRequest::query()->count());
    }

    // ---- totals, status categories and document-type breakdown -------------------

    public function test_summary_counts_only_finished_requests_and_breaks_them_down_by_document_type(): void
    {
        $staff = $this->user('st1', Role::Staff);
        $goodMoral = $this->documentType('Good Moral');
        $transcript = $this->documentType('Transcript');

        // Finished: 3 completed + 1 legacy rejected.
        $this->makeRequest($this->user('alice', Role::Student), ['document_type_id' => $goodMoral->id, 'status' => RequestStatus::Completed]);
        $this->makeRequest($this->user('bob', Role::Student), ['document_type_id' => $goodMoral->id, 'status' => RequestStatus::Completed]);
        $this->makeRequest($this->user('cara', Role::Student), ['document_type_id' => $goodMoral->id, 'status' => 'Rejected']);
        $this->makeRequest($this->user('dan', Role::Student), ['document_type_id' => $transcript->id, 'status' => RequestStatus::Completed]);

        // Noise that must never be counted.
        $this->makeRequest($this->user('erin', Role::Student), ['document_type_id' => $goodMoral->id, 'status' => RequestStatus::Pending]);
        $this->makeRequest($this->user('fay', Role::Student), ['document_type_id' => $transcript->id, 'status' => RequestStatus::Cancelled]);
        $this->makeRequest($this->user('gus', Role::Student), ['document_type_id' => $transcript->id, 'status' => RequestStatus::InProgress]);
        $this->makeRequest($this->user('hal', Role::Student), ['document_type_id' => $goodMoral->id, 'status' => RequestStatus::ReadyForPickup]);

        $response = $this->actingAs($staff)->get(route('request-reports.show'))->assertOk();

        $this->assertSame([
            'total' => 4,
            'completed' => 3,
            'rejected' => 1,
            'by_type' => [
                ['name' => 'Good Moral', 'completed' => 2, 'rejected' => 1, 'total' => 3],
                ['name' => 'Transcript', 'completed' => 1, 'rejected' => 0, 'total' => 1],
            ],
        ], $response->viewData('summary'));

        $response->assertSee('alice Test')->assertSee('dan Test')
            ->assertDontSee('erin Test')->assertDontSee('fay Test')
            ->assertDontSee('gus Test')->assertDontSee('hal Test');
    }

    public function test_a_student_with_several_requests_is_not_double_counted_by_the_search(): void
    {
        $staff = $this->user('st1', Role::Staff);
        $typeA = $this->documentType('Good Moral');
        $typeB = $this->documentType('Transcript');
        $alice = $this->user('alice', Role::Student);

        $this->makeRequest($alice, ['document_type_id' => $typeA->id, 'status' => RequestStatus::Completed, 'created_at' => '2026-01-10 09:00:00']);
        $this->makeRequest($alice, ['document_type_id' => $typeA->id, 'status' => 'Rejected', 'created_at' => '2026-01-11 09:00:00']);
        $this->makeRequest($alice, ['document_type_id' => $typeB->id, 'status' => RequestStatus::Completed, 'created_at' => '2026-01-12 09:00:00']);
        $this->makeRequest($this->user('alicia', Role::Student), ['document_type_id' => $typeB->id, 'status' => RequestStatus::Completed]);

        // Two search terms, the school ID, and a name: each must still yield exactly alice's three rows.
        foreach (['alice Test', 'alice-001', 'alice'] as $search) {
            $response = $this->actingAs($staff)->get(route('request-reports.show', ['search' => $search]))->assertOk();

            $summary = $response->viewData('summary');

            $this->assertSame(3, $summary['total'], "search: {$search}");
            $this->assertSame(2, $summary['completed'], "search: {$search}");
            $this->assertSame(1, $summary['rejected'], "search: {$search}");
            $this->assertSame(3, $response->viewData('requests')->count(), "search: {$search}");
        }
    }

    // ---- filters --------------------------------------------------------------

    public function test_status_and_document_type_filters(): void
    {
        $staff = $this->user('st1', Role::Staff);
        $goodMoral = $this->documentType('Good Moral');
        $transcript = $this->documentType('Transcript');

        $this->makeRequest($this->user('alice', Role::Student), ['document_type_id' => $goodMoral->id, 'status' => RequestStatus::Completed]);
        $this->makeRequest($this->user('bob', Role::Student), ['document_type_id' => $transcript->id, 'status' => 'Rejected']);
        $this->makeRequest($this->user('cara', Role::Student), ['document_type_id' => $transcript->id, 'status' => RequestStatus::Completed]);

        $byType = $this->actingAs($staff)->get(route('request-reports.show', ['document_type_id' => $transcript->id]))->assertOk();
        $this->assertSame(2, $byType->viewData('summary')['total']);
        $byType->assertSee('bob Test')->assertSee('cara Test')->assertDontSee('alice Test')
            ->assertSee('Document: Transcript');

        $byStatus = $this->actingAs($staff)->get(route('request-reports.show', ['status' => 'Rejected']))->assertOk();
        $this->assertSame(['total' => 1, 'completed' => 0, 'rejected' => 1], collect($byStatus->viewData('summary'))->only(['total', 'completed', 'rejected'])->all());
        $byStatus->assertSee('bob Test')->assertDontSee('alice Test')->assertSee('Status: Rejected');

        $both = $this->actingAs($staff)->get(route('request-reports.show', ['document_type_id' => $goodMoral->id, 'status' => 'Rejected']))->assertOk();
        $this->assertSame(0, $both->viewData('summary')['total']);
        $both->assertSee('No request records found.');
    }

    public function test_date_range_is_inclusive_on_both_ends_and_uses_the_requested_date(): void
    {
        $staff = $this->user('st1', Role::Staff);

        $this->makeRequest($this->user('alice', Role::Student), ['status' => RequestStatus::Completed, 'created_at' => '2026-03-04 23:59:59']); // day before
        $this->makeRequest($this->user('bob', Role::Student), ['status' => RequestStatus::Completed, 'created_at' => '2026-03-05 00:00:00']);   // first second of the day
        $this->makeRequest($this->user('cara', Role::Student), ['status' => RequestStatus::Completed, 'created_at' => '2026-03-05 23:59:59']);  // last second of the day
        $this->makeRequest($this->user('dan', Role::Student), ['status' => RequestStatus::Completed, 'created_at' => '2026-03-06 00:00:00']);   // day after

        $response = $this->actingAs($staff)
            ->get(route('request-reports.show', ['date_from' => '2026-03-05', 'date_to' => '2026-03-05']))
            ->assertOk();

        $this->assertSame(2, $response->viewData('summary')['total']);
        $response->assertSee('bob Test')->assertSee('cara Test')
            ->assertDontSee('alice Test')->assertDontSee('dan Test')
            ->assertSee('Requested from: 2026-03-05 | Requested to: 2026-03-05');

        $fromOnly = $this->actingAs($staff)->get(route('request-reports.show', ['date_from' => '2026-03-06']))->assertOk();
        $this->assertSame(1, $fromOnly->viewData('summary')['total']);
        $fromOnly->assertSee('dan Test')->assertDontSee('cara Test');

        $toOnly = $this->actingAs($staff)->get(route('request-reports.show', ['date_to' => '2026-03-04']))->assertOk();
        $this->assertSame(1, $toOnly->viewData('summary')['total']);
        $toOnly->assertSee('alice Test')->assertDontSee('bob Test');
    }

    public function test_search_by_name_and_request_reference_and_wildcards_are_literal(): void
    {
        $staff = $this->user('st1', Role::Staff);
        $this->makeRequest($this->user('alice', Role::Student), ['status' => RequestStatus::Completed]);
        $forBob = $this->makeRequest($this->user('bob', Role::Student), ['status' => RequestStatus::Completed]);

        $this->actingAs($staff)->get(route('request-reports.show', ['search' => 'alice Test']))
            ->assertOk()->assertSee('alice Test')->assertDontSee('bob Test')->assertSee('Search: alice Test');

        $this->actingAs($staff)->get(route('request-reports.show', ['search' => $forBob->reference]))
            ->assertOk()->assertSee('bob Test')->assertDontSee('alice Test');

        foreach (['%', '_'] as $wildcard) {
            $response = $this->actingAs($staff)->get(route('request-reports.show', ['search' => $wildcard]))->assertOk();

            $this->assertSame(0, $response->viewData('summary')['total'], "wildcard: {$wildcard}");
            $response->assertSee('No request records found.');
        }
    }

    public function test_the_filter_line_lists_the_filters_in_force_or_the_default_description(): void
    {
        $staff = $this->user('st1', Role::Staff);
        $this->makeRequest($this->user('alice', Role::Student), ['status' => RequestStatus::Completed]);

        $this->actingAs($staff)->get(route('request-reports.show'))
            ->assertOk()->assertSee('All Completed and Rejected Requests');

        $this->actingAs($staff)->get(route('request-reports.show', ['status' => 'Completed', 'date_from' => '2026-03-01']))
            ->assertOk()->assertSee('Status: Completed | Requested from: 2026-03-01')
            ->assertDontSee('All Completed and Rejected Requests');
    }

    public function test_invalid_filters_are_refused_and_sent_back_to_the_records_page(): void
    {
        $staff = $this->user('st1', Role::Staff);
        $this->makeRequest($this->user('alice', Role::Student), ['status' => RequestStatus::Completed]);
        $this->makeRequest($this->user('bob', Role::Student), ['status' => RequestStatus::Pending]);

        $invalid = [
            ['status' => 'Pending'],                                       // not a record status
            ['status' => 'Cancelled'],
            ['date_from' => 'not-a-date'],
            ['date_to' => '2026-13-45'],
            ['date_from' => '2026-05-01', 'date_to' => '2026-04-01'],      // impossible range
            ['document_type_id' => 'abc'],
            ['document_type_id' => '0'],
            ['search' => str_repeat('x', 101)],
            ['status' => ['Pending']],                                     // array smuggling
        ];

        foreach ($invalid as $query) {
            $expected = route('request-records.index', $query);

            $this->actingAs($staff)->get(route('request-reports.show', $query))->assertRedirect($expected);
            $this->actingAs($staff)->get(route('request-reports.export', $query))->assertRedirect($expected);
        }
    }

    // ---- empty and legacy data --------------------------------------------------

    public function test_an_empty_history_renders_zero_totals_and_the_empty_message(): void
    {
        $staff = $this->user('st1', Role::Staff);
        // Only open requests exist, so the history is empty.
        $this->makeRequest($this->user('alice', Role::Student), ['status' => RequestStatus::Pending]);

        $response = $this->actingAs($staff)->get(route('request-reports.show'))->assertOk();

        $this->assertSame(['total' => 0, 'completed' => 0, 'rejected' => 0, 'by_type' => []], $response->viewData('summary'));
        $response->assertSee('No request records found.')
            ->assertSee('All Completed and Rejected Requests')
            ->assertDontSee('Requests by document type')
            ->assertDontSee('alice Test');

        $csv = $this->parseCsv($this->actingAs($staff)->get(route('request-reports.export'))->assertOk()->streamedContent());
        $this->assertSame([self::CSV_HEADINGS], $csv);
    }

    public function test_legacy_rows_with_missing_dates_and_student_details_do_not_break_the_outputs(): void
    {
        $staff = $this->user('st1', Role::Staff);

        // Legacy rejected request: no rejected_at, no completed_at, no reason; student has no course/year/section.
        $legacy = $this->makeRequest($this->user('bob', Role::Student), ['status' => 'Rejected', 'created_at' => '2026-02-01 08:00:00']);
        // Completed request with no completed_at.
        $done = $this->makeRequest($this->user('cara', Role::Student), ['status' => RequestStatus::Completed, 'created_at' => '2026-02-02 08:00:00']);

        $this->actingAs($staff)->get(route('request-reports.show'))
            ->assertOk()->assertSee('bob Test')->assertSee('cara Test')->assertSee('Rejected')->assertSee('Completed');

        $rows = $this->parseCsv($this->actingAs($staff)->get(route('request-reports.export'))->assertOk()->streamedContent());

        $this->assertCount(3, $rows);
        // Newest first: cara (Feb 2) then bob (Feb 1).
        $this->assertSame(
            [$done->reference, 'cara Test', 'cara@example.test', '', '', '', 'Enrollment Verification', 'Completed', 'Feb. 2, 2026 8:00 AM', '', '', ''],
            $rows[1]
        );
        $this->assertSame(
            [$legacy->reference, 'bob Test', 'bob@example.test', '', '', '', 'Enrollment Verification', 'Rejected', 'Feb. 1, 2026 8:00 AM', '', '', ''],
            $rows[2]
        );
    }

    // ---- printable page content ---------------------------------------------------

    public function test_the_report_lists_every_matching_row_instead_of_one_page_and_stays_escaped(): void
    {
        $staff = $this->user('st1', Role::Staff);
        $student = $this->user('alice', Role::Student);

        foreach (range(1, 20) as $i) {
            $this->makeRequest($student, ['status' => RequestStatus::Completed, 'created_at' => now()->subDays(60 - $i)]);
        }

        $hostile = $this->user('mallory', Role::Student, ['firstname' => '<script>alert(1)</script>']);
        $this->makeRequest($hostile, ['status' => RequestStatus::Completed]);

        $response = $this->actingAs($staff)->get(route('request-reports.show'))->assertOk();

        // 21 completed rows, more than the 15 per page used by the records screen.
        $this->assertSame(21, $response->viewData('summary')['completed']);
        $this->assertSame(21, substr_count($response->getContent(), 'class="completed"'));

        $response->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee(e('<script>alert(1)</script>'), false);
    }

    public function test_the_printable_report_does_not_include_email_course_or_rejection_details(): void
    {
        $staff = $this->user('st1', Role::Staff);
        $this->makeRequest(
            $this->user('alice', Role::Student, ['course' => 'BSIT', 'year' => '3', 'section' => 'A']),
            ['status' => 'Rejected', 'rejection_reason' => 'Private reason text']
        );

        $this->actingAs($staff)->get(route('request-reports.show'))
            ->assertOk()->assertSee('alice Test')
            ->assertDontSee('alice@example.test')
            ->assertDontSee('BSIT')
            ->assertDontSee('Private reason text');
    }

    // ---- CSV export ---------------------------------------------------------------

    public function test_the_csv_has_the_reference_columns_values_and_download_headers(): void
    {
        $staff = $this->user('st1', Role::Staff);

        $completed = $this->makeRequest(
            $this->user('alice', Role::Student, ['course' => 'BSIT', 'year' => '3', 'section' => 'A']),
            ['status' => RequestStatus::Completed, 'created_at' => '2026-03-05 14:30:00', 'completed_at' => '2026-03-09 10:05:00']
        );
        $rejected = $this->makeRequest(
            $this->user('bob', Role::Student),
            ['status' => 'Rejected', 'created_at' => '2026-02-01 08:00:00', 'rejected_at' => '2026-02-03 16:45:00', 'rejection_reason' => 'Incomplete documents']
        );

        $response = $this->actingAs($staff)->get(route('request-reports.export'))->assertOk();

        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertMatchesRegularExpression(
            '/^attachment; filename="?request_report_\d{4}-\d{2}-\d{2}\.csv"?$/',
            $response->headers->get('Content-Disposition')
        );
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));

        $rows = $this->parseCsv($response->streamedContent());

        $this->assertCount(3, $rows);
        $this->assertSame(self::CSV_HEADINGS, $rows[0]);
        $this->assertSame(
            [$completed->reference, 'alice Test', 'alice@example.test', 'BSIT', '3', 'A', 'Enrollment Verification', 'Completed', 'Mar. 5, 2026 2:30 PM', 'Mar. 9, 2026 10:05 AM', '', ''],
            $rows[1]
        );
        $this->assertSame(
            [$rejected->reference, 'bob Test', 'bob@example.test', '', '', '', 'Enrollment Verification', 'Rejected', 'Feb. 1, 2026 8:00 AM', '', 'Feb. 3, 2026 4:45 PM', 'Incomplete documents'],
            $rows[2]
        );
    }

    public function test_the_csv_applies_the_same_filters_as_the_report(): void
    {
        $staff = $this->user('st1', Role::Staff);
        $goodMoral = $this->documentType('Good Moral');
        $transcript = $this->documentType('Transcript');

        $alice = $this->makeRequest($this->user('alice', Role::Student), ['document_type_id' => $goodMoral->id, 'status' => RequestStatus::Completed, 'created_at' => '2026-01-10 09:00:00']);
        $bob = $this->makeRequest($this->user('bob', Role::Student), ['document_type_id' => $transcript->id, 'status' => 'Rejected', 'created_at' => '2026-03-05 09:00:00']);
        $this->makeRequest($this->user('cara', Role::Student), ['document_type_id' => $transcript->id, 'status' => RequestStatus::Completed, 'created_at' => '2026-03-06 09:00:00']);
        $this->makeRequest($this->user('dan', Role::Student), ['status' => RequestStatus::Pending]);

        $references = fn (array $query) => collect($this->parseCsv(
            $this->actingAs($staff)->get(route('request-reports.export', $query))->assertOk()->streamedContent()
        ))->slice(1)->pluck(0)->values()->all();

        $this->assertSame([$alice->reference], $references(['document_type_id' => $goodMoral->id]));
        $this->assertSame([$bob->reference], $references(['status' => 'Rejected']));
        $this->assertSame([$bob->reference], $references(['search' => 'bob']));
        $this->assertSame([$bob->reference], $references(['date_from' => '2026-03-05', 'date_to' => '2026-03-05']));
        $this->assertSame([], $references(['document_type_id' => $goodMoral->id, 'status' => 'Rejected']));

        // Unfiltered: the three finished requests, newest first; the pending one never appears.
        $this->assertCount(3, $references([]));
    }

    public function test_the_csv_neutralises_spreadsheet_formula_injection(): void
    {
        $staff = $this->user('st1', Role::Staff);

        $this->makeRequest(
            $this->user('mallory', Role::Student, [
                'firstname' => '=HYPERLINK("http://evil.test","x")',
                'email' => '+evil@example.test',
                'course' => '-2+3',
                'section' => '@SUM(A1:A2)',
            ]),
            ['status' => 'Rejected', 'rejection_reason' => "\t=1+1"]
        );

        $rows = $this->parseCsv($this->actingAs($staff)->get(route('request-reports.export'))->assertOk()->streamedContent());

        $this->assertCount(2, $rows);
        $row = $rows[1];

        $this->assertSame("'=HYPERLINK(\"http://evil.test\",\"x\") Test", $row[1]);
        $this->assertSame("'+evil@example.test", $row[2]);
        $this->assertSame("'-2+3", $row[3]);
        $this->assertSame("'@SUM(A1:A2)", $row[5]);
        $this->assertSame("'\t=1+1", $row[11]);

        // No cell in the data row may start with a formula character.
        foreach ($row as $cell) {
            $this->assertDoesNotMatchRegularExpression('/^[=+\-@\t\r]/', $cell);
        }
    }

    // ---- records page integration ----------------------------------------------------

    public function test_the_records_page_links_to_the_report_and_export_with_only_valid_filters(): void
    {
        $staff = $this->user('st1', Role::Staff);
        $this->makeRequest($this->user('alice', Role::Student), ['status' => RequestStatus::Completed]);

        $this->actingAs($staff)->get(route('request-records.index'))
            ->assertOk()
            ->assertSee('Generate Request Report')
            ->assertSee('Generate PDF Report')
            ->assertSee('Export CSV')
            ->assertSee(e(route('request-reports.show')), false)
            ->assertSee(e(route('request-reports.export')), false);

        $filtered = ['search' => 'alice', 'status' => 'Completed'];

        $this->actingAs($staff)->get(route('request-records.index', $filtered))
            ->assertOk()
            ->assertSee(e(route('request-reports.show', $filtered)), false)
            ->assertSee(e(route('request-reports.export', $filtered)), false);

        // An invalid status is dropped from the links, so they never lead to a redirect.
        $this->actingAs($staff)->get(route('request-records.index', ['status' => 'Pending', 'date_from' => '2026-03-01']))
            ->assertOk()
            ->assertSee(e(route('request-reports.show', ['date_from' => '2026-03-01'])), false)
            ->assertDontSee('status=Pending', false);
    }

    public function test_the_student_records_page_stays_excluded(): void
    {
        $staff = $this->user('st1', Role::Staff);

        $this->actingAs($staff)->get('/student-records')->assertNotFound();
        $this->actingAs($staff)->get('/request-reports/students')->assertNotFound();
    }

    // ---- helpers ------------------------------------------------------------------

    /** @param  array<string, mixed>  $extra */
    private function user(string $key, Role $role, array $extra = []): User
    {
        return User::factory()->create(array_merge([
            'name' => $key,
            'school_id' => $key.'-001',
            'firstname' => $key,
            'surname' => 'Test',
            'email' => $key.'@example.test',
            'password' => 'password',
            'role' => $role,
            'status' => UserStatus::Activated,
        ], $extra));
    }

    private function documentType(string $name): DocumentType
    {
        return DocumentType::create([
            'name' => $name,
            'description' => 'd',
            'processing_days' => 2,
            'fee' => 0,
            'is_active' => true,
        ]);
    }

    /** @param  array<string, mixed>  $attributes */
    private function makeRequest(User $student, array $attributes = []): DocumentRequest
    {
        $typeId = $attributes['document_type_id'] ?? (DocumentType::query()->first() ?? DocumentType::create([
            'name' => 'Enrollment Verification',
            'description' => 'Verification letter',
            'processing_days' => 3,
            'fee' => 0,
            'is_active' => true,
        ]))->id;

        $request = new DocumentRequest();
        $request->forceFill(array_merge([
            'user_id' => $student->id,
            'document_type_id' => $typeId,
            'status' => RequestStatus::Pending,
        ], $attributes))->save();

        return $request->refresh();
    }

    /**
     * Parse a downloaded CSV: asserts the UTF-8 BOM, strips it, and reads RFC 4180 rows
     * (quoted fields may contain commas, quotes and newlines).
     *
     * @return array<int, array<int, string|null>>
     */
    private function parseCsv(string $content): array
    {
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);

        $handle = fopen('php://memory', 'r+');
        fwrite($handle, substr($content, 3));
        rewind($handle);

        $rows = [];

        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }
}
