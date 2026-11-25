{{-- Chatbot Insights + Knowledge Base (IT Admin). The improvement loop: see what fails → add an article → no deploy. --}}
@php
    $isEn = $locale === 'en';
    $typeLabel = ['glossary' => $isEn ? 'Glossary' : 'Glosarium', 'guide' => $isEn ? 'Guide' : 'Panduan', 'faq' => 'FAQ'];
    $roleOptions = ['gm' => 'GM', 'hod' => 'HOD', 'staff' => 'Staff', 'it_admin' => 'IT Admin'];
    $aiCheck = collect($systemDiagnostics['checks'] ?? [])->firstWhere('key', 'chatbot_ai');
    $cb = config('hotel.chatbot');
    $resolvedCb = \App\Services\ChatbotService::resolveProviderConfig();
    $hostPort = parse_url((string) $resolvedCb['endpoint'], PHP_URL_HOST)
        .(parse_url((string) $resolvedCb['endpoint'], PHP_URL_PORT) ? ':'.parse_url((string) $resolvedCb['endpoint'], PHP_URL_PORT) : '');
    // ponytail: read-only view of .env; editing secrets/models from the browser is deliberately not offered.
    $settings = [
        ['CHATBOT_PROVIDER', $resolvedCb['provider'], $isEn ? 'ollama = on-premise AI, gemini/openrouter/openai = cloud AI, local = keyword only' : 'ollama = AI lokal, gemini/openrouter/openai = AI cloud, local = kata kunci saja'],
        ['ACTIVE MODEL', $resolvedCb['model'], $isEn ? 'Effective model resolved for active provider' : 'Model aktif yang dipakai provider terpilih'],
        ['ACTIVE ENDPOINT', $hostPort ?: '-', $isEn ? 'Effective AI API host' : 'Alamat server API AI aktif'],
        ['CHATBOT_TIMEOUT_INTENT / CHATBOT_TIMEOUT', $cb['timeout_intent'].' s / '.$cb['timeout'].' s', $isEn ? 'Read the question / explain "why"' : 'Membaca maksud pertanyaan / menjelaskan "kenapa"'],
        ['CHATBOT_BREAKER_FAILURES / _COOLDOWN', $cb['breaker_failures'].'× / '.$cb['breaker_cooldown'].' s', $isEn ? 'Skip the AI after repeated failures' : 'Lewati AI sementara jika gagal berturut-turut'],
        ['CHATBOT_RETENTION_DAYS', $cb['retention_days'].($isEn ? ' days' : ' hari'), $isEn ? 'Chat log retention' : 'Lama log chat disimpan'],
        ['—', $isEn ? '30 / min per user' : '30 / menit per user', $isEn ? 'Question rate limit (fixed in code)' : 'Batas jumlah pertanyaan (tetap di kode)'],
        ['CHATBOT_REVIEW_SLA_DAYS', $cb['thresholds']['review_sla_days'].($isEn ? ' days' : ' hari'), $isEn ? '"Waiting for review too long"' : '"Menunggu review terlalu lama"'],
        ['CHATBOT_CHANGE_PCT / _WBP_SHARE_PCT', $cb['thresholds']['change_pct'].'% / '.$cb['thresholds']['wbp_share_pct'].'%', $isEn ? 'Utility conclusion thresholds' : 'Ambang kesimpulan utilitas (POMEC)'],
    ];
@endphp

@php
    $off = $chatbotAdmin['disabled'] ?? null;
    $aiOk = ($aiCheck['status'] ?? 'warn') === 'pass';
    $card = 'bg-white border border-slate-200/80 rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.04)]';
@endphp

