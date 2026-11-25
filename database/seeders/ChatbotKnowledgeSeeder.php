<?php

namespace Database\Seeders;

use App\Models\ChatbotKnowledge;
use Illuminate\Database\Seeder;

/**
 * Starter chatbot knowledge (moved out of ChatbotService). Idempotent by slug; IT edits further entries in Admin.
 * Seeding never overwrites an article IT has already edited (only missing slugs are inserted).
 */
class ChatbotKnowledgeSeeder extends Seeder
{
    private const HOW = 'cara|bagaimana|gimana|gmn|how|panduan|guide|bantuan|help|langkah|tutorial';

    public function run(): void
    {
        foreach ([...$this->guides(), ...$this->glossary()] as $row) {
            ChatbotKnowledge::firstOrCreate(['slug' => $row['slug']], $row);
        }
    }

    /** Definition rules: "apa itu X", "X itu apa", "arti X", … plus the bare term. */
    private function glossaryTriggers(string ...$terms): string
    {
        $lines = [];
        foreach ($terms as $t) {
            $lines[] = implode('|', ["apa itu {$t}", "apa sih {$t}", "{$t} itu apa", "{$t} apa itu", "{$t} apaan", "{$t} artinya apa", "arti {$t}", "artinya {$t}",
                "maksud {$t}", "maksudnya {$t}", "definisi {$t}", "singkatan {$t}", "kepanjangan {$t}", "{$t} singkatan apa", "what is {$t}", "meaning of {$t}"]);
            $lines[] = "={$t}";
        }

        return implode("\n", $lines);
    }

