<?php

namespace app\admin\controller;

use app\common\controller\AdminApiController;
use think\facade\Db;
use think\facade\Request;

class ExpenseBudget extends AdminApiController
{
    public function index()
    {
        $period = trim((string)Request::get('period', ''));
        $deptId = Request::get('dept_id');

        $query = Db::table('expense_budgets')
            ->alias('b')
            ->leftJoin('departments d', 'd.id = b.dept_id')
            ->field('b.*, d.name as dept_name')
            ->order('b.period', 'desc')
            ->order('b.dept_id')
            ->order('b.type');

        if ($period !== '') {
            $query->where('b.period', $period);
        }
        if ($deptId !== null && $deptId !== '') {
            $query->where('b.dept_id', (int)$deptId);
        }

        $items = $query->select()->toArray();

        $typeMap = [
            'travel' => '差旅费',
            'meal' => '餐费',
            'transport' => '交通费',
            'office' => '办公用品',
            'purchase' => '采购费用',
            'other' => '其他',
            '' => '所有类型',
        ];

        $result = array_map(function ($item) use ($typeMap) {
            $deptId = (int)$item['dept_id'];
            $usedAmount = $this->calcUsedAmount($deptId, $item['type'], $item['period']);
            $budgetAmount = (float)$item['budget_amount'];
            return [
                'id'             => (int)$item['id'],
                'dept_id'        => $deptId ?: null,
                'dept_name'      => $item['dept_name'] ?? '全局',
                'type'           => $item['type'],
                'type_label'     => $typeMap[$item['type']] ?? $item['type'],
                'period'         => $item['period'],
                'budget_amount'  => $budgetAmount,
                'used_amount'    => $usedAmount,
                'remain_amount'  => round(max(0, $budgetAmount - $usedAmount), 2),
                'usage_percent'  => $budgetAmount > 0 ? round($usedAmount / $budgetAmount * 100, 1) : 0,
                'created_at'     => $item['created_at'],
                'updated_at'     => $item['updated_at'],
            ];
        }, $items);

        return $this->success(['items' => $result]);
    }

    public function save()
    {
        $payload = $this->requestData();

        $deptId = isset($payload['dept_id']) && $payload['dept_id'] !== '' ? (int)$payload['dept_id'] : null;
        $type = trim((string)($payload['type'] ?? ''));
        $period = trim((string)($payload['period'] ?? ''));
        $budgetAmount = isset($payload['budget_amount']) ? (float)$payload['budget_amount'] : 0;

        if ($period === '' || !preg_match('/^\d{4}-\d{2}$/', $period)) {
            $this->errorResponse('请输入正确的预算周期（格式：YYYY-MM）');
        }
        if ($budgetAmount <= 0) {
            $this->errorResponse('预算金额必须大于0');
        }

        $exists = Db::table('expense_budgets')
            ->where('dept_id', $deptId)
            ->where('type', $type)
            ->where('period', $period)
            ->find();

        if ($exists) {
            Db::table('expense_budgets')
                ->where('id', $exists['id'])
                ->update(['budget_amount' => round($budgetAmount, 2)]);

            $item = Db::table('expense_budgets')
                ->alias('b')
                ->leftJoin('departments d', 'd.id = b.dept_id')
                ->field('b.*, d.name as dept_name')
                ->where('b.id', $exists['id'])
                ->find();

            return $this->success(['item' => $this->formatItem($item)], '预算已更新');
        }

        $id = Db::table('expense_budgets')->insertGetId([
            'dept_id'       => $deptId,
            'type'          => $type,
            'period'        => $period,
            'budget_amount' => round($budgetAmount, 2),
            'created_at'    => date('Y-m-d H:i:s'),
        ]);

        $item = Db::table('expense_budgets')
            ->alias('b')
            ->leftJoin('departments d', 'd.id = b.dept_id')
            ->field('b.*, d.name as dept_name')
            ->where('b.id', $id)
            ->find();

        return $this->success(['item' => $this->formatItem($item)], '预算已创建', 201);
    }

    public function update($id)
    {
        $budget = Db::table('expense_budgets')->find($id);
        if (!$budget) {
            $this->errorResponse('预算记录不存在', 404);
        }

        $payload = $this->requestData();
        $update = [];

        if (isset($payload['budget_amount'])) {
            $amount = (float)$payload['budget_amount'];
            if ($amount <= 0) {
                $this->errorResponse('预算金额必须大于0');
            }
            $update['budget_amount'] = round($amount, 2);
        }

        if (empty($update)) {
            $this->errorResponse('没有需要更新的字段');
        }

        Db::table('expense_budgets')->where('id', $id)->update($update);

        $item = Db::table('expense_budgets')
            ->alias('b')
            ->leftJoin('departments d', 'd.id = b.dept_id')
            ->field('b.*, d.name as dept_name')
            ->where('b.id', $id)
            ->find();

        return $this->success(['item' => $this->formatItem($item)], '预算已更新');
    }

    public function delete($id)
    {
        $budget = Db::table('expense_budgets')->find($id);
        if (!$budget) {
            $this->errorResponse('预算记录不存在', 404);
        }

        Db::table('expense_budgets')->where('id', $id)->delete();
        return $this->success([], '预算已删除');
    }

    protected function formatItem(array $item): array
    {
        $typeMap = [
            'travel' => '差旅费',
            'meal' => '餐费',
            'transport' => '交通费',
            'office' => '办公用品',
            'purchase' => '采购费用',
            'other' => '其他',
            '' => '所有类型',
        ];

        $deptId = (int)$item['dept_id'];
        $usedAmount = $this->calcUsedAmount($deptId, $item['type'], $item['period']);
        $budgetAmount = (float)$item['budget_amount'];

        return [
            'id'             => (int)$item['id'],
            'dept_id'        => $deptId ?: null,
            'dept_name'      => $item['dept_name'] ?? '全局',
            'type'           => $item['type'],
            'type_label'     => $typeMap[$item['type']] ?? $item['type'],
            'period'         => $item['period'],
            'budget_amount'  => $budgetAmount,
            'used_amount'    => $usedAmount,
            'remain_amount'  => round(max(0, $budgetAmount - $usedAmount), 2),
            'usage_percent'  => $budgetAmount > 0 ? round($usedAmount / $budgetAmount * 100, 1) : 0,
            'created_at'     => $item['created_at'],
            'updated_at'     => $item['updated_at'],
        ];
    }

    protected function calcUsedAmount(int $deptId, string $type, string $period): float
    {
        $start = $period . '-01 00:00:00';
        $end = date('Y-m-t', strtotime($start)) . ' 23:59:59';

        if ($deptId > 0) {
            $userIds = Db::table('users')
                ->where('dept_id', $deptId)
                ->column('id');
        } else {
            $userIds = Db::table('users')->column('id');
        }

        if (empty($userIds)) {
            return 0.0;
        }

        $query = Db::table('expense_reports')
            ->whereIn('user_id', $userIds)
            ->whereIn('status', ['pending', 'approved'])
            ->where('created_at', '>=', $start)
            ->where('created_at', '<=', $end);

        if ($type !== '') {
            $query->where('type', $type);
        }

        $sum = $query->sum('amount');

        return round((float)$sum, 2);
    }
}
