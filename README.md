# Hotel Management Report System

**Swiss-Belhotel Waterfront Makassar** — Sistem pelaporan operasional antar-departemen, analitik utilitas **POMEC**, alur persetujuan **RBAC**, pusat **Audit Trail**, dan asisten AI operasional (*read-only*).

---

## Fitur Utama

| Modul | Deskripsi Ringkas |
|---|---|
| **Executive Dashboard (GM)** | Ringkasan kepatuhan 8 departemen, *operational pulse* (FO, F&B, HK, ENG, FIN, SLS, HR), utilitas POMEC, dan Topologi Sistem (*read-only*). |
| **Department Reports** | Input laporan berbasis template dinamis per departemen, alur review & revisi HOD, lampiran file, riwayat workflow, serta ekspor **Excel (`.xlsx`)** dan **PDF**. |
| **POMEC Utility** | Impor Excel dengan preview berbasis hash SHA-256, pemantauan pemakaian listrik (LWBP/WBP) dan air, grafik tren, serta ekspor laporan utilitas. |
| **User Management** | Pengelolaan akun, role, delegasi lintas departemen, pemutusan sesi aktif, aktivasi template tabel departemen, dan snapshot backup database. |
| **Audit Trail** | Pusat audit terstruktur (*System & Security* + *Workflow Approval*) dengan filter multi-kriteria, inspeksi payload JSON, dan ekspor **CSV**. |
| **System Topology** | Pemantauan status komponen infrastruktur, *health check* langsung, serta metrik kesiapan produksi (khusus IT Admin). |
| **AI Assistant (Bernie)** | Asisten chat *read-only* sesuai hak akses RBAC dengan Knowledge Base, guardrail keamanan, serta dukungan LLM lokal (**Ollama**) maupun cloud (**Gemini / OpenRouter / OpenAI**). |
| **Tema Terang & Gelap** | Default **Light Mode** dengan switcher **Obsidian Dark Mode** yang reaktif di halaman login, workspace, grafik Chart.js, dan widget Bernie. |

---

## Matriks Hak Akses (RBAC)

| Role | Cakupan Data | Input & Review | Topologi Sistem | User Management & Audit |
|---|---|---|---|---|
| **General Manager (`gm`)** | Seluruh departemen | Baca & Ekspor | *Read-Only* | — |
| **Head of Department (`hod`)** | Departemen sendiri (+ delegasi) | Input, Review & Ekspor | — | — |
| **Admin / Staff (`staff`)** | Departemen sendiri (+ delegasi) | Input & Ekspor | — | — |
| **IT Administrator (`it_admin`)** | Sistem & seluruh departemen | Akses penuh | Kelola & Health Check | Akses penuh |

---

## Tools & Teknologi yang Digunakan

