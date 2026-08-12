<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $exists = DB::table('permissions')->where('slug', 'reports.export')->exists();
        if (! $exists) {
            $id = DB::table('permissions')->insertGetId([
                'name' => 'Reports Export',
                'slug' => 'reports.export',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $id = DB::table('permissions')->where('slug', 'reports.export')->value('id');
        }

        $roleIds = DB::table('roles')->whereIn('slug', ['admin', 'super_admin', 'accountant'])->pluck('id');
        foreach ($roleIds as $roleId) {
            $pivotExists = DB::table('role_permission')
                ->where('role_id', $roleId)
                ->where('permission_id', $id)
                ->exists();
            if (! $pivotExists) {
                DB::table('role_permission')->insert([
                    'role_id' => $roleId,
                    'permission_id' => $id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        $id = DB::table('permissions')->where('slug', 'reports.export')->value('id');
        if (! $id) {
            return;
        }
        DB::table('role_permission')->where('permission_id', $id)->delete();
        DB::table('permissions')->where('id', $id)->delete();
    }
};
