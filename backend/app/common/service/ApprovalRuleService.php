<?php

namespace app\common\service;

use think\facade\Db;

class ApprovalRuleService
{
    const APPROVER_TYPES = ['dept_leader', 'specific_user', 'level_up'];

    const FLOW_STATUS_PENDING = 'pending';
    const FLOW_STATUS_APPROVED = 'approved';
    const FLOW_STATUS_REJECTED = 'rejected';
    const FLOW_STATUS_AUTO_SKIPPED = 'auto_skipped';

    const SKIP_REASON_AUTO_APPROVE = 'auto_approve';
    const SKIP_REASON_WITHDRAWN = 'withdrawn';

    protected $approverTypeLabels = [
        'dept_leader'   => '部门主管',
        'specific_user' => '指定用户',
        'level_up'      => '直属上级',
    ];

    protected $flowStatusLabels = [
        'pending'      => '待审批',
        'approved'     => '已通过',
        'rejected'     => '已拒绝',
        'auto_skipped' => '自动通过',
    ];

    protected $skipReasonLabels = [
        'auto_approve' => '配置为自动通过',
        'withdrawn'    => '申请已撤回',
    ];

    public function matchRule(int $userId, string $leaveType): ?array
    {
        $user = Db::table('users')->where('id', $userId)->find();
        if (!$user) {
            return null;
        }

        $rules = Db::table('leave_approval_rules')
            ->where('status', 1)
            ->order('priority', 'desc')
            ->order('id', 'asc')
            ->select()
            ->toArray();

        foreach ($rules as $rule) {
            if ($this->ruleMatches($rule, $user, $leaveType)) {
                $steps = $this->loadAndMigrateSteps((int)$rule['id']);
                $rule['steps'] = $steps;
                return $rule;
            }
        }

        return null;
    }

    public function getDefaultRule(): ?array
    {
        $default = Db::table('leave_approval_rules')
            ->where('status', 1)
            ->whereNull('dept_id')
            ->whereNull('level_min')
            ->whereNull('level_max')
            ->whereNull('leave_type')
            ->order('priority', 'desc')
            ->order('id', 'asc')
            ->find();

        if ($default) {
            $steps = $this->loadAndMigrateSteps((int)$default['id']);
            $default['steps'] = $steps;
            $default['_is_default'] = true;
            return $default;
        }

        return null;
    }

    public function hasDefaultRule(): bool
    {
        return Db::table('leave_approval_rules')
            ->where('status', 1)
            ->whereNull('dept_id')
            ->whereNull('level_min')
            ->whereNull('level_max')
            ->whereNull('leave_type')
            ->count() > 0;
    }

    public function getRuleSteps(int $ruleId): array
    {
        return $this->loadAndMigrateSteps($ruleId);
    }

    protected function loadAndMigrateSteps(int $ruleId): array
    {
        $steps = Db::table('leave_approval_steps')
            ->where('rule_id', $ruleId)
            ->order('step_order', 'asc')
            ->select()
            ->toArray();

        foreach ($steps as &$step) {
            if (isset($step['approver_type']) && $step['approver_type'] === 'role') {
                $step['approver_type'] = 'dept_leader';
                $step['_migrated'] = true;
                $step['_migration_note'] = '原 role 类型已自动迁移为 dept_leader';
            }
        }
        unset($step);

        return $steps;
    }

    protected function ruleMatches(array $rule, array $user, string $leaveType): bool
    {
        if ($rule['dept_id'] !== null && (int)$rule['dept_id'] !== (int)$user['dept_id']) {
            return false;
        }

        $userLevel = $user['level'] !== null ? (int)$user['level'] : null;
        if ($rule['level_min'] !== null && $userLevel !== null && $userLevel < (int)$rule['level_min']) {
            return false;
        }
        if ($rule['level_max'] !== null && $userLevel !== null && $userLevel > (int)$rule['level_max']) {
            return false;
        }

        if ($rule['leave_type'] !== null && $rule['leave_type'] !== $leaveType) {
            return false;
        }

        return true;
    }

