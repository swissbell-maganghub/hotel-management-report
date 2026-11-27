# Hotel Management Report System

**Swiss-Belhotel Waterfront Makassar** — Internal departmental reporting, POMEC Control (utility monitoring), role-based approval workflow, audit governance, and read-only AI operational assistant.

---

## Core Capabilities

- **Role-Based Executive & Operational Workspaces (`RBAC`)**
  - **General Manager (`GM`)**: Dedicated **Executive Dashboard** (`/?tab=dashboard`) with cross-departmental compliance matrix, operational pulse (FO, F&B, HK, ENG, FIN, SLS, HR), read-only System Topology, and Chart.js analytics.
  - **Head of Department (`HOD`) & Staff**: Departmental report authoring, draft/submit/revision/review workflow, multi-department delegation support, and workflow revision history.
  - **IT Administrator**: **System Overview**, Executive Preview, **System Topology** & live health checks, **User Management** (accounts, active sessions, RBAC matrix, report templates, database backup snapshots), **Audit Trail** center, and AI Knowledge Base administration.
- **Departmental Reports & Workflow (`Reports`)**
  - Dynamic per-department table templates, attachment support, HOD review & revision loop, filterable searchable tables, and Excel (`.xlsx`) + PDF export (`PhpSpreadsheet` & `Dompdf`).
- **POMEC Control (`POMEC`)**
  - Structured Excel preview/commit workflow (electricity LWBP/WBP and water consumption), interactive trend & split charts, and PDF/Excel export.
- **Dedicated Security & Workflow Audit Center (`Audit Trail`)**
  - Sortable, multi-filter audit log viewer (`Auth`, `RBAC`, `Reports`, `POMEC`, `Topology`, `Chatbot`, `Templates`, `Database`), JSON payload inspection modal, Workflow Approval audit view, and CSV export.
- **Interactive AI Assistant (`Bernie`)**
  - Read-only RBAC-scoped assistant with hybrid Knowledge Base & LLM routing (`Ollama` on-premise, `Gemini`, `OpenRouter`, `OpenAI`, or deterministic `local` fallback) and circuit-breaker protection. See [docs/chatbot.md](docs/chatbot.md).
- **Light & Obsidian Dark Mode**
  - Persistent Light default with optional dark mode toggle across login, workspace, Bernie chat widget, and reactive Chart.js charts.

---

## Tools & Teknologi yang Digunakan

### 1. Backend & Core Framework

| Kategori | Tool / Package | Versi (`lock` / Constraint) | Peran dalam Sistem |
|---|---|---|---|
| Runtime | **PHP** | `^8.3` (terpasang `8.3.30`) | Runtime utama aplikasi server-side |
| Framework | **Laravel Framework** (`laravel/framework`) | `v13.43.0` | Routing, Eloquent ORM, Auth, RBAC, Validation, Scheduler |
| Dokumen Spreadsheet | **PhpSpreadsheet** (`phpoffice/phpspreadsheet`) | `5.11.0` | Impor & preview Excel POMEC serta ekspor laporan `.xlsx` |
| Dokumen PDF | **Laravel Dompdf** (`barryvdh/laravel-dompdf`) | `v3.1.2` | Pembuatan & ekspor laporan PDF (Departemen & POMEC) |
| CLI REPL | **Laravel Tinker** (`laravel/tinker`) | `v3.2.0` | Konsol interaktif untuk inspeksi data & debugging |

### 2. Frontend, UI & Visualisasi Data

| Kategori | Tool / Package | Versi (`lock` / Constraint) | Peran dalam Sistem |
|---|---|---|---|
| Templating | **Laravel Blade + Vanilla ES Modules** | Bawaan Laravel 13 | Struktur tampilan server-rendered & interaktivitas SPA-tab (`resources/js/workspace.js`) |
| CSS Framework | **Tailwind CSS** (`tailwindcss`, `@tailwindcss/vite`) | `4.3.5` | Desain antarmuka responsif Light Mode & Obsidian Dark Mode |
| Asset Bundler | **Vite** (`vite`, `laravel-vite-plugin`) | `8.3.8` / `3.2.1` | Kompilasi aset produksi & Hot Module Replacement (`npm run dev`) |
| Grafik & Chart | **Chart.js** (`chart.js`) | `4.6.1` | 8 grafik analitik interaktif (Executive Dashboard, Reports, POMEC) |
| Tipografi Web | **Google Fonts** (`Plus Jakarta Sans`, `Inter`, `JetBrains Mono`) | CDN Webfont | Font heading, teks antarmuka, dan angka tabular monospace |

