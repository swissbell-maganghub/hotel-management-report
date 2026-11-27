<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\DepartmentReport;
use App\Models\PomecImport;
use App\Models\PomecRecord;
use App\Models\ReportHistory;
use App\Models\ReportTemplate;
use App\Models\User;
use App\Services\PomecExcelService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $departments = Department::all()->keyBy('code');
        $templates = ReportTemplate::all()->keyBy('code');
        $password = Hash::make('password');

        $users = [
            'gm' => User::firstOrCreate(['email' => 'gm@swissbel.local'], [
                'name' => 'Budi Santoso',
                'password' => $password,
                'role' => User::ROLE_GM,
                'department_id' => null,
                'is_active' => true,
                'locale' => 'id',
            ]),
            'hod_eng' => User::firstOrCreate(['email' => 'hod.eng@swissbel.local'], [
                'name' => 'Andi Pratama',
                'password' => $password,
                'role' => User::ROLE_HOD,
                'department_id' => $departments['ENG']->id,
                'is_active' => true,
                'locale' => 'id',
            ]),
            'staff_eng' => User::firstOrCreate(['email' => 'staff.eng@swissbel.local'], [
                'name' => 'Siti Rahma',
                'password' => $password,
                'role' => User::ROLE_STAFF,
                'department_id' => $departments['ENG']->id,
                'is_active' => true,
                'locale' => 'id',
            ]),
            'hod_fo' => User::firstOrCreate(['email' => 'hod.fo@swissbel.local'], [
                'name' => 'Dewi Lestari',
                'password' => $password,
                'role' => User::ROLE_HOD,
                'department_id' => $departments['FO']->id,
                'is_active' => true,
                'locale' => 'id',
            ]),
            'staff_fo' => User::firstOrCreate(['email' => 'staff.fo@swissbel.local'], [
                'name' => 'Fajar Nugroho',
                'password' => $password,
                'role' => User::ROLE_STAFF,
                'department_id' => $departments['FO']->id,
                'is_active' => true,
                'locale' => 'id',
            ]),
            'staff_fnb' => User::firstOrCreate(['email' => 'staff.fnb@swissbel.local'], [
                'name' => 'Rina Kusuma',
                'password' => $password,
                'role' => User::ROLE_STAFF,
                'department_id' => $departments['FNB']->id,
                'is_active' => true,
                'locale' => 'id',
            ]),
            'it_admin' => User::firstOrCreate(['email' => 'it.admin@swissbel.local'], [
                'name' => 'Rizky Wijaya',
                'password' => $password,
                'role' => User::ROLE_IT_ADMIN,
                'department_id' => null,
                'is_active' => true,
                'locale' => 'id',
            ]),
        ];

        if (DepartmentReport::count() === 0) {
            $r1 = DepartmentReport::create([
                'report_code' => 'RPT-ENG-202611-001',
                'department_id' => $departments['ENG']->id,
                'report_template_id' => $templates['ENG-MAINT']->id,
                'title' => 'Preventive Maintenance Harian Engineering — Shift Pagi',
                'period_type' => 'daily',
                'period_date' => '2026-11-18',
                'status' => DepartmentReport::STATUS_SUBMITTED,
                'rows_data' => [
                    ['equipment' => 'Chiller Plant #1', 'location' => 'Rooftop Lt. 12', 'condition' => 'Normal', 'downtime_min' => 0, 'action_taken' => 'Temperatur & tekanan normal'],
                    ['equipment' => 'Booster Pump B', 'location' => 'Basement Pump Room', 'condition' => 'Need Repair', 'downtime_min' => 25, 'action_taken' => 'Seal mekanis dijadwalkan ganti'],
                ],
                'summary_notes' => 'Chiller #1 berjalan optimal, Booster Pump B perlu penggantian seal saat low-load.',
                'created_by' => $users['staff_eng']->id,
                'submitted_at' => now()->subHours(4),
            ]);
            ReportHistory::create([
                'department_report_id' => $r1->id,
                'user_id' => $users['staff_eng']->id,
                'from_status' => null,
                'to_status' => DepartmentReport::STATUS_SUBMITTED,
                'action' => 'submitted',
                'note' => 'Dikirim untuk pemeriksaan HOD Engineering.',
                'created_at' => now()->subHours(4),
            ]);

            $r2 = DepartmentReport::create([
                'report_code' => 'RPT-FO-202611-002',
                'department_id' => $departments['FO']->id,
                'report_template_id' => $templates['FO-OCCUPANCY']->id,
                'title' => 'Rekap Okupansi & Pergerakan Tamu — 18 Nov 2026',
                'period_type' => 'daily',
                'period_date' => '2026-11-18',
                'status' => DepartmentReport::STATUS_REVIEWED,
                'rows_data' => [
                    ['room_type' => 'Deluxe Waterfront', 'available' => 80, 'occupied' => 68, 'arrivals' => 24, 'departures' => 19, 'vip_notes' => 'Corporate Group 12 kamar'],
                    ['room_type' => 'Superior City View', 'available' => 90, 'occupied' => 74, 'arrivals' => 28, 'departures' => 21, 'vip_notes' => 'Early check-in 3 kamar'],
                ],
                'summary_notes' => 'Okupansi mencapai 83.5% dengan kedatangan corporate group.',
                'created_by' => $users['staff_fo']->id,
                'submitted_at' => now()->subDay()->addHours(2),
                'reviewed_by' => $users['hod_fo']->id,
                'reviewed_at' => now()->subDay()->addHours(4),
                'review_note' => 'Data sesuai dengan Night Audit PMS. Disetujui untuk monitoring GM.',
            ]);
            ReportHistory::create([
                'department_report_id' => $r2->id,
                'user_id' => $users['hod_fo']->id,
                'from_status' => DepartmentReport::STATUS_SUBMITTED,
                'to_status' => DepartmentReport::STATUS_REVIEWED,
                'action' => 'reviewed',
                'note' => 'Data sesuai dengan Night Audit PMS.',
                'created_at' => now()->subDay()->addHours(4),
            ]);

            $r3 = DepartmentReport::create([
                'report_code' => 'RPT-FNB-202611-003',
                'department_id' => $departments['FNB']->id,
                'report_template_id' => $templates['FNB-OUTLET']->id,
                'title' => 'Penjualan Outlet & Banquet F&B — 18 Nov 2026',
                'period_type' => 'daily',
                'period_date' => '2026-11-18',
                'status' => DepartmentReport::STATUS_RETURNED,
                'rows_data' => [
                    ['outlet' => 'Swiss-Café Restaurant', 'session' => 'Breakfast', 'covers' => 184, 'food_rev' => 22500000, 'bev_rev' => 3800000, 'notes' => 'Buffet penuh 07:00-09:30'],
                    ['outlet' => 'Waterfront Lounge & Bar', 'session' => 'Dinner', 'covers' => 62, 'food_rev' => 9400000, 'bev_rev' => 7600000, 'notes' => 'Live acoustic event'],
                ],
                'summary_notes' => 'Menunggu penyesuaian angka Banquet Ballroom B.',
                'created_by' => $users['staff_fnb']->id,
                'submitted_at' => now()->subHours(8),
                'reviewed_by' => $users['hod_fo']->id,
                'reviewed_at' => now()->subHours(6),
                'review_note' => 'Mohon lengkapi baris Ballroom Banquet B beserta total covers-nya.',
            ]);
            ReportHistory::create([
                'department_report_id' => $r3->id,
                'user_id' => $users['hod_fo']->id,
                'from_status' => DepartmentReport::STATUS_SUBMITTED,
                'to_status' => DepartmentReport::STATUS_RETURNED,
                'action' => 'returned',
                'note' => 'Mohon lengkapi baris Ballroom Banquet B.',
                'created_at' => now()->subHours(6),
            ]);
        }

        // Ensure all 7 operational departments have baseline demo reports
        $additionalReports = [
            [
                'report_code' => 'RPT-HK-202611-004',
                'department_id' => $departments['HK']->id,
                'report_template_id' => $templates['HK-STATUS']->id,
                'title' => 'Laporan Kesiapan Kamar & Linen Housekeeping — 18 Nov 2026',
                'period_type' => 'daily',
                'period_date' => '2026-11-18',
                'status' => DepartmentReport::STATUS_REVIEWED,
                'rows_data' => [
                    ['floor' => 'Lantai 3 - 6 (Deluxe)', 'vc' => 14, 'vd' => 4, 'oc' => 64, 'ooo' => 1, 'remarks' => '1 kamar OOO perbaikan AC'],
                    ['floor' => 'Lantai 7 - 11 (Superior/Suite)', 'vc' => 12, 'vd' => 3, 'oc' => 70, 'ooo' => 1, 'remarks' => 'Stok linen & amenities aman'],
                ],
                'summary_notes' => 'Kesiapan kamar mencapai 94.7% dengan 2 kamar dalam status OOO.',
                'created_by' => $users['staff_fo']->id,
                'submitted_at' => now()->subHours(7),
                'reviewed_by' => $users['hod_fo']->id,
                'reviewed_at' => now()->subHours(5),
                'review_note' => 'Selaras dengan room discrepancy report FO.',
            ],
            [
                'report_code' => 'RPT-FIN-202611-005',
                'department_id' => $departments['FIN']->id,
                'report_template_id' => $templates['FIN-RECON']->id,
                'title' => 'Rekonsiliasi Settlement & Kas Harian Finance — 18 Nov 2026',
                'period_type' => 'daily',
                'period_date' => '2026-11-18',
                'status' => DepartmentReport::STATUS_REVIEWED,
                'rows_data' => [
                    ['channel' => 'EDC / QRIS Bank Mandiri & BCA', 'gross_amount' => 118500000, 'mdr_fee' => 1185000, 'variance' => 0, 'status_note' => 'Matched'],
                    ['channel' => 'Transfer Corporate & OTA Settlement', 'gross_amount' => 84200000, 'mdr_fee' => 0, 'variance' => 0, 'status_note' => 'Matched'],
                ],
                'summary_notes' => 'Seluruh batch EDC & transfer bank telah cocok dengan General Cashier.',
                'created_by' => $users['it_admin']->id,
                'submitted_at' => now()->subHours(5),
                'reviewed_by' => $users['hod_fo']->id,
                'reviewed_at' => now()->subHours(3),
                'review_note' => 'Terverifikasi oleh Accounting & Income Audit.',
            ],
            [
                'report_code' => 'RPT-HR-202611-006',
                'department_id' => $departments['HR']->id,
                'report_template_id' => $templates['HR-ATTEND']->id,
                'title' => 'Rekap Kehadiran, Cuti & Pelatihan Karyawan — 18 Nov 2026',
                'period_type' => 'daily',
                'period_date' => '2026-11-18',
                'status' => DepartmentReport::STATUS_SUBMITTED,
                'rows_data' => [
                    ['dept_unit' => 'Front Office & Housekeeping', 'scheduled' => 48, 'present' => 46, 'absent_leave' => 2, 'training_hours' => 6, 'hr_notes' => '2 staf cuti tahunan'],
                    ['dept_unit' => 'F&B Service, Kitchen & Engineering', 'scheduled' => 54, 'present' => 53, 'absent_leave' => 1, 'training_hours' => 8, 'hr_notes' => 'Briefing food safety 1 jam'],
                ],
                'summary_notes' => 'Tingkat kehadiran operasional harian 97.1% across all shifts.',
                'created_by' => $users['it_admin']->id,
                'submitted_at' => now()->subHours(3),
            ],
            [
                'report_code' => 'RPT-SLS-202611-007',
                'department_id' => $departments['SLS']->id,
                'report_template_id' => $templates['SLS-PIPELINE']->id,
                'title' => 'Pipeline Group Corporate & MICE Sales — Minggu 3 Nov 2026',
                'period_type' => 'weekly',
                'period_date' => '2026-11-18',
                'status' => DepartmentReport::STATUS_SUBMITTED,
                'rows_data' => [
                    ['segment' => 'MICE / Banquet', 'account_name' => 'Kementerian Keuangan RI (Makassar)', 'room_nights' => 95, 'est_revenue' => 165000000, 'stage' => 'Confirmed', 'remarks' => 'Deposit 50% diterima'],
                    ['segment' => 'Corporate', 'account_name' => 'PT Semen Tonasa & Pertamina Region', 'room_nights' => 60, 'est_revenue' => 78000000, 'stage' => 'Proposal Sent', 'remarks' => 'Follow-up kontrak Q4'],
                ],
                'summary_notes' => 'Prospek MICE pemerintahan dan BUMN meningkat untuk periode Desember 2026.',
                'created_by' => $users['it_admin']->id,
                'submitted_at' => now()->subHours(2),
            ],
        ];

        foreach ($additionalReports as $payload) {
            if (! DepartmentReport::where('report_code', $payload['report_code'])->exists()) {
                $rpt = DepartmentReport::create($payload);
                ReportHistory::create([
                    'department_report_id' => $rpt->id,
                    'user_id' => $payload['reviewed_by'] ?? $payload['created_by'],
                    'from_status' => null,
                    'to_status' => $payload['status'],
                    'action' => $payload['status'] === DepartmentReport::STATUS_REVIEWED ? 'reviewed' : 'submitted',
                    'note' => $payload['review_note'] ?? $payload['summary_notes'],
                    'created_at' => $payload['reviewed_at'] ?? $payload['submitted_at'],
                ]);
            }
        }

        if (PomecImport::count() === 0) {
            $elecImport = PomecImport::create([
                'batch_code' => 'POMEC-ELC-202611-01',
                'utility_type' => 'electricity',
                'period_month' => '2026-11',
                'original_filename' => 'pomec_electricity_nov2026_w1_w3.xlsx',
                'file_hash_sha256' => hash('sha256', 'seed-electricity-2026-11'),
                'total_rows' => 5,
                'total_usage' => 12215.00,
                'status' => 'committed',
                'uploaded_by' => $users['staff_eng']->id,
                'committed_at' => now()->subHours(6),
            ]);

            foreach ([
                ['2026-11-14', 'Main Panel MDP-01', 1840, 460, 'Operasi normal'],
                ['2026-11-15', 'Main Panel MDP-01', 1910, 485, 'Weekend occupancy tinggi'],
                ['2026-11-16', 'Main Panel MDP-01', 1950, 510, 'Event ballroom siang'],
                ['2026-11-17', 'Main Panel MDP-01', 1920, 490, 'Operasi normal'],
                ['2026-11-18', 'Main Panel MDP-01', 2110, 540, 'Chiller peak load'],
            ] as [$date, $meter, $lwbp, $wbp, $note]) {
                $total = $lwbp + $wbp;
                PomecRecord::create([
                    'pomec_import_id' => $elecImport->id,
                    'utility_type' => 'electricity',
                    'reading_date' => $date,
                    'meter_name' => $meter,
                    'lwbp_value' => $lwbp,
                    'wbp_value' => $wbp,
                    'total_usage' => $total,
                    'transaction_hash' => hash('sha256', "electricity|{$date}|{$meter}|{$total}"),
                    'notes' => $note,
                ]);
            }

            $waterImport = PomecImport::create([
                'batch_code' => 'POMEC-WTR-202611-01',
                'utility_type' => 'water',
                'period_month' => '2026-11',
                'original_filename' => 'pomec_water_nov2026_w1_w3.xlsx',
                'file_hash_sha256' => hash('sha256', 'seed-water-2026-11'),
                'total_rows' => 5,
                'total_usage' => 448.00,
                'status' => 'committed',
                'uploaded_by' => $users['staff_eng']->id,
                'committed_at' => now()->subHours(5),
            ]);

            foreach ([
                ['2026-11-14', 'PDAM Main Meter', 82, 'Normal'],
                ['2026-11-15', 'PDAM Main Meter', 91, 'Laundry peak'],
                ['2026-11-16', 'PDAM Main Meter', 95, 'Banquet & kitchen'],
                ['2026-11-17', 'PDAM Main Meter', 88, 'Normal'],
                ['2026-11-18', 'PDAM Main Meter', 92, 'Normal'],
            ] as [$date, $meter, $m3, $note]) {
                PomecRecord::create([
                    'pomec_import_id' => $waterImport->id,
                    'utility_type' => 'water',
                    'reading_date' => $date,
                    'meter_name' => $meter,
                    'water_m3' => $m3,
                    'total_usage' => $m3,
                    'transaction_hash' => hash('sha256', "water|{$date}|{$meter}|{$m3}"),
                    'notes' => $note,
                ]);
            }
        }

        // Setiap unggahan POMEC punya laporan Engineering (alur Draft → Reviewed).
        app(PomecExcelService::class)->syncReports();

        if (AuditLog::count() === 0) {
            AuditLog::record($users['staff_eng'], 'pomec.import', 'POMEC', 'Mengimpor file pomec_electricity_nov2026_w1_w3.xlsx (5 baris, SHA-256 terverifikasi).');
            AuditLog::record($users['hod_fo'], 'report.reviewed', 'Reports', 'Menyetujui laporan RPT-FO-202611-002 (Front Office).');
        }
    }
}
