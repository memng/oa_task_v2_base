<?php

namespace app\api\controller;

use app\common\controller\ApiController;
use think\facade\Db;
use think\facade\Request;

class Lookup extends ApiController
{
    public function enums()
    {
        return $this->success([
            'task_types' => [
                'procurement', 'nameplate', 'machine_data', 'acceptance',
                'packaging', 'shipment', 'inspection', 'temporary', 'document',
            ],
            'task_status' => ['pending', 'in_progress', 'waiting_audit', 'rejected', 'completed', 'cancelled'],
            'order_status'=> ['draft', 'in_progress', 'completed', 'cancelled'],
            'cost_scope'  => ['domestic', 'international', 'finance'],
            'cost_categories' => [
                'domestic_freight', 'trailer', 'wood_case', 'warehouse',
                'domestic_other', 'sea_freight', 'express', 'certificate',
                'international_other', 'receipt_fee', 'usd_fee',
            ],
            'doc_types' => ['pi','commercial_invoice','customs_declaration','bill_of_lading','freight_invoice','payment_receipt'],
        ]);
    }

    public function staff()
    {
        $group = Request::get('group');
        $deptId = (int)Request::get('dept_id', 0);
        $keyword = trim((string)Request::get('keyword', ''));

        $query = Db::table('users')
            ->alias('u')
            ->leftJoin('departments d', 'd.id = u.dept_id')
            ->where('u.status', 'active');

        if ($deptId > 0) {
            $query->where('u.dept_id', $deptId);
        } elseif ($group === 'procurement') {
            $deptIds = Db::table('departments')
                ->where('status', 1)
                ->where(function ($q) {
                    $q->whereLike('name', '%采购%')
                        ->whereOr('code', 'procurement');
                })
                ->column('id');
            if ($deptIds) {
                $query->whereIn('u.dept_id', $deptIds);
            }
        }

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->whereLike('u.name', "%{$keyword}%")
                    ->whereOr('u.mobile', 'like', "%{$keyword}%");
            });
        }

        $users = $query->field([
            'u.id',
            'u.name',
            'u.mobile',
            'u.dept_id',
            'd.name as dept_name',
        ])
            ->order('u.name', 'asc')
            ->select()
            ->toArray();

        $items = array_map(static function ($user) {
            return [
                'id'        => (int)$user['id'],
                'name'      => $user['name'],
                'mobile'    => $user['mobile'],
                'dept_id'   => $user['dept_id'] ? (int)$user['dept_id'] : null,
                'dept_name' => $user['dept_name'] ?? null,
            ];
        }, $users);

        return $this->success([
            'items' => $items,
        ]);
    }
}