    public function resolveApproverUserId(string $approverType, ?int $approverUserId, int $applicantUserId, ?int $applicantDeptId): ?int
    {
        $approverType = $this->normalizeApproverType($approverType);
        if (!in_array($approverType, self::APPROVER_TYPES, true)) {
            return null;
        }

        switch ($approverType) {
            case 'specific_user':
                if ($approverUserId) {
                    $user = Db::table('users')
                        ->where('id', (int)$approverUserId)
                        ->where('status', 'active')
                        ->find();
                    return $user ? (int)$approverUserId : null;
                }
                return null;

            case 'dept_leader':
                if (!$applicantDeptId) {
                    return null;
                }
                $dept = Db::table('departments')->where('id', (int)$applicantDeptId)->find();
                if (!$dept || !$dept['leader_user_id']) {
                    return null;
                }
                $leader = Db::table('users')
                    ->where('id', (int)$dept['leader_user_id'])
                    ->where('status', 'active')
                    ->find();
                return $leader ? (int)$dept['leader_user_id'] : null;

            case 'level_up':
                $applicant = Db::table('users')->where('id', $applicantUserId)->find();
                if (!$applicant || !$applicant['manager_id']) {
                    return null;
                }
                $manager = Db::table('users')
                    ->where('id', (int)$applicant['manager_id'])
                    ->where('status', 'active')
                    ->find();
                return $manager ? (int)$applicant['manager_id'] : null;

            default:
                return null;
        }
    }

    protected function normalizeApproverType(string $type): string
    {
        if ($type === 'role') {
            return 'dept_leader';
        }
        return $type;
    }