<div class="space-y-5" id="admin-chatbot">
    {{-- Controls: kill switch + AI connection --}}
    <div class="grid lg:grid-cols-2 gap-5">
        <div class="{{ $card }} p-5 flex flex-col gap-4">
            <div class="flex items-start justify-between gap-4">
                <div class="space-y-1">
                    <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ $isEn ? 'Chatbot service' : 'Layanan Chatbot' }}</div>
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full {{ $off ? 'bg-slate-400' : 'bg-emerald-500 ring-4 ring-emerald-500/15' }}"></span>
                        <span class="font-heading font-bold text-lg text-navy">{{ $off ? ($isEn ? 'Off' : 'Nonaktif') : ($isEn ? 'Active' : 'Aktif') }}</span>
                    </div>
                </div>
                <label class="relative inline-flex items-center cursor-pointer shrink-0" title="{{ $isEn ? 'Turn the chatbot on/off for all users' : 'Aktifkan/nonaktifkan chatbot untuk semua user' }}">
                    <input type="checkbox" data-chatbot-toggle class="sr-only peer" @checked(! $off)>
                    <span class="w-11 h-6 rounded-full bg-slate-300 peer-checked:bg-teal transition-colors peer-focus-visible:ring-2 peer-focus-visible:ring-navy/30 after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:w-5 after:h-5 after:rounded-full after:bg-white after:shadow-sm after:transition-transform peer-checked:after:translate-x-5"></span>
                    <span class="sr-only">{{ $isEn ? 'Chatbot on' : 'Chatbot aktif' }}</span>
                </label>
            </div>
            <p class="text-xs text-slate-500 leading-relaxed">
                {{ $isEn ? 'Switch off to stop the assistant for every user at once (maintenance or a wrong answer). Users see a maintenance notice; reports are unaffected.' : 'Matikan untuk menghentikan asisten bagi semua user sekaligus (perawatan atau ada jawaban bermasalah). User akan melihat pesan perawatan; laporan tidak terpengaruh.' }}
            </p>
            @if ($off)
                <div class="text-xs rounded-xl bg-amber-50 border border-amber-200 text-amber-800 px-3 py-2">
                    {{ $isEn ? 'Switched off by' : 'Dinonaktifkan oleh' }} <span class="font-semibold">{{ $off['by'] }}</span>
                    @if (($off['at'] ?? '-') !== '-') • {{ \Illuminate\Support\Carbon::parse($off['at'])->timezone('Asia/Makassar')->translatedFormat('d M Y, H:i') }} WITA @endif
                </div>
            @endif
        </div>

        <div class="{{ $card }} p-5 flex flex-col gap-4">
            <div class="flex items-start justify-between gap-4">
                <div class="space-y-1 min-w-0">
                    <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ $isEn ? 'AI connection' : 'Koneksi AI' }} • <span class="font-mono-num normal-case tracking-normal">{{ $resolvedCb['provider'] }} / {{ $resolvedCb['model'] }}</span></div>
                    <div class="flex items-center gap-2" data-ai-status>
                        <span data-ai-dot class="w-2.5 h-2.5 rounded-full {{ $aiOk ? 'bg-emerald-500 ring-4 ring-emerald-500/15' : 'bg-amber-500 ring-4 ring-amber-500/15' }}"></span>
                        <span data-ai-current class="font-heading font-bold text-lg text-navy truncate">{{ $aiCheck['current'] ?? '-' }}</span>
                    </div>
                </div>
                <button type="button" data-ai-check class="shrink-0 px-3 py-2 rounded-xl border border-slate-200 text-xs font-semibold text-navy hover:bg-slate-50 transition cursor-pointer disabled:opacity-60 disabled:cursor-wait">
                    {{ $isEn ? 'Test connection' : 'Tes koneksi' }}
                </button>
            </div>
            <p data-ai-advice class="text-xs text-slate-500 leading-relaxed">{{ $aiCheck['advice'] ?? '' }}</p>
            <p class="text-[11px] text-slate-400">{{ $isEn ? 'Testing also clears the automatic AI pause after repeated failures, so a recovered model is used immediately.' : 'Tes juga membuka jeda otomatis AI (setelah gagal berturut-turut), sehingga AI yang sudah pulih langsung dipakai lagi.' }} <span data-ai-time></span></p>
        </div>
    </div>

    {{-- Test a question --}}
    <div class="{{ $card }}">
        <div class="px-5 py-4 border-b border-slate-100">
            <h3 class="font-heading font-bold text-navy">{{ $isEn ? 'Test a question' : 'Uji Pertanyaan' }}</h3>
            <p class="text-xs text-slate-500">{{ $isEn ? 'See exactly how the assistant answers — and why — as any user. Tests are not counted in Insights and do not touch anyone\'s chat.' : 'Lihat persis jawaban asisten beserta alasannya, sebagai user mana pun. Uji tidak dihitung di Insight dan tidak mengubah chat siapa pun.' }}</p>
        </div>
        <form data-chat-test class="p-5 flex flex-col sm:flex-row gap-3">
            <input name="question" required maxlength="500" autocomplete="off" placeholder="{{ $isEn ? 'e.g. what is lwbp?  •  why was the F&B report returned?' : 'mis. apa itu lwbp?  •  kenapa laporan F&B dikembalikan?' }}"
                class="flex-1 min-w-0 px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-navy/15 focus:border-navy/40">
            <select name="user_id" class="sm:w-64 px-3 py-2.5 rounded-xl border border-slate-200 text-xs text-slate-700 bg-white" aria-label="{{ $isEn ? 'Test as' : 'Uji sebagai' }}">
                <option value="">{{ $isEn ? 'As me' : 'Sebagai saya' }} ({{ $user->name }})</option>
                @foreach (($managedUsers ?? collect())->where('is_active', true)->where('id', '!=', $user->id) as $mu)
                    <option value="{{ $mu->id }}">{{ $mu->name }} — {{ $mu->roleLabel($locale) }}{{ $mu->department ? ' · '.$mu->department->code : '' }}</option>
                @endforeach
            </select>
            <button type="submit" class="px-4 py-2.5 rounded-xl bg-navy text-white text-xs font-semibold hover:bg-navy/90 transition cursor-pointer disabled:opacity-60 disabled:cursor-wait">{{ $isEn ? 'Run test' : 'Uji' }}</button>
        </form>
        <div data-chat-test-result class="hidden px-5 pb-5"></div>
    </div>

    {{-- Insights --}}
    <div class="bg-white border border-slate-200/80 rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
        <div class="px-5 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h3 class="font-heading font-bold text-navy">{{ $isEn ? 'Chatbot Insights' : 'Insight Chatbot' }}</h3>
                <p class="text-xs text-slate-500">{{ $isEn ? "Last {$ins['days']} days. Unrecognised and down-voted questions are what to improve next." : "{$ins['days']} hari terakhir. Pertanyaan yang belum dikenali dan dinilai kurang tepat adalah prioritas perbaikan." }}</p>
            </div>
        </div>
        <div class="p-5 space-y-5">
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 text-center">
                @php
                    $stats = [
                        [$isEn ? 'Questions' : 'Pertanyaan', number_format($ins['total'])],
                        [$isEn ? 'Helpful' : 'Membantu', number_format($ins['up'])],
                        [$isEn ? 'Not helpful' : 'Kurang tepat', number_format($ins['down'])],
                        [$isEn ? 'AI fallback' : 'Fallback AI', $ins['fallback_pct'].'%'],
                        ['p50', number_format($ins['p50_ms'] / 1000, 1).' s'],
                        ['p95', number_format($ins['p95_ms'] / 1000, 1).' s'],
                    ];
                @endphp
                @foreach ($stats as [$label, $value])
                    <div class="rounded-xl border border-slate-200/80 bg-alabaster px-3 py-2.5">
                        <div class="text-[11px] text-slate-500">{{ $label }}</div>
                        <div class="font-mono-num font-bold text-navy text-lg">{{ $value }}</div>
                    </div>
                @endforeach
            </div>
            <p class="text-[11px] text-slate-400">{{ $isEn ? 'Latency is measured only on answers that used the AI model.' : 'Latensi hanya dihitung untuk jawaban yang memakai model AI.' }}</p>

            <div class="grid lg:grid-cols-2 gap-5">
                <div>
                    <h4 class="text-sm font-semibold text-navy mb-2">{{ $isEn ? 'Unrecognised questions' : 'Pertanyaan belum dikenali' }}</h4>
                    <ul class="divide-y divide-slate-100 text-xs border border-slate-200/80 rounded-xl">
                        @forelse ($ins['unmatched'] as $u)
                            <li class="px-3 py-2 flex items-center justify-between gap-2">
                                <span class="text-slate-700">{{ $u->question_excerpt }} <span class="text-slate-400 font-mono-num">×{{ $u->n }}</span></span>
                                <button type="button" data-kb-from-question="{{ $u->question_excerpt }}" class="shrink-0 px-2 py-1 rounded-lg bg-teal/10 text-teal font-semibold hover:bg-teal hover:text-white transition cursor-pointer">
                                    {{ $isEn ? 'Make article' : 'Jadikan artikel' }}
                                </button>
                            </li>
                        @empty
                            <li class="px-3 py-4 text-center text-slate-400">{{ $isEn ? 'None — every question was recognised.' : 'Tidak ada — semua pertanyaan dikenali.' }}</li>
                        @endforelse
                    </ul>
                </div>
                <div>
                    <h4 class="text-sm font-semibold text-navy mb-2">{{ $isEn ? 'Down-voted answers' : 'Jawaban dinilai kurang tepat' }}</h4>
                    <ul class="divide-y divide-slate-100 text-xs border border-slate-200/80 rounded-xl">
                        @forelse ($ins['downvoted'] as $d)
                            <li class="px-3 py-2 space-y-0.5">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-slate-700">{{ $d->question_excerpt }}</span>
                                    <button type="button" data-kb-from-question="{{ $d->question_excerpt }}" class="shrink-0 px-2 py-1 rounded-lg bg-teal/10 text-teal font-semibold hover:bg-teal hover:text-white transition cursor-pointer">
                                        {{ $isEn ? 'Make article' : 'Jadikan artikel' }}
                                    </button>
                                </div>
                                <div class="text-[11px] text-slate-400 font-mono-num">{{ $d->source }} • {{ $d->user?->name ?? '-' }} • {{ $d->created_at?->timezone('Asia/Makassar')->format('d M H:i') }}</div>
                                @if ($d->comment)<div class="text-[11px] text-slate-500 italic">“{{ $d->comment }}”</div>@endif
                            </li>
                        @empty
                            <li class="px-3 py-4 text-center text-slate-400">{{ $isEn ? 'No down-votes yet.' : 'Belum ada jawaban yang dinilai kurang tepat.' }}</li>
                        @endforelse
                    </ul>
                </div>
            </div>

            @if (! empty($ins['by_source']))
                <div class="flex flex-wrap gap-1.5 text-[11px]">
                    @foreach ($ins['by_source'] as $src => $n)
                        <span class="px-2 py-0.5 rounded-full border border-slate-200 bg-slate-50 text-slate-600 font-mono-num">{{ $src }}: {{ $n }}</span>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Knowledge base --}}
    <div class="bg-white border border-slate-200/80 rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
        <div class="px-5 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h3 class="font-heading font-bold text-navy">{{ $isEn ? 'Chatbot Knowledge Base' : 'Knowledge Base Chatbot' }}</h3>
                <p class="text-xs text-slate-500">{{ $isEn ? 'Glossary, guides and FAQ answered word-for-word by the assistant. Changes apply immediately.' : 'Glosarium, panduan, dan FAQ yang dijawab apa adanya oleh asisten. Perubahan langsung berlaku.' }}</p>
            </div>
            <button type="button" data-kb-new class="px-3 py-2 rounded-xl bg-navy text-white text-xs font-semibold hover:bg-navy/90 transition cursor-pointer">+ {{ $isEn ? 'New article' : 'Artikel baru' }}</button>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="py-3 px-4">{{ $isEn ? 'Title' : 'Judul' }}</th>
                        <th class="py-3 px-4">{{ $isEn ? 'Type' : 'Jenis' }}</th>
                        <th class="py-3 px-4">{{ $isEn ? 'Roles' : 'Role' }}</th>
                        <th class="py-3 px-4 text-right">{{ $isEn ? 'Hits' : 'Dipakai' }}</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4 text-right">{{ $isEn ? 'Actions' : 'Aksi' }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($kb as $k)
                        <tr class="hover:bg-slate-50/70">
                            <td class="py-3 px-4 font-medium text-navy">{{ $k->title }}</td>
                            <td class="py-3 px-4">{{ $typeLabel[$k->type] ?? $k->type }}</td>
                            <td class="py-3 px-4 text-slate-500">{{ $k->roles ? collect($k->roles)->map(fn ($r) => $roleOptions[$r] ?? $r)->implode(', ') : ($isEn ? 'All' : 'Semua') }}</td>
                            <td class="py-3 px-4 text-right font-mono-num">{{ $k->hits }}</td>
                            <td class="py-3 px-4">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold border {{ $k->is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-500 border-slate-200' }}">{{ $k->is_active ? ($isEn ? 'Active' : 'Aktif') : ($isEn ? 'Inactive' : 'Nonaktif') }}</span>
                            </td>
                            <td class="py-3 px-4 text-right whitespace-nowrap">
                                <button type="button" data-kb-edit="{{ json_encode($k->only(['id', 'type', 'title', 'triggers', 'body_id', 'body_en', 'roles', 'priority', 'is_active', 'hits'])) }}" class="px-2.5 py-1.5 rounded-lg bg-slate-100 hover:bg-navy hover:text-white text-navy font-semibold transition cursor-pointer">{{ $isEn ? 'Edit' : 'Ubah' }}</button>
                                <button type="button" data-kb-delete="{{ $k->id }}" class="px-2.5 py-1.5 rounded-lg bg-rose-50 hover:bg-crimson hover:text-white text-crimson-dark font-semibold transition cursor-pointer">{{ $isEn ? 'Del' : 'Hapus' }}</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Technical configuration (.env), read-only and collapsed: rarely needed day to day --}}
    <details class="{{ $card }} group">
        <summary class="px-5 py-4 flex items-center justify-between gap-3 cursor-pointer list-none select-none">
            <div>
                <h3 class="font-heading font-bold text-navy">{{ $isEn ? 'Technical configuration' : 'Konfigurasi Teknis' }}</h3>
                <p class="text-xs text-slate-500">{{ $isEn ? 'Read-only. To change: edit .env on the server, then run php artisan config:clear.' : 'Hanya dibaca. Cara mengubah: edit .env di server, lalu jalankan php artisan config:clear.' }}</p>
            </div>
            <svg class="w-4 h-4 text-slate-400 transition-transform group-open:rotate-180 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </summary>
        <div class="overflow-x-auto border-t border-slate-100">
            <table class="w-full text-xs">
                <tbody class="divide-y divide-slate-100">
                    @foreach ($settings as [$key, $value, $hint])
                        <tr>
                            <td class="py-2.5 px-5 font-mono-num text-slate-500 whitespace-nowrap">{{ $key }}</td>
                            <td class="py-2.5 px-4 font-mono-num font-semibold text-navy whitespace-nowrap">{{ $value }}</td>
                            <td class="py-2.5 px-4 text-slate-500">{{ $hint }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>

    {{-- Editor (native <dialog>) --}}
    @php
        $field = 'w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-white text-sm text-slate-800 placeholder:text-slate-400 transition focus:outline-none focus:border-navy/40 focus:ring-4 focus:ring-navy/10 aria-[invalid=true]:border-rose-400 aria-[invalid=true]:ring-rose-100';
        $sectionTitle = 'text-[11px] font-semibold uppercase tracking-wider text-slate-400';
        $typeHelp = [
            'glossary' => $isEn ? 'Meaning of a term or abbreviation' : 'Arti istilah atau singkatan',
            'guide' => $isEn ? 'Steps to use the application' : 'Langkah memakai aplikasi',
            'faq' => $isEn ? 'Other common questions & SOP' : 'Pertanyaan umum & SOP lainnya',
        ];
    @endphp
    {{-- m-auto: Tailwind preflight zeroes the margin that centres a native <dialog>. --}}
    <dialog id="kb-dialog" aria-labelledby="kb-dialog-title"
        class="m-auto p-0 rounded-2xl bg-white shadow-2xl w-[min(900px,calc(100vw-24px))] max-h-[calc(100dvh-24px)] backdrop:bg-navy/45 backdrop:backdrop-blur-[2px] open:flex open:flex-col overflow-hidden">
        <form id="kb-form" method="dialog" novalidate class="flex flex-col min-h-0 flex-1">
            <input type="hidden" name="id">

            {{-- Header --}}
            <div class="flex items-start justify-between gap-4 px-6 py-4 border-b border-slate-100">
                <div class="flex items-start gap-3 min-w-0">
                    <div class="w-10 h-10 rounded-xl bg-navy/5 text-navy grid place-items-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                    </div>
                    <div class="min-w-0">
                        <h3 id="kb-dialog-title" class="font-heading font-bold text-navy text-lg leading-tight">{{ $isEn ? 'New article' : 'Artikel baru' }}</h3>
                        <p class="text-xs text-slate-500 mt-0.5">{{ $isEn ? 'The assistant shows this answer word-for-word. It goes live as soon as you save.' : 'Asisten menampilkan jawaban ini apa adanya. Langsung berlaku setelah disimpan.' }}</p>
                    </div>
                </div>
                <button type="button" data-kb-cancel class="p-2 -m-1 rounded-lg text-slate-400 hover:text-navy hover:bg-slate-100 transition cursor-pointer" aria-label="{{ $isEn ? 'Close' : 'Tutup' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Body --}}
            <div class="flex-1 min-h-0 overflow-y-auto px-6 py-5 space-y-7">
                <div data-kb-errors class="hidden rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs text-crimson-dark" role="alert"></div>

                {{-- 1. Article --}}
                <section class="space-y-4">
                    <div class="{{ $sectionTitle }}">1 · {{ $isEn ? 'Article' : 'Artikel' }}</div>
                    <label class="block space-y-1.5">
                        <span class="flex items-baseline justify-between text-xs font-semibold text-slate-700">{{ $isEn ? 'Title' : 'Judul' }} <span class="font-normal text-slate-400 font-mono-num" data-count-for="title">0/150</span></span>
                        <input name="title" required maxlength="150" class="{{ $field }}" placeholder="{{ $isEn ? 'e.g. Guest check-in SOP' : 'mis. SOP check-in tamu' }}">
                    </label>
                    <fieldset class="space-y-1.5">
                        <legend class="text-xs font-semibold text-slate-700 mb-1.5">{{ $isEn ? 'Type' : 'Jenis' }}</legend>
                        <div class="grid sm:grid-cols-3 gap-2">
                            @foreach ($typeLabel as $v => $l)
                                <label class="relative flex flex-col gap-0.5 rounded-xl border border-slate-200 px-3.5 py-2.5 cursor-pointer transition hover:border-slate-300 has-[:checked]:border-navy has-[:checked]:bg-navy/[0.03] has-[:checked]:ring-4 has-[:checked]:ring-navy/5 has-[:focus-visible]:ring-4 has-[:focus-visible]:ring-navy/15">
                                    <input type="radio" name="type" value="{{ $v }}" class="sr-only peer">
                                    <span class="text-sm font-semibold text-slate-700 peer-checked:text-navy">{{ $l }}</span>
                                    <span class="text-[11px] text-slate-500">{{ $typeHelp[$v] }}</span>
                                    <svg class="hidden peer-checked:block absolute top-2.5 right-2.5 w-4 h-4 text-navy" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                </section>

                {{-- 2. Triggers --}}
                <section class="space-y-3">
                    <div class="{{ $sectionTitle }}">2 · {{ $isEn ? 'When is it used?' : 'Kapan artikel ini dipakai?' }}</div>
                    <label class="block space-y-1.5">
                        <span class="text-xs font-semibold text-slate-700">{{ $isEn ? 'Triggers — one rule per line' : 'Pemicu — satu aturan per baris' }}</span>
                        <textarea name="triggers" required rows="4" spellcheck="false" class="{{ $field }} font-mono-num text-[13px] leading-relaxed resize-y" placeholder="cara|sop|prosedur + check in|checkin&#10;=checkin"></textarea>
                    </label>
                    <div class="grid sm:grid-cols-3 gap-2 text-[11px] text-slate-600">
                        <div class="rounded-lg bg-slate-50 border border-slate-100 px-3 py-2"><code class="font-mono-num font-semibold text-navy">a|b</code> — {{ $isEn ? 'any of these words' : 'salah satu kata ini' }}</div>
                        <div class="rounded-lg bg-slate-50 border border-slate-100 px-3 py-2"><code class="font-mono-num font-semibold text-navy">a + b</code> — {{ $isEn ? 'every group must appear' : 'semua kelompok harus ada' }}</div>
                        <div class="rounded-lg bg-slate-50 border border-slate-100 px-3 py-2"><code class="font-mono-num font-semibold text-navy">=a</code> — {{ $isEn ? 'the whole message is exactly a' : 'pesan persis berisi a' }}</div>
                    </div>
                    <div class="rounded-xl border border-dashed border-slate-200 p-3 space-y-2">
                        <div class="flex flex-col sm:flex-row gap-2">
                            <input data-kb-try maxlength="500" class="{{ $field }} sm:flex-1" placeholder="{{ $isEn ? 'Type a question a user might ask…' : 'Ketik contoh pertanyaan user…' }}" aria-label="{{ $isEn ? 'Sample question' : 'Contoh pertanyaan' }}">
                            <button type="button" data-kb-try-run class="px-4 py-2.5 rounded-xl border border-slate-200 bg-white text-xs font-semibold text-navy hover:bg-slate-50 transition cursor-pointer disabled:opacity-60">{{ $isEn ? 'Test trigger' : 'Uji pemicu' }}</button>
                        </div>
                        <p data-kb-try-result class="text-xs text-slate-500">{{ $isEn ? 'Check that the questions users really ask reach this article — before saving.' : 'Pastikan pertanyaan yang biasa diajukan user mengarah ke artikel ini — sebelum disimpan.' }}</p>
                    </div>
                </section>

                {{-- 3. Answer --}}
                <section class="space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="{{ $sectionTitle }}">3 · {{ $isEn ? 'Answer' : 'Jawaban' }}</div>
                        <div class="inline-flex p-0.5 rounded-lg bg-slate-100 text-xs font-semibold" role="tablist">
                            <button type="button" role="tab" data-kb-lang="id" class="px-3 py-1.5 rounded-md cursor-pointer transition">Bahasa Indonesia</button>
                            <button type="button" role="tab" data-kb-lang="en" class="px-3 py-1.5 rounded-md cursor-pointer transition">English <span class="font-normal opacity-70">({{ $isEn ? 'optional' : 'opsional' }})</span></button>
                        </div>
                    </div>
                    <div class="grid md:grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <textarea name="body_id" required rows="9" maxlength="5000" data-kb-body="id" class="{{ $field }} leading-relaxed resize-y" placeholder="{{ $isEn ? 'Write the answer…' : 'Tulis jawaban…' }}&#10;&#10;**{{ $isEn ? 'Steps' : 'Langkah' }}:**&#10;1. …&#10;2. …"></textarea>
                            <textarea name="body_en" rows="9" maxlength="5000" data-kb-body="en" class="{{ $field }} leading-relaxed resize-y hidden" placeholder="{{ $isEn ? 'English answer (falls back to Indonesian when empty)' : 'Jawaban bahasa Inggris (kosong = pakai Bahasa Indonesia)' }}"></textarea>
                            <div class="flex items-center justify-between text-[11px] text-slate-400">
                                <span><code class="font-mono-num">**{{ $isEn ? 'bold' : 'tebal' }}**</code> · <code class="font-mono-num">- {{ $isEn ? 'bullet' : 'poin' }}</code> · <code class="font-mono-num">1. {{ $isEn ? 'step' : 'langkah' }}</code></span>
                                <span class="font-mono-num" data-count-for="body">0/5000</span>
                            </div>
                        </div>
                        <div class="rounded-xl border border-slate-200/80 bg-slate-50/70 p-3 flex flex-col min-h-[180px]">
                            <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-2">{{ $isEn ? 'Preview — as users will see it' : 'Pratinjau — seperti yang dilihat user' }}</div>
                            <div data-kb-preview class="bg-white border border-slate-200/80 rounded-2xl rounded-tl-md px-3.5 py-2.5 text-[13px] text-slate-700 leading-relaxed space-y-2 shadow-[0_1px_2px_rgba(15,23,42,0.04)]"></div>
                        </div>
                    </div>
                </section>

                {{-- 4. Access --}}
                <section class="space-y-3">
                    <div class="{{ $sectionTitle }}">4 · {{ $isEn ? 'Access & ranking' : 'Akses & urutan' }}</div>
                    <div class="grid sm:grid-cols-[1fr_auto] gap-5">
                        <fieldset class="space-y-1.5">
                            <legend class="text-xs font-semibold text-slate-700 mb-1.5">{{ $isEn ? 'Visible to' : 'Terlihat oleh' }}</legend>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($roleOptions as $v => $l)
                                    <label class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border border-slate-200 text-xs font-semibold text-slate-600 cursor-pointer transition hover:border-slate-300 has-[:checked]:bg-navy has-[:checked]:border-navy has-[:checked]:text-white has-[:focus-visible]:ring-4 has-[:focus-visible]:ring-navy/15">
                                        <input type="checkbox" name="roles" value="{{ $v }}" class="sr-only">{{ $l }}
                                    </label>
                                @endforeach
                            </div>
                            <p class="text-[11px] text-slate-400">{{ $isEn ? 'None selected = everyone.' : 'Tidak ada yang dipilih = semua role.' }}</p>
                        </fieldset>
                        <div class="flex gap-5">
                            <label class="space-y-1.5">
                                <span class="block text-xs font-semibold text-slate-700">{{ $isEn ? 'Priority' : 'Prioritas' }}</span>
                                <input name="priority" type="number" min="-100" max="100" value="0" class="{{ $field }} w-24 font-mono-num">
                                <span class="block text-[11px] text-slate-400 max-w-[150px]">{{ $isEn ? 'Higher wins when two articles match.' : 'Lebih tinggi menang bila dua artikel cocok.' }}</span>
                            </label>
                            <div class="space-y-1.5">
                                <span class="block text-xs font-semibold text-slate-700">Status</span>
                                <label class="inline-flex items-center gap-2 cursor-pointer py-2.5">
                                    <span class="relative inline-flex">
                                        <input type="checkbox" name="is_active" class="sr-only peer" checked>
                                        <span class="w-10 h-[22px] rounded-full bg-slate-300 peer-checked:bg-teal transition-colors peer-focus-visible:ring-4 peer-focus-visible:ring-navy/15 after:content-[''] after:absolute after:top-[3px] after:left-[3px] after:w-4 after:h-4 after:rounded-full after:bg-white after:shadow-sm after:transition-transform peer-checked:after:translate-x-[18px]"></span>
                                    </span>
                                    <span class="text-xs font-semibold text-slate-600">{{ $isEn ? 'Active' : 'Aktif' }}</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            {{-- Footer --}}
            <div class="flex flex-wrap items-center justify-between gap-3 px-6 py-3.5 border-t border-slate-100 bg-slate-50/70">
                <span data-kb-meta class="text-[11px] text-slate-400"></span>
                <div class="flex items-center gap-2 ml-auto">
                    <span class="hidden sm:inline text-[11px] text-slate-400 mr-1"><kbd class="font-mono-num">Ctrl</kbd> + <kbd class="font-mono-num">Enter</kbd> {{ $isEn ? 'to save' : 'untuk simpan' }}</span>
                    <button type="button" data-kb-cancel class="px-4 py-2 rounded-xl border border-slate-200 bg-white text-xs font-semibold text-slate-600 hover:bg-slate-50 transition cursor-pointer">{{ $isEn ? 'Cancel' : 'Batal' }}</button>
                    <button type="submit" data-kb-save class="px-5 py-2 rounded-xl bg-navy text-white text-xs font-semibold hover:bg-navy/90 transition cursor-pointer disabled:opacity-60 disabled:cursor-wait">{{ $isEn ? 'Save article' : 'Simpan artikel' }}</button>
                </div>
            </div>
        </form>
    </dialog>
</div>