    private function guides(): array
    {
        return [
            [
                'slug' => 'guide-pomec-upload', 'type' => 'guide', 'priority' => 10, 'title' => 'Cara unggah laporan POMEC',
                'triggers' => self::HOW.'|unggah|upload|impor|import + pomec|listrik|air|water|utilit|excel|meter',
                'body_id' => "Langkah mengunggah laporan POMEC:\n1. Buka tab POMEC Utility.\n2. Unduh template Excel contoh jika perlu (Listrik LWBP/WBP atau Air m³).\n3. Unggah file .xlsx untuk masuk ke tahap Preview & Cek Duplikasi SHA-256.\n4. Pastikan semua baris berstatus Valid, lalu klik Simpan Data Valid ke Database.",
                'body_en' => "Steps to import a POMEC Excel report:\n1. Open the POMEC Utility tab.\n2. Download the sample Excel template if needed (Electricity LWBP/WBP or Water m³).\n3. Upload your .xlsx file to enter Preview & SHA-256 Deduplication.\n4. Verify all rows show Valid, then click Commit Valid Rows to Database.",
            ],
            [
                'slug' => 'guide-escalation', 'type' => 'guide', 'priority' => 5, 'title' => 'Eskalasi ke HOD / IT',
                'triggers' => 'eskalasi|escalat|hubungi|kontak|contact|it admin|kendala|lapor masalah|minta bantuan|error|tidak bisa login|lupa password|reset password',
                'body_id' => "Panduan Eskalasi (Pemeriksaan Manusia):\n- Kesesuaian angka & revisi laporan: eskalasikan ke Head of Department (HOD) departemen terkait.\n- Hak akses RBAC, reset kata sandi, atau kendala server/backup: hubungi IT Administrator.",
                'body_en' => "Escalation & Human Verification Guide:\n- Content / operational revisions: escalate to your Head of Department (HOD) via the Return/Review workflow.\n- Account permissions, password resets, or server/backup issues: contact IT Administrator.",
            ],
            [
                'slug' => 'guide-export', 'type' => 'guide', 'priority' => 0, 'title' => 'Cara ekspor laporan (Excel/PDF)',
                'triggers' => self::HOW.'|unduh|download + ekspor|export|pdf|xlsx|excel|cetak|print',
                'body_id' => "Cara ekspor laporan (Excel / PDF):\n1. Buka tab Laporan (/?tab=reports) atau Dashboard.\n2. Klik tombol XLSX atau PDF pada baris laporan, atau buka Detail lalu pilih Export Excel / PDF.\n3. Ekspor hanya tersedia untuk departemen sesuai hak akses Anda.",
                'body_en' => "How to export reports (Excel / PDF):\n1. Open the Reports tab (/?tab=reports) or Dashboard.\n2. Click XLSX or PDF on the report row, or open Detail and choose Export Excel / Export PDF.\n3. Exports only work for departments within your RBAC permission.",
            ],
            [
                'slug' => 'guide-history', 'type' => 'guide', 'priority' => 0, 'title' => 'Cara menelusuri histori laporan',
                'triggers' => self::HOW.'|lihat|cek + histori|history|riwayat|jejak|audit trail',
                'body_id' => "Cara menelusuri histori laporan:\n1. Buka tab Laporan lalu lihat bagian 'Histori Alur, Revisi & Verifikasi Laporan' (atau klik pintasan Histori di Dashboard).\n2. Saring berdasarkan aktivitas (Created, Submitted, Review, Returned, Reviewed) atau departemen.\n3. Timeline per laporan juga tersedia di dalam modal Detail.",
                'body_en' => "Where to trace report history:\n1. Open the Reports tab and scroll to 'Report Workflow & Revision History' (or click the History shortcut on Dashboard).\n2. Filter by activity (Created, Submitted, Review, Returned, Reviewed) or department.\n3. You can also view per-report timeline inside the Detail modal.",
            ],
            [
                'slug' => 'guide-input-review', 'type' => 'guide', 'priority' => 0, 'title' => 'Cara input & review laporan',
                'triggers' => self::HOW.' + review|periksa|revisi|input|buat|membuat|isi|mengisi|kirim|submit|approve|setujui',
                'body_id' => "Panduan Input & Pemeriksaan Laporan (Tahap 1–5):\n1. Buat/Kirim: Klik '+ Buat Laporan', pilih template departemen, isi tabel, lalu Simpan Draft atau Kirim ke HOD.\n2. Pemeriksaan HOD: HOD membuka Detail -> 'Mulai Pemeriksaan', lalu pilih 'Tandai Reviewed' atau 'Kembalikan untuk Revisi' (wajib isi catatan).\n3. Monitoring: Laporan berstatus Reviewed siap dipantau manajemen.",
                'body_en' => "Departmental Reporting Steps (Stages 1–5):\n1. Create/Edit: Click '+ Create Report', select your template, fill table rows, then Save Draft or Submit.\n2. HOD Review: HOD opens Detail -> 'Start Review', then chooses 'Mark as Reviewed' or 'Return for Revision' (with note).\n3. Management Monitoring: Reviewed reports become 'Available for monitoring'.",
            ],
            [
                'slug' => 'guide-dashboard', 'type' => 'guide', 'priority' => 0, 'title' => 'Cara membaca dashboard',
                'triggers' => self::HOW.'|baca|membaca|arti + dashboard|grafik|chart|executive',
                'body_id' => "Cara membaca Dashboard:\n1. Kartu ringkasan di atas menunjukkan jumlah laporan per status (menunggu review, dikembalikan, sudah direview).\n2. Grafik POMEC menampilkan pemakaian listrik (LWBP/WBP) dan air per hari.\n3. Klik kartu atau pintasan untuk langsung membuka daftar laporan dengan filter yang sesuai.\n4. Data yang tampil selalu mengikuti hak akses role Anda.",
                'body_en' => "How to read the Dashboard:\n1. Summary cards at the top show report counts per status (awaiting review, returned, reviewed).\n2. POMEC charts show daily electricity (LWBP/WBP) and water usage.\n3. Click a card or shortcut to open the report list with the matching filter.\n4. Data always follows your role's access rights.",
            ],
        ];
    }

