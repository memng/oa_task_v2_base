<?php

namespace app\admin\controller;

use app\common\controller\AdminApiController;
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
        return $this->success([
            'items' => array_map([$this, 'formatLeave'], $items),
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
            'status'         => $row['status'],
            'created_at'     => $row['created_at'],
            'approved_at'    => $row['approved_at'],
        ];
    }
}
