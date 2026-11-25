<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\ReportTemplate;
use App\Models\TopologyComponent;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CoreSystemSeeder extends Seeder
{
    public function run(): void
    {
        $departments = collect([
            [
                'code' => 'ANG',
                'name' => 'Admin & General',
                'name_en' => 'Admin & General',
                'description' => 'Administrasi umum, koordinasi manajemen, IT, dan keamanan hotel.',
            ],
            [
                'code' => 'ENG',
                'name' => 'Engineering',
                'name_en' => 'Engineering',
                'description' => 'Pemeliharaan gedung, utilitas POMEC (listrik LWBP/WBP, air), dan peralatan hotel.',
            ],
            [
                'code' => 'FIN',
                'name' => 'Finance',
                'name_en' => 'Finance',
                'description' => 'Rekonsiliasi kas, settlement kartu, piutang, dan laporan keuangan harian.',
            ],
            [
                'code' => 'FNB',
                'name' => 'F&B',
                'name_en' => 'Food & Beverage',
                'description' => 'Penjualan outlet restoran, banquet, dan jumlah covers harian.',
            ],
            [
                'code' => 'FO',
                'name' => 'FO',
                'name_en' => 'Front Office',
                'description' => 'Okupansi kamar, check-in/out, reservasi, dan layanan tamu VIP.',
            ],
            [
                'code' => 'HK',
                'name' => 'HK',
                'name_en' => 'Housekeeping',
                'description' => 'Kesiapan kamar, status kebersihan, dan inventaris linen.',
            ],
            [
                'code' => 'HR',
                'name' => 'HR',
                'name_en' => 'Human Resources',
                'description' => 'Kehadiran staf, jadwal shift, rekrutmen, dan pelatihan karyawan.',
            ],
            [
                'code' => 'SLS',
                'name' => 'Sales',
                'name_en' => 'Sales & Marketing',
                'description' => 'Aktivitas penjualan, group inquiry, corporate account, dan pipeline revenue.',
            ],
        ])->mapWithKeys(function (array $dept) {
            $model = Department::updateOrCreate(['code' => $dept['code']], $dept);

            return [$dept['code'] => $model];
        });

        // Bootstrap IT Admin account under Admin & General only if none exists yet
        if (! User::where('role', User::ROLE_IT_ADMIN)->exists()) {
            User::create([
                'name' => 'IT Administrator',
                'email' => 'it.admin@swissbel.local',
                'password' => Hash::make(env('INITIAL_ADMIN_PASSWORD', 'ChangeMeProd#2026')),
                'role' => User::ROLE_IT_ADMIN,
                'department_id' => $departments['ANG']->id,
                'is_active' => true,
                'locale' => 'id',
            ]);
        }

        // 8 Department-specific report table templates
        $templateDefinitions = [
            'ANG-OPS' => [
                'department_id' => $departments['ANG']->id,
                'name' => 'Laporan Operasional Admin & General (Manajemen / IT)',
                'name_en' => 'Admin & General Operational Report (Mgmt / IT)',
                'description' => 'Checklist operasional umum, infrastruktur IT, keamanan, dan tindak lanjut manajemen.',
                'description_en' => 'General administration, IT infrastructure, security, and management follow-up checklist.',
                'columns_schema' => [
                    ['key' => 'unit', 'label' => 'Unit / Area', 'label_en' => 'Unit / Area', 'type' => 'text', 'required' => true],
                    ['key' => 'activity', 'label' => 'Item / Aktivitas', 'label_en' => 'Item / Activity', 'type' => 'text', 'required' => true],
                    ['key' => 'status', 'label' => 'Status', 'label_en' => 'Status', 'type' => 'select', 'options' => ['Normal', 'Follow-up', 'Urgent'], 'required' => true],
                    ['key' => 'pic', 'label' => 'Penanggung Jawab', 'label_en' => 'PIC', 'type' => 'text', 'required' => true],
                    ['key' => 'notes', 'label' => 'Catatan', 'label_en' => 'Notes', 'type' => 'text', 'required' => false],
                ],
                'default_rows' => [
                    ['unit' => 'IT & Network', 'activity' => 'Pemeriksaan server laporan & backup NAS', 'status' => 'Normal', 'pic' => 'IT Admin', 'notes' => ''],
                ],
            ],
            'ENG-MAINT' => [
                'department_id' => $departments['ENG']->id,
                'name' => 'Log Preventive Maintenance & Peralatan Engineering',
                'name_en' => 'Engineering Preventive Maintenance & Equipment Log',
                'description' => 'Pemeriksaan chiller, genset, lift, pompa air, dan panel listrik.',
                'description_en' => 'Inspection of chiller, generator, elevators, water pumps, and electrical panels.',
                'columns_schema' => [
                    ['key' => 'equipment', 'label' => 'Nama Peralatan', 'label_en' => 'Equipment Name', 'type' => 'text', 'required' => true],
                    ['key' => 'location', 'label' => 'Area / Lantai', 'label_en' => 'Area / Floor', 'type' => 'text', 'required' => true],
                    ['key' => 'condition', 'label' => 'Kondisi', 'label_en' => 'Condition', 'type' => 'select', 'options' => ['Normal', 'Need Repair', 'Critical'], 'required' => true],
                    ['key' => 'downtime_min', 'label' => 'Downtime (Menit)', 'label_en' => 'Downtime (Min)', 'type' => 'number', 'sum' => true, 'required' => true],
                    ['key' => 'action_taken', 'label' => 'Tindakan / Catatan', 'label_en' => 'Action / Remarks', 'type' => 'text', 'required' => false],
                ],
                'default_rows' => [
                    ['equipment' => 'Chiller Plant #1', 'location' => 'Rooftop Lt. 12', 'condition' => 'Normal', 'downtime_min' => 0, 'action_taken' => ''],
                ],
            ],
            'FIN-RECON' => [
                'department_id' => $departments['FIN']->id,
                'name' => 'Rekonsiliasi Settlement & Kas Harian Finance',
                'name_en' => 'Finance Daily Cash & Settlement Reconciliation',
                'description' => 'Pencocokan EDC, QRIS, transfer bank, dan kas tunai FO/F&B.',
                'description_en' => 'Reconciliation of EDC, QRIS, bank transfers, and cash drawers.',
                'columns_schema' => [
                    ['key' => 'channel', 'label' => 'Kanal Pembayaran', 'label_en' => 'Payment Channel', 'type' => 'text', 'required' => true],
                    ['key' => 'gross_amount', 'label' => 'Nominal Bruto (IDR)', 'label_en' => 'Gross Amount (IDR)', 'type' => 'currency', 'sum' => true, 'required' => true],
                    ['key' => 'mdr_fee', 'label' => 'Biaya Admin / MDR (IDR)', 'label_en' => 'Fee / MDR (IDR)', 'type' => 'currency', 'sum' => true, 'required' => true],
                    ['key' => 'variance', 'label' => 'Selisih (IDR)', 'label_en' => 'Variance (IDR)', 'type' => 'currency', 'sum' => true, 'required' => true],
                    ['key' => 'status_note', 'label' => 'Status Rekonsiliasi', 'label_en' => 'Recon Status', 'type' => 'select', 'options' => ['Matched', 'Pending Bank', 'Investigating'], 'required' => true],
                ],
                'default_rows' => [
                    ['channel' => 'EDC / QRIS', 'gross_amount' => 0, 'mdr_fee' => 0, 'variance' => 0, 'status_note' => 'Matched'],
                ],
            ],
            'FNB-OUTLET' => [
                'department_id' => $departments['FNB']->id,
                'name' => 'Laporan Penjualan Outlet & Banquet F&B',
                'name_en' => 'F&B Outlet Covers & Revenue Report',
                'description' => 'Rekap covers tamu dan pendapatan makanan/minuman per outlet.',
                'description_en' => 'Guest covers and food/beverage revenue summary per outlet.',
                'columns_schema' => [
                    ['key' => 'outlet', 'label' => 'Nama Outlet', 'label_en' => 'Outlet Name', 'type' => 'text', 'required' => true],
                    ['key' => 'session', 'label' => 'Sesi Layanan', 'label_en' => 'Meal Period', 'type' => 'select', 'options' => ['Breakfast', 'Lunch', 'Dinner', 'Banquet', 'All Day'], 'required' => true],
                    ['key' => 'covers', 'label' => 'Jumlah Covers', 'label_en' => 'Covers', 'type' => 'number', 'sum' => true, 'required' => true],
                    ['key' => 'food_rev', 'label' => 'Revenue Food (IDR)', 'label_en' => 'Food Revenue (IDR)', 'type' => 'currency', 'sum' => true, 'required' => true],
                    ['key' => 'bev_rev', 'label' => 'Revenue Beverage (IDR)', 'label_en' => 'Beverage Revenue (IDR)', 'type' => 'currency', 'sum' => true, 'required' => true],
                    ['key' => 'notes', 'label' => 'Catatan Operasional', 'label_en' => 'Operational Notes', 'type' => 'text', 'required' => false],
                ],
                'default_rows' => [
                    ['outlet' => 'Swiss-Café Restaurant', 'session' => 'Breakfast', 'covers' => 0, 'food_rev' => 0, 'bev_rev' => 0, 'notes' => ''],
                ],
            ],
            'FO-OCCUPANCY' => [
                'department_id' => $departments['FO']->id,
                'name' => 'Laporan Okupansi & Pergerakan Tamu Front Office',
                'name_en' => 'Front Office Occupancy & Guest Movement Report',
                'description' => 'Rekap okupansi per tipe kamar, arrival, departure, dan catatan VIP.',
                'description_en' => 'Room type occupancy summary, arrivals, departures, and VIP notes.',
                'columns_schema' => [
                    ['key' => 'room_type', 'label' => 'Tipe Kamar', 'label_en' => 'Room Type', 'type' => 'text', 'required' => true],
                    ['key' => 'available', 'label' => 'Kamar Tersedia', 'label_en' => 'Available', 'type' => 'number', 'sum' => true, 'required' => true],
                    ['key' => 'occupied', 'label' => 'Terisi', 'label_en' => 'Occupied', 'type' => 'number', 'sum' => true, 'required' => true],
                    ['key' => 'arrivals', 'label' => 'Arrival', 'label_en' => 'Arrivals', 'type' => 'number', 'sum' => true, 'required' => true],
                    ['key' => 'departures', 'label' => 'Departure', 'label_en' => 'Departures', 'type' => 'number', 'sum' => true, 'required' => true],
                    ['key' => 'vip_notes', 'label' => 'Catatan VIP / Group', 'label_en' => 'VIP / Group Notes', 'type' => 'text', 'required' => false],
                ],
                'default_rows' => [
                    ['room_type' => 'Deluxe Waterfront', 'available' => 0, 'occupied' => 0, 'arrivals' => 0, 'departures' => 0, 'vip_notes' => ''],
                ],
            ],
            'HK-STATUS' => [
                'department_id' => $departments['HK']->id,
                'name' => 'Laporan Kesiapan Kamar & Linen Housekeeping',
                'name_en' => 'Housekeeping Room Readiness & Linen Report',
                'description' => 'Status kebersihan kamar per lantai (VC, VD, OC, OOO) dan remark linen.',
                'description_en' => 'Floor-by-floor room status (VC, VD, OC, OOO) and linen remarks.',
                'columns_schema' => [
                    ['key' => 'floor', 'label' => 'Lantai / Zona', 'label_en' => 'Floor / Zone', 'type' => 'text', 'required' => true],
                    ['key' => 'vc', 'label' => 'Vacant Clean', 'label_en' => 'Vacant Clean', 'type' => 'number', 'sum' => true, 'required' => true],
                    ['key' => 'vd', 'label' => 'Vacant Dirty', 'label_en' => 'Vacant Dirty', 'type' => 'number', 'sum' => true, 'required' => true],
                    ['key' => 'oc', 'label' => 'Occupied Clean', 'label_en' => 'Occupied Clean', 'type' => 'number', 'sum' => true, 'required' => true],
                    ['key' => 'ooo', 'label' => 'Out of Order', 'label_en' => 'Out of Order', 'type' => 'number', 'sum' => true, 'required' => true],
                    ['key' => 'remarks', 'label' => 'Catatan HK / Linen', 'label_en' => 'HK / Linen Remarks', 'type' => 'text', 'required' => false],
                ],
                'default_rows' => [
                    ['floor' => 'Lantai 3 - 5', 'vc' => 0, 'vd' => 0, 'oc' => 0, 'ooo' => 0, 'remarks' => ''],
                ],
            ],
            'HR-ATTEND' => [
                'department_id' => $departments['HR']->id,
                'name' => 'Laporan Kehadiran, Cuti & Pelatihan HR',
                'name_en' => 'HR Attendance, Leave & Training Report',
                'description' => 'Rekap kehadiran harian per departemen, staf cuti/izin, dan jam pelatihan.',
                'description_en' => 'Daily department headcount, staff on leave, and training hours.',
                'columns_schema' => [
                    ['key' => 'dept_unit', 'label' => 'Departemen / Bagian', 'label_en' => 'Department / Section', 'type' => 'text', 'required' => true],
                    ['key' => 'scheduled', 'label' => 'Jadwal Shift', 'label_en' => 'Scheduled', 'type' => 'number', 'sum' => true, 'required' => true],
                    ['key' => 'present', 'label' => 'Hadir', 'label_en' => 'Present', 'type' => 'number', 'sum' => true, 'required' => true],
                    ['key' => 'absent_leave', 'label' => 'Cuti / Izin / Sakit', 'label_en' => 'Leave / Absent', 'type' => 'number', 'sum' => true, 'required' => true],
                    ['key' => 'training_hours', 'label' => 'Jam Training', 'label_en' => 'Training Hrs', 'type' => 'number', 'sum' => true, 'required' => true],
                    ['key' => 'hr_notes', 'label' => 'Catatan HR', 'label_en' => 'HR Notes', 'type' => 'text', 'required' => false],
                ],
                'default_rows' => [
                    ['dept_unit' => 'Front Office & HK', 'scheduled' => 0, 'present' => 0, 'absent_leave' => 0, 'training_hours' => 0, 'hr_notes' => ''],
                ],
            ],
            'SLS-PIPELINE' => [
                'department_id' => $departments['SLS']->id,
                'name' => 'Laporan Aktivitas Sales & Pipeline Group/Corporate',
                'name_en' => 'Sales Activity & Group/Corporate Pipeline Report',
                'description' => 'Prospek corporate, MICE/banquet inquiry, estimasi room nights, dan revenue.',
                'description_en' => 'Corporate prospects, MICE/banquet inquiries, room nights, and estimated revenue.',
                'columns_schema' => [
                    ['key' => 'segment', 'label' => 'Segmen', 'label_en' => 'Segment', 'type' => 'select', 'options' => ['Corporate', 'Government', 'MICE / Banquet', 'OTA / Travel Agent', 'Wedding'], 'required' => true],
                    ['key' => 'account_name', 'label' => 'Nama Klien / Perusahaan', 'label_en' => 'Client / Account', 'type' => 'text', 'required' => true],
                    ['key' => 'room_nights', 'label' => 'Room Nights', 'label_en' => 'Room Nights', 'type' => 'number', 'sum' => true, 'required' => true],
                    ['key' => 'est_revenue', 'label' => 'Estimasi Revenue (IDR)', 'label_en' => 'Est. Revenue (IDR)', 'type' => 'currency', 'sum' => true, 'required' => true],
                    ['key' => 'stage', 'label' => 'Tahap', 'label_en' => 'Stage', 'type' => 'select', 'options' => ['Prospect', 'Proposal Sent', 'Confirmed', 'Lost'], 'required' => true],
                    ['key' => 'remarks', 'label' => 'Tindak Lanjut', 'label_en' => 'Follow-up', 'type' => 'text', 'required' => false],
                ],
                'default_rows' => [
                    ['segment' => 'Corporate', 'account_name' => '', 'room_nights' => 0, 'est_revenue' => 0, 'stage' => 'Prospect', 'remarks' => ''],
                ],
            ],
        ];

        foreach ($templateDefinitions as $code => $attrs) {
            ReportTemplate::updateOrCreate(['code' => $code], $attrs);
        }

        // Baseline 8 Topology Components (Section 8.2 of Proposal)
        $topologyItems = [
            ['component_key' => 'app_server', 'name' => 'Application Server', 'name_en' => 'Application Server', 'zone' => 'Application Zone', 'status' => 'healthy', 'gm_summary' => 'Server aplikasi terkonfigurasi untuk layanan intranet.', 'gm_summary_en' => 'Application server configured for intranet service.', 'it_technical_detail' => 'Perlu verifikasi host & kapasitas produksi oleh IT.', 'host_or_endpoint' => 'Intranet App Host', 'sort_order' => 1],
            ['component_key' => 'web_server', 'name' => 'Web Server', 'name_en' => 'Web Server', 'zone' => 'Application Zone', 'status' => 'healthy', 'gm_summary' => 'Layanan web HTTPS internal aktif.', 'gm_summary_en' => 'Internal HTTPS web service active.', 'it_technical_detail' => 'Apache / Nginx • TLS Internal CA', 'host_or_endpoint' => config('app.url'), 'sort_order' => 2],
            ['component_key' => 'laravel_app', 'name' => 'Laravel Application', 'name_en' => 'Laravel Application', 'zone' => 'Application Zone', 'status' => 'healthy', 'gm_summary' => 'Aplikasi pelaporan operasional siap digunakan.', 'gm_summary_en' => 'Operational reporting application ready.', 'it_technical_detail' => 'Laravel '.app()->version().' • PHP '.PHP_VERSION, 'host_or_endpoint' => base_path(), 'sort_order' => 3],
            ['component_key' => 'mysql_db', 'name' => 'MySQL Database', 'name_en' => 'MySQL Database', 'zone' => 'Data Zone', 'status' => 'healthy', 'gm_summary' => 'Koneksi database terhubung & terisolasi.', 'gm_summary_en' => 'Database connection active & isolated.', 'it_technical_detail' => 'Driver: '.config('database.default').' • Internal VLAN Only', 'host_or_endpoint' => config('database.connections.'.config('database.default').'.host', 'local'), 'sort_order' => 4],
            ['component_key' => 'private_storage', 'name' => 'Private Storage', 'name_en' => 'Private Storage', 'zone' => 'File Zone', 'status' => 'healthy', 'gm_summary' => 'Penyimpanan dokumen impor & lampiran privat aktif.', 'gm_summary_en' => 'Private document & attachment storage active.', 'it_technical_detail' => 'storage/app/private • Non-public directory', 'host_or_endpoint' => 'storage/app/private', 'sort_order' => 5],
            ['component_key' => 'vpn_gateway', 'name' => 'VPN Gateway', 'name_en' => 'VPN Gateway', 'zone' => 'Perimeter Zone', 'status' => 'healthy', 'gm_summary' => 'Jalur akses remote aman melalui VPN hotel.', 'gm_summary_en' => 'Secure remote access via hotel VPN.', 'it_technical_detail' => 'Konfigurasi manual: pastikan MFA & kebijakan port aktif.', 'host_or_endpoint' => 'VPN Gateway (IT Managed)', 'sort_order' => 6],
            ['component_key' => 'backup', 'name' => 'Backup Storage', 'name_en' => 'Backup Storage', 'zone' => 'Backup Zone', 'status' => 'healthy', 'gm_summary' => 'Kebijakan backup terpisah ke NAS/media eksternal.', 'gm_summary_en' => 'Separate backup policy to NAS/external media.', 'it_technical_detail' => 'Jadwalkan mysqldump & verifikasi restore berkala.', 'host_or_endpoint' => 'Separate NAS / Backup Target', 'sort_order' => 7],
            ['component_key' => 'firewall_network', 'name' => 'Firewall / Network', 'name_en' => 'Firewall / Network', 'zone' => 'Perimeter Zone', 'status' => 'healthy', 'gm_summary' => 'Perimeter firewall membatasi akses port yang diizinkan.', 'gm_summary_en' => 'Perimeter firewall restricts inbound service ports.', 'it_technical_detail' => 'Hanya HTTPS & VPN terotorisasi; DB tertutup dari internet.', 'host_or_endpoint' => 'Hotel Core Firewall', 'sort_order' => 8],
        ];

        foreach ($topologyItems as $item) {
            TopologyComponent::firstOrCreate(
                ['component_key' => $item['component_key']],
                array_merge($item, ['last_checked_at' => now()])
            );
        }
    }
}
