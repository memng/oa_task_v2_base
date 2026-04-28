<?php

namespace app\common\service;

use think\facade\Db;

class NotificationService
{
    const CHANNEL_SYSTEM = 'system';
    const CHANNEL_MINIAPP = 'miniapp';
    const CHANNEL_SERVICE_ACCOUNT = 'service_account';
    const CHANNEL_EMAIL = 'email';
    const CHANNEL_SMS = 'sms';

    const TEMPLATE_ORDER_CREATED = 'order_created';
    const TEMPLATE_TASK_ASSIGNED = 'task_assigned';
    const TEMPLATE_LEAVE_APPROVED = 'leave_approved';
    const TEMPLATE_REIMBURSE_APPROVED = 'reimburse_approved';
    const TEMPLATE_LEAVE_REJECTED = 'leave_rejected';
    const TEMPLATE_REIMBURSE_REJECTED = 'reimburse_rejected';

    public function sendOrderCreated(int $userId, array $order, ?int $initiatorId = null): void
    {
        $piNumber = $order['pi_number'] ?? ($order['pi_numbers'][0] ?? '');
        $title = '订单创建成功';
        $content = sprintf('您的订单 %s（%s）已创建成功。', $piNumber ?: 'N/A', $order['customer_name'] ?? '');
        
        $this->createNotification($userId, [
            'channel' => self::CHANNEL_SYSTEM,
            'template_code' => self::TEMPLATE_ORDER_CREATED,
            'title' => $title,
            'content' => $content,
            'payload' => [
                'type' => 'order_created',
                'order_id' => $order['id'] ?? null,
                'order_pi_number' => $piNumber,
                'customer_name' => $order['customer_name'] ?? null,
                'initiator_id' => $initiatorId,
            ],
        ]);
    }

    public function sendTaskAssigned(int $userId, array $task, ?int $assignorId = null): void
    {
        $typeMap = [
            'procurement' => '采购任务',
            'nameplate' => '铭牌制作',
            'machine_data' => '机器数据',
            'acceptance' => '机器验收',
            'packaging' => '打包唛头',
            'shipment' => '装柜发货',
            'inspection' => '客户验厂',
            'temporary' => '临时任务',
            'factory_order' => '工厂订单',
            'fee' => '费用',
            'document' => '资料',
            'announcement' => '公告',
        ];
        $typeLabel = $typeMap[$task['type'] ?? ''] ?? '任务';
        $title = '新任务分配';
        $content = sprintf('您有一个新的%s待处理：%s', $typeLabel, $task['title'] ?? '');
        
        $this->createNotification($userId, [
            'channel' => self::CHANNEL_SYSTEM,
            'template_code' => self::TEMPLATE_TASK_ASSIGNED,
            'title' => $title,
            'content' => $content,
            'payload' => [
                'type' => 'task_assigned',
                'task_id' => $task['id'] ?? null,
                'task_title' => $task['title'] ?? null,
                'task_type' => $task['type'] ?? null,
                'order_id' => $task['order_id'] ?? null,
                'assignor_id' => $assignorId,
                'due_at' => $task['due_at'] ?? null,
            ],
        ]);
    }

    public function sendLeaveApproved(int $userId, array $leaveRequest): void
    {
        $typeMap = [
            'annual' => '年假',
            'sick' => '病假',
            'personal' => '事假',
            'other' => '其他',
        ];
        $typeLabel = $typeMap[$leaveRequest['leave_type'] ?? ''] ?? '请假';
        
        $startDate = $leaveRequest['start_at'] ? date('m月d日 H:i', strtotime($leaveRequest['start_at'])) : '';
        $endDate = $leaveRequest['end_at'] ? date('m月d日 H:i', strtotime($leaveRequest['end_at'])) : '';
        
        $title = '请假审批通过';
        $content = sprintf('您的%s申请已通过审批。请假时间：%s 至 %s。', 
            $typeLabel, 
            $startDate ?: '未知', 
            $endDate ?: '未知'
        );
        
        $this->createNotification($userId, [
            'channel' => self::CHANNEL_SYSTEM,
            'template_code' => self::TEMPLATE_LEAVE_APPROVED,
            'title' => $title,
            'content' => $content,
            'payload' => [
                'type' => 'leave_approved',
                'leave_type' => $leaveRequest['leave_type'] ?? null,
                'leave_id' => $leaveRequest['id'] ?? null,
                'start_at' => $leaveRequest['start_at'] ?? null,
                'end_at' => $leaveRequest['end_at'] ?? null,
            ],
        ]);
    }

