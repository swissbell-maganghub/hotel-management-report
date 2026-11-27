<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Khusus gas: tabung yang datang hari itu dan stok akhir hari. Null untuk utilitas lain.
        Schema::table('pomec_records', function (Blueprint $table) {
            $table->smallInteger('qty_received')->nullable()->after('cost');
            $table->smallInteger('stock_full')->nullable()->after('qty_received');
            $table->smallInteger('stock_empty')->nullable()->after('stock_full');
            $table->smallInteger('stock_installed')->nullable()->after('stock_empty');
        });
    }

    public function down(): void
    {
        Schema::table('pomec_records', function (Blueprint $table) {
            $table->dropColumn(['qty_received', 'stock_full', 'stock_empty', 'stock_installed']);
        });
    }
};
