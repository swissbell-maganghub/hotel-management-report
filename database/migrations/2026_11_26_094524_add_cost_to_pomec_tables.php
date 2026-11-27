<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rupiah sebelum PPJ/PPN. Null untuk utilitas tanpa biaya di file sumber (gas, solar).
        Schema::table('pomec_records', function (Blueprint $table) {
            $table->decimal('cost', 15, 2)->nullable()->after('total_usage');
        });

        Schema::table('pomec_imports', function (Blueprint $table) {
            $table->decimal('total_cost', 15, 2)->nullable()->after('total_usage');
        });
    }

    public function down(): void
    {
        Schema::table('pomec_records', function (Blueprint $table) {
            $table->dropColumn('cost');
        });

        Schema::table('pomec_imports', function (Blueprint $table) {
            $table->dropColumn('total_cost');
        });
    }
};