### 3. Environment Lokal, Database, Storage & Operasional

| Kategori | Tool / Layanan | Keterangan / Versi | Peran dalam Sistem |
|---|---|---|---|
| Local Dev Stack | **Laragon** | PHP `8.3.30` & MySQL `8.4.3` (Apache/Nginx, Composer) | Lingkungan development lokal Windows untuk PHP CLI, web server, & MySQL |
| Database Produksi | **MySQL** | Versi `8.x` (`8.4.3` lokal / VLAN internal) | Penyimpanan relasional laporan, audit log, user, sesi, & cache |
| Database Dev / Test | **SQLite** | `database.sqlite` / `:memory:` | Database ringan untuk pengembangan lokal dan suite PHPUnit |
| Penyimpanan File | **Laravel Local Storage** (`storage/app/private`) | Terproteksi non-publik | Lampiran laporan, file preview POMEC sementara, & snapshot backup DB |
| Penjadwalan | **Laravel Scheduler** (`php artisan schedule:run`) | Cron / Task Scheduler | Pembersihan otomatis preview POMEC kedaluwarsa (`pomec:cleanup-temp`) |

### 4. AI Assistant (`Bernie`) & Keamanan

| Kategori | Provider / Komponen | Konfigurasi Default | Peran dalam Sistem |
|---|---|---|---|
| Local LLM (On-Premise) | **Ollama** | Model `qwen3:4b-instruct` | Pemrosesan intent & narasi AI lokal tanpa keluar jaringan hotel |
| Cloud LLM (Opsional) | **Google Gemini / OpenRouter / OpenAI** | `gemini-3.1-flash-lite` (Gemini) | Alternatif model AI cloud dengan sanitasi data & kontrol RBAC |
| Fallback & Guardrail | **Hybrid Keyword & Knowledge Base** | Mode `local` / Circuit Breaker | Jawaban deterministik glosarium, SOP, dan proteksi saat AI offline |

### 5. IDE, Testing, Code Quality & Dev Tooling

| Kategori | Tool / Package | Versi (`lock` / Keterangan) | Peran dalam Sistem |
|---|---|---|---|
| AI Agentic IDE | **Google Antigravity** | Desktop IDE | Pengembangan fitur, refactoring UI/UX, debugging, & verifikasi otomatis |
| Code Editor / IDE | **Visual Studio Code (VS Code)** | Desktop Editor | Penyuntingan kode PHP, Blade, JS, konfigurasi, & manajemen Git |
| Automated Testing | **PHPUnit** (`phpunit/phpunit`) | `12.5.44` | Menjalankan 48 unit & feature test suite (`php artisan test`) |
| Code Formatter | **Laravel Pint** (`laravel/pint`) | `v1.32.3` | Standarisasi gaya penulisan kode PHP |
| Test Helper | **Faker** & **Mockery** | `^1.23` / `^1.6` | Generator data seeder/uji & mocking HTTP/layanan |
| Dev Runner & Log | **Concurrently**, **Laravel Pail**, **Collision** | `10.0.7` / `^1.2.5` / `^8.6` | Orkestrasi proses `dev`, pemantauan log, & pelaporan error CLI |

---

## Quick Start (Local Development)

1. **Install dependencies**
   ```bash
   composer install
   npm install
   ```

2. **Configure environment**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   Update database and optional `CHATBOT_PROVIDER` settings in `.env`.

3. **Migrate & seed initial data**
   ```bash
   php artisan migrate --seed
   ```

4. **Build assets & start dev server**
   ```bash
   npm run build
   php artisan serve
   ```
   Or run `npm run dev` alongside `php artisan serve` for live HMR.

---

## Verification & Quality Gates

- Run the automated feature & unit test suite (AI provider defaulted to deterministic `local` in `phpunit.xml`):
  ```bash
  php artisan test
  ```
- Optional live LLM evaluation (see [docs/chatbot.md](docs/chatbot.md)):
  ```bash
  php artisan chatbot:eval
  ```

---

## Production Deployment

Refer to [DEPLOYMENT.md](DEPLOYMENT.md) for server prerequisites, HTTPS/VLAN security checklist, `APP_ENV=production` hardening, scheduler setup (`pomec:cleanup-temp`), and backup procedures.

