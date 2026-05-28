<?php

namespace app\admin\controller;

use app\common\controller\AdminApiController;
use app\common\service\ApprovalRuleService;
use think\facade\Db;

class ApprovalRule extends AdminApiController
{
    public function index()
    {
        $rules = Db::table('leave_approval_rules')
            ->order('priority', 'desc')
            ->order('id', 'asc')
            ->select()
            ->toArray();

        $service = new ApprovalRuleService();
        $hasDefaultRule = $service->hasDefaultRule();

        $result = [];
        foreach ($rules as $rule) {
            $steps = $service->getRuleSteps((int)$rule['id']);

            $deptName = null;
            if ($rule['dept_id']) {
                $dept = Db::table('departments')->where('id', (int)$rule['dept_id'])->find();
                if ($dept) {
                    $deptName = $dept['name'];
                }
            }

            $isGlobalDefault = $rule['dept_id'] === null
                && $rule['level_min'] === null
                && $rule['level_max'] === null
                && $rule['leave_type'] === null;

            $stepItems = [];
            foreach ($steps as $step) {
                $approverName = null;
                if ($step['approver_user_id']) {
                    $user = Db::table('users')
                        ->where('id', (int)$step['approver_user_id'])
                        ->field('name, nickname')
                        ->find();
                    if ($user) {
                        $approverName = $user['name'] ?: $user['nickname'];
                    }
                }
                $stepItems[] = [
                    'id'                => (int)$step['id'],
                    'step_order'        => (int)$step['step_order'],
                    'step_name'         => $step['step_name'],
                    'approver_type'     => $step['approver_type'],
                    'approver_user_id'  => $step['approver_user_id'] ? (int)$step['approver_user_id'] : null,
                    'approver_name'     => $approverName,
                    'auto_approve'      => (int)$step['auto_approve'],
                    'migrated'          => !empty($step['_migrated']),
                    'migration_note'    => $step['_migration_note'] ?? null,
                ];
            }

            $result[] = [
                'id'               => (int)$rule['id'],
                'name'             => $rule['name'],
                'dept_id'          => $rule['dept_id'] ? (int)$rule['dept_id'] : null,
                'dept_name'        => $deptName,
                'level_min'        => $rule['level_min'] !== null ? (int)$rule['level_min'] : null,
                'level_max'        => $rule['level_max'] !== null ? (int)$rule['level_max'] : null,
                'leave_type'       => $rule['leave_type'],
                'priority'         => (int)$rule['priority'],
                'status'           => (int)$rule['status'],
                'is_global_default' => $isGlobalDefault && (int)$rule['status'] === 1,
                'steps'            => $stepItems,
                'created_at'       => $rule['created_at'],
                'updated_at'       => $rule['updated_at'],
            ];
        }

        return $this->success([
            'items'           => $result,
            'has_default_rule' => $hasDefaultRule,
        ]);
    }

