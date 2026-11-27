<?php

// [WASTAGE-REPORT] Migration for Wastage Reports and Wastage Daily Records
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wastage_reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_code', 50)->unique(); // misal: WST-202610-001
            $table->string('period_month', 7); // format: YYYY-MM (e.g. 2026-10)
            $table->decimal('total_wastage_kg', 12, 2)->default(0);
            $table->unsignedInteger('total_cover')->default(0);
            $table->decimal('kg_per_cover', 8, 4)->default(0);
            $table->decimal('ytd_kg', 14, 2)->default(0);
            $table->unsignedInteger('ytd_cover')->default(0);
            $table->string('original_filename')->nullable(); // nama file sumber impor
            $table->string('file_hash_sha256', 64)->nullable(); // hash integritas file
            $table->string('source_type', 20)->default('manual'); // 'excel_import' atau 'manual'
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete(); // Diimpor / Dibuat oleh
            $table->timestamp('imported_at')->nullable(); // Tanggal impor
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('period_month');
        });

        Schema::create('wastage_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wastage_report_id')->constrained('wastage_reports')->cascadeOnDelete();
            $table->date('record_date');

            // Buffet Lunch, Dinner, Gravity (KG)
            $table->decimal('buffet_lunch', 10, 2)->default(0);
            $table->decimal('buffet_dinner', 10, 2)->default(0);
            $table->decimal('buffet_gravity', 10, 2)->default(0);

            // Preparation (KG)
            $table->decimal('prep_butcher', 10, 2)->default(0);
            $table->decimal('prep_main_kitchen', 10, 2)->default(0);
            $table->decimal('prep_pastry', 10, 2)->default(0);
            $table->decimal('prep_cold_kitchen', 10, 2)->default(0);

            // Plate Waste & Breakfast Swiss Café (KG)
            $table->decimal('plate_waste', 10, 2)->default(0);
            $table->decimal('breakfast_cafe', 10, 2)->default(0);

            // Total KG/Day & Cover
            $table->decimal('total_kg', 10, 2)->default(0);
            $table->unsignedInteger('covers')->default(0);

            // Cumulative YTD & Ratio
            $table->decimal('ytd_kg', 12, 2)->default(0);
            $table->unsignedInteger('ytd_cover')->default(0);
            $table->decimal('kg_per_cover', 8, 4)->default(0);

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['wastage_report_id', 'record_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wastage_records');
        Schema::dropIfExists('wastage_reports');
    }
};
