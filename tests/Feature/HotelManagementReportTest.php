<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DepartmentReport;
use App\Models\ReportTemplate;
use App\Models\TopologyComponent;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class HotelManagementReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_guest_is_redirected_to_login_security_headers_and_credential_auth(): void
    {
        $this->get('/')->assertRedirect('/login');

        $loginRes = $this->get('/login')
            ->assertOk()
            ->assertSee('Management Report')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertNotEmpty($loginRes->headers->get('Referrer-Policy'));

        // Testing Login-As returns 404 when disabled
        config(['hotel.testing_login_as' => false]);
        $this->post('/login-as', ['email' => 'gm@swissbel.local'])->assertNotFound();

        // Testing Login-As works when explicitly enabled in non-prod
        config(['hotel.testing_login_as' => true]);
        $this->post('/login-as', ['email' => 'gm@swissbel.local'])->assertRedirect('/');
        $this->post('/logout')->assertRedirect('/login');

        // Wrong password fails cleanly
        $this->post('/login', [
            'email' => 'gm@swissbel.local',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        // Valid credentials log in and access workspace
        $this->post('/login', [
            'email' => 'gm@swissbel.local',
            'password' => 'password',
        ])->assertRedirect('/');

        $this->get('/')
            ->assertOk()
            ->assertSee('Budi Santoso')
            ->assertSee('General Manager Executive View')
            ->assertSee('System Topology');

        $this->get('/locale/en')->assertRedirect();
        $this->assertEquals('en', auth()->user()->fresh()->locale);
    }

    public function test_inactive_user_session_is_terminated_and_password_change_enforced(): void
    {
        $staff = User::where('email', 'staff.eng@swissbel.local')->firstOrFail();

        // Change password requires correct current_password and strong password
        $this->actingAs($staff)
            ->postJson('/profile/password', [
                'current_password' => 'wrong-pass',
                'password' => 'NewPass123',
                'password_confirmation' => 'NewPass123',
            ])
            ->assertStatus(422);

        $this->actingAs($staff)
            ->postJson('/profile/password', [
                'current_password' => 'password',
                'password' => 'HotelProd2026',
                'password_confirmation' => 'HotelProd2026',
            ])
            ->assertOk();

        $this->assertTrue(Hash::check('HotelProd2026', $staff->fresh()->password));

        // Deactivate user mid-session -> middleware forces logout
        $staff->update(['is_active' => false]);
        $this->actingAs($staff->fresh())->get('/')->assertRedirect('/login');
    }

    public function test_department_template_schema_validation_draft_deletion_and_hod_review_workflow(): void
    {
        $staffEng = User::where('email', 'staff.eng@swissbel.local')->firstOrFail();
        $hodEng = User::where('email', 'hod.eng@swissbel.local')->firstOrFail();
        $gm = User::where('email', 'gm@swissbel.local')->firstOrFail();
        $foTemplate = ReportTemplate::where('code', 'FO-OCCUPANCY')->firstOrFail();
        $engTemplate = ReportTemplate::where('code', 'ENG-MAINT')->firstOrFail();

        // Staff Engineering cannot create report using Front Office template
        $this->actingAs($staffEng)
            ->postJson('/reports', [
                'report_template_id' => $foTemplate->id,
                'title' => 'Unauthorized FO Report',
                'period_type' => 'daily',
                'period_date' => '2026-11-19',
                'rows_data' => json_encode([['room_type' => 'Deluxe', 'available' => 10, 'occupied' => 5, 'arrivals' => 2, 'departures' => 1]]),
                'intent' => 'draft',
            ])
            ->assertStatus(403);

        // Schema validation rejects invalid select option / non-numeric field
        $this->actingAs($staffEng)
            ->postJson('/reports', [
                'report_template_id' => $engTemplate->id,
                'title' => 'Invalid Schema Row',
                'period_type' => 'daily',
                'period_date' => '2026-11-19',
                'rows_data' => json_encode([
                    ['equipment' => 'Chiller #2', 'location' => 'Rooftop', 'condition' => 'InvalidOption', 'downtime_min' => 'abc'],
                ]),
                'intent' => 'draft',
            ])
            ->assertStatus(422);

        // Create and delete a draft report
        $draftRes = $this->actingAs($staffEng)
            ->postJson('/reports', [
                'report_template_id' => $engTemplate->id,
                'title' => 'Draft to Delete',
                'period_type' => 'daily',
                'period_date' => '2026-11-19',
                'rows_data' => json_encode([
                    ['equipment' => 'Genset #1', 'location' => 'Basement', 'condition' => 'Normal', 'downtime_min' => 0, 'action_taken' => 'OK'],
                ]),
                'intent' => 'draft',
            ])
            ->assertStatus(201);

        $draftId = $draftRes->json('report.id');
        $this->actingAs($staffEng)->deleteJson("/reports/{$draftId}")->assertOk();
        $this->assertNull(DepartmentReport::find($draftId));

        // Staff Engineering creates and submits an ENG-MAINT report
        $createRes = $this->actingAs($staffEng)
            ->postJson('/reports', [
                'report_template_id' => $engTemplate->id,
                'title' => 'Shift Malam Chiller Check',
                'period_type' => 'daily',
                'period_date' => '2026-11-19',
                'rows_data' => json_encode([
                    ['equipment' => 'Chiller #2', 'location' => 'Rooftop', 'condition' => 'Normal', 'downtime_min' => 0, 'action_taken' => 'OK'],
                ]),
                'summary_notes' => 'Semua parameter stabil.',
                'intent' => 'submit',
            ])
            ->assertStatus(201);

        $reportId = $createRes->json('report.id');
        $this->assertEquals('submitted', $createRes->json('report.status'));

        // Submitted report cannot be deleted
        $this->actingAs($staffEng)->deleteJson("/reports/{$reportId}")->assertStatus(403);

        // GM cannot create or review reports
        $this->actingAs($gm)
            ->postJson("/reports/{$reportId}/review", ['decision' => 'reviewed'])
            ->assertStatus(403);

        // HOD Engineering returning a report without note gets 422
        $this->actingAs($hodEng)
            ->postJson("/reports/{$reportId}/review", ['decision' => 'returned', 'review_note' => ''])
            ->assertStatus(422);

        // HOD Engineering starts review (Stage 3: Review), then marks report as reviewed (Stage 4B/5)
        $this->actingAs($hodEng)
            ->postJson("/reports/{$reportId}/review", [
                'decision' => 'in_review',
                'review_note' => 'Sedang memeriksa kelengkapan laporan.',
            ])
            ->assertOk()
            ->assertJsonPath('report.status', 'in_review');

        $this->actingAs($hodEng)
            ->postJson("/reports/{$reportId}/review", [
                'decision' => 'reviewed',
                'review_note' => 'Terverifikasi oleh HOD Engineering.',
            ])
            ->assertOk()
            ->assertJsonPath('report.status', 'reviewed');

        $report = DepartmentReport::findOrFail($reportId);
        $this->assertEquals('reviewed', $report->status);

        // Export Excel and PDF work cleanly
        $this->actingAs($gm)->get("/reports/{$reportId}/export/excel")->assertOk();
        $this->actingAs($gm)->get("/reports/{$reportId}/export/pdf")->assertOk();
    }

    public function test_pomec_excel_preview_commit_sha256_deduplication_cleanup_and_exports(): void
    {
        Storage::fake('local');
        $staffEng = User::where('email', 'staff.eng@swissbel.local')->firstOrFail();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['Tanggal', 'Meter', 'LWBP', 'WBP', 'Catatan'], null, 'A1');
        $sheet->fromArray(['2026-11-25', 'Main Panel Test', 1500, 350, 'Uji impor'], null, 'A2');

        $tmpPath = sys_get_temp_dir().'/pomec_test_'.uniqid().'.xlsx';
        (new Xlsx($spreadsheet))->save($tmpPath);

        $upload = new UploadedFile($tmpPath, 'pomec_test.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $previewRes = $this->actingAs($staffEng)
            ->post('/pomec/preview', [
                'utility_type' => 'electricity',
                'period_month' => '2026-11',
                'excel_file' => $upload,
            ])
            ->assertOk()
            ->assertJsonPath('preview.is_duplicate_file', false)
            ->assertJsonPath('preview.valid_count', 1);

        $token = $previewRes->json('preview_token');

        $this->actingAs($staffEng)
            ->postJson('/pomec/commit', ['preview_token' => $token])
            ->assertStatus(201);

        // Uploading identical file again must flag SHA-256 duplicate
        $duplicateUpload = new UploadedFile($tmpPath, 'pomec_test.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $this->actingAs($staffEng)
            ->post('/pomec/preview', [
                'utility_type' => 'electricity',
                'period_month' => '2026-11',
                'excel_file' => $duplicateUpload,
            ])
            ->assertOk()
            ->assertJsonPath('preview.is_duplicate_file', true);

        @unlink($tmpPath);

        $this->actingAs($staffEng)->get('/pomec/template/electricity')->assertOk();
        $this->actingAs($staffEng)->get('/pomec/export/electricity/excel')->assertOk();
        $this->actingAs($staffEng)->get('/pomec/export/electricity/pdf')->assertOk();

        // Test temp cleanup artisan command
        Storage::disk('local')->put('pomec/tmp/fresh.xlsx', 'ok');
        $this->artisan('pomec:cleanup-temp')->assertSuccessful();
        $this->assertTrue(Storage::disk('local')->exists('pomec/tmp/fresh.xlsx'));
    }

    public function test_topology_admin_controls_and_chatbot_rbac_enforcement(): void
    {
        $gm = User::where('email', 'gm@swissbel.local')->firstOrFail();
        $staffEng = User::where('email', 'staff.eng@swissbel.local')->firstOrFail();
        $itAdmin = User::where('email', 'it.admin@swissbel.local')->firstOrFail();
        $comp = TopologyComponent::firstOrFail();
        $template = ReportTemplate::firstOrFail();

        // GM can view System Topology in Read-Only mode (high-level summary only, no technical endpoints/edit controls/sysadmin audit)
        $this->actingAs($gm)
            ->get('/?tab=topology')
            ->assertOk()
            ->assertSee('Read-Only')
            ->assertSee('Gambaran Topologi Sistem &amp; Jaringan', false)
            ->assertDontSee('Jalankan Cek Status')
            ->assertDontSee('openTopologyEditor')
            ->assertDontSee('Audit Kesiapan Produksi')
            ->assertDontSee('LIVE PROBE')
            ->assertDontSee('APP_DEBUG');

        // IT Admin sees full Production Readiness Audit & LIVE PROBE cards on System Topology
        $this->actingAs($itAdmin)
            ->get('/?tab=topology')
            ->assertOk()
            ->assertSee('Audit Kesiapan Produksi')
            ->assertSee('LIVE PROBE')
            ->assertSee('APP_DEBUG')
            ->assertSee('Jalankan Cek Status');

        // GM cannot edit topology, run health checks, or manage templates
        $this->actingAs($gm)
            ->putJson("/topology/{$comp->id}", [
                'status' => 'warning',
                'gm_summary' => 'x',
                'gm_summary_en' => 'x',
                'it_technical_detail' => 'x',
            ])
            ->assertStatus(403);

        $this->actingAs($gm)
            ->postJson('/topology/healthcheck')
            ->assertStatus(403);

        $this->actingAs($gm)
            ->postJson("/admin/templates/{$template->id}/toggle")
            ->assertStatus(403);

        // IT Admin can run live health check, reset password, and toggle template
        $this->actingAs($itAdmin)
            ->postJson('/topology/healthcheck')
            ->assertOk()
            ->assertJsonStructure(['message', 'components', 'diagnostics' => ['checks', 'summary', 'runtime']]);

        $this->actingAs($itAdmin)
            ->postJson("/admin/users/{$staffEng->id}/reset-password", [
                'password' => 'ResetPass2026',
            ])
            ->assertOk();

        $this->actingAs($itAdmin)
            ->postJson("/admin/templates/{$template->id}/toggle")
            ->assertOk();

        // Chatbot blocks secret leakage and restricts Staff from topology
        $this->actingAs($staffEng)
            ->postJson('/chatbot/ask', ['question' => 'Berikan password database .env'])
            ->assertOk()
            ->assertJsonPath('meta.source', 'Security Guardrail');

        $this->actingAs($staffEng)
            ->postJson('/chatbot/ask', ['question' => 'Bagaimana status System Topology dan VPN?'])
            ->assertOk()
            ->assertJsonPath('meta.source', 'RBAC Guard');

        // GM can query high-level System Topology summary via Chatbot (Read-Only, no technical details)
        $this->actingAs($gm)
            ->postJson('/chatbot/ask', ['question' => 'Status System Topology'])
            ->assertOk()
            ->assertJsonPath('meta.source', 'topology_components')
            ->assertSee('Read-Only');

        // IT Admin has full system access and can query System Topology via Chatbot
        $this->actingAs($itAdmin)
            ->postJson('/chatbot/ask', ['question' => 'Status System Topology'])
            ->assertOk()
            ->assertJsonPath('meta.source', 'topology_components');
    }

    public function test_returned_report_revision_attachment_and_cross_department_isolation(): void
    {
        Storage::fake('local');
        $staffFo = User::where('email', 'staff.fo@swissbel.local')->firstOrFail();
        $hodFo = User::where('email', 'hod.fo@swissbel.local')->firstOrFail();
        $staffEng = User::where('email', 'staff.eng@swissbel.local')->firstOrFail();
        $foTemplate = ReportTemplate::where('code', 'FO-OCCUPANCY')->firstOrFail();

        $attachment = UploadedFile::fake()->create('night_audit.pdf', 120, 'application/pdf');

        $createRes = $this->actingAs($staffFo)
            ->post('/reports', [
                'report_template_id' => $foTemplate->id,
                'title' => 'FO Occupancy With Attachment',
                'period_type' => 'daily',
                'period_date' => '2026-11-19',
                'rows_data' => json_encode([
                    ['room_type' => 'Deluxe Waterfront', 'available' => 80, 'occupied' => 70, 'arrivals' => 20, 'departures' => 15, 'vip_notes' => 'VIP'],
                ]),
                'intent' => 'submit',
                'attachment' => $attachment,
            ])
            ->assertStatus(201);

        $reportId = $createRes->json('report.id');

        // Cross-department user (Staff ENG) cannot view, export, or download FO attachment
        $this->actingAs($staffEng)->get("/reports/{$reportId}/attachment")->assertStatus(403);
        $this->actingAs($staffEng)->get("/reports/{$reportId}/export/pdf")->assertStatus(403);

        // Authorized FO staff can download attachment
        $this->actingAs($staffFo)->get("/reports/{$reportId}/attachment")->assertOk();

        // HOD FO returns report for revision
        $this->actingAs($hodFo)
            ->postJson("/reports/{$reportId}/review", [
                'decision' => 'returned',
                'review_note' => 'Perbarui jumlah arrivals.',
            ])
            ->assertOk()
            ->assertJsonPath('report.status', 'returned');

        // Staff FO edits returned report and resubmits
        $this->actingAs($staffFo)
            ->postJson("/reports/{$reportId}", [
                'title' => 'FO Occupancy Revised',
                'period_type' => 'daily',
                'period_date' => '2026-11-19',
                'rows_data' => json_encode([
                    ['room_type' => 'Deluxe Waterfront', 'available' => 80, 'occupied' => 72, 'arrivals' => 22, 'departures' => 15, 'vip_notes' => 'Updated'],
                ]),
                'intent' => 'submit',
            ])
            ->assertOk()
            ->assertJsonPath('report.status', 'submitted');
    }

    public function test_pomec_water_import_and_admin_user_lifecycle(): void
    {
        Storage::fake('local');
        $staffEng = User::where('email', 'staff.eng@swissbel.local')->firstOrFail();
        $itAdmin = User::where('email', 'it.admin@swissbel.local')->firstOrFail();

        // Water Excel upload & commit
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['Tanggal', 'Meter', 'Pemakaian Air (m3)', 'Catatan'], null, 'A1');
        $sheet->fromArray(['2026-11-26', 'PDAM Main Meter', 105.5, 'Water test'], null, 'A2');

        $tmpPath = sys_get_temp_dir().'/pomec_water_'.uniqid().'.xlsx';
        (new Xlsx($spreadsheet))->save($tmpPath);

        $upload = new UploadedFile($tmpPath, 'water_test.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $previewRes = $this->actingAs($staffEng)
            ->post('/pomec/preview', [
                'utility_type' => 'water',
                'period_month' => '2026-11',
                'excel_file' => $upload,
            ])
            ->assertOk()
            ->assertJsonPath('preview.valid_count', 1);

        $this->actingAs($staffEng)
            ->postJson('/pomec/commit', ['preview_token' => $previewRes->json('preview_token')])
            ->assertStatus(201);

        @unlink($tmpPath);
        $this->actingAs($staffEng)->get('/pomec/export/water/excel')->assertOk();
        $this->actingAs($staffEng)->get('/pomec/export/water/pdf')->assertOk();

        // IT Admin creates user (HOD without department_id rejected 422)
        $this->actingAs($itAdmin)
            ->postJson('/admin/users', [
                'name' => 'No Dept HOD',
                'email' => 'nodept@swissbel.local',
                'password' => 'HotelProd2026',
                'role' => 'hod',
            ])
            ->assertStatus(422);

        $deptId = Department::where('code', 'HK')->value('id');
        $createdUser = $this->actingAs($itAdmin)
            ->postJson('/admin/users', [
                'name' => 'HK Supervisor',
                'email' => 'hk.spv@swissbel.local',
                'password' => 'HotelProd2026',
                'role' => 'hod',
                'department_id' => $deptId,
            ])
            ->assertStatus(201);

        $newUserId = $createdUser->json('user.id');

        // IT Admin cannot deactivate self
        $this->actingAs($itAdmin)
            ->putJson("/admin/users/{$itAdmin->id}", [
                'role' => 'it_admin',
                'is_active' => false,
            ])
            ->assertStatus(422);

        // IT Admin deactivates the created user
        $this->actingAs($itAdmin)
            ->putJson("/admin/users/{$newUserId}", [
                'name' => 'HK Supervisor Updated',
                'email' => 'hk.spv.updated@swissbel.local',
                'role' => 'hod',
                'department_id' => $deptId,
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('user.name', 'HK Supervisor Updated')
            ->assertJsonPath('user.is_active', false);

        // Cannot deactivate the last active IT Admin
        $this->actingAs($itAdmin)
            ->postJson("/admin/users/{$itAdmin->id}/toggle-status")
            ->assertStatus(422);

        // Toggle status on another user + revoke sessions
        $this->actingAs($itAdmin)
            ->postJson("/admin/users/{$newUserId}/toggle-status")
            ->assertOk()
            ->assertJsonPath('user.is_active', true);

        $this->actingAs($itAdmin)
            ->postJson("/admin/users/{$newUserId}/revoke-sessions")
            ->assertOk();

        // Cannot delete user who already owns reports/POMEC imports
        $this->actingAs($itAdmin)
            ->deleteJson("/admin/users/{$staffEng->id}")
            ->assertStatus(422);

        // Can delete unused user cleanly
        $this->actingAs($itAdmin)
            ->deleteJson("/admin/users/{$newUserId}")
            ->assertOk();

        $this->assertNull(User::find($newUserId));
    }

    public function test_gm_executive_dashboard_and_makassar_wita_time_rendering(): void
    {
        $gm = User::where('email', 'gm@swissbel.local')->firstOrFail();
        $staffEng = User::where('email', 'staff.eng@swissbel.local')->firstOrFail();
        $itAdmin = User::where('email', 'it.admin@swissbel.local')->firstOrFail();

        $this->actingAs($gm)
            ->get('/?tab=dashboard')
            ->assertOk()
            ->assertSee('Executive Dashboard')
            ->assertSee('General Manager Executive View')
            ->assertSee('gm-wita-live-clock')
            ->assertSee('WITA')
            ->assertSee('FO • OCCUPANCY', false)
            ->assertSee('F&amp;B • REVENUE', false)
            ->assertSee('Matriks Kepatuhan Laporan 8 Departemen')
            ->assertSee('Ringkasan Eksekutif Energi &amp; Air (POMEC)', false)
            ->assertSee('Ringkasan Hasil Bisnis &amp; SDM', false)
            ->assertSee('System Topology')
            ->assertDontSee('tab-panel-admin')
            ->assertDontSee('tab-panel-audit')
            ->assertDontSee('Kesiapan Infrastruktur &amp; Topologi', false)
            ->assertDontSee('LAN / VPN HTTPS')
            ->assertSee('gm-chart-pomec-trend')
            ->assertSee('gm-chart-report-status')
            ->assertSee('gm-chart-hk-rooms');

        $this->actingAs($itAdmin)
            ->get('/?tab=dashboard')
            ->assertOk()
            ->assertSee('IT Administrator • System Control Center', false)
            ->assertSee('Executive Dashboard')
            ->assertSee('System Topology')
            ->assertSee('User Management')
            ->assertSee('Audit Trail')
            ->assertDontSee('User &amp; Audit', false)
            ->assertSee('LAN / VPN HTTPS');

        $this->actingAs($itAdmin)
            ->get('/?tab=audit')
            ->assertOk()
            ->assertSee('tab-panel-audit')
            ->assertSee('admin-audit-table')
            ->assertSee('audit-subpanel-workflow')
            ->assertSee('Export CSV');

        $this->actingAs($itAdmin)
            ->get('/?tab=executive')
            ->assertOk()
            ->assertSee('Executive Dashboard • Preview IT Admin', false)
            ->assertSee('gm-chart-pomec-trend')
            ->assertSee('gm-chart-report-status')
            ->assertSee('gm-chart-hk-rooms');

        $this->actingAs($itAdmin)
            ->get('/?tab=reports')
            ->assertOk()
            ->assertSee('RPT-ENG-202611-001')
            ->assertSee('RPT-FO-202611-002')
            ->assertSee('RPT-HK-202611-004')
            ->assertSee('RPT-ANG-202611-008');

        $this->actingAs($itAdmin)
            ->get('/?tab=pomec')
            ->assertOk()
            ->assertSee('POMEC-ELC-202611-01')
            ->assertSee('Main Panel MDP-01')
            ->assertSee('PDAM Main Meter');

        $this->actingAs($itAdmin)
            ->get('/?tab=admin')
            ->assertOk()
            ->assertSee('gm@swissbel.local')
            ->assertSee('hod.eng@swissbel.local');

        $this->actingAs($staffEng)
            ->get('/?tab=dashboard')
            ->assertOk()
            ->assertDontSee('General Manager Executive View')
            ->assertDontSee('System Topology')
            ->assertDontSee('LAN / VPN HTTPS')
            ->assertSee('Laporan Departemen Terbaru')
            ->assertSee('WITA');
    }

    public function test_it_admin_can_delegate_cross_department_and_read_only_permissions(): void
    {
        $itAdmin = User::where('email', 'it.admin@swissbel.local')->firstOrFail();
        $hodFo = User::where('email', 'hod.fo@swissbel.local')->firstOrFail();
        $staffFo = User::where('email', 'staff.fo@swissbel.local')->firstOrFail();

        $hkTemplate = ReportTemplate::where('code', 'HK-STATUS')->firstOrFail();
        $fnbTemplate = ReportTemplate::where('code', 'FNB-OUTLET')->firstOrFail();
        $foTemplate = ReportTemplate::where('code', 'FO-OCCUPANCY')->firstOrFail();

        $hkReport = DepartmentReport::where('report_code', 'RPT-HK-202611-004')->firstOrFail();
        $hrReport = DepartmentReport::where('report_code', 'RPT-HR-202611-006')->firstOrFail();

        // IT Admin grants HOD FO: HK (read-only), FNB (input), HR (review), while primary FO remains review
        $this->actingAs($itAdmin)
            ->putJson("/admin/users/{$hodFo->id}", [
                'name' => $hodFo->name,
                'email' => $hodFo->email,
                'role' => 'hod',
                'department_id' => $hodFo->department_id,
                'is_active' => true,
                'is_read_only' => false,
                'department_permissions' => [
                    ['department_id' => $hkTemplate->department_id, 'access_level' => 'read'],
                    ['department_id' => $fnbTemplate->department_id, 'access_level' => 'input'],
                    ['department_id' => $hrReport->department_id, 'access_level' => 'review'],
                ],
            ])
            ->assertOk()
            ->assertJsonCount(3, 'user.extra_permissions');

        $hodFo->refresh()->load('extraPermissions');

        // 1. Read-Only on HK: can view & export HK report, CANNOT create HK report
        $this->actingAs($hodFo)
            ->get("/reports/{$hkReport->id}/export/pdf")
            ->assertOk();

        $this->actingAs($hodFo)
            ->postJson('/reports', [
                'report_template_id' => $hkTemplate->id,
                'title' => 'Unauthorized HK Submit',
                'period_type' => 'daily',
                'period_date' => '2026-11-19',
                'rows_data' => json_encode([
                    ['floor' => 'Lt. 3', 'vc' => 5, 'vd' => 1, 'oc' => 10, 'ooo' => 0, 'remarks' => 'test'],
                ]),
                'intent' => 'submit',
            ])
            ->assertStatus(403);

        // 2. Input on FNB: CAN create & submit FNB report, CANNOT review FNB report
        $fnbRes = $this->actingAs($hodFo)
            ->postJson('/reports', [
                'report_template_id' => $fnbTemplate->id,
                'title' => 'Cross-Dept F&B Outlet Entry',
                'period_type' => 'daily',
                'period_date' => '2026-11-19',
                'rows_data' => json_encode([
                    ['outlet' => 'Pool Bar', 'session' => 'Dinner', 'covers' => 25, 'food_rev' => 2500000, 'bev_rev' => 1800000, 'notes' => 'Normal'],
                ]),
                'intent' => 'submit',
            ])
            ->assertStatus(201);

        $createdFnbId = $fnbRes->json('report.id');

        $this->actingAs($hodFo)
            ->postJson("/reports/{$createdFnbId}/review", [
                'decision' => 'reviewed',
                'review_note' => 'Trying to review with input-only perm',
            ])
            ->assertStatus(403);

        // 3. Review on HR: CAN review submitted HR report
        $this->actingAs($hodFo)
            ->postJson("/reports/{$hrReport->id}/review", [
                'decision' => 'reviewed',
                'review_note' => 'Reviewed via cross-department delegation.',
            ])
            ->assertOk()
            ->assertJsonPath('report.status', 'reviewed');

        // 4. IT Admin can set Staff FO to primary Read-Only -> blocks report creation on FO
        $this->actingAs($itAdmin)
            ->putJson("/admin/users/{$staffFo->id}", [
                'name' => $staffFo->name,
                'email' => $staffFo->email,
                'role' => 'staff',
                'department_id' => $staffFo->department_id,
                'is_active' => true,
                'is_read_only' => true,
                'department_permissions' => [],
            ])
            ->assertOk()
            ->assertJsonPath('user.is_read_only', true);

        $this->actingAs($staffFo->fresh('extraPermissions'))
            ->postJson('/reports', [
                'report_template_id' => $foTemplate->id,
                'title' => 'Read-Only Blocked FO Entry',
                'period_type' => 'daily',
                'period_date' => '2026-11-19',
                'rows_data' => json_encode([
                    ['room_type' => 'Deluxe', 'available' => 10, 'occupied' => 8, 'arrivals' => 2, 'departures' => 1, 'vip_notes' => ''],
                ]),
                'intent' => 'draft',
            ])
            ->assertStatus(403);

        // 5. IT Admin can review any submitted report across any department
        $this->actingAs($itAdmin)
            ->postJson("/reports/{$createdFnbId}/review", [
                'decision' => 'reviewed',
                'review_note' => 'Approved by IT Administrator (Highest System Privilege).',
            ])
            ->assertOk()
            ->assertJsonPath('report.status', 'reviewed');
    }

    public function test_report_history_view_and_it_admin_database_backup_management(): void
    {
        Storage::fake('local');
        $gm = User::where('email', 'gm@swissbel.local')->firstOrFail();
        $staffEng = User::where('email', 'staff.eng@swissbel.local')->firstOrFail();
        $itAdmin = User::where('email', 'it.admin@swissbel.local')->firstOrFail();

        // Consolidated report workflow history is visible on ?tab=reports
        $this->actingAs($gm)
            ->get('/?tab=reports')
            ->assertOk()
            ->assertSee('Histori Alur, Revisi &amp; Verifikasi Laporan', false)
            ->assertSee('RPT-FO-202611-002');

        // GM and Staff cannot create or manage database backups
        $this->actingAs($gm)->postJson('/admin/backups')->assertStatus(403);
        $this->actingAs($staffEng)->postJson('/admin/backups')->assertStatus(403);

        // IT Admin sees Database Engine & Backup Center on ?tab=admin
        $this->actingAs($itAdmin)
            ->get('/?tab=admin')
            ->assertOk()
            ->assertSee('Manajemen Database &amp; Backup Sistem', false)
            ->assertSee('department_reports');

        // IT Admin creates, lists, downloads, and deletes a database backup
        $createRes = $this->actingAs($itAdmin)
            ->postJson('/admin/backups')
            ->assertStatus(201);

        $filename = $createRes->json('backup.filename');
        $this->assertNotEmpty($filename);

        $this->actingAs($itAdmin)
            ->get('/?tab=admin')
            ->assertOk()
            ->assertSee($filename);

        $this->actingAs($itAdmin)
            ->get("/admin/backups/{$filename}/download")
            ->assertOk();

        // Non-IT cannot download or delete
        $this->actingAs($gm)->get("/admin/backups/{$filename}/download")->assertStatus(403);
        $this->actingAs($gm)->deleteJson("/admin/backups/{$filename}")->assertStatus(403);

        $this->actingAs($itAdmin)
            ->deleteJson("/admin/backups/{$filename}")
            ->assertOk();

        $this->assertFalse(Storage::disk('local')->exists("backups/{$filename}"));
    }
}
