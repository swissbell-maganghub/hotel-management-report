<?php

// [WASTAGE-REPORT] Feature test for Wastage Report
namespace Tests\Feature;

use App\Models\User;
use App\Models\WastageReport;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class WastageReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_it_admin_can_access_wastage_report_tab(): void
    {
        $admin = User::where('email', 'it.admin@swissbel.local')->firstOrFail();

        $res = $this->actingAs($admin)->get('/?tab=wastage');
        $res->assertOk();
        // [WASTAGE-REPORT] Tabel tunggal berformat template Excel
        $res->assertSee('FOOD WASTE SWISS-BELHOTEL WATERFRONT MAKASSAR');
        $res->assertSee('Buffet Lunch, Dinner, Gravity');
        $res->assertSee('Preparation');
        $res->assertSee('TOTAL KG/Day');
        $res->assertSee('KG/COVER');
        $res->assertSee('Wastage Report');
        $res->assertSee('M19 7l-.867 12.142', false);
    }

    public function test_staff_fnb_can_store_daily_manual_input(): void
    {
        $staffFnb = User::where('email', 'staff.fnb@swissbel.local')->firstOrFail();

        $response = $this->actingAs($staffFnb)->post('/wastage/daily', [
            'period_month' => '2026-10',
            'record_date' => '2026-10-01',
            'buffet_lunch' => 1,
            'buffet_dinner' => 0,
            'buffet_gravity' => 1,
            'prep_butcher' => 0,
            'prep_main_kitchen' => 6,
            'prep_pastry' => 10,
            'prep_cold_kitchen' => 2,
            'plate_waste' => 1,
            'breakfast_cafe' => 3,
            'covers' => 668,
        ]);

        $response->assertRedirect();
        
        $this->assertDatabaseHas('wastage_reports', [
            'period_month' => '2026-10',
            'total_wastage_kg' => 24.00,
            'total_cover' => 668,
        ]);

        $this->assertDatabaseHas('wastage_records', [
            'total_kg' => 24.00,
            'covers' => 668,
        ]);
    }

    // [WASTAGE-REPORT] Verifikasi input manual tidak tabrakan kode laporan jika impor Excel sudah memakai -001
    public function test_manual_daily_input_does_not_collide_with_existing_excel_report_code(): void
    {
        $staffFnb = User::where('email', 'staff.fnb@swissbel.local')->firstOrFail();

        WastageReport::create([
            'report_code' => 'WST-202610-001',
            'period_month' => '2026-10',
            'total_wastage_kg' => 20.00,
            'total_cover' => 500,
            'kg_per_cover' => 0.0400,
            'ytd_kg' => 20.00,
            'ytd_cover' => 500,
            'source_type' => 'excel_import',
            'imported_by' => $staffFnb->id,
        ]);

        $response = $this->actingAs($staffFnb)->post('/wastage/daily', [
            'period_month' => '2026-10',
            'record_date' => '2026-10-02',
            'buffet_lunch' => 5,
            'covers' => 250,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('wastage_reports', [
            'report_code' => 'WST-202610-002',
            'period_month' => '2026-10',
            'source_type' => 'manual',
        ]);
    }

    public function test_can_download_excel_template(): void
    {
        $admin = User::where('email', 'it.admin@swissbel.local')->firstOrFail();

        $response = $this->actingAs($admin)->get('/wastage/template');
        $response->assertOk();
        $this->assertStringContainsString('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
    }

    // [WASTAGE-REPORT] Verifikasi payload grafik per hari & per bulan
    public function test_wastage_tab_renders_daily_and_monthly_chart_data(): void
    {
        $admin = User::where('email', 'it.admin@swissbel.local')->firstOrFail();

        $octReport1 = WastageReport::create([
            'report_code' => 'WST-202610-001',
            'period_month' => '2026-10',
            'total_wastage_kg' => 24.00,
            'total_cover' => 600,
            'kg_per_cover' => 0.0400,
            'ytd_kg' => 24.00,
            'ytd_cover' => 600,
            'source_type' => 'excel_import',
            'imported_by' => $admin->id,
        ]);
        $octReport1->records()->create([
            'record_date' => '2026-10-01',
            'buffet_lunch' => 1.00,
            'prep_main_kitchen' => 10.00,
            'prep_pastry' => 13.00,
            'total_kg' => 24.00,
            'covers' => 600,
            'ytd_kg' => 24.00,
            'ytd_cover' => 600,
            'kg_per_cover' => 0.0400,
        ]);

        $octReport2 = WastageReport::create([
            'report_code' => 'WST-202610-002',
            'period_month' => '2026-10',
            'total_wastage_kg' => 26.00,
            'total_cover' => 400,
            'kg_per_cover' => 0.0650,
            'ytd_kg' => 50.00,
            'ytd_cover' => 1000,
            'source_type' => 'excel_import',
            'imported_by' => $admin->id,
        ]);
        $octReport2->records()->create([
            'record_date' => '2026-10-02',
            'buffet_lunch' => 2.00,
            'plate_waste' => 24.00,
            'total_kg' => 26.00,
            'covers' => 400,
            'ytd_kg' => 50.00,
            'ytd_cover' => 1000,
            'kg_per_cover' => 0.0650,
        ]);

        $novReport = WastageReport::create([
            'report_code' => 'WST-202611-001',
            'period_month' => '2026-11',
            'total_wastage_kg' => 15.00,
            'total_cover' => 500,
            'kg_per_cover' => 0.0300,
            'ytd_kg' => 65.00,
            'ytd_cover' => 1500,
            'source_type' => 'manual',
            'imported_by' => $admin->id,
        ]);
        $novReport->records()->create([
            'record_date' => '2026-11-05',
            'breakfast_cafe' => 15.00,
            'total_kg' => 15.00,
            'covers' => 500,
            'ytd_kg' => 65.00,
            'ytd_cover' => 1500,
            'kg_per_cover' => 0.0300,
        ]);

        $res = $this->actingAs($admin)->get('/?tab=wastage');
        $res->assertOk();
        $res->assertSee('wastage-chart-daily');
        $res->assertSee('wastage-chart-monthly');
        $res->assertSee('wastage-chart-yearly');
        $res->assertSee('wastage-chart-date-filter');
        $res->assertSee('wastage-chart-month-filter');
        $res->assertSee('wastage-chart-yearly-filter');
        $res->assertSee('wastage-chart-daily-legend');
        $res->assertSee('wastage-chart-monthly-mom');
        $res->assertSee('wastage-chart-yearly-eval');
        $res->assertSee('wastage-period-filter');
        $res->assertDontSee('wastage-chart-year-filter');
        // [WASTAGE-REPORT] Default membuka periode bulan Masehi terbaru (2026-11) & tidak mencampur bulan Oktober
        $res->assertSee('Nov 5, 2026');
        $res->assertDontSee('Oct 1, 2026');
        $res->assertDontSee('Oct 2, 2026');

        // Filter ke periode Oktober 2026: hanya baris Oktober yang tampil
        $resOct = $this->actingAs($admin)->get('/?tab=wastage&period=2026-10');
        $resOct->assertOk();
        $resOct->assertSee('Oct 1, 2026');
        $resOct->assertSee('Oct 2, 2026');
        $resOct->assertDontSee('Nov 5, 2026');

        // Filter ke periode September 2026 (kosong): tampil pesan kosong untuk September 2026
        $resSep = $this->actingAs($admin)->get('/?tab=wastage&period=2026-09');
        $resSep->assertOk();
        $resSep->assertDontSee('Oct 1, 2026');
        $resSep->assertDontSee('Nov 5, 2026');
        $resSep->assertSee('Belum ada data food waste untuk September 2026');

        $charts = $res->viewData('wastageCharts');
        $this->assertIsArray($charts);
        $this->assertCount(3, $charts['daily']);
        $this->assertCount(9, $charts['categoriesMeta']);
        $this->assertArrayHasKey('day_of_week', $charts['daily'][0]);
        $this->assertArrayHasKey('is_weekend', $charts['daily'][0]);
        $this->assertArrayHasKey('is_high_cover', $charts['daily'][0]);
        $this->assertTrue($charts['daily'][0]['is_high_cover']);
        $this->assertEquals(1.00, $charts['daily'][0]['categories']['buffet_lunch']);
        $this->assertEquals(10.00, $charts['daily'][0]['categories']['prep_main_kitchen']);

        $monthlyByKey = collect($charts['monthly'])->keyBy('month');
        $this->assertEquals(50.00, $monthlyByKey['2026-10']['total_kg']);
        $this->assertEquals(1000, $monthlyByKey['2026-10']['covers']);
        $this->assertEquals(2, $monthlyByKey['2026-10']['days']);
        $this->assertEquals(3.00, $monthlyByKey['2026-10']['categories']['buffet_lunch']);
        $this->assertEquals(24.00, $monthlyByKey['2026-10']['categories']['plate_waste']);
        $this->assertEquals(3.00, $monthlyByKey['2026-10']['groups']['buffet']);
        $this->assertEquals(24.00, $monthlyByKey['2026-10']['groups']['plate_waste']);
        $this->assertEquals(50.00, $monthlyByKey['2026-10']['ytd_kg']);
        $this->assertEquals(1000, $monthlyByKey['2026-10']['ytd_cover']);
        $this->assertEquals(15.00, $monthlyByKey['2026-11']['total_kg']);
        $this->assertEquals(15.00, $monthlyByKey['2026-11']['groups']['breakfast_cafe']);
        $this->assertEquals(65.00, $monthlyByKey['2026-11']['ytd_kg']);
        $this->assertEquals(1500, $monthlyByKey['2026-11']['ytd_cover']);

        $yearlyByKey = collect($charts['yearly'])->keyBy('year');
        $this->assertTrue($yearlyByKey->has('2026'));
        $this->assertEquals(65.00, $yearlyByKey['2026']['ytd_kg']);
        $this->assertEquals(1500, $yearlyByKey['2026']['ytd_cover']);
        $this->assertEquals(0.0433, $yearlyByKey['2026']['avg_kg_per_cover']);
        $this->assertEquals(0.0390, $yearlyByKey['2026']['target_next_year_kg_per_cover']);
    }

    // [WASTAGE-REPORT] Verifikasi ekspor PDF bulanan terfilter per bulan Masehi
    public function test_can_export_monthly_wastage_pdf_filtered_by_period(): void
    {
        $admin = User::where('email', 'it.admin@swissbel.local')->firstOrFail();

        $oct1 = WastageReport::create([
            'report_code' => 'WST-202610-001',
            'period_month' => '2026-10',
            'total_wastage_kg' => 24.00,
            'total_cover' => 600,
            'kg_per_cover' => 0.0400,
            'ytd_kg' => 24.00,
            'ytd_cover' => 600,
            'source_type' => 'excel_import',
            'imported_by' => $admin->id,
        ]);
        $oct1->records()->create([
            'record_date' => '2026-10-01',
            'buffet_lunch' => 1.00,
            'prep_main_kitchen' => 10.00,
            'prep_pastry' => 13.00,
            'total_kg' => 24.00,
            'covers' => 600,
            'ytd_kg' => 24.00,
            'ytd_cover' => 600,
            'kg_per_cover' => 0.0400,
        ]);

        $oct2 = WastageReport::create([
            'report_code' => 'WST-202610-002',
            'period_month' => '2026-10',
            'total_wastage_kg' => 26.00,
            'total_cover' => 400,
            'kg_per_cover' => 0.0650,
            'ytd_kg' => 50.00,
            'ytd_cover' => 1000,
            'source_type' => 'excel_import',
            'imported_by' => $admin->id,
        ]);
        $oct2->records()->create([
            'record_date' => '2026-10-02',
            'buffet_lunch' => 2.00,
            'plate_waste' => 24.00,
            'total_kg' => 26.00,
            'covers' => 400,
            'ytd_kg' => 50.00,
            'ytd_cover' => 1000,
            'kg_per_cover' => 0.0650,
        ]);

        $sep = WastageReport::create([
            'report_code' => 'WST-202609-001',
            'period_month' => '2026-09',
            'total_wastage_kg' => 21.50,
            'total_cover' => 570,
            'kg_per_cover' => 0.0377,
            'ytd_kg' => 21.50,
            'ytd_cover' => 570,
            'source_type' => 'excel_import',
            'imported_by' => $admin->id,
        ]);
        $sep->records()->create([
            'record_date' => '2026-09-30',
            'breakfast_cafe' => 21.50,
            'total_kg' => 21.50,
            'covers' => 570,
            'ytd_kg' => 21.50,
            'ytd_cover' => 570,
            'kg_per_cover' => 0.0377,
        ]);

        // Pastikan tombol preview PDF & modal tampil mengarah ke period=2026-10 dengan mode=preview
        $page = $this->actingAs($admin)->get('/?tab=wastage&period=2026-10');
        $page->assertOk();
        $page->assertSee('wastage-export-monthly-pdf-btn');
        $page->assertSee('wastage-pdf-preview-modal');
        $page->assertSee('wastage-confirm-download-pdf-btn');
        $page->assertSee('period=2026-10');
        $page->assertSee('mode=preview');

        // Preview inline PDF untuk Oktober 2026
        $previewRes = $this->actingAs($admin)->get('/wastage/export/monthly/pdf?period=2026-10&mode=preview');
        $previewRes->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $previewRes->headers->get('Content-Type'));
        $this->assertStringContainsString('inline', (string) $previewRes->headers->get('Content-Disposition'));
        $this->assertStringContainsString('food_waste_report_2026-10.pdf', (string) $previewRes->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', $previewRes->getContent());

        // Unduh attachment PDF untuk Oktober 2026
        $pdfRes = $this->actingAs($admin)->get('/wastage/export/monthly/pdf?period=2026-10');
        $pdfRes->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdfRes->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', (string) $pdfRes->headers->get('Content-Disposition'));
        $this->assertStringContainsString('food_waste_report_2026-10.pdf', (string) $pdfRes->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', $pdfRes->getContent());

        // Verifikasi isi HTML template PDF hanya memuat baris Oktober 2026 dan bukan September
        $octRecords = \App\Models\WastageRecord::whereBetween('record_date', ['2026-10-01', '2026-10-31'])
            ->orderBy('record_date')
            ->get();
        $rendered = view('exports.wastage-monthly-pdf', [
            'period' => '2026-10',
            'periodLabel' => 'Oktober 2026',
            'records' => $octRecords,
            'totalKg' => 50.00,
            'totalCover' => 1000,
            'kgPerCover' => 0.0500,
            'generatedBy' => $admin,
        ])->render();
        $this->assertStringContainsString('Oct 1, 2026', $rendered);
        $this->assertStringContainsString('Oct 2, 2026', $rendered);
        $this->assertStringNotContainsString('Sep 30, 2026', $rendered);

        // Format period tidak valid ditolak dengan 422
        $invalidRes = $this->actingAs($admin)->get('/wastage/export/monthly/pdf?period=invalid');
        $invalidRes->assertStatus(422);
    }
}