---

## Panduan Kerja Tim di GitHub (Agar Rapi & Tidak Bentrok)

Prinsip utamanya sederhana: branch `main` **harus selalu bersih dan siap jalan**. Jadi saat mengerjakan fitur atau memperbaiki bug, kerjakan di branch terpisah dulu, lalu gabungkan ke `main` lewat **Pull Request (PR)**.

### 1. Aturan Penamaan Branch & Pesan Commit

| Tujuan Kerja | Format Nama Branch | Awalan Pesan Commit | Contoh |
|---|---|---|---|
| Bikin fitur baru | `feat/<nama-fitur>` | `feat: ...` | `feat/ekspor-audit-csv` |
| Perbaiki bug / error | `fix/<nama-masalah>` | `fix: ...` | `fix/tombol-darkmode-login` |
| Rapikan tampilan / kode | `refactor/<bagian>` | `refactor: ...` | `refactor/warna-darkmode` |
| Update panduan / README | `docs/<bagian>` | `docs: ...` | `docs/panduan-tim-github` |

### 2. Urutan Kerja Sehari-hari (5 Langkah)

1. **Ambil update terbaru dari `main`, lalu buat branch baru** *(jangan coding langsung di `main`)*:
   ```bash
   git checkout main
   git pull origin main
   git checkout -b feat/nama-fitur
   ```
2. **Simpan pekerjaan secara bertahap** *(biasakan cek `git status` sebelum `git add`)*:
   ```bash
   git status
   git add .
   git commit -m "feat: jelaskan singkat apa yang baru dibuat"
   ```
3. **Gabungkan update terbaru dari teman (`main`) sebelum upload** *(supaya kalau ada kode bentrok, beres di laptop sendiri dulu)*:
   ```bash
   git fetch origin
   git merge origin/main
   ```
4. **Pastikan build & tes tetap hijau sebelum upload**:
   ```bash
   npm run build
   php artisan test
   ```
5. **Upload branch ke GitHub & buat Pull Request (PR)**:
   ```bash
   git push -u origin feat/nama-fitur
   ```
   Buka GitHub → klik **Compare & pull request** → minta minimal 1 rekan cek → klik **Merge pull request**, lalu hapus branch yang sudah selesai dipakai.

### 3. Hal yang Harus Dihindari Supaya Tidak Saling Timpa Kode

| Bagian | Aturan Sederhana |
|---|---|
| **File Migration Database** | **Jangan edit** file migration lama yang sudah masuk ke `main`. Kalau mau tambah/ubah tabel, selalu buat file baru (`php artisan make:migration ...`). |
| **File yang Sering Diedit Bareng** | Kabari tim dulu sebelum ubah `routes/web.php`, `resources/js/workspace.js`, `resources/css/app.css`, atau `resources/views/workspace/index.blade.php` biar tidak edit bagian yang sama bersamaan. |
| **File Rahasia & File Sementara** | **Jangan pernah upload** `.env`, API key/password, `database/*.sqlite*`, `public/hot`, `public/build`, `storage/logs/*.log`, `vendor/`, atau `node_modules/`. |
| **Install Package Baru** | Kalau habis jalankan `composer require` atau `npm install`, file `.lock` (`composer.lock` / `package-lock.json`) wajib ikut di-commit supaya versi laptop tim sama semua. |

### 4. Cara Mengatasi Jika Terjadi Bentrok (*Merge Conflict*)

Kalau saat menjalankan `git merge origin/main` muncul peringatan *conflict*, jangan panik:

1. Ketik `git status` untuk melihat file mana saja yang bentrok (biasanya bertanda merah / *both modified*).
2. Buka file tersebut di **VS Code** atau **Antigravity**, lalu cari tanda `<<<<<<<`, `=======`, dan `>>>>>>>`.
3. Pilih kode mana yang mau dipakai (kode kamu, kode teman, atau gabungkan keduanya), lalu **hapus semua garis tanda** `<<<<<<<`, `=======`, dan `>>>>>>>`.
4. Simpan file, jalankan `npm run build` dan `php artisan test`, lalu selesaikan penggabungannya:
   ```bash
   git add .
   git commit -m "fix: selesaikan conflict dengan main"
   git push
   ```
   *(Kalau ragu di tengah jalan dan ingin batal dulu ke kondisi sebelum merge, ketik `git merge --abort`.)*
