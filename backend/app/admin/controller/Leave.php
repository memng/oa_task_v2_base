<?php

namespace app\admin\controller;

use app\common\controller\AdminApiController;
use app\common\service\ApprovalRuleService;
use app\common\service\NotificationService;
use think\facade\Db;
use think\facade\Request;

class Leave extends AdminApiController
{
    public function index()
    {
        $status = trim((string)Request::get('status', ''));
        $query = Db::table('leave_requests')->alias('l')
            ->leftJoin('users u', 'u.id = l.user_id')
            ->order('l.id', 'desc')
            ->field('l.*, u.name as user_name, u.mobile as user_mobile');
        if ($status !== '') {
            $query->where('l.status', $status);
        }
        $items = $query->select()->toArray();

        $result = [];
        foreach ($items as $row) {
            $item = $this->formatLeave($row);

            $flows = Db::table('leave_approval_flows')
                ->where('leave_request_id', $item['id'])
                ->order('step_order', 'asc')
                ->select()
                ->toArray();

            $flowItems = [];
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
                $flowItems[] = [
                    'step_order'       => (int)$flow['step_order'],
                    'step_name'        => $flow['step_name'],
                    'approver_type'    => $flow['approver_type'],
                    'approver_user_id' => $flow['approver_user_id'] ? (int)$flow['approver_user_id'] : null,
                    'approver_name'    => $approverName,
                    'status'           => $flow['status'],
                    'approved_at'      => $flow['approved_at'],
                    'reason'           => $flow['reason'],
                ];
            }
            $item['approval_flows'] = $flowItems;
            $result[] = $item;
        }

        return $this->success([
            'items' => $result,
        ]);
    }

    public function updateStatus($id)
    {
        $leave = Db::table('leave_requests')->find($id);
        if (!$leave) {
            $this->errorResponse('请假申请不存在', 404);
        }
        $payload = $this->requestData();
        $status = $payload['status'] ?? 'approved';
        if (!in_array($status, ['approved', 'rejected'], true)) {
            $this->errorResponse('状态非法');
        }
        
        $oldStatus = $leave['status'] ?? '';
        if ($oldStatus !== 'pending') {
            $row = Db::table('leave_requests')->alias('l')
                ->leftJoin('users u', 'u.id = l.user_id')
                ->field('l.*, u.name as user_name, u.mobile as user_mobile')
                ->where('l.id', $id)
                ->find();
            return $this->success([
                'leave' => $this->formatLeave($row),
            ], '状态已更新');
        }

        $hasFlow = Db::table('leave_approval_flows')
            ->where('leave_request_id', $id)
            ->count();

        if ($hasFlow > 0) {
            $pendingFlows = Db::table('leave_approval_flows')
                ->where('leave_request_id', $id)
                ->where('status', 'pending')
                ->order('step_order', 'asc')
                ->select()
                ->toArray();

            foreach ($pendingFlows as $flow) {
                Db::table('leave_approval_flows')
                    ->where('id', $flow['id'])
                    ->update([
                        'status'      => $status === 'approved' ? 'approved' : 'rejected',
                        'approved_at' => date('Y-m-d H:i:s'),
                        'reason'      => $payload['remark'] ?? $payload['reason'] ?? null,
                    ]);
            }
        }
        
        $update = [
            'status'      => $status,
            'approver_id' => $this->currentUser['id'] ?? null,
            'approved_at' => date('Y-m-d H:i:s'),
        ];
        Db::table('leave_requests')->where('id', $id)->update($update);
        
        $notificationService = new NotificationService();
        $applicantId = (int)$leave['user_id'];
        $reason = $payload['remark'] ?? $payload['reason'] ?? null;
        
        if ($status === 'approved') {
            $notificationService->sendLeaveApproved($applicantId, $leave);
        } else {
            $notificationService->sendLeaveRejected($applicantId, $leave, $reason);
        }
        
        $row = Db::table('leave_requests')->alias('l')
            ->leftJoin('users u', 'u.id = l.user_id')
            ->field('l.*, u.name as user_name, u.mobile as user_mobile')
            ->where('l.id', $id)
            ->find();
        return $this->success([
            'leave' => $this->formatLeave($row),
        ], '状态已更新');
    }

    protected function formatLeave(array $row): array
    {
        return [
            'id'             => (int)$row['id'],
            'user_id'        => (int)$row['user_id'],
            'user_name'      => $row['user_name'] ?? null,
            'user_mobile'    => $row['user_mobile'] ?? null,
            'leave_type'     => $row['leave_type'],
            'start_at'       => $row['start_at'],
            'end_at'         => $row['end_at'],
            'duration_hours' => (float)($row['duration_hours'] ?? 0),
            'reason'         => $row['reason'],
            'cancel_reason'  => $row['cancel_reason'] ?? null,
            'status'         => $row['status'],
            'current_step'   => $row['current_step'] ?? null,
            'rule_id'        => $row['rule_id'] ?? null,
            'created_at'     => $row['created_at'],
            'approved_at'    => $row['approved_at'],
        ];
    }
}
