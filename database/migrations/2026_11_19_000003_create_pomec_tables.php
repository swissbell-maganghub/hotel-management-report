<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pomec_imports', function (Blueprint $table) {
            $table->id();
            $table->string('batch_code', 50)->unique();
            $table->string('utility_type', 20); // electricity, water
            $table->string('period_month', 7); // YYYY-MM
            $table->string('original_filename');
            $table->string('file_hash_sha256', 64)->unique();
            $table->string('stored_path')->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->decimal('total_usage', 14, 2)->default(0);
            $table->string('status', 20)->default('committed');
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('committed_at')->nullable();
            $table->timestamps();

            $table->index(['utility_type', 'period_month']);
        });

        Schema::create('pomec_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pomec_import_id')->constrained('pomec_imports')->cascadeOnDelete();
            $table->string('utility_type', 20); // electricity, water
            $table->date('reading_date');
            $table->string('meter_name');
            $table->decimal('lwbp_value', 12, 2)->nullable();
            $table->decimal('wbp_value', 12, 2)->nullable();
            $table->decimal('water_m3', 12, 2)->nullable();
            $table->decimal('total_usage', 12, 2)->default(0);
            $table->string('transaction_hash', 64)->unique();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['utility_type', 'reading_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pomec_records');
        Schema::dropIfExists('pomec_imports');
    }
};