| Lapisan | Tool / Teknologi | Versi / Konfigurasi | Fungsi Utama |
|---|---|---|---|
| **Local Environment** | **Laragon** | PHP `8.3.30`, MySQL `8.4.3`, Apache/Nginx | Paket lingkungan development lokal di Windows |
| **Runtime & Package** | **PHP**, **Composer**, **Node.js**, **npm** | PHP `^8.3` · Composer `2.10.1` · Node `24.16` | Runtime server-side serta manajemen dependensi PHP & JS |
| **Backend Framework** | **Laravel Framework** (`laravel/framework`) | `v13.43.0` | Routing, Eloquent ORM, Auth, RBAC, Validasi, Scheduler |
| **Frontend & Styling** | **Blade**, **Vanilla JS**, **Tailwind CSS** | Tailwind `4.3.5` · `@tailwindcss/vite` | Tampilan server-rendered, state workspace, Light & Dark Mode |
| **Asset Bundler** | **Vite** (`vite`, `laravel-vite-plugin`) | `8.3.8` / `3.2.1` | Kompilasi aset CSS/JS produksi & HMR (`npm run dev`) |
| **Visualisasi Grafik** | **Chart.js** (`chart.js`) | `4.6.1` | 8 grafik interaktif (Dashboard Eksekutif, Laporan, POMEC) |
| **Tipografi** | **Google Fonts** | `Plus Jakarta Sans`, `Inter`, `JetBrains Mono` | Font heading, antarmuka, dan angka tabular monospace |
| **Ekspor Dokumen** | **PhpSpreadsheet** & **Laravel Dompdf** | `5.11.0` / `v3.1.2` | Impor/ekspor Excel (`.xlsx`) dan render dokumen PDF |
| **Database Produksi** | **MySQL** | `8.x` (`8.4.3` lokal / VLAN internal) | Penyimpanan laporan, audit log, user, sesi, dan cache |
| **Database Dev / Test** | **SQLite** | `database.sqlite` / `:memory:` | Database ringan untuk pengembangan lokal & test suite |
| **File Storage & Cron** | **Laravel Local Storage** & **Scheduler** | `storage/app/private` | Penyimpanan lampiran, backup DB, & cleanup preview POMEC |
| **AI / LLM Engine** | **Ollama**, **Gemini**, **OpenRouter**, **OpenAI** | Default: `qwen3:4b-instruct` / `local` fallback | Routing intent & narasi chatbot *read-only* dengan circuit breaker |
| **Automated Testing** | **PHPUnit**, **Faker**, **Mockery** | PHPUnit `12.5.44` (`48` tes) | Pengujian otomatis fitur, RBAC, guardrail chatbot, & UI |
| **Code Quality & CLI** | **Laravel Pint**, **Tinker**, **Pail**, **Concurrently** | Pint `v1.32.3` · Tinker `v3.2.0` | Formatting PHP, REPL debugging, log tailing, & runner dev |

---

## Struktur Direktori Utama

```text
app/
├── Console/Commands/       # Evaluasi chatbot, retensi chat, & cleanup temp POMEC
├── Http/Controllers/       # Auth, Workspace, Reports, POMEC, Topology, Admin, Chatbot
├── Models/                 # User, Department, DepartmentReport, Pomec*, AuditLog, Chatbot*
├── Policies/               # Otorisasi akses laporan per departemen
└── Services/               # ChatbotService, Analytics, Export Excel/PDF, Backup, Diagnostics
database/
├── migrations/             # Skema tabel sistem, RBAC granular, POMEC, audit, & chatbot
└── seeders/                # CoreSystemSeeder, ChatbotKnowledgeSeeder, DemoDataSeeder
resources/
├── css/app.css             # Token tema Swiss-Belhotel (Light & Obsidian Dark Mode)
├── js/workspace.js         # Kontroler interaktif UI, filter, paginasi, Chart.js, & Bernie
└── views/                  # Template Blade login, workspace, partial tab, & ekspor PDF
tests/
├── Feature/                # HotelManagementReportTest & ChatbotSpecTest (48 tes)
└── fixtures/               # Golden evaluation set chatbot (chatbot_eval.json)
```

---

## Panduan Menjalankan Secara Lokal (Laragon)

1. **Siapkan proyek & dependensi**
   ```bash
   composer install
   npm install
   ```

2. **Konfigurasi environment**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   Sesuaikan koneksi database (`DB_*` di MySQL Laragon atau SQLite) dan opsi `CHATBOT_PROVIDER` pada `.env`.

3. **Migrasi & seed data awal**
   ```bash
   php artisan migrate --seed
   ```

4. **Build aset & jalankan server**
   ```bash
   npm run build
   php artisan serve
   ```
   Untuk pengembangan aktif dengan *Hot Module Replacement*, jalankan `npm run dev` secara paralel.

---

## Pengujian & Dokumentasi Lanjutan

- **Menjalankan seluruh unit & feature test** (otomatis memakai SQLite `:memory:` dan mode chatbot `local` sesuai `phpunit.xml`):
  ```bash
  php artisan test
  ```
- **Evaluasi akurasi model AI secara langsung** (opsional):
  ```bash
  php artisan chatbot:eval
  ```
- **Panduan operasional AI Chatbot & Knowledge Base**: [docs/chatbot.md](docs/chatbot.md)
- **Checklist deploy server produksi & keamanan VLAN**: [DEPLOYMENT.md](DEPLOYMENT.md)
