<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Laporan Engineering berasal dari satu unggahan POMEC; hapus unggahan = laporannya ikut hilang. */
    public function up(): void
    {
        Schema::table('department_reports', function (Blueprint $table) {
            $table->foreignId('pomec_import_id')->nullable()->unique()->after('report_template_id')
                ->constrained('pomec_imports')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('department_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pomec_import_id');
        });
    }
};
