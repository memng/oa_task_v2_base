<?php

use think\facade\Db;

if (!function_exists('user_belongs_to_admin_dept')) {
    function user_belongs_to_admin_dept(?array $user): bool
    {
        if (empty($user)) {
            return false;
        }
        if (!empty($user['is_admin'])) {
            return true;
        }
        $deptId = isset($user['dept_id']) ? (int)$user['dept_id'] : 0;
        if ($deptId <= 0) {
            return false;
        }
        static $deptTypeCache = [];
        if (!array_key_exists($deptId, $deptTypeCache)) {
            $deptTypeCache[$deptId] = Db::table('departments')
                ->where('id', $deptId)
                ->value('type');
        }
        return in_array($deptTypeCache[$deptId] ?? null, ['operation', 'finance'], true);
    }
}
