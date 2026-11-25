<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\AuditLog;
use App\Models\ChatbotKnowledge;
use App\Models\DepartmentReport;
use App\Models\PomecImport;
use App\Models\ReportTemplate;
use App\Models\User;
use App\Services\DatabaseBackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminController extends Controller
{
    public function storeUser(StoreUserRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $primaryDeptId = in_array($validated['role'], [User::ROLE_GM, User::ROLE_IT_ADMIN], true) ? null : ($validated['department_id'] ?? null);
        $isReadOnly = in_array($validated['role'], [User::ROLE_HOD, User::ROLE_STAFF], true)
            ? (bool) ($validated['is_read_only'] ?? false)
            : false;

        $newUser = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'department_id' => $primaryDeptId,
            'is_active' => true,
            'is_read_only' => $isReadOnly,
            'locale' => 'id',
        ]);

        $this->syncExtraPermissions($newUser, $validated['department_permissions'] ?? [], $primaryDeptId, $validated['role']);
        $newUser->load(['department', 'extraPermissions.department']);
        $newUser->setAttribute('active_sessions_count', 0);

        AuditLog::record($request->user(), 'user.created', 'RBAC', "Akun {$newUser->email} ({$newUser->role}) dibuat oleh IT Admin.");

        return response()->json([
            'message' => session('locale', 'id') === 'en'
                ? "User {$newUser->name} created."
                : "Pengguna {$newUser->name} berhasil ditambahkan.",
            'user' => $newUser,
        ], 201);
    }

    public function updateUser(UpdateUserRequest $request, User $managedUser): JsonResponse
    {
        $validated = $request->validated();

        $primaryDeptId = in_array($validated['role'], [User::ROLE_GM, User::ROLE_IT_ADMIN], true) ? null : ($validated['department_id'] ?? null);
        $isReadOnly = in_array($validated['role'], [User::ROLE_HOD, User::ROLE_STAFF], true)
            ? (bool) ($validated['is_read_only'] ?? false)
            : false;

        $managedUser->update([
            'name' => $validated['name'] ?? $managedUser->name,
            'email' => $validated['email'] ?? $managedUser->email,
            'role' => $validated['role'],
            'department_id' => $primaryDeptId,
            'is_active' => $validated['is_active'],
            'is_read_only' => $isReadOnly,
        ]);

        if (array_key_exists('department_permissions', $validated) || in_array($validated['role'], [User::ROLE_GM, User::ROLE_IT_ADMIN], true)) {
            $this->syncExtraPermissions($managedUser, $validated['department_permissions'] ?? [], $primaryDeptId, $validated['role']);
        }

        if (! $managedUser->is_active) {
            $this->clearUserSessions($managedUser);
        }

        $extraCount = $managedUser->extraPermissions()->count();
        AuditLog::record($request->user(), 'user.updated', 'RBAC', "Akun {$managedUser->email} diperbarui (role: {$managedUser->role}, read_only: ".($isReadOnly ? 'ya' : 'tidak').", akses_lintas_dept: {$extraCount}, aktif: ".($managedUser->is_active ? 'ya' : 'tidak').').');

        return response()->json([
            'message' => session('locale', 'id') === 'en'
                ? "User {$managedUser->name} updated."
                : "Akun {$managedUser->name} berhasil diperbarui.",
            'user' => $this->withSessionCount($managedUser->fresh(['department', 'extraPermissions.department'])),
        ]);
    }

    public function toggleUserStatus(Request $request, User $managedUser): JsonResponse
    {
        abort_unless($request->user()->canManageUsers(), 403);

        if ($managedUser->id === $request->user()->id) {
            return response()->json(['message' => 'Anda tidak dapat menonaktifkan akun Anda sendiri.'], 422);
        }

        if ($managedUser->isItAdmin() && $managedUser->is_active) {
            $otherAdmins = User::where('role', User::ROLE_IT_ADMIN)
                ->where('is_active', true)
                ->where('id', '!=', $managedUser->id)
                ->count();
            if ($otherAdmins === 0) {
                return response()->json(['message' => 'Minimal harus ada 1 IT Administrator yang aktif di sistem.'], 422);
            }
        }

        $managedUser->update(['is_active' => ! $managedUser->is_active]);

        if (! $managedUser->is_active) {
            $this->clearUserSessions($managedUser);
        }

        AuditLog::record($request->user(), 'user.status_toggled', 'RBAC', "Status akun {$managedUser->email} diubah menjadi ".($managedUser->is_active ? 'Aktif' : 'Nonaktif').'.');

        return response()->json([
            'message' => session('locale', 'id') === 'en'
                ? "Account {$managedUser->name} is now ".($managedUser->is_active ? 'Active' : 'Inactive').'.'
                : "Akun {$managedUser->name} sekarang ".($managedUser->is_active ? 'Aktif' : 'Nonaktif').'.',
            'user' => $this->withSessionCount($managedUser->fresh(['department', 'extraPermissions.department'])),
        ]);
    }

    public function resetUserPassword(Request $request, User $managedUser): JsonResponse
    {
        abort_unless($request->user()->canManageUsers(), 403);

        $validated = $request->validate([
            'password' => ['required', 'string', Password::min(8)->mixedCase()->numbers()],
        ]);

        $managedUser->update([
            'password' => Hash::make($validated['password']),
        ]);

        AuditLog::record($request->user(), 'user.password_reset', 'RBAC', "Password akun {$managedUser->email} direset oleh IT Admin.");

        return response()->json([
            'message' => session('locale', 'id') === 'en'
                ? "Password reset for {$managedUser->name}."
                : "Password untuk {$managedUser->name} berhasil direset.",
        ]);
    }

    public function revokeSessions(Request $request, User $managedUser): JsonResponse
    {
        abort_unless($request->user()->canManageUsers(), 403);

        if ($managedUser->id === $request->user()->id) {
            return response()->json(['message' => 'Gunakan menu Keluar Sistem untuk mengakhiri sesi Anda sendiri.'], 422);
        }

        $removed = $this->clearUserSessions($managedUser);

        AuditLog::record($request->user(), 'user.sessions_revoked', 'RBAC', "IT Admin mengakhiri {$removed} sesi aktif milik {$managedUser->email}.");

        return response()->json([
            'message' => session('locale', 'id') === 'en'
                ? "Revoked {$removed} active session(s) for {$managedUser->name}."
                : "{$removed} sesi aktif milik {$managedUser->name} berhasil dihentikan.",
            'user' => $this->withSessionCount($managedUser->fresh(['department', 'extraPermissions.department'])),
        ]);
    }

    public function destroyUser(Request $request, User $managedUser): JsonResponse
    {
        abort_unless($request->user()->canManageUsers(), 403);

        if ($managedUser->id === $request->user()->id) {
            return response()->json(['message' => 'Anda tidak dapat menghapus akun Anda sendiri.'], 422);
        }

        if ($managedUser->isItAdmin()) {
            $otherAdmins = User::where('role', User::ROLE_IT_ADMIN)
                ->where('is_active', true)
                ->where('id', '!=', $managedUser->id)
                ->count();
            if ($otherAdmins === 0) {
                return response()->json(['message' => 'Minimal harus ada 1 IT Administrator di sistem.'], 422);
            }
        }

        $hasRecords = DepartmentReport::where('created_by', $managedUser->id)->exists()
            || DepartmentReport::where('reviewed_by', $managedUser->id)->exists()
            || PomecImport::where('uploaded_by', $managedUser->id)->exists();

        if ($hasRecords) {
            return response()->json([
                'message' => session('locale', 'id') === 'en'
                    ? 'Cannot delete user with existing reports or POMEC imports. Deactivate the account instead to preserve audit history.'
                    : 'Pengguna yang sudah memiliki laporan atau impor POMEC tidak dapat dihapus. Nonaktifkan akun untuk menjaga integritas audit.',
            ], 422);
        }

        $email = $managedUser->email;
        $this->clearUserSessions($managedUser);
        $managedUser->delete();

        AuditLog::record($request->user(), 'user.deleted', 'RBAC', "Akun {$email} dihapus oleh IT Admin.");

        return response()->json([
            'message' => session('locale', 'id') === 'en'
                ? "User {$email} deleted."
                : "Akun {$email} berhasil dihapus.",
        ]);
    }

    public function toggleTemplate(Request $request, ReportTemplate $template): JsonResponse
    {
        abort_unless($request->user()->canManageUsers(), 403);

        $template->update(['is_active' => ! $template->is_active]);

        AuditLog::record($request->user(), 'template.toggled', 'Templates', "Status template {$template->code} diubah menjadi ".($template->is_active ? 'Aktif' : 'Nonaktif').'.');

        return response()->json([
            'message' => "Template {$template->code}: ".($template->is_active ? 'Active' : 'Inactive'),
            'template' => $template->fresh('department'),
        ]);
    }

    public function storeKnowledge(Request $request): JsonResponse
    {
        abort_unless($request->user()->canManageUsers(), 403);
        $data = $this->validateKnowledge($request);
        $data['slug'] = Str::limit(Str::slug($data['type'].'-'.$data['title']), 60, '').'-'.Str::lower(Str::random(5));
        $k = ChatbotKnowledge::create($data);

        AuditLog::record($request->user(), 'chatbot.knowledge_created', 'Chatbot', "Artikel chatbot '{$k->title}' ditambahkan.");

        return response()->json(['message' => session('locale', 'id') === 'en' ? 'Article saved.' : 'Artikel tersimpan.', 'knowledge' => $k], 201);
    }

    public function updateKnowledge(Request $request, ChatbotKnowledge $knowledge): JsonResponse
    {
        abort_unless($request->user()->canManageUsers(), 403);
        $knowledge->update($this->validateKnowledge($request));

        AuditLog::record($request->user(), 'chatbot.knowledge_updated', 'Chatbot', "Artikel chatbot '{$knowledge->title}' diperbarui.");

        return response()->json(['message' => session('locale', 'id') === 'en' ? 'Article updated.' : 'Artikel diperbarui.', 'knowledge' => $knowledge]);
    }

    public function destroyKnowledge(Request $request, ChatbotKnowledge $knowledge): JsonResponse
    {
        abort_unless($request->user()->canManageUsers(), 403);
        $title = $knowledge->title;
        $knowledge->delete();

        AuditLog::record($request->user(), 'chatbot.knowledge_deleted', 'Chatbot', "Artikel chatbot '{$title}' dihapus.");

        return response()->json(['message' => session('locale', 'id') === 'en' ? 'Article deleted.' : 'Artikel dihapus.']);
    }

    /** Editor "test trigger": would this (unsaved) article answer the sample question, or does another article win? */
    public function matchKnowledge(Request $request): JsonResponse
    {
        abort_unless($request->user()->canManageUsers(), 403);
        $v = $request->validate([
            'triggers' => ['required', 'string', 'max:4000'],
            'question' => ['required', 'string', 'max:500'],
            'priority' => ['nullable', 'integer', 'between:-100,100'],
            'id' => ['nullable', 'integer'],
        ]);

        $q = ChatbotKnowledge::normalize($v['question']);
        $score = (new ChatbotKnowledge(['triggers' => $v['triggers']]))->score($q);
        $priority = (int) ($v['priority'] ?? 0);
        // Same ranking as ChatbotService::matchKnowledge (score, then priority); role visibility is ignored here.
        $rival = ChatbotKnowledge::active()
            ->reject(fn (ChatbotKnowledge $k): bool => $k->id === (int) ($v['id'] ?? 0))
            ->map(fn (ChatbotKnowledge $k): array => [$k, $k->score($q)])
            ->filter(fn (array $p): bool => $p[1] > 0)
            ->sortByDesc(fn (array $p): array => [$p[1], $p[0]->priority])
            ->first();

        return response()->json([
            'matched' => $score > 0,
            'wins' => $score > 0 && (! $rival || [$score, $priority] >= [$rival[1], $rival[0]->priority]),
            'rival' => $rival[0]->title ?? null,
        ]);
    }

    private function validateKnowledge(Request $request): array
    {
        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', ChatbotKnowledge::TYPES)],
            'title' => ['required', 'string', 'max:150'],
            'triggers' => ['required', 'string', 'max:4000'],
            'body_id' => ['required', 'string', 'max:5000'],
            'body_en' => ['nullable', 'string', 'max:5000'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['in:'.implode(',', [User::ROLE_GM, User::ROLE_HOD, User::ROLE_STAFF, User::ROLE_IT_ADMIN])],
            'priority' => ['nullable', 'integer', 'between:-100,100'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['roles'] = empty($data['roles']) ? null : array_values($data['roles']);
        $data['priority'] = (int) ($data['priority'] ?? 0);
        $data['is_active'] = (bool) ($data['is_active'] ?? true);

        return $data;
    }

    public function createBackup(Request $request, DatabaseBackupService $backupService): JsonResponse
    {
        abort_unless($request->user()->canManageUsers(), 403);

        $backup = $backupService->createBackup($request->user());

        return response()->json([
            'message' => session('locale', 'id') === 'en'
                ? "Backup {$backup['filename']} created ({$backup['size_human']})."
                : "Backup {$backup['filename']} berhasil dibuat ({$backup['size_human']}).",
            'backup' => $backup,
        ], 201);
    }

    public function downloadBackup(Request $request, string $filename, DatabaseBackupService $backupService): StreamedResponse
    {
        abort_unless($request->user()->canManageUsers(), 403);

        $relative = $backupService->resolveBackupStoragePath($filename);
        abort_unless($relative !== null, 404);

        AuditLog::record($request->user(), 'backup.downloaded', 'Database', "File backup {$filename} diunduh oleh IT Admin.");

        return Storage::disk('local')->download($relative, $filename);
    }

    public function deleteBackup(Request $request, string $filename, DatabaseBackupService $backupService): JsonResponse
    {
        abort_unless($request->user()->canManageUsers(), 403);

        $deleted = $backupService->deleteBackup($filename, $request->user());
        abort_unless($deleted, 404);

        return response()->json([
            'message' => session('locale', 'id') === 'en'
                ? "Backup {$filename} deleted."
                : "File backup {$filename} berhasil dihapus.",
        ]);
    }

    private function clearUserSessions(User $user): int
    {
        try {
            return DB::table('sessions')->where('user_id', $user->id)->delete();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function syncExtraPermissions(User $user, array $items, ?int $primaryDeptId, string $role): void
    {
        $user->extraPermissions()->delete();

        if (! in_array($role, [User::ROLE_HOD, User::ROLE_STAFF], true)) {
            return;
        }

        $uniqueByDept = [];
        foreach ($items as $item) {
            $deptId = (int) ($item['department_id'] ?? 0);
            $level = (string) ($item['access_level'] ?? '');
            if ($deptId <= 0 || $deptId === (int) $primaryDeptId) {
                continue;
            }
            if (! in_array($level, ['read', 'input', 'review'], true)) {
                continue;
            }
            $uniqueByDept[$deptId] = $level;
        }

        foreach ($uniqueByDept as $deptId => $level) {
            $user->extraPermissions()->create([
                'department_id' => $deptId,
                'access_level' => $level,
            ]);
        }
    }

    private function withSessionCount(User $user): User
    {
        try {
            $count = DB::table('sessions')->where('user_id', $user->id)->count();
        } catch (\Throwable) {
            $count = 0;
        }
        $user->setAttribute('active_sessions_count', $count);

        return $user;
    }
}
