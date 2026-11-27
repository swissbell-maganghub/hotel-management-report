<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DepartmentReport;
use App\Models\PomecImport;
use App\Models\PomecRecord;
use App\Models\ReportTemplate;
use App\Models\TopologyComponent;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PomecNovemberSampleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class HotelManagementReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Alur form manual tetap diuji; default aplikasi mematikannya (lihat test khusus di bawah).
        config(['hotel.manual_reports' => true]);
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
        $staffEng = User::where('email', 'staff.fo@swissbel.local')->firstOrFail();
        $hodEng = User::where('email', 'hod.fo@swissbel.local')->firstOrFail();
        $gm = User::where('email', 'gm@swissbel.local')->firstOrFail();
        // Engineering melapor lewat POMEC; alur laporan manual diuji dengan Front Office.
        $foTemplate = ReportTemplate::where('code', 'HK-STATUS')->firstOrFail();
        $engTemplate = ReportTemplate::where('code', 'FO-OCCUPANCY')->firstOrFail();
        $this->assertFalse(ReportTemplate::where('code', 'ENG-MAINT')->value('is_active'));

        // Staff Front Office cannot create report using Housekeeping template
        $this->actingAs($staffEng)
            ->postJson('/reports', [
                'report_template_id' => $foTemplate->id,
                'title' => 'Unauthorized FO Report',
                'period_type' => 'daily',
                'period_date' => '2026-11-19',
                'rows_data' => json_encode([['floor' => 'Lt 3', 'vc' => 1, 'vd' => 0, 'oc' => 2, 'ooo' => 0]]),
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
                    ['room_type' => 'Deluxe', 'available' => 'abc', 'occupied' => 5, 'arrivals' => 2, 'departures' => 1],
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
                    ['room_type' => 'Deluxe', 'available' => 10, 'occupied' => 5, 'arrivals' => 2, 'departures' => 1, 'vip_notes' => 'OK'],
                ]),
                'intent' => 'draft',
            ])
            ->assertStatus(201);

        $draftId = $draftRes->json('report.id');
        $this->actingAs($staffEng)->deleteJson("/reports/{$draftId}")->assertOk();
        $this->assertNull(DepartmentReport::find($draftId));

        // Staff Front Office creates and submits a report
        $createRes = $this->actingAs($staffEng)
            ->postJson('/reports', [
                'report_template_id' => $engTemplate->id,
                'title' => 'Shift Malam Chiller Check',
                'period_type' => 'daily',
                'period_date' => '2026-11-19',
                'rows_data' => json_encode([
                    ['room_type' => 'Deluxe', 'available' => 10, 'occupied' => 8, 'arrivals' => 3, 'departures' => 2, 'vip_notes' => 'OK'],
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

        // HOD Front Office returning a report without note gets 422
        $this->actingAs($hodEng)
            ->postJson("/reports/{$reportId}/review", ['decision' => 'returned', 'review_note' => ''])
            ->assertStatus(422);

        // HOD Front Office starts review (Stage 3: Review), then marks report as reviewed (Stage 4B/5)
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
                'review_note' => 'Terverifikasi oleh HOD Front Office.',
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

        // Tata letak mini mengikuti file asli: nilai langsung di sel yang dibaca parser.
        $day = fn (int $d) => Date::PHPToExcel("2026-10-{$d} 00:00:00");
        $files = [
            'electricity' => [
                'Electric consumtion Gedung' => ['A7' => [[$day(1), 100, 110, 20, 20700, 50, 55, 10, 15530, 36230]]],
                // Di sheet Laundry kolom D = WBP dan H = LWBP; baris kedua belum punya angka akhir.
                'Electric consumtion Laundry' => ['A7' => [
                    [$day(1), 10, 11, 5, 500, 20, 21, 40, 4000, 4500],
                    [$day(2), 11, 0, -440, -1, 21, 0, -840, -1, -2],
                ]],
            ],
            'water' => [
                'Pemakaian air gedung' => ['B5' => [[$day(1), 100, 110, 10, 230000]]],
                'Pemakaian air Laundry' => ['B5' => [[$day(1), 5, 7, 2, 22332]]],
                // Jumlah tangki kosong = belum diisi.
                'Tangki' => ['B4' => [[$day(1), 8, 64, 3000000], [$day(2), null, 0, 0]]],
            ],
            'gas' => [
                'Sheet1' => [
                    'A3' => [['MONTH : OCTOBER 2026']],
                    'A6' => [
                        [1, '07,00', null, null, null, 8, 5, 12, 25, 4, 'Ganti Gas'],
                        [null, '15,00', null, null, null, 8, 17, 0, 25, 2, 'Gas Masuk 16 Tbg'],
                        [null, null, null, null, null, null, null, null, 'Total', 6],
                    ],
                ],
            ],
            'solar' => [
                // Baris 2 Oktober dilewati karena stok 3 Oktober belum diisi.
                'SOLAR' => ['B5' => [[$day(1), 1000, 500, 1500, 100], [$day(2), 900, 500, 1400, 1400], [$day(3), null, null, 0, 0]]],
            ],
        ];
        $expected = [
            'electricity' => ['rows' => 2, 'usage' => 75, 'cost' => 40730],
            'water' => ['rows' => 3, 'usage' => 76, 'cost' => 3252332],
            'gas' => ['rows' => 1, 'usage' => 6, 'cost' => null],
            'solar' => ['rows' => 1, 'usage' => 100, 'cost' => null],
        ];

        foreach ($files as $type => $sheets) {
            $spreadsheet = new Spreadsheet;
            $spreadsheet->removeSheetByIndex(0);
            foreach ($sheets as $title => $blocks) {
                $sheet = $spreadsheet->createSheet()->setTitle($title);
                foreach ($blocks as $cell => $data) {
                    $sheet->fromArray($data, null, $cell, true);
                }
            }
            $tmpPath = sys_get_temp_dir().'/pomec_test_'.uniqid().'.xlsx';
            (new Xlsx($spreadsheet))->save($tmpPath);
            $upload = fn () => ['utility_type' => $type, 'period_month' => '2026-10', 'excel_file' => new UploadedFile($tmpPath, "{$type}.xlsx", null, null, true)];

            $previewRes = $this->actingAs($staffEng)
                ->post('/pomec/preview', $upload())
                ->assertOk()
                ->assertJsonPath('preview.is_duplicate_file', false)
                ->assertJsonPath('preview.valid_count', $expected[$type]['rows'])
                ->assertJsonPath('preview.error_count', 0);

            $committed = $this->actingAs($staffEng)
                ->postJson('/pomec/commit', ['preview_token' => $previewRes->json('preview_token')])
                ->assertStatus(201);
            // Tanggal polos (tanpa jam/zona) dan data akun pengunggah tidak ikut terkirim.
            $this->assertMatchesRegularExpression('/^2026-10-\d{2}$/', $committed->json('import.records.0.reading_date'), $type);
            $this->assertSame(['id', 'name'], array_keys($committed->json('import.uploader')), $type);

            $import = PomecImport::where('utility_type', $type)->latest('id')->firstOrFail();
            $this->assertEqualsWithDelta($expected[$type]['usage'], (float) $import->total_usage, 0.01, $type);
            $this->assertEquals($expected[$type]['cost'], $import->total_cost === null ? null : (float) $import->total_cost, $type);

            // Uploading identical file again must flag SHA-256 duplicate and duplicate rows
            $this->actingAs($staffEng)
                ->post('/pomec/preview', $upload())
                ->assertOk()
                ->assertJsonPath('preview.is_duplicate_file', true)
                ->assertJsonPath('preview.duplicate_row_count', $expected[$type]['rows']);

            // Periode yang tidak ada di file ditolak dengan pesan, bukan error server
            $this->actingAs($staffEng)
                ->post('/pomec/preview', ['period_month' => '2026-09'] + $upload())
                ->assertOk()
                ->assertJsonPath('preview.valid_count', 0)
                ->assertJsonPath('preview.error_count', 1);

            $tmpPaths[] = $tmpPath;

            $this->actingAs($staffEng)->get("/pomec/export/{$type}/excel")->assertOk()->streamedContent();
            $this->actingAs($staffEng)->get("/pomec/export/{$type}/pdf?period_month=2026-10")->assertOk()->assertDownload("pomec_{$type}_2026-10.pdf");
        }

        $this->assertEquals(40, (float) PomecRecord::where('meter_name', 'PLN Laundry')->value('lwbp_value'));

        // Gas: stok dari shift terakhir, tabung datang dari catatan; data lama dilengkapi dari file tersimpan.
        $gas = PomecRecord::where('utility_type', 'gas')->latest('id')->firstOrFail();
        $this->assertSame([16, 17, 0, 8], array_values($gas->only(PomecRecord::GAS_STOCK)));
        $gas->update(array_fill_keys(PomecRecord::GAS_STOCK, null));
        $this->artisan('pomec:sync-reports')->assertSuccessful();
        $this->assertSame([16, 17, 0, 8], array_values($gas->fresh()->only(PomecRecord::GAS_STOCK)));
        $this->assertSame(17, (int) $gas->import->report->rows_data[0]['stock_full']);
        $this->actingAs($staffEng)->get('/?tab=pomec')->assertOk()->assertSee('PLN Laundry')->assertSee('Gas LPG');

        // Tanggal gas yang lebih baru tidak boleh menggeser grafik Listrik/Air di dashboard GM.
        $gm = User::where('email', 'gm@swissbel.local')->firstOrFail();
        PomecRecord::where('utility_type', 'gas')->firstOrFail()->replicate()
            ->fill(['reading_date' => '2026-12-31', 'transaction_hash' => hash('sha256', 'gas-later')])
            ->save();
        $dashboard = $this->actingAs($gm)->get('/?tab=dashboard')->assertOk();
        $this->assertNotContains('31 Dec', $dashboard->viewData('gmCharts')['pomec']['labels']);
        $this->assertSame(['id', 'name'], array_keys($dashboard->viewData('pomecImports')->first()->uploader->toArray()));
        $this->assertArrayNotHasKey('transaction_hash', $dashboard->viewData('pomecRecords')->first()->toArray());

        // Hapus batch: hanya HOD Engineering / IT Admin. $upload masih menunjuk file solar.
        $hodEng = User::where('email', 'hod.eng@swissbel.local')->firstOrFail();
        $solar = PomecImport::where('utility_type', 'solar')->firstOrFail();
        $this->actingAs($staffEng)->deleteJson("/pomec/imports/{$solar->id}")->assertForbidden();
        $this->actingAs($gm)->deleteJson("/pomec/imports/{$solar->id}")->assertForbidden();
        Storage::disk('local')->assertExists($solar->stored_path);

        $this->actingAs($hodEng)->deleteJson("/pomec/imports/{$solar->id}")->assertOk();
        $this->assertModelMissing($solar);
        $this->assertSame(0, PomecRecord::where('pomec_import_id', $solar->id)->count());
        $this->assertDatabaseHas('audit_logs', ['event' => 'pomec.import.deleted', 'user_id' => $hodEng->id]);
        Storage::disk('local')->assertMissing($solar->stored_path);
        $this->assertCount(1, Storage::disk('local')->files('pomec/deleted'));

        // Setelah dihapus, file yang sama bisa diunggah lagi dengan kode batch baru.
        $again = $this->actingAs($staffEng)->post('/pomec/preview', $upload())
            ->assertOk()
            ->assertJsonPath('preview.is_duplicate_file', false)
            ->assertJsonPath('preview.valid_count', 1);
        $this->actingAs($staffEng)->postJson('/pomec/commit', ['preview_token' => $again->json('preview_token')])->assertStatus(201);
        $this->assertNotSame($solar->batch_code, PomecImport::where('utility_type', 'solar')->firstOrFail()->batch_code);
        array_map('unlink', $tmpPaths);

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

    public function test_pomec_daily_upload_saves_only_new_dates_and_flags_changes(): void
    {
        Storage::fake('local');
        Carbon::setTestNow('2026-10-04 09:00:00');
        $staffEng = User::where('email', 'staff.eng@swissbel.local')->firstOrFail();
        PomecRecord::query()->delete();

        // Satu baris listrik Gedung: tanggal, LWBP awal/akhir/selisih/biaya, WBP awal/akhir/selisih/biaya, total biaya.
        $gedung = fn (int $d, float $lwbp) => [Date::PHPToExcel("2026-10-0{$d} 00:00:00"), 100, 110, $lwbp, 1000, 50, 55, 20, 2000, 3000];
        $laundry = [Date::PHPToExcel('2026-10-01 00:00:00'), 10, 11, 5, 500, 20, 21, 40, 4000, 4500];
        $upload = function (array $gedungRows) use ($laundry) {
            $spreadsheet = new Spreadsheet;
            $spreadsheet->getActiveSheet()->setTitle('Electric consumtion Gedung')->fromArray($gedungRows, null, 'A7', true);
            $spreadsheet->createSheet()->setTitle('Electric consumtion Laundry')->fromArray([$laundry], null, 'A7', true);
            $path = sys_get_temp_dir().'/pomec_daily_'.uniqid().'.xlsx';
            (new Xlsx($spreadsheet))->save($path);

            return ['utility_type' => 'electricity', 'period_month' => '2026-10', 'excel_file' => new UploadedFile($path, 'PLN.xlsx', null, null, true)];
        };

        // Hari pertama: tanggal 1.
        $first = $this->actingAs($staffEng)->post('/pomec/preview', $upload([$gedung(1, 100)]))->assertOk()->assertJsonPath('preview.valid_count', 2);
        $this->actingAs($staffEng)->postJson('/pomec/commit', ['preview_token' => $first->json('preview_token')])
            ->assertStatus(201)
            ->assertJsonPath('message', fn (string $m) => str_starts_with($m, '2 data Listrik 1 Oktober 2026 dikirim ke HOD (RPT-ENG-'));

        // Beberapa hari kemudian file yang sama sudah bertambah. Tanggal 1 Gedung dikoreksi, tanggal 3 belum terbaca, tanggal 5 belum terjadi.
        $second = $this->actingAs($staffEng)->post('/pomec/preview', $upload([$gedung(1, 101), $gedung(2, 90), $gedung(4, 95), $gedung(5, 80)]))
            ->assertOk()
            ->assertJsonPath('preview.valid_count', 2)
            ->assertJsonPath('preview.existing_count', 1)
            ->assertJsonPath('preview.changed_count', 1)
            ->assertJsonPath('preview.error_count', 1)
            ->assertJsonPath('preview.missing_dates', ['2026-10-03'])
            ->assertJsonPath('preview.last_saved_date', '2026-10-01');
        $this->actingAs($staffEng)->postJson('/pomec/commit', ['preview_token' => $second->json('preview_token')])
            ->assertStatus(201)
            ->assertJsonPath('message', fn (string $m) => str_starts_with($m, '2 data Listrik 2–4 Oktober 2026 dikirim ke HOD (RPT-ENG-'));

        // Angka lama tidak ditimpa; hanya tanggal 2 dan 4 yang bertambah.
        $this->assertEquals(120, (float) PomecRecord::where('meter_name', 'PLN Gedung')->whereDate('reading_date', '2026-10-01')->value('total_usage'));
        $this->assertSame(4, PomecRecord::count());
        Carbon::setTestNow();
    }

    public function test_pomec_upload_is_an_engineering_report_with_draft_review_and_reupload(): void
    {
        Storage::fake('local');
        Carbon::setTestNow('2026-10-10 09:00:00');
        $staffEng = User::where('email', 'staff.eng@swissbel.local')->firstOrFail();
        $hodEng = User::where('email', 'hod.eng@swissbel.local')->firstOrFail();
        PomecRecord::query()->delete();

        // File solar: tanggal, stok tangki biru, stok tangki merah, total stok, pemakaian.
        $upload = function (array $rows, ?int $replace = null) {
            $spreadsheet = new Spreadsheet;
            $spreadsheet->getActiveSheet()->setTitle('SOLAR')->fromArray($rows, null, 'B5', true);
            $path = sys_get_temp_dir().'/pomec_report_'.uniqid().'.xlsx';
            (new Xlsx($spreadsheet))->save($path);

            return array_filter([
                'utility_type' => 'solar',
                'period_month' => '2026-10',
                'replace_report_id' => $replace,
                'excel_file' => new UploadedFile($path, 'Solar.xlsx', null, null, true),
            ]);
        };
        $day = fn (int $d) => Date::PHPToExcel("2026-10-0{$d} 00:00:00");

        // Simpan sebagai draft: satu unggahan = satu laporan Engineering, kolom sesuai solar.
        $preview = $this->actingAs($staffEng)->post('/pomec/preview', $upload([[$day(1), 1000, 0, 1000, 40], [$day(2), 960, 0, 960, 0]]))->assertOk();
        $draft = $this->actingAs($staffEng)
            ->postJson('/pomec/commit', ['preview_token' => $preview->json('preview_token'), 'intent' => 'draft'])
            ->assertStatus(201)
            ->assertJsonPath('report.status', 'draft')
            ->assertJsonPath('report.department.code', 'ENG')
            ->assertJsonPath('report.template.code', 'ENG-POMEC-SOL')
            ->assertJsonPath('report.rows_data.0.total_usage', 40)
            ->assertJsonPath('import.report.status', 'draft');
        $reportId = $draft->json('report.id');
        $this->assertSame(['reading_date', 'total_usage', 'notes'], array_column($draft->json('report.template.columns_schema'), 'key'));

        // Isi laporan POMEC tidak bisa diubah lewat form; draft dikirim ke HOD.
        $this->actingAs($staffEng)->postJson("/reports/{$reportId}", [
            'title' => 'X', 'period_type' => 'daily', 'period_date' => '2026-10-01', 'rows_data' => '[]', 'intent' => 'submit',
        ])->assertStatus(422);
        $this->actingAs($hodEng)->postJson("/reports/{$reportId}/submit")->assertOk()->assertJsonPath('report.status', 'submitted');
        $this->actingAs($staffEng)->postJson("/reports/{$reportId}/submit")->assertForbidden();

        // Unggah ulang hanya untuk laporan yang dikembalikan.
        $this->actingAs($staffEng)->post('/pomec/preview', $upload([[$day(1), 1000, 0, 1000, 30]], $reportId))->assertStatus(422);
        $this->actingAs($hodEng)->postJson("/reports/{$reportId}/review", ['decision' => 'returned', 'review_note' => 'Pemakaian tanggal 1 salah.'])->assertOk();

        // File perbaikan: tanggal yang sama dianggap baru, laporan yang sama kembali Submitted, batch lama hilang.
        $oldImportId = DepartmentReport::findOrFail($reportId)->pomec_import_id;
        $fixed = $this->actingAs($staffEng)->post('/pomec/preview', $upload([[$day(1), 1000, 0, 1000, 30], [$day(2), 970, 0, 970, 0]], $reportId))
            ->assertOk()
            ->assertJsonPath('preview.valid_count', 2)
            ->assertJsonPath('preview.existing_count', 0);
        $this->actingAs($staffEng)->postJson('/pomec/commit', ['preview_token' => $fixed->json('preview_token')])
            ->assertStatus(201)
            ->assertJsonPath('report.id', $reportId)
            ->assertJsonPath('report.status', 'submitted')
            ->assertJsonPath('replaced_import_id', $oldImportId);
        $this->assertNull(PomecImport::find($oldImportId));
        $this->assertEquals(30, (float) PomecRecord::whereDate('reading_date', '2026-10-01')->value('total_usage'));
        $this->assertSame(['created', 'submitted', 'returned', 'submitted'], DepartmentReport::findOrFail($reportId)->histories()->reorder('id')->pluck('action')->all());

        $this->actingAs($hodEng)->postJson("/reports/{$reportId}/review", ['decision' => 'reviewed'])->assertOk()->assertJsonPath('report.status', 'reviewed');
        $this->actingAs($hodEng)->get("/reports/{$reportId}/export/excel")->assertOk();

        // Draft POMEC yang dihapus ikut menghapus unggahannya.
        $other = $this->actingAs($staffEng)->post('/pomec/preview', $upload([[$day(3), 970, 0, 970, 10], [$day(4), 960, 0, 960, 0]]))->assertOk();
        $otherReport = $this->actingAs($staffEng)
            ->postJson('/pomec/commit', ['preview_token' => $other->json('preview_token'), 'intent' => 'draft'])
            ->json('report');
        $this->actingAs($staffEng)->deleteJson("/reports/{$otherReport['id']}")->assertOk();
        $this->assertNull(PomecImport::find($otherReport['pomec_import_id']));
        $this->assertNull(DepartmentReport::find($otherReport['id']));
        Carbon::setTestNow();
    }

    public function test_manual_report_form_is_hidden_but_old_reports_stay_reviewable(): void
    {
        config(['hotel.manual_reports' => false]);
        $staffFo = User::where('email', 'staff.fo@swissbel.local')->firstOrFail();
        $staffEng = User::where('email', 'staff.eng@swissbel.local')->firstOrFail();
        $returned = DepartmentReport::where('status', 'returned')->firstOrFail();
        $author = User::findOrFail($returned->created_by);

        $this->actingAs($staffFo)->get('/?tab=reports')->assertOk()
            ->assertDontSee('onclick="openReportEditor', false)
            ->assertDontSee('createReportForActiveDept()', false);
        // Unggah hanya ada di dalam POMEC Control milik Engineering, bukan tombol umum.
        $this->actingAs($staffEng)->get('/?tab=pomec')->assertOk()->assertSee('id="pomec-upload-toggle"', false)->assertDontSee('openPomecUploadPage', false);
        $this->actingAs($staffFo)->get('/?tab=dashboard')->assertOk()->assertDontSee('id="pomec-upload-toggle"', false);

        $this->actingAs($staffFo)->postJson('/reports', [
            'report_template_id' => ReportTemplate::where('code', 'FO-OCCUPANCY')->value('id'),
            'title' => 'X', 'period_type' => 'daily', 'period_date' => '2026-11-19',
            'rows_data' => json_encode([['room_type' => 'Deluxe', 'available' => 1, 'occupied' => 1, 'arrivals' => 0, 'departures' => 0]]),
            'intent' => 'draft',
        ])->assertForbidden();
        $this->assertFalse($returned->isEditableBy($author));
        $this->actingAs($author)->get("/reports/{$returned->id}/export/excel")->assertOk();
    }

    public function test_pomec_november_sample_seeder_replaces_demo_and_is_repeatable(): void
    {
        // Satu pembacaan Oktober sebagai sumber, ditempelkan ke batch yang sudah ada.
        PomecRecord::create([
            'pomec_import_id' => PomecImport::firstOrFail()->id,
            'utility_type' => 'electricity',
            'reading_date' => '2026-10-05',
            'meter_name' => 'PLN Gedung',
            'lwbp_value' => 1000,
            'wbp_value' => 200,
            'total_usage' => 1200,
            'cost' => 1500000,
            'transaction_hash' => hash('sha256', 'electricity|2026-10-05|PLN Gedung'),
        ]);
        $source = PomecImport::firstOrFail();
        $source->update(['file_hash_sha256' => hash('sha256', 'file-oktober'), 'batch_code' => 'POMEC-ELC-202610-01']);
        $source->records()->whereDate('reading_date', '>=', '2026-11-01')->delete();

        $this->seed(PomecNovemberSampleSeeder::class);
        $this->seed(PomecNovemberSampleSeeder::class);

        $november = PomecRecord::whereDate('reading_date', '>=', '2026-11-01')->get();
        // Data contoh lama (air) ikut terganti; karena tidak ada air di sumber, tinggal listrik.
        $this->assertCount(1, $november);
        $this->assertSame('2026-11-05', $november[0]->reading_date->format('Y-m-d'));
        $this->assertEqualsWithDelta(1200, (float) $november[0]->total_usage, 120);
        $this->assertEqualsWithDelta((float) $november[0]->total_usage * 1250, (float) $november[0]->cost, 0.05);
        $this->assertSame(1, PomecImport::where('batch_code', 'POMEC-ELC-202611-CONTOH')->count());
    }

    public function test_admin_user_lifecycle(): void
    {
        Storage::fake('local');
        $staffEng = User::where('email', 'staff.eng@swissbel.local')->firstOrFail();
        $itAdmin = User::where('email', 'it.admin@swissbel.local')->firstOrFail();

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

        $this->assertSame(7, Department::count());
        $this->assertDatabaseMissing('departments', ['code' => 'ANG']);
        $this->assertNull($gm->department_id);
        $this->assertNull($itAdmin->department_id);

        $this->actingAs($gm)
            ->get('/?tab=dashboard')
            ->assertOk()
            ->assertSee('Executive Dashboard')
            ->assertSee('General Manager Executive View')
            ->assertSee('gm-wita-live-clock')
            ->assertSee('WITA')
            ->assertSee('FO • OCCUPANCY', false)
            ->assertSee('F&amp;B • REVENUE', false)
            ->assertSee('Matriks Kepatuhan Laporan 7 Departemen')
            ->assertSee('Ringkasan Eksekutif Energi &amp; Air (POMEC Control)', false)
            ->assertSee('Ringkasan Hasil Bisnis &amp; SDM', false)
            ->assertSee('System Topology')
            ->assertDontSee('Admin &amp; General', false)
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
            ->assertSee('reports-overview-cards')
            ->assertSee('sidebar-reports-submenu')
            ->assertDontSee('data-sidebar-dept="ANG"', false)
            ->assertSee('RPT-ENG-202611-001')
            ->assertSee('RPT-FO-202611-002')
            ->assertSee('RPT-HK-202611-004')
            ->assertDontSee('RPT-ANG-202611-008')
            ->assertDontSee('Admin &amp; General', false);

        $this->actingAs($itAdmin)
            ->get('/?tab=admin')
            ->assertOk()
            ->assertSee('Executive (Semua Departemen)');

        $this->actingAs($itAdmin)
            ->get('/?tab=reports&dept=ANG')
            ->assertOk()
            ->assertSee('activeReportDept":""', false);

        $this->actingAs($itAdmin)
            ->get('/?tab=reports&dept=FO')
            ->assertOk()
            ->assertSee('activeReportDept":"FO"', false)
            ->assertSee('Buat Laporan FO');

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

        // Staf satu departemen langsung ke halaman departemennya; Engineering punya pengalih ke POMEC.
        $this->actingAs($staffEng)
            ->get('/?tab=reports')
            ->assertOk()
            ->assertDontSee('id="reports-overview"', false)
            ->assertDontSee('id="sidebar-reports-submenu"', false)
            ->assertSee('"activeTab":"pomec"', false);
        $this->actingAs($itAdmin)->get('/?tab=reports&dept=ENG')->assertOk()->assertSee('"activeTab":"pomec"', false);
        $this->actingAs(User::where('email', 'staff.fo@swissbel.local')->firstOrFail())
            ->get('/?tab=reports')
            ->assertOk()
            ->assertSee('"activeTab":"reports"', false);

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
