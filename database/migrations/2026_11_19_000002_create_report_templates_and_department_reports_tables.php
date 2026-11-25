<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->string('name_en');
            $table->string('description')->nullable();
            $table->string('description_en')->nullable();
            $table->json('columns_schema');
            $table->json('default_rows')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('department_reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_code', 50)->unique();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->foreignId('report_template_id')->constrained('report_templates')->cascadeOnDelete();
            $table->string('title');
            $table->string('period_type', 20)->default('daily'); // daily, weekly, monthly
            $table->date('period_date');
            $table->string('status', 30)->default('draft'); // draft, submitted, returned, reviewed
            $table->json('rows_data');
            $table->text('summary_notes')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->index(['department_id', 'status', 'period_date']);
        });

        Schema::create('report_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_report_id')->constrained('department_reports')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->string('action', 50);
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_histories');
        Schema::dropIfExists('department_reports');
        Schema::dropIfExists('report_templates');
    }
};