    private function glossary(): array
    {
        $g = fn (string $slug, string $title, array $terms, string $id, string $en): array => [
            'slug' => "glossary-{$slug}", 'type' => 'glossary', 'priority' => 20, 'title' => $title,
            'triggers' => $this->glossaryTriggers(...$terms), 'body_id' => $id, 'body_en' => $en,
        ];

        return [
            $g('lwbp', 'LWBP / WBP', ['lwbp', 'wbp'],
                '**LWBP** (Luar Waktu Beban Puncak): pemakaian listrik di luar jam beban puncak; dikenakan tarif lebih rendah. Pasangannya **WBP** (Waktu Beban Puncak): pemakaian pada jam beban puncak, umumnya pukul 17.00–22.00 sesuai ketentuan PLN, dengan tarif lebih tinggi. Keduanya dicatat per meter pada modul POMEC Utility.',
                '**LWBP** (Luar Waktu Beban Puncak): off-peak electricity usage, billed at the lower tariff. Its counterpart **WBP** (Waktu Beban Puncak) is peak-hour usage, typically 17:00–22:00 under PLN tariffs, billed higher. Both are recorded per meter in the POMEC Utility module.'),
            $g('pomec', 'POMEC', ['pomec'],
                '**POMEC** adalah modul utilitas pada sistem ini untuk mencatat pembacaan meter **listrik** (LWBP/WBP, kWh) dan **air** (m³), diimpor dari file Excel dengan pengecekan duplikasi.',
                "**POMEC** is this system's utility module for recording **electricity** (LWBP/WBP, kWh) and **water** (m³) meter readings, imported from Excel with duplicate checks."),
            $g('gm', 'GM (General Manager)', ['gm', 'general manager'],
                '**GM** (General Manager) adalah pimpinan tertinggi operasional hotel. Pada sistem ini GM dapat melihat ringkasan laporan **seluruh departemen** dan ringkasan System Topology secara read-only. GM adalah jabatan, bukan departemen.',
                "**GM** (General Manager) is the hotel's top operational executive. In this system the GM sees report summaries for **all departments** and a read-only System Topology summary. GM is a role, not a department."),
            $g('hod', 'HOD (Head of Department)', ['hod', 'head of department'],
                '**HOD** (Head of Department) adalah kepala departemen. HOD memeriksa laporan stafnya: menandai **Reviewed** atau **mengembalikan untuk revisi** dengan catatan.',
                "**HOD** (Head of Department) reviews their staff's reports: marks them **Reviewed** or **returns them for revision** with a note."),
            $g('ooo', 'OOO (Out of Order)', ['ooo', 'out of order'],
                '**OOO** (Out of Order): kamar yang tidak dapat dijual karena perbaikan atau kerusakan; tidak dihitung dalam kamar tersedia.',
                '**OOO** (Out of Order): rooms that cannot be sold due to repair or damage; excluded from available rooms.'),
            $g('mice', 'MICE', ['mice'],
                '**MICE** (Meetings, Incentives, Conventions, Exhibitions): segmen bisnis acara korporat dan pemerintahan, dicatat pada laporan pipeline Sales.',
                '**MICE** (Meetings, Incentives, Conventions, Exhibitions): the corporate/government events segment, tracked in the Sales pipeline report.'),
            $g('okupansi', 'Okupansi (Occupancy)', ['okupansi', 'occupancy'],
                '**Okupansi** (occupancy): persentase kamar terisi dibanding kamar tersedia, dilaporkan oleh Front Office (FO).',
                '**Occupancy**: occupied rooms as a percentage of available rooms, reported by Front Office (FO).'),
            $g('fo', 'FO (Front Office)', ['fo', 'front office'],
                '**FO** (Front Office): departemen penerimaan tamu — check-in/out, reservasi, dan rekap okupansi.',
                '**FO** (Front Office): guest-facing department — check-in/out, reservations and occupancy recap.'),
            $g('hk', 'HK (Housekeeping)', ['hk', 'housekeeping'],
                '**HK** (Housekeeping): departemen kebersihan dan kesiapan kamar serta linen.',
                '**HK** (Housekeeping): room cleanliness, readiness and linen.'),
            $g('fnb', 'F&B (Food & Beverage)', ['f&b', 'fnb', 'food and beverage'],
                '**F&B** (Food & Beverage): departemen restoran, outlet, dan banquet.',
                '**F&B** (Food & Beverage): restaurants, outlets and banquets.'),
            $g('edc', 'EDC', ['edc'],
                '**EDC** (Electronic Data Capture): mesin pembayaran kartu/QRIS; batch EDC direkonsiliasi oleh Finance.',
                '**EDC** (Electronic Data Capture): card/QRIS payment terminals; EDC batches are reconciled by Finance.'),
            $g('mdr', 'MDR', ['mdr'],
                '**MDR** (Merchant Discount Rate): biaya potongan bank atas transaksi kartu/QRIS.',
                '**MDR** (Merchant Discount Rate): the bank fee on card/QRIS transactions.'),
            $g('rbac', 'RBAC', ['rbac'],
                '**RBAC** (Role-Based Access Control): pembatasan akses berdasarkan jabatan dan departemen; chatbot mengikuti aturan yang sama.',
                '**RBAC** (Role-Based Access Control): access limited by role and department; the chatbot follows the same rules.'),
        ];
    }
}