    public function validateRuleSteps(array $steps, ?int $ruleDeptId = null, ?int $applicantUserId = null, ?int $applicantDeptId = null, ?int $ruleLevelMin = null, ?int $ruleLevelMax = null, ?string $ruleLeaveType = null): array
    {
        $errors = [];
        $userInfo = null;
        $deptInfo = null;

        $checkDeptId = $applicantDeptId ?? $ruleDeptId;

        if ($applicantUserId) {
            $userInfo = Db::table('users')->where('id', $applicantUserId)->find();
            if ($userInfo && $userInfo['dept_id']) {
                $deptInfo = Db::table('departments')->where('id', (int)$userInfo['dept_id'])->find();
            }
        }
        if ($checkDeptId && !$deptInfo) {
            $deptInfo = Db::table('departments')->where('id', (int)$checkDeptId)->find();
        }

        foreach ($steps as $step) {
            $stepOrder = $step['step_order'] ?? '?';
            $stepName = $step['step_name'] ?? "步骤{$stepOrder}";
            $approverType = $this->normalizeApproverType($step['approver_type'] ?? '');
            $autoApprove = !empty($step['auto_approve']);

            if ($approverType === 'role') {
                $errors[] = "「{$stepName}」：「角色」审批类型已不再支持，请改为部门主管、直属上级或指定用户";
                continue;
            }

            if (!in_array($approverType, self::APPROVER_TYPES, true)) {
                $errors[] = "「{$stepName}」：不支持的审批人类型「{$approverType}」";
                continue;
            }

            $approverUserId = !empty($step['approver_user_id']) ? (int)$step['approver_user_id'] : null;
            $testDeptId = $applicantDeptId ?? ($deptInfo ? (int)$deptInfo['id'] : null);
            $testUserId = $applicantUserId ?? 0;

            $resolvedApprover = $this->resolveApproverUserId($approverType, $approverUserId, $testUserId, $testDeptId);

            if ($resolvedApprover === null && !$autoApprove) {
                switch ($approverType) {
                    case 'dept_leader':
                        if (!$checkDeptId) {
                            $errors[] = "「{$stepName}」：规则未指定适用部门，部门主管审批类型在未指定部门的规则中无法确定审批人，请指定部门或改为自动通过";
                        } elseif (!$deptInfo) {
                            $errors[] = "「{$stepName}」：指定的部门不存在";
                        } elseif (!$deptInfo['leader_user_id']) {
                            $errors[] = "「{$stepName}」：部门「{$deptInfo['name']}」未设置主管，无法确定审批人，请先设置部门主管或将此步骤设为自动通过";
                        } else {
                            $errors[] = "「{$stepName}」：部门主管用户不存在或已离职";
                        }
                        break;

                    case 'specific_user':
                        if (!$approverUserId) {
                            $errors[] = "「{$stepName}」：未指定审批用户";
                        } else {
                            $errors[] = "「{$stepName}」：指定的审批用户不存在或已离职";
                        }
                        break;

                    case 'level_up':
                        if ($testUserId) {
                            if (!$userInfo) {
                                $errors[] = "「{$stepName}」：申请人信息不存在";
                            } elseif (!$userInfo['manager_id']) {
                                $errors[] = "「{$stepName}」：申请人未设置直属上级，无法确定审批人，请先设置用户直属上级或将此步骤设为自动通过";
                            } else {
                                $errors[] = "「{$stepName}」：申请人的直属上级不存在或已离职";
                            }
                        } else {
                            $userQuery = Db::table('users')
                                ->where('status', 'active')
                                ->where(function ($q) use ($ruleDeptId, $ruleLevelMin, $ruleLevelMax) {
                                    if ($ruleDeptId) {
                                        $q->where('dept_id', $ruleDeptId);
                                    }
                                    if ($ruleLevelMin !== null) {
                                        $q->where('level', '>=', $ruleLevelMin);
                                    }
                                    if ($ruleLevelMax !== null) {
                                        $q->where('level', '<=', $ruleLevelMax);
                                    }
                                });

                            $usersWithoutManager = (clone $userQuery)
                                ->whereNull('manager_id')
                                ->count();
                            $totalInScope = $userQuery->count();

                            if ($usersWithoutManager > 0) {
                                $scopeDesc = '';
                                if ($ruleDeptId && $deptInfo) {
                                    $scopeDesc .= "部门「{$deptInfo['name']}」";
                                }
                                if ($ruleLevelMin !== null || $ruleLevelMax !== null) {
                                    $scopeDesc .= ($scopeDesc ? '，' : '') . "职级 {$ruleLevelMin} ~ {$ruleLevelMax}";
                                }
                                if (!$scopeDesc) {
                                    $scopeDesc = '全公司';
                                }
                                $errors[] = "「{$stepName}」：{$scopeDesc}范围内有 {$usersWithoutManager}/{$totalInScope} 个活跃用户未设置直属上级（manager_id），这些用户提交请假时将因无法解析审批人而失败。建议：将此步骤设为自动通过，或确保适用范围内的用户均已设置直属上级";
                            }
                        }
                        break;
                }
            }
        }

        return $errors;
    }

    public function createApprovalFlows(int $leaveRequestId, array $rule, int $applicantUserId, ?int $applicantDeptId): array
    {
        $flows = [];
        $steps = $rule['steps'] ?? [];

        $validationErrors = $this->validateRuleSteps(
            $steps,
            $rule['dept_id'] ?? null,
            $applicantUserId,
            $applicantDeptId,
            $rule['level_min'] ?? null,
            $rule['level_max'] ?? null,
            $rule['leave_type'] ?? null
        );
        if (!empty($validationErrors)) {
            throw new \RuntimeException(implode('；', $validationErrors));
        }

        foreach ($steps as $step) {
            $approverType = $this->normalizeApproverType($step['approver_type'] ?? '');
            $approverId = $this->resolveApproverUserId(
                $approverType,
                $step['approver_user_id'] ?? null,
                $applicantUserId,
                $applicantDeptId
            );

            $status = self::FLOW_STATUS_PENDING;
            $skipReason = null;

            if ($approverId === null) {
                if (!empty($step['auto_approve'])) {
                    $status = self::FLOW_STATUS_AUTO_SKIPPED;
                    $skipReason = self::SKIP_REASON_AUTO_APPROVE;
                } else {
                    throw new \RuntimeException(sprintf(
                        '「%s」步骤无法确定审批人，请先完善主数据或设置该步骤为自动通过',
                        $step['step_name'] ?? "步骤{$step['step_order']}"
                    ));
                }
            }

            Db::table('leave_approval_flows')->insert([
                'leave_request_id' => $leaveRequestId,
                'step_order'       => $step['step_order'],
                'step_name'        => $step['step_name'],
                'approver_type'    => $approverType,
                'approver_user_id' => $approverId,
                'status'           => $status,
                'skip_reason'      => $skipReason,
                'created_at'       => date('Y-m-d H:i:s'),
            ]);

            $flows[] = [
                'step_order'       => $step['step_order'],
                'step_name'        => $step['step_name'],
                'approver_type'    => $approverType,
                'approver_user_id' => $approverId,
                'status'           => $status,
                'skip_reason'      => $skipReason,
            ];
        }

        return $flows;
    }

