<?php

namespace app\common\service;

use think\facade\Db;

class ReimburseRuleService
{
    public function checkAll(int $userId, string $type, float $amount, array $receiptMediaIds = [], ?int $excludeId = null): array
    {
        $violations = [];

        $overBudget = $this->checkOverBudget($userId, $type, $amount, $excludeId);
        if ($overBudget) {
            $violations[] = $overBudget;
        }

        $duplicate = $this->checkDuplicate($userId, $type, $amount, $excludeId);
        if ($duplicate) {
            $violations[] = $duplicate;
        }

        $missingReceipt = $this->checkMissingReceipt($type, $amount, $receiptMediaIds);
        if ($missingReceipt) {
            $violations[] = $missingReceipt;
        }

        return $violations;
    }

    public function checkOverBudget(int $userId, string $type, float $amount, ?int $excludeId = null): ?array
    {
        $user = Db::table('users')->find($userId);
        if (!$user || empty($user['dept_id'])) {
            return null;
        }

        $deptId = (int)$user['dept_id'];
        $period = date('Y-m');

        $resolved = $this->resolveBudget($deptId, $type, $period);
        if (!$resolved) {
            return null;
        }

        $budget = $resolved['budget'];
        $budgetDeptId = $resolved['dept_id'];
        $budgetAmount = (float)$budget['budget_amount'];
        $budgetType = $budget['type'];
        $usedAmount = $this->calcUsedAmount($budgetDeptId, $budgetType, $period, $excludeId);

        if (($usedAmount + $amount) > $budgetAmount) {
            return [
                'rule'       => 'over_budget',
                'level'      => 'warning',
                'message'    => sprintf(
                    '报销金额超出预算：预算 ¥%.2f，已用 ¥%.2f，本次 ¥%.2f，超出 ¥%.2f',
                    $budgetAmount,
                    $usedAmount,
                    $amount,
                    $usedAmount + $amount - $budgetAmount
                ),
                'budget_amount'  => $budgetAmount,
                'used_amount'    => $usedAmount,
                'current_amount' => $amount,
                'over_amount'    => round($usedAmount + $amount - $budgetAmount, 2),
            ];
        }

        return null;
    }

    public function checkDuplicate(int $userId, string $type, float $amount, ?int $excludeId = null): ?array
    {
        $since = date('Y-m-d H:i:s', strtotime('-7 days'));

        $query = Db::table('expense_reports')
            ->where('user_id', $userId)
            ->where('type', $type)
            ->where('amount', $amount)
            ->where('created_at', '>=', $since);

        if ($excludeId) {
            $query->where('id', '<>', $excludeId);
        }

        $duplicates = $query->select()->toArray();

        if (!empty($duplicates)) {
            $latest = $duplicates[count($duplicates) - 1];
            return [
                'rule'          => 'duplicate',
                'level'         => 'warning',
                'message'       => sprintf(
                    '发现7天内存在相同类型和金额(¥%.2f)的报销记录（ID: %d，提交于 %s），请确认是否重复提交',
                    $amount,
                    (int)$latest['id'],
                    substr($latest['created_at'], 0, 16)
                ),
                'duplicate_id'     => (int)$latest['id'],
                'duplicate_amount' => (float)$latest['amount'],
                'duplicate_date'   => substr($latest['created_at'], 0, 16),
            ];
        }

        return null;
    }

    public function checkMissingReceipt(string $type, float $amount, array $receiptMediaIds = []): ?array
    {
        $thresholds = [
            'travel'    => 100,
            'purchase'  => 0,
            'meal'      => 50,
            'transport' => 30,
            'office'    => 0,
            'other'     => 50,
        ];

        $threshold = $thresholds[$type] ?? 50;

        if ($amount > $threshold && empty($receiptMediaIds)) {
            return [
                'rule'      => 'missing_receipt',
                'level'     => 'warning',
                'message'   => sprintf(
                    '金额 ¥%.2f 超过 ¥%d 阈值，请上传票据凭证',
                    $amount,
                    $threshold
                ),
                'amount'    => $amount,
                'threshold' => $threshold,
            ];
        }

        return null;
    }

    public function getBudgetStatus(int $userId, string $type): array
    {
        $user = Db::table('users')->find($userId);
        if (!$user || empty($user['dept_id'])) {
            return ['has_budget' => false];
        }

        $deptId = (int)$user['dept_id'];
        $period = date('Y-m');

        $resolved = $this->resolveBudget($deptId, $type, $period);
        if (!$resolved) {
            return ['has_budget' => false];
        }

        $budget = $resolved['budget'];
        $budgetDeptId = $resolved['dept_id'];
        $budgetAmount = (float)$budget['budget_amount'];
        $budgetType = $budget['type'];
        $usedAmount = $this->calcUsedAmount($budgetDeptId, $budgetType, $period);
        $remainAmount = $budgetAmount - $usedAmount;

        return [
            'has_budget'    => true,
            'dept_id'       => $deptId,
            'type'          => $type,
            'budget_type'   => $budgetType,
            'budget_dept_id' => $budgetDeptId,
            'period'        => $period,
            'budget_amount' => $budgetAmount,
            'used_amount'   => $usedAmount,
            'remain_amount' => round($remainAmount, 2),
            'usage_percent' => $budgetAmount > 0 ? round($usedAmount / $budgetAmount * 100, 1) : 0,
        ];
    }

    protected function resolveBudget(int $deptId, string $type, string $period): ?array
    {
        $priorities = [
            ['dept_id' => $deptId, 'type' => $type],
            ['dept_id' => $deptId, 'type' => ''],
            ['dept_id' => null,    'type' => $type],
            ['dept_id' => null,    'type' => ''],
        ];

        foreach ($priorities as $p) {
            $budget = $this->findBudget($p['dept_id'], $p['type'], $period);
            if ($budget) {
                return [
                    'budget'  => $budget,
                    'dept_id' => $p['dept_id'] ?? 0,
                ];
            }
        }

        return null;
    }

    protected function findBudget(?int $deptId, string $type, string $period): ?array
    {
        $query = Db::table('expense_budgets')
            ->where('period', $period);

        if ($deptId === null) {
            $query->whereNull('dept_id');
        } else {
            $query->where('dept_id', $deptId);
        }

        $query->where('type', $type);

        return $query->find();
    }

    protected function calcUsedAmount(int $deptId, string $type, string $period, ?int $excludeId = null): float
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

        if ($excludeId) {
            $query->where('id', '<>', $excludeId);
        }

        $sum = $query->sum('amount');

        return round((float)$sum, 2);
    }
}
