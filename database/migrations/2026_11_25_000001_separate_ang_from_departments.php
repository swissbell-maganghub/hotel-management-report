<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Clear department_id on all Executive accounts (GM & IT Admin) since Executive is not a department.
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'department_id')) {
            DB::table('users')
                ->whereIn('role', ['gm', 'it_admin'])
                ->update(['department_id' => null]);
        }

        if (! Schema::hasTable('departments')) {
            return;
        }

        $angId = DB::table('departments')->where('code', 'ANG')->value('id');
        if (! $angId) {
            return;
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'department_id')) {
            DB::table('users')
                ->where('department_id', $angId)
                ->update(['department_id' => null]);
        }

        if (Schema::hasTable('user_department_permissions')) {
            DB::table('user_department_permissions')
                ->where('department_id', $angId)
                ->delete();
        }

        $angTemplateIds = Schema::hasTable('report_templates')
            ? DB::table('report_templates')->where('department_id', $angId)->pluck('id')
            : collect();

        if (Schema::hasTable('department_reports')) {
            $reportIdsQuery = DB::table('department_reports')->where('department_id', $angId);
            if ($angTemplateIds->isNotEmpty()) {
                $reportIdsQuery->orWhereIn('report_template_id', $angTemplateIds);
            }
            $angReportIds = $reportIdsQuery->pluck('id');

            if ($angReportIds->isNotEmpty()) {
                if (Schema::hasTable('report_histories')) {
                    DB::table('report_histories')->whereIn('department_report_id', $angReportIds)->delete();
                }
                if (Schema::hasTable('report_attachments')) {
                    DB::table('report_attachments')->whereIn('department_report_id', $angReportIds)->delete();
                }
                DB::table('department_reports')->whereIn('id', $angReportIds)->delete();
            }
        }

        if (Schema::hasTable('report_templates')) {
            DB::table('report_templates')->where('department_id', $angId)->delete();
        }

        DB::table('departments')->where('id', $angId)->delete();
    }

    public function down(): void
    {
        if (! Schema::hasTable('departments')) {
            return;
        }

        $now = now();
        $angId = DB::table('departments')->where('code', 'ANG')->value('id');

        if (! $angId) {
            $angId = DB::table('departments')->insertGetId([
                'code' => 'ANG',
                'name' => 'Admin & General',
                'name_en' => 'Admin & General',
                'description' => 'Administrasi umum, koordinasi manajemen, IT, dan keamanan hotel.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if ($angId && Schema::hasTable('users') && Schema::hasColumn('users', 'department_id')) {
            DB::table('users')
                ->where('role', 'it_admin')
                ->whereNull('department_id')
                ->update(['department_id' => $angId]);
        }
    }
};