    public function getCurrentPendingFlow(int $leaveRequestId): ?array
    {
        $allFlows = Db::table('leave_approval_flows')
            ->where('leave_request_id', $leaveRequestId)
            ->order('step_order', 'asc')
            ->select()
            ->toArray();

        foreach ($allFlows as $flow) {
            if ($flow['status'] === self::FLOW_STATUS_REJECTED) {
                return null;
            }
            if ($flow['status'] === self::FLOW_STATUS_PENDING) {
                return $flow;
            }
        }

        return null;
    }

    public function isFullyApproved(int $leaveRequestId): bool
    {
        $pendingCount = Db::table('leave_approval_flows')
            ->where('leave_request_id', $leaveRequestId)
            ->where('status', self::FLOW_STATUS_PENDING)
            ->count();

        return $pendingCount === 0;
    }

    public function advanceToNextStep(int $leaveRequestId): ?array
    {
        $nextPending = $this->getCurrentPendingFlow($leaveRequestId);

        if ($nextPending) {
            Db::table('leave_requests')
                ->where('id', $leaveRequestId)
                ->update(['current_step' => (int)$nextPending['step_order']]);

            return [
                'step_order'       => (int)$nextPending['step_order'],
                'step_name'        => $nextPending['step_name'],
                'approver_user_id' => $nextPending['approver_user_id'] ? (int)$nextPending['approver_user_id'] : null,
            ];
        }

        return null;
    }

    public function getApprovalFlows(int $leaveRequestId): array
    {
        $flows = Db::table('leave_approval_flows')
            ->where('leave_request_id', $leaveRequestId)
            ->order('step_order', 'asc')
            ->select()
            ->toArray();

        $result = [];
        foreach ($flows as $flow) {
            $approverName = null;
            if ($flow['approver_user_id']) {
                $approver = Db::table('users')
                    ->where('id', (int)$flow['approver_user_id'])
                    ->field('name, nickname')
                    ->find();
                if ($approver) {
                    $approverName = $approver['name'] ?: $approver['nickname'];
                }
            }

            $statusLabel = $this->flowStatusLabels[$flow['status']] ?? $flow['status'];
            $skipReasonLabel = $flow['skip_reason'] ? ($this->skipReasonLabels[$flow['skip_reason']] ?? $flow['skip_reason']) : null;

            $approverType = $this->normalizeApproverType($flow['approver_type']);
            $approverTypeLabel = $this->approverTypeLabels[$approverType] ?? $approverType;

            $result[] = [
                'id'                  => (int)$flow['id'],
                'step_order'          => (int)$flow['step_order'],
                'step_name'           => $flow['step_name'],
                'approver_type'       => $approverType,
                'approver_type_label' => $approverTypeLabel,
                'approver_user_id'    => $flow['approver_user_id'] ? (int)$flow['approver_user_id'] : null,
                'approver_name'       => $approverName,
                'status'              => $flow['status'],
                'status_label'        => $statusLabel,
                'skip_reason'         => $flow['skip_reason'],
                'skip_reason_label'   => $skipReasonLabel,
                'approved_at'         => $flow['approved_at'],
                'reason'              => $flow['reason'],
            ];
        }

        return $result;
    }

