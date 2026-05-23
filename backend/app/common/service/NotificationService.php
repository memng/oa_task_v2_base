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

    const BUSINESS_TYPE_TASK = 'task';
    const BUSINESS_TYPE_APPROVAL = 'approval';
    const BUSINESS_TYPE_SYSTEM = 'system';

    const TEMPLATE_ORDER_CREATED = 'order_created';
    const TEMPLATE_TASK_ASSIGNED = 'task_assigned';
    const TEMPLATE_TASK_URGED = 'task_urged';
    const TEMPLATE_TASK_UPDATED = 'task_updated';
    const TEMPLATE_TASK_FOLLOWED = 'task_followed';
    const TEMPLATE_LEAVE_APPROVED = 'leave_approved';
    const TEMPLATE_REIMBURSE_APPROVED = 'reimburse_approved';
    const TEMPLATE_LEAVE_REJECTED = 'leave_rejected';
    const TEMPLATE_REIMBURSE_REJECTED = 'reimburse_rejected';

    const TASK_TEMPLATE_CODES = [
        self::TEMPLATE_TASK_ASSIGNED,
        self::TEMPLATE_TASK_URGED,
        self::TEMPLATE_ORDER_CREATED,
        self::TEMPLATE_TASK_UPDATED,
        self::TEMPLATE_TASK_FOLLOWED,
    ];

    const APPROVAL_TEMPLATE_CODES = [
        self::TEMPLATE_LEAVE_APPROVED,
        self::TEMPLATE_LEAVE_REJECTED,
        self::TEMPLATE_REIMBURSE_APPROVED,
        self::TEMPLATE_REIMBURSE_REJECTED,
    ];

    public static function getBusinessType(?string $templateCode, ?string $payloadType = null): string
    {
        $code = $templateCode ?? $payloadType ?? '';

        if (in_array($code, self::TASK_TEMPLATE_CODES, true)) {
            return self::BUSINESS_TYPE_TASK;
        }
        if (strpos($code, 'task') !== false) {
            return self::BUSINESS_TYPE_TASK;
        }

        if (in_array($code, self::APPROVAL_TEMPLATE_CODES, true)) {
            return self::BUSINESS_TYPE_APPROVAL;
        }
        if (strpos($code, 'leave') !== false || strpos($code, 'reimburse') !== false) {
            return self::BUSINESS_TYPE_APPROVAL;
        }

        return self::BUSINESS_TYPE_SYSTEM;
    }

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

    public function sendTaskUpdated(int $userId, array $task, array $changes = [], ?int $operatorId = null): void
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
        $title = '任务变更通知';
        $summary = $this->summarizeTaskChanges($changes);
        $content = sprintf('您关注的%s「%s」已更新。%s', $typeLabel, $task['title'] ?? '', $summary);

        $this->createNotification($userId, [
            'channel' => self::CHANNEL_SYSTEM,
            'template_code' => self::TEMPLATE_TASK_UPDATED,
            'title' => $title,
            'content' => $content,
            'payload' => [
                'type' => 'task_updated',
                'task_id' => $task['id'] ?? null,
                'task_title' => $task['title'] ?? null,
                'task_type' => $task['type'] ?? null,
                'order_id' => $task['order_id'] ?? null,
                'operator_id' => $operatorId,
                'changes' => $changes,
                'due_at' => $task['due_at'] ?? null,
            ],
        ]);
    }

    public function batchSendTaskUpdated(array $userIds, array $task, array $changes = [], ?int $operatorId = null): void
    {
        if (empty($userIds)) {
            return;
        }
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
        $summary = $this->summarizeTaskChanges($changes);
        $content = sprintf('您关注的%s「%s」已更新。%s', $typeLabel, $task['title'] ?? '', $summary);
        $this->batchCreateNotifications(array_values(array_unique(array_map('intval', $userIds))), [
            'channel' => self::CHANNEL_SYSTEM,
            'template_code' => self::TEMPLATE_TASK_UPDATED,
            'title' => '任务变更通知',
            'content' => $content,
            'payload' => [
                'type' => 'task_updated',
                'task_id' => $task['id'] ?? null,
                'task_title' => $task['title'] ?? null,
                'task_type' => $task['type'] ?? null,
                'order_id' => $task['order_id'] ?? null,
                'operator_id' => $operatorId,
                'changes' => $changes,
                'due_at' => $task['due_at'] ?? null,
            ],
        ]);
    }

    protected function summarizeTaskChanges(array $changes): string
    {
        if (empty($changes)) {
            return '';
        }
        $labels = [
            'status' => '状态',
            'assigned_to' => '负责人',
            'due_at' => '截止时间',
            'start_at' => '开始时间',
            'priority' => '优先级',
            'description' => '说明',
            'completed_at' => '完成时间',
            'need_audit' => '需要审核',
        ];
        $parts = [];
        foreach ($changes as $key => $value) {
            if ($key === 'updated_at') {
                continue;
            }
            $label = $labels[$key] ?? $key;
            $parts[] = $label;
        }
        if (!$parts) {
            return '';
        }
        return '变更字段：' . implode('、', $parts) . '。';
    }

    public function sendTaskFollowed(int $userId, int $taskId, string $taskTitle, ?int $operatorId = null): void
    {
        $title = '任务已关注';
        $content = sprintf('您已成功关注任务「%s」，后续该任务的变更会通知到您。', $taskTitle);
        $this->createNotification($userId, [
            'channel' => self::CHANNEL_SYSTEM,
            'template_code' => self::TEMPLATE_TASK_FOLLOWED,
            'title' => $title,
            'content' => $content,
            'payload' => [
                'type' => 'task_followed',
                'task_id' => $taskId,
                'task_title' => $taskTitle,
                'operator_id' => $operatorId,
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

    public function sendTaskUrged(int $userId, array $task, ?int $urgedById = null): void
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
        $title = '任务催办提醒';
        $content = sprintf('您负责的%s「%s」已被催办，请及时处理。', $typeLabel, $task['title'] ?? '');
        
        $this->createNotification($userId, [
            'channel' => self::CHANNEL_SYSTEM,
            'template_code' => self::TEMPLATE_TASK_URGED,
            'title' => $title,
            'content' => $content,
            'payload' => [
                'type' => 'task_urged',
                'task_id' => $task['id'] ?? null,
                'task_title' => $task['title'] ?? null,
                'task_type' => $task['type'] ?? null,
                'order_id' => $task['order_id'] ?? null,
                'urged_by_id' => $urgedById,
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