    public function sendLeaveRejected(int $userId, array $leaveRequest, ?string $reason = null): void
    {
        $typeMap = [
            'annual' => '年假',
            'sick' => '病假',
            'personal' => '事假',
            'other' => '其他',
        ];
        $typeLabel = $typeMap[$leaveRequest['leave_type'] ?? ''] ?? '请假';
        
        $title = '请假审批不通过';
        $content = sprintf('您的%s申请未通过审批。', $typeLabel);
        if ($reason) {
            $content .= sprintf(' 原因：%s', $reason);
        }
        
        $this->createNotification($userId, [
            'channel' => self::CHANNEL_SYSTEM,
            'template_code' => self::TEMPLATE_LEAVE_REJECTED,
            'title' => $title,
            'content' => $content,
            'payload' => [
                'type' => 'leave_rejected',
                'leave_type' => $leaveRequest['leave_type'] ?? null,
                'leave_id' => $leaveRequest['id'] ?? null,
                'reason' => $reason,
            ],
        ]);
    }

    public function sendReimburseApproved(int $userId, array $expenseReport): void
    {
        $typeMap = [
            'travel' => '差旅费',
            'meal' => '餐费',
            'transport' => '交通费',
            'office' => '办公用品',
            'other' => '其他费用',
        ];
        $typeLabel = $typeMap[$expenseReport['type'] ?? ''] ?? '报销';
        $amount = number_format((float)($expenseReport['amount'] ?? 0), 2);
        
        $title = '报销审批通过';
        $content = sprintf('您的%s申请已通过审批，金额：¥%s。', $typeLabel, $amount);
        
        $this->createNotification($userId, [
            'channel' => self::CHANNEL_SYSTEM,
            'template_code' => self::TEMPLATE_REIMBURSE_APPROVED,
            'title' => $title,
            'content' => $content,
            'payload' => [
                'type' => 'reimburse_approved',
                'reimburse_type' => $expenseReport['type'] ?? null,
                'reimburse_id' => $expenseReport['id'] ?? null,
                'amount' => $expenseReport['amount'] ?? null,
            ],
        ]);
    }

    public function sendReimburseRejected(int $userId, array $expenseReport, ?string $reason = null): void
    {
        $typeMap = [
            'travel' => '差旅费',
            'meal' => '餐费',
            'transport' => '交通费',
            'office' => '办公用品',
            'other' => '其他费用',
        ];
        $typeLabel = $typeMap[$expenseReport['type'] ?? ''] ?? '报销';
        $amount = number_format((float)($expenseReport['amount'] ?? 0), 2);
        
        $title = '报销审批不通过';
        $content = sprintf('您的%s申请未通过审批，金额：¥%s。', $typeLabel, $amount);
        if ($reason) {
            $content .= sprintf(' 原因：%s', $reason);
        }
        
        $this->createNotification($userId, [
            'channel' => self::CHANNEL_SYSTEM,
            'template_code' => self::TEMPLATE_REIMBURSE_REJECTED,
            'title' => $title,
            'content' => $content,
            'payload' => [
                'type' => 'reimburse_rejected',
                'reimburse_type' => $expenseReport['type'] ?? null,
                'reimburse_id' => $expenseReport['id'] ?? null,
                'amount' => $expenseReport['amount'] ?? null,
                'reason' => $reason,
            ],
        ]);
    }

    public function createNotification(int $userId, array $data): void
    {
        if ($userId <= 0) {
            return;
        }
        
        $now = date('Y-m-d H:i:s');
        $payload = null;
        if (!empty($data['payload']) && is_array($data['payload'])) {
            $payload = json_encode($data['payload'], JSON_UNESCAPED_UNICODE);
        }
        
        Db::table('notifications')->insert([
            'user_id'       => $userId,
            'channel'       => $data['channel'] ?? self::CHANNEL_SYSTEM,
            'template_code' => $data['template_code'] ?? null,
            'title'         => $data['title'],
            'content'       => $data['content'] ?? '',
            'payload'       => $payload,
            'status'        => 'sent',
            'created_at'    => $now,
        ]);
    }

    public function batchCreateNotifications(array $userIds, array $data): void
    {
        if (empty($userIds)) {
            return;
        }
        
        $now = date('Y-m-d H:i:s');
        $payload = null;
        if (!empty($data['payload']) && is_array($data['payload'])) {
            $payload = json_encode($data['payload'], JSON_UNESCAPED_UNICODE);
        }
        
        $rows = [];
        foreach ($userIds as $userId) {
            $userId = (int)$userId;
            if ($userId <= 0) {
                continue;
            }
            $rows[] = [
                'user_id'       => $userId,
                'channel'       => $data['channel'] ?? self::CHANNEL_SYSTEM,
                'template_code' => $data['template_code'] ?? null,
                'title'         => $data['title'],
                'content'       => $data['content'] ?? '',
                'payload'       => $payload,
                'status'        => 'sent',
                'created_at'    => $now,
            ];
        }
        
        if (!empty($rows)) {
            Db::table('notifications')->insertAll($rows);
        }
    }
}