    public function getPendingApprovals(int $approverUserId): array
    {
        $currentFlows = Db::table('leave_approval_flows')
            ->alias('f')
            ->join('leave_requests r', 'r.id = f.leave_request_id')
            ->where('f.approver_user_id', $approverUserId)
            ->where('f.status', self::FLOW_STATUS_PENDING)
            ->where('r.status', 'pending')
            ->field('f.leave_request_id, f.step_order, f.step_name')
            ->select()
            ->toArray();

        if (empty($currentFlows)) {
            return [];
        }

        $requestIds = array_unique(array_column($currentFlows, 'leave_request_id'));
        $myStepsMap = [];
        foreach ($currentFlows as $f) {
            $myStepsMap[(int)$f['leave_request_id']] = [
                'step_order' => (int)$f['step_order'],
                'step_name'  => $f['step_name'],
            ];
        }

        $result = [];
        foreach ($requestIds as $requestId) {
            $myStep = $myStepsMap[$requestId] ?? null;
            if (!$myStep) {
                continue;
            }

            $currentFlow = $this->getCurrentPendingFlow($requestId);
            if (!$currentFlow || $currentFlow['step_order'] != $myStep['step_order']) {
                continue;
            }
            if ($currentFlow['approver_user_id'] != $approverUserId) {
                continue;
            }

            $leave = Db::table('leave_requests')->where('id', (int)$requestId)->find();
            if (!$leave || $leave['status'] !== 'pending') {
                continue;
            }

            $user = Db::table('users')
                ->where('id', (int)$leave['user_id'])
                ->field('name, nickname, dept_id')
                ->find();

            $deptName = null;
            if ($user && $user['dept_id']) {
                $dept = Db::table('departments')->where('id', (int)$user['dept_id'])->find();
                if ($dept) {
                    $deptName = $dept['name'];
                }
            }

            $result[] = [
                'id'              => (int)$requestId,
                'user_id'         => (int)$leave['user_id'],
                'user_name'       => $user ? ($user['name'] ?: $user['nickname']) : null,
                'user_dept'       => $deptName,
                'leave_type'      => $leave['leave_type'],
                'start_at'        => $leave['start_at'],
                'end_at'          => $leave['end_at'],
                'duration_hours'  => (float)$leave['duration_hours'],
                'reason'          => $leave['reason'],
                'status'          => $leave['status'],
                'current_step'    => $myStep['step_order'],
                'step_name'       => $myStep['step_name'],
                'created_at'      => $leave['created_at'],
            ];
        }

        usort($result, function ($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        return $result;
    }

    public function canApprove(int $leaveRequestId, int $userId): bool
    {
        $currentFlow = $this->getCurrentPendingFlow($leaveRequestId);
        if (!$currentFlow) {
            return false;
        }
        return $currentFlow['approver_user_id'] == $userId;
    }

    public function getAllApprovers(int $leaveRequestId): array
    {
        $flows = Db::table('leave_approval_flows')
            ->where('leave_request_id', $leaveRequestId)
            ->whereNotNull('approver_user_id')
            ->order('step_order', 'asc')
            ->select()
            ->toArray();

        $approvers = [];
        foreach ($flows as $flow) {
            $approverId = (int)$flow['approver_user_id'];
            if ($approverId > 0 && !in_array($approverId, $approvers, true)) {
                $approvers[] = $approverId;
            }
        }

        return $approvers;
    }

    public function getRelevantApproversForWithdraw(int $leaveRequestId): array
    {
        $flows = Db::table('leave_approval_flows')
            ->where('leave_request_id', $leaveRequestId)
            ->where('status', self::FLOW_STATUS_PENDING)
            ->whereNotNull('approver_user_id')
            ->order('step_order', 'asc')
            ->select()
            ->toArray();

        $approvers = [];
        foreach ($flows as $flow) {
            $approverId = (int)$flow['approver_user_id'];
            if ($approverId > 0 && !in_array($approverId, $approvers, true)) {
                $approvers[] = $approverId;
            }
        }

        return $approvers;
    }

    public function getPendingFlows(int $leaveRequestId): array
    {
        return Db::table('leave_approval_flows')
            ->where('leave_request_id', $leaveRequestId)
            ->where('status', self::FLOW_STATUS_PENDING)
            ->order('step_order', 'asc')
            ->select()
            ->toArray();
    }
}
