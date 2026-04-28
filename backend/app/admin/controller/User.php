<?php

namespace app\admin\controller;

use app\common\controller\AdminApiController;
use think\facade\Db;
use think\facade\Request;

class User extends AdminApiController
{
    public function index()
    {
        $status = Request::param('status', 'pending');
        $query = Db::table('users')->order('created_at', 'desc');
        if (!empty($status)) {
            $query->where('status', $status);
        }
        $users = $query->select()->toArray();
        $deptIds = array_unique(array_filter(array_column($users, 'dept_id')));
        $deptMap = [];
        if (!empty($deptIds)) {
            $deptMap = Db::table('departments')
                ->whereIn('id', $deptIds)
                ->column('name', 'id');
        }

        $items = array_map(function ($user) use ($deptMap) {
            return $this->formatUser($user, $deptMap);
        }, $users);

        return $this->success([
            'items' => $items,
        ]);
    }

    public function approve(int $id)
    {
        $user = Db::table('users')->find($id);
        if (!$user) {
            $this->errorResponse('用户不存在');
        }
        if ($user['status'] === 'active') {
            return $this->success([], '用户已处于启用状态');
        }
        Db::table('users')
            ->where('id', $id)
            ->update([
                'status'     => 'active',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        return $this->success([], '已通过审核');
    }

    public function reject(int $id)
    {
        $payload = $this->requestData();
        $rejectReason = trim((string)($payload['reject_reason'] ?? ''));

        $user = Db::table('users')->find($id);
        if (!$user) {
            $this->errorResponse('用户不存在');
        }
        if (empty($rejectReason)) {
            $this->errorResponse('请填写拒绝原因');
        }
        Db::table('users')
            ->where('id', $id)
            ->update([
                'status'        => 'disabled',
                'reject_reason' => $rejectReason,
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);
        return $this->success([], '已拒绝该注册');
    }

    protected function formatUser(array $user, array $deptMap): array
    {
        return [
            'id'         => (int)$user['id'],
            'name'       => $user['name'],
            'mobile'     => $user['mobile'],
            'dept_name'  => $user['dept_id'] && isset($deptMap[$user['dept_id']]) ? $deptMap[$user['dept_id']] : null,
            'status'     => $user['status'],
            'created_at' => $user['created_at'],
        ];
    }
}
