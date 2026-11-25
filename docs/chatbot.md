# AI Chatbot Assistant — Operations Guide

For IT Administrators who run and maintain the chatbot (spec §16–17). The chatbot is **read-only** and follows the same RBAC as the application.

## 1. How it answers (3 layers)

| Layer | What happens | Uses AI? |
|---|---|---|
| 1. Fixed answers | Security guardrail (password/secret/delete requests), greetings & identity, off-topic decline, **Knowledge Base** (glossary, guides, FAQ), available-dates info | No |
| 2. Intent | The model reads *what the user wants* and returns JSON (`intent`, department, period, status, date, topic). Only the question is sent — **no report data**. PHP validates every field, intersects the department with the user's RBAC scope, and corrects obvious model mistakes. | Yes (short call) |
| 3. Answer | Figures, lists, comparisons, trends and conclusions are built by code from controlled queries (`app/Services/ChatbotAnalytics.php`). Only "why…" questions about specific reports are narrated by the model, from those reports' fields, and any number it writes is checked against the data. | Only for "why…" |

If the AI is down or slow, a **circuit breaker** skips it and the keyword router answers instantly (spec 16.4). Code: `app/Services/ChatbotService.php`.

## 2. Making the chatbot smarter (no deploy needed)

1. Open **Admin → Insight Chatbot**. Review:
   - **Pertanyaan belum dikenali**: questions with no route (grouped, with counts).
   - **Jawaban diberi 👎**: answers users found unhelpful (with their comment).
2. Click **Jadikan artikel** → write the answer → save. It is live immediately.
3. Re-ask the question in the chat to confirm.

### Trigger syntax (Knowledge Base)
One rule per line. An article matches if **any** line matches.

| Syntax | Meaning | Example |
|---|---|---|
| `a\|b\|c` | any of the alternatives | `checkin\|check in` |
| `group + group` | every group must appear | `cara\|sop\|prosedur + check in\|checkin` |
| `=term` | the whole message is exactly the term | `=lwbp` |

Terms of ≤3 letters (`fo`, `gm`) must be whole words; longer terms match at the start of a word (`ekspor` also matches `ekspornya`). When several articles match, the longest matched text wins, then **Priority**. **Visible to** limits an article to roles (empty = everyone). Answers support `**bold**`, `- ` bullets and `1. ` steps.

## 3. Quality gate before any change

Run the golden set against the real model **before** changing the model, prompts or routing rules:

```
php artisan chatbot:eval                       # model from .env
php artisan chatbot:eval --model=qwen3:8b      # try a model without touching .env
php artisan chatbot:eval --only=rbac           # subset
```

Cases: `tests/fixtures/chatbot_eval.json` (57 cases: random chat, typos, follow-ups, glossary, guides, reports, POMEC, topology, RBAC, prompt injection). Exit code ≠ 0 when the pass rate is below `--min` (default 90%). Add every real failure from Insights as a new case. Needs the demo seed data.

Fast deterministic tests (CI): `php artisan test` (AI calls are faked).

## 4. Changing or upgrading the model

1. `ollama pull <model>` — always choose an **`-instruct`** tag (plain `qwen3:4b` is a thinking model and returns empty/slow answers).
2. `php artisan chatbot:eval --model=<model>` → must stay ≥ 90%.
3. Set `CHATBOT_MODEL=<model>` in `.env`, then `php artisan config:clear`.

| Server | Suggested model |
|---|---|
| 8 GB RAM, CPU only | `qwen3:4b-instruct` (current) |
| 32 GB RAM, CPU only | `qwen3:30b-a3b-instruct` |
| GPU ≥ 16 GB VRAM | `qwen3:14b` / `qwen3:32b` instruct variants |

Cloud models (OpenAI-compatible endpoints) work via `CHATBOT_PROVIDER=openai`, but require management/IT approval and a privacy assessment (spec §18).

## 5. Running Ollama on the server

- Install Ollama; set the system environment variable `OLLAMA_MODELS` (e.g. `D:\ai-ollama\models`) **before** pulling models.
- Run Ollama as a service that starts on boot (Windows: the Ollama app starts at login; for a headless server use a service wrapper such as NSSM around `ollama serve`).
- Health: **Chatbot AI** menu (IT Admin) shows whether Ollama is reachable and the model is installed; **Tes koneksi** re-checks, clears the circuit breaker and loads the model. The same check appears under **Topology → Audit Kesiapan Produksi → Model AI Asisten**.
- **Kill switch**: the toggle in **Chatbot AI** turns the assistant off for everyone (users see a maintenance notice). It is stored in `storage/app/chatbot-disabled.json`, so cache clears during a deploy don't turn it back on. Logged as `chatbot.disabled` / `chatbot.enabled`.
- **Uji Pertanyaan**: run any question as any user to see the route, article/filter, AI calls and time. Not logged as usage, works while the chatbot is off.
- The model stays loaded for 60 minutes after the last question (`keep_alive`). Loading it again takes ~9 s, longer than the 8 s intent timeout, so the **first question after idle falls back** to keyword answers. On a dedicated server set the system environment variable `OLLAMA_KEEP_ALIVE=-1` (never unload; costs ~3 GB RAM permanently). **Chatbot AI → Tes koneksi** also loads the model.

## 6. Configuration (`.env` → `config/hotel.php`)

| Key | Default | Purpose |
|---|---|---|
| `CHATBOT_PROVIDER` | `local` | `ollama` (on-premise AI), `openai` (approved cloud), `local` (no AI) |
| `CHATBOT_MODEL` | `qwen3:4b-instruct` | Model tag |
| `CHATBOT_TIMEOUT` / `CHATBOT_TIMEOUT_INTENT` | 20 / 8 s | Narration vs intent call timeouts |
| `CHATBOT_BREAKER_FAILURES` / `_COOLDOWN` | 3 / 120 s | Skip the AI after N failures in 60 s |
| `CHATBOT_RETENTION_DAYS` | 90 | Chat turn log retention (`chatbot:prune`, daily 02:30) |
| `CHATBOT_REVIEW_SLA_DAYS` | 2 | "Waiting for review too long" threshold |
| `CHATBOT_CHANGE_PCT` / `CHATBOT_WBP_SHARE_PCT` | 10 / 25 | Utility conclusion thresholds — confirm with Engineering |

The scheduler must run (`php artisan schedule:work`, or a Task Scheduler/cron entry for `schedule:run` every minute).

## 7. Security & privacy controls

- **RBAC**: every query goes through `ChatbotAnalytics::reportsQuery()` (user scope applied first; filters can only narrow it). Departments outside scope are refused.
- **Read-only**: no write paths; write/delete/approve requests are blocked.
- **Secrets**: requests mentioning passwords/tokens are blocked; model output is scanned for credential patterns and private IPs (masked for non-IT roles) → `chatbot.output_blocked` in the audit log.
- **Prompt injection**: staff-written text (titles, notes, table rows) is stripped of instruction phrases and chat-template tokens and fenced as data before reaching the model.
- **Conversation memory** is server-side (session); the browser cannot inject history. Reset clears it.
- **Data to the model**: intent calls send only the question and department codes; "why" narration sends the relevant reports' titles/notes. Utility figures and statistics are never sent.
- **Logs**: `chatbot_turns` stores a 200-character question excerpt, route, latency and rating — **not** the answer text. Pruned after the retention period.
