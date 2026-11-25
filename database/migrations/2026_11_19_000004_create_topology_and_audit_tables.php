<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topology_components', function (Blueprint $table) {
            $table->id();
            $table->string('component_key', 50)->unique();
            $table->string('name');
            $table->string('name_en');
            $table->string('zone', 50);
            $table->string('status', 20)->default('healthy'); // healthy, warning, degraded
            $table->string('gm_summary');
            $table->string('gm_summary_en');
            $table->string('it_technical_detail');
            $table->string('host_or_endpoint')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 80);
            $table->string('module', 40);
            $table->text('description');
            $table->string('ip_address', 45)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['module', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('topology_components');
    }
};
