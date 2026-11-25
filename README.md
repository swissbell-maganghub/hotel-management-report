# Hotel Management Report System

**Swiss-Belhotel Waterfront Makassar** — Internal departmental reporting, POMEC utility analytics, role-based approval workflow, audit governance, and read-only AI operational assistant.

---

## Core Capabilities

- **Role-Based Executive & Operational Workspaces (`RBAC`)**
  - **General Manager (`GM`)**: Dedicated **Executive Dashboard** (`/?tab=dashboard`) with cross-departmental compliance matrix, operational pulse (FO, F&B, HK, ENG, FIN, SLS, HR), read-only System Topology, and Chart.js analytics.
  - **Head of Department (`HOD`) & Staff**: Departmental report authoring, draft/submit/revision/review workflow, multi-department delegation support, and workflow revision history.
  - **IT Administrator**: **System Overview**, Executive Preview, **System Topology** & live health checks, **User Management** (accounts, active sessions, RBAC matrix, report templates, database backup snapshots), **Audit Trail** center, and AI Knowledge Base administration.
- **Departmental Reports & Workflow (`Reports`)**
  - Dynamic per-department table templates, attachment support, HOD review & revision loop, filterable searchable tables, and Excel (`.xlsx`) + PDF export (`PhpSpreadsheet` & `Dompdf`).
- **POMEC Utility Analytics (`POMEC`)**
  - Structured Excel preview/commit workflow (electricity LWBP/WBP and water consumption), interactive trend & split charts, and PDF/Excel export.
- **Dedicated Security & Workflow Audit Center (`Audit Trail`)**
  - Sortable, multi-filter audit log viewer (`Auth`, `RBAC`, `Reports`, `POMEC`, `Topology`, `Chatbot`, `Templates`, `Database`), JSON payload inspection modal, Workflow Approval audit view, and CSV export.
- **Interactive AI Assistant (`Bernie`)**
  - Read-only RBAC-scoped assistant with hybrid Knowledge Base & LLM routing (`Ollama` on-premise, `Gemini`, `OpenRouter`, `OpenAI`, or deterministic `local` fallback) and circuit-breaker protection. See [docs/chatbot.md](docs/chatbot.md).
- **Light & Obsidian Dark Mode**
  - Persistent Light default with optional dark mode toggle across login, workspace, Bernie chat widget, and reactive Chart.js charts.

---

## Tech Stack

- **Backend**: PHP 8.3+, Laravel 13 (`laravel/framework`), Laravel Dompdf, PhpSpreadsheet
- **Frontend**: Blade, Tailwind CSS 4 (`@tailwindcss/vite`), Vite 8, Chart.js 4
- **Database**: MySQL 8.x (production) / SQLite (local/testing)
- **Testing**: PHPUnit 12 (`php artisan test`)

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
