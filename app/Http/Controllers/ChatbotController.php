<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ChatbotTurn;
use App\Models\User;
use App\Services\ChatbotService;
use App\Services\SystemDiagnosticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ChatbotController extends Controller
{
    public function ask(Request $request, ChatbotService $chatbot): JsonResponse
    {
        // Conversation history is kept server-side (session); any client-sent "history" is ignored.
        $validated = $request->validate([
            'question' => ['required', 'string', 'max:500'],
            'locale' => ['nullable', 'in:id,en'],
            'track' => ['nullable', 'in:guide,data'],
        ]);

        $locale = $validated['locale'] ?? session('locale', 'id');

        return response()->json(
            $chatbot->answer($request->user()->load('department'), $validated['question'], $locale, null, $validated['track'] ?? null)
        );
    }

    public function reset(ChatbotService $chatbot): JsonResponse
    {
        $chatbot->resetConversation();

        return response()->json(['ok' => true]);
    }

    /** 👍/👎 on one answer; users can only rate their own turns. */
    public function feedback(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'turn_id' => ['required', 'integer'],
            'rating' => ['required', 'in:1,-1'],
            'comment' => ['nullable', 'string', 'max:500'],
        ]);

        $turn = ChatbotTurn::where('id', $validated['turn_id'])->where('user_id', $request->user()->id)->firstOrFail();
        $turn->update(['rating' => (int) $validated['rating'], 'comment' => $validated['comment'] ?? null]);

        return response()->json(['ok' => true]);
    }

    /** Admin: run a question as any user (to check routing / RBAC) without logging it as real usage. */
    public function adminTest(Request $request, ChatbotService $chatbot): JsonResponse
    {
        abort_unless($request->user()->canManageUsers(), 403);
        $validated = $request->validate([
            'question' => ['required', 'string', 'max:500'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $asUser = User::with('department')->find($validated['user_id'] ?? $request->user()->id);
        AuditLog::record($request->user(), 'chatbot.admin_test', 'Chatbot', 'Uji pertanyaan chatbot sebagai '.$asUser->name.'.', ['as_user_id' => $asUser->id]);

        return response()->json($chatbot->preview($asUser, $validated['question'], session('locale', 'id')));
    }

    /** Admin: clear the circuit breaker and re-probe the AI model right now. */
    public function adminCheckAi(Request $request, SystemDiagnosticsService $diagnostics): JsonResponse
    {
        abort_unless($request->user()->canManageUsers(), 403);
        $cfg = ChatbotService::resolveProviderConfig();
        $provider = $cfg['provider'];
        Cache::forget('chatbot.llm.failures');
        Cache::forget('chatbot.llm.open_until');
        Cache::forget("chatbot.llm.{$provider}.failures");
        Cache::forget("chatbot.llm.{$provider}.open_until");
        Cache::forget("chatbot.llm.{$provider}.last_error");
        $probe = $diagnostics->probeChatbotAi(session('locale', 'id') === 'en', true);

        // Warm up: an unloaded Ollama model needs ~9 s to load, longer than the intent timeout.
        if ($provider === 'ollama' && $probe['status'] === 'pass') {
            try {
                Http::timeout(60)->withOptions(['proxy' => ''])->post(
                    preg_replace('#/v1/.*$#', '', (string) $cfg['endpoint']).'/api/generate',
                    ['model' => $cfg['model'], 'keep_alive' => '60m']
                );
            } catch (\Throwable) {
                // the probe result already tells IT what is wrong
            }
        }

        return response()->json($probe + ['checked_at' => now()->format('H:i:s')]);
    }

    /** Admin: kill switch for every user (maintenance / bad answers). */
    public function adminToggle(Request $request): JsonResponse
    {
        abort_unless($request->user()->canManageUsers(), 403);
        $enabled = $request->validate(['enabled' => ['required', 'boolean']])['enabled'];

        ChatbotService::setEnabled((bool) $enabled, $request->user());
        AuditLog::record($request->user(), $enabled ? 'chatbot.enabled' : 'chatbot.disabled', 'Chatbot', $enabled ? 'Chatbot diaktifkan kembali.' : 'Chatbot dinonaktifkan untuk semua user.');

        $isEn = session('locale', 'id') === 'en';

        return response()->json(['message' => $enabled
            ? ($isEn ? 'Chatbot is on for all users.' : 'Chatbot aktif untuk semua user.')
            : ($isEn ? 'Chatbot is off for all users.' : 'Chatbot dinonaktifkan untuk semua user.')]);
    }
}