    public function save()
    {
        $payload = $this->requestData();
        $name = trim((string)($payload['name'] ?? ''));
        if ($name === '') {
            $this->errorResponse('请输入规则名称');
        }

        $steps = $payload['steps'] ?? [];
        if (empty($steps) || !is_array($steps)) {
            $this->errorResponse('请至少配置一个审批步骤');
        }

        $deptId = !empty($payload['dept_id']) ? (int)$payload['dept_id'] : null;
        if ($deptId) {
            $deptExists = Db::table('departments')->where('id', $deptId)->count();
            if (!$deptExists) {
                $this->errorResponse('指定的部门不存在');
            }
        }

        $ruleLevelMin = isset($payload['level_min']) && $payload['level_min'] !== null && $payload['level_min'] !== '' ? (int)$payload['level_min'] : null;
        $ruleLevelMax = isset($payload['level_max']) && $payload['level_max'] !== null && $payload['level_max'] !== '' ? (int)$payload['level_max'] : null;
        $ruleLeaveType = !empty($payload['leave_type']) ? $payload['leave_type'] : null;

        $validationErrors = $this->validateSteps($steps, $deptId, $ruleLevelMin, $ruleLevelMax, $ruleLeaveType);
        if (!empty($validationErrors)) {
            $this->errorResponse('配置错误：' . implode('；', $validationErrors), 422, [
                'validation_errors' => $validationErrors
            ]);
        }

        Db::startTrans();
        try {
            $now = date('Y-m-d H:i:s');
            $ruleId = Db::table('leave_approval_rules')->insertGetId([
                'name'       => $name,
                'dept_id'    => $deptId,
                'level_min'  => isset($payload['level_min']) && $payload['level_min'] !== null && $payload['level_min'] !== '' ? (int)$payload['level_min'] : null,
                'level_max'  => isset($payload['level_max']) && $payload['level_max'] !== null && $payload['level_max'] !== '' ? (int)$payload['level_max'] : null,
                'leave_type' => !empty($payload['leave_type']) ? $payload['leave_type'] : null,
                'priority'   => (int)($payload['priority'] ?? 0),
                'status'     => (int)($payload['status'] ?? 1),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($steps as $idx => $step) {
                $stepOrder = (int)($step['step_order'] ?? ($idx + 1));
                $stepName = trim((string)($step['step_name'] ?? ''));
                $approverType = $step['approver_type'] ?? 'dept_leader';

                Db::table('leave_approval_steps')->insert([
                    'rule_id'           => $ruleId,
                    'step_order'        => $stepOrder,
                    'step_name'         => $stepName,
                    'approver_type'     => $approverType,
                    'approver_user_id'  => !empty($step['approver_user_id']) ? (int)$step['approver_user_id'] : null,
                    'auto_approve'      => !empty($step['auto_approve']) ? 1 : 0,
                    'created_at'        => $now,
                ]);
            }

            Db::commit();
            return $this->success(['id' => $ruleId], '审批规则已创建', 201);
        } catch (\Exception $e) {
            Db::rollback();
            $this->errorResponse('创建失败：' . $e->getMessage());
        }
    }

    public function update($id)
    {
        $rule = Db::table('leave_approval_rules')->find((int)$id);
        if (!$rule) {
            $this->errorResponse('审批规则不存在');
        }

        $payload = $this->requestData();
        $data = [];

        if (array_key_exists('name', $payload)) {
            $name = trim((string)$payload['name']);
            if ($name === '') {
                $this->errorResponse('规则名称不能为空');
            }
            $data['name'] = $name;
        }

        if (array_key_exists('dept_id', $payload)) {
            $deptId = !empty($payload['dept_id']) ? (int)$payload['dept_id'] : null;
            if ($deptId) {
                $deptExists = Db::table('departments')->where('id', $deptId)->count();
                if (!$deptExists) {
                    $this->errorResponse('指定的部门不存在');
                }
            }
            $data['dept_id'] = $deptId;
        }

        if (array_key_exists('level_min', $payload)) {
            $data['level_min'] = $payload['level_min'] !== null && $payload['level_min'] !== '' ? (int)$payload['level_min'] : null;
        }

        if (array_key_exists('level_max', $payload)) {
            $data['level_max'] = $payload['level_max'] !== null && $payload['level_max'] !== '' ? (int)$payload['level_max'] : null;
        }

        if (array_key_exists('leave_type', $payload)) {
            $data['leave_type'] = !empty($payload['leave_type']) ? $payload['leave_type'] : null;
        }

        if (array_key_exists('priority', $payload)) {
            $data['priority'] = (int)$payload['priority'];
        }

        if (array_key_exists('status', $payload)) {
            $data['status'] = (int)$payload['status'] ? 1 : 0;
        }

        if (array_key_exists('steps', $payload)) {
            $steps = $payload['steps'];
            if (empty($steps) || !is_array($steps)) {
                $this->errorResponse('请至少配置一个审批步骤');
            }

            $checkDeptId = $data['dept_id'] ?? $rule['dept_id'];
            $checkLevelMin = array_key_exists('level_min', $payload)
                ? ($payload['level_min'] !== null && $payload['level_min'] !== '' ? (int)$payload['level_min'] : null)
                : ($rule['level_min'] !== null ? (int)$rule['level_min'] : null);
            $checkLevelMax = array_key_exists('level_max', $payload)
                ? ($payload['level_max'] !== null && $payload['level_max'] !== '' ? (int)$payload['level_max'] : null)
                : ($rule['level_max'] !== null ? (int)$rule['level_max'] : null);
            $checkLeaveType = array_key_exists('leave_type', $payload)
                ? (!empty($payload['leave_type']) ? $payload['leave_type'] : null)
                : ($rule['leave_type'] ?: null);

            $validationErrors = $this->validateSteps(
                $steps,
                $checkDeptId ? (int)$checkDeptId : null,
                $checkLevelMin,
                $checkLevelMax,
                $checkLeaveType
            );
            if (!empty($validationErrors)) {
                $this->errorResponse('配置错误：' . implode('；', $validationErrors), 422, [
                    'validation_errors' => $validationErrors
                ]);
            }

            Db::startTrans();
            try {
                Db::table('leave_approval_steps')->where('rule_id', (int)$id)->delete();

                foreach ($steps as $idx => $step) {
                    $stepOrder = (int)($step['step_order'] ?? ($idx + 1));
                    $stepName = trim((string)($step['step_name'] ?? ''));
                    $approverType = $step['approver_type'] ?? 'dept_leader';

                    Db::table('leave_approval_steps')->insert([
                        'rule_id'           => (int)$id,
                        'step_order'        => $stepOrder,
                        'step_name'         => $stepName,
                        'approver_type'     => $approverType,
                        'approver_user_id'  => !empty($step['approver_user_id']) ? (int)$step['approver_user_id'] : null,
                        'auto_approve'      => !empty($step['auto_approve']) ? 1 : 0,
                        'created_at'        => date('Y-m-d H:i:s'),
                    ]);
                }

                $data['updated_at'] = date('Y-m-d H:i:s');
                Db::table('leave_approval_rules')->where('id', (int)$id)->update($data);
                Db::commit();
            } catch (\Exception $e) {
                Db::rollback();
                $this->errorResponse('更新失败：' . $e->getMessage());
            }
        } else {
            if (!empty($data)) {
                $data['updated_at'] = date('Y-m-d H:i:s');
                Db::table('leave_approval_rules')->where('id', (int)$id)->update($data);
            }
        }

        return $this->success([], '审批规则已更新');
    }

    public function delete($id)
    {
        $rule = Db::table('leave_approval_rules')->find((int)$id);
        if (!$rule) {
            $this->errorResponse('审批规则不存在');
        }

        Db::table('leave_approval_steps')->where('rule_id', (int)$id)->delete();
        Db::table('leave_approval_rules')->where('id', (int)$id)->delete();

        return $this->success([], '审批规则已删除');
    }

    public function read($id)
    {
        $rule = Db::table('leave_approval_rules')->find((int)$id);
        if (!$rule) {
            $this->errorResponse('审批规则不存在', 404);
        }

        $service = new ApprovalRuleService();
        $steps = $service->getRuleSteps((int)$id);

        $deptName = null;
        if ($rule['dept_id']) {
            $dept = Db::table('departments')->where('id', (int)$rule['dept_id'])->find();
            if ($dept) {
                $deptName = $dept['name'];
            }
        }

        $isGlobalDefault = $rule['dept_id'] === null
            && $rule['level_min'] === null
            && $rule['level_max'] === null
            && $rule['leave_type'] === null;

        $stepItems = [];
        foreach ($steps as $step) {
            $approverName = null;
            if ($step['approver_user_id']) {
                $user = Db::table('users')
                    ->where('id', (int)$step['approver_user_id'])
                    ->field('name, nickname')
                    ->find();
                if ($user) {
                    $approverName = $user['name'] ?: $user['nickname'];
                }
            }
            $stepItems[] = [
                'id'                => (int)$step['id'],
                'step_order'        => (int)$step['step_order'],
                'step_name'         => $step['step_name'],
                'approver_type'     => $step['approver_type'],
                'approver_user_id'  => $step['approver_user_id'] ? (int)$step['approver_user_id'] : null,
                'approver_name'     => $approverName,
                'auto_approve'      => (int)$step['auto_approve'],
                'migrated'          => !empty($step['_migrated']),
                'migration_note'    => $step['_migration_note'] ?? null,
            ];
        }

        return $this->success([
            'id'                => (int)$rule['id'],
            'name'              => $rule['name'],
            'dept_id'           => $rule['dept_id'] ? (int)$rule['dept_id'] : null,
            'dept_name'         => $deptName,
            'level_min'         => $rule['level_min'] !== null ? (int)$rule['level_min'] : null,
            'level_max'         => $rule['level_max'] !== null ? (int)$rule['level_max'] : null,
            'leave_type'        => $rule['leave_type'],
            'priority'          => (int)$rule['priority'],
            'status'            => (int)$rule['status'],
            'is_global_default' => $isGlobalDefault && (int)$rule['status'] === 1,
            'steps'             => $stepItems,
            'created_at'        => $rule['created_at'],
            'updated_at'        => $rule['updated_at'],
        ]);
    }

    protected function validateSteps(array $steps, ?int $ruleDeptId = null, ?int $ruleLevelMin = null, ?int $ruleLevelMax = null, ?string $ruleLeaveType = null): array
    {
        $errors = [];
        $allowedTypes = ['dept_leader', 'specific_user', 'level_up'];
        $deptInfo = null;

        if ($ruleDeptId) {
            $deptInfo = Db::table('departments')->where('id', (int)$ruleDeptId)->find();
        }

        foreach ($steps as $idx => $step) {
            $stepOrder = (int)($step['step_order'] ?? ($idx + 1));
            $stepName = trim((string)($step['step_name'] ?? ''));
            $approverType = $step['approver_type'] ?? '';
            $approverUserId = !empty($step['approver_user_id']) ? (int)$step['approver_user_id'] : null;
            $autoApprove = !empty($step['auto_approve']);

            if ($stepName === '') {
                $errors[] = "第{$stepOrder}步缺少步骤名称";
                continue;
            }

            if ($approverType === 'role') {
                $errors[] = "「{$stepName}」：「角色」审批类型已不再支持，请改为部门主管、直属上级或指定用户";
                continue;
            }

            if (!in_array($approverType, $allowedTypes, true)) {
                $errors[] = "「{$stepName}」：不支持的审批人类型「{$approverType}」，仅支持：" . implode('、', $allowedTypes);
                continue;
            }

            if ($approverType === 'specific_user') {
                if (!$approverUserId) {
                    $errors[] = "「{$stepName}」：选择「指定用户」类型时必须指定审批用户";
                } else {
                    $user = Db::table('users')
                        ->where('id', $approverUserId)
                        ->where('status', 'active')
                        ->find();
                    if (!$user) {
                        $errors[] = "「{$stepName}」：指定的审批用户不存在或已离职";
                    }
                }
            }

            if ($approverType === 'dept_leader' && !$autoApprove) {
                if (!$ruleDeptId) {
                    $errors[] = "「{$stepName}」：规则未指定适用部门，部门主管审批类型在未指定部门的规则中无法确定审批人，请指定部门、改为其他审批类型或设置为自动通过";
                } else {
                    $dept = Db::table('departments')->where('id', (int)$ruleDeptId)->find();
                    if (!$dept) {
                        $errors[] = "「{$stepName}」：指定的部门不存在";
                    } elseif (!$dept['leader_user_id']) {
                        $errors[] = "「{$stepName}」：部门「{$dept['name']}」未设置主管，无法确定审批人，请先设置部门主管或将此步骤设为自动通过";
                    } else {
                        $leader = Db::table('users')
                            ->where('id', (int)$dept['leader_user_id'])
                            ->where('status', 'active')
                            ->find();
                        if (!$leader) {
                            $errors[] = "「{$stepName}」：部门主管用户不存在或已离职，请更新部门主管配置";
                        }
                    }
                }
            }

            if ($approverType === 'level_up' && !$autoApprove) {
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
        }

        return $errors;
    }
}
