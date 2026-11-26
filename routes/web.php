<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\DepartmentReportController;
use App\Http\Controllers\PomecController;
use App\Http\Controllers\TopologyController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::get('/locale/{locale}', [AuthController::class, 'switchLocale'])->name('locale.switch');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login.attempt');
    Route::post('/login-as', [AuthController::class, 'loginAs'])->name('login.as');
});

Route::middleware(['auth', 'active'])->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('/profile/password', [AuthController::class, 'changePassword'])->name('profile.password');

    Route::get('/', [WorkspaceController::class, 'index'])->name('workspace.index');

    // Departmental Reports (Dynamic Per-Department Table Templates + Workflow)
    Route::post('/reports', [DepartmentReportController::class, 'store'])->name('reports.store');
    Route::post('/reports/{report}', [DepartmentReportController::class, 'update'])->name('reports.update');
    Route::post('/reports/{report}/review', [DepartmentReportController::class, 'review'])->name('reports.review');
    Route::delete('/reports/{report}', [DepartmentReportController::class, 'destroy'])->name('reports.destroy');
    Route::get('/reports/{report}/export/excel', [DepartmentReportController::class, 'exportExcel'])->name('reports.export.excel');
    Route::get('/reports/{report}/export/pdf', [DepartmentReportController::class, 'exportPdf'])->name('reports.export.pdf');
    Route::get('/reports/{report}/attachment', [DepartmentReportController::class, 'downloadAttachment'])->name('reports.attachment');

    // POMEC Utility Management (Excel Import + SHA-256 Preview + Commit + Export)
    Route::post('/pomec/preview', [PomecController::class, 'preview'])->name('pomec.preview');
    Route::post('/pomec/commit', [PomecController::class, 'commit'])->name('pomec.commit');
    Route::delete('/pomec/imports/{import}', [PomecController::class, 'destroy'])->name('pomec.imports.destroy');
    Route::get('/pomec/export/{utilityType}/excel', [PomecController::class, 'exportExcel'])->name('pomec.export.excel');
    Route::get('/pomec/export/{utilityType}/pdf', [PomecController::class, 'exportPdf'])->name('pomec.export.pdf');

    // System Topology (GM Read-Only & IT Admin Edit/Healthcheck)
    Route::post('/topology/healthcheck', [TopologyController::class, 'runHealthCheck'])->name('topology.healthcheck');
    Route::put('/topology/{component}', [TopologyController::class, 'update'])->name('topology.update');

    // IT Admin User, RBAC & Template Management
    Route::post('/admin/users', [AdminController::class, 'storeUser'])->name('admin.users.store');
    Route::put('/admin/users/{managedUser}', [AdminController::class, 'updateUser'])->name('admin.users.update');
    Route::post('/admin/users/{managedUser}/toggle-status', [AdminController::class, 'toggleUserStatus'])->name('admin.users.toggle_status');
    Route::post('/admin/users/{managedUser}/reset-password', [AdminController::class, 'resetUserPassword'])->name('admin.users.reset_password');
    Route::post('/admin/users/{managedUser}/revoke-sessions', [AdminController::class, 'revokeSessions'])->name('admin.users.revoke_sessions');
    Route::delete('/admin/users/{managedUser}', [AdminController::class, 'destroyUser'])->name('admin.users.destroy');
    Route::post('/admin/templates/{template}/toggle', [AdminController::class, 'toggleTemplate'])->name('admin.templates.toggle');
    Route::post('/admin/backups', [AdminController::class, 'createBackup'])->name('admin.backups.store');
    Route::get('/admin/backups/{filename}/download', [AdminController::class, 'downloadBackup'])->name('admin.backups.download');
    Route::delete('/admin/backups/{filename}', [AdminController::class, 'deleteBackup'])->name('admin.backups.destroy');

    // Chatbot knowledge base (IT Admin; spec 17)
    Route::post('/admin/chatbot-knowledge', [AdminController::class, 'storeKnowledge'])->name('admin.chatbot_knowledge.store');
    Route::post('/admin/chatbot-knowledge/match', [AdminController::class, 'matchKnowledge'])->name('admin.chatbot_knowledge.match');
    Route::put('/admin/chatbot-knowledge/{knowledge}', [AdminController::class, 'updateKnowledge'])->name('admin.chatbot_knowledge.update');
    Route::delete('/admin/chatbot-knowledge/{knowledge}', [AdminController::class, 'destroyKnowledge'])->name('admin.chatbot_knowledge.destroy');
    Route::post('/admin/chatbot/test', [ChatbotController::class, 'adminTest'])->middleware('throttle:chatbot')->name('admin.chatbot.test');
    Route::post('/admin/chatbot/check-ai', [ChatbotController::class, 'adminCheckAi'])->name('admin.chatbot.check_ai');
    Route::post('/admin/chatbot/toggle', [ChatbotController::class, 'adminToggle'])->name('admin.chatbot.toggle');

    // AI Chatbot Assistant (Read-Only, RBAC-Scoped, Rate-Limited)
    Route::post('/chatbot/ask', [ChatbotController::class, 'ask'])->middleware('throttle:chatbot')->name('chatbot.ask');
    Route::post('/chatbot/reset', [ChatbotController::class, 'reset'])->name('chatbot.reset');
    Route::post('/chatbot/feedback', [ChatbotController::class, 'feedback'])->middleware('throttle:chatbot')->name('chatbot.feedback');
});
