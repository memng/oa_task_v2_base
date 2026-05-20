<?php

namespace app\api\controller;

use app\common\controller\ApiController;
use app\common\service\TaskService;
use think\facade\Db;
use think\facade\Request;

class Dashboard extends ApiController
{
    public function summary()
    {
        $orderTotal = Db::table('orders')->count();
        $orderInProgress = Db::table('orders')->where('status', 'in_progress')->count();
        $orderCompleted = Db::table('orders')->where('status', 'completed')->count();

        $taskPending = Db::table('tasks')->where('status', 'pending')->count();
        $taskInProgress = Db::table('tasks')->where('status', 'in_progress')->count();
        $taskWaitingAudit = Db::table('tasks')->where('status', 'waiting_audit')->count();

        $announcements = Db::table('announcements')
            ->where('publish_status', 'published')
            ->order('published_at', 'desc')
            ->limit(5)
            ->select()
            ->toArray();

        $intentOrders = Db::table('intent_orders')
            ->whereNotIn('status', ['won', 'lost'])
            ->order('created_at', 'desc')
            ->limit(5)
            ->select()
            ->toArray();

        return $this->success([
            'orders'        => [
                'total'       => $orderTotal,
                'in_progress' => $orderInProgress,
                'completed'   => $orderCompleted,
            ],
            'tasks'         => [
                'pending'       => $taskPending,
                'in_progress'   => $taskInProgress,
                'waiting_audit' => $taskWaitingAudit,
            ],
            'announcements' => $announcements,
            'intent_orders' => $intentOrders,
        ]);
    }

    public function factoryBoard()
    {
        $orders = Db::table('order_products')
            ->alias('p')
            ->leftJoin('orders o', 'o.id = p.order_id')
            ->field([
                'p.id as product_id',
                'o.id as order_id',
                'o.pi_number',
                'o.customer_name',
                'o.status',
                'o.expected_delivery_at',
                'o.created_at',
                'p.product_name',
                'p.model',
                'p.voltage',
                'p.quantity',
            ])
            ->order('o.expected_delivery_at', 'asc')
            ->select()
            ->toArray();

        $statusMap = [
            'draft'       => '待生产',
            'pending'     => '待生产',
            'in_progress' => '生产中',
            'completed'   => '已完成',
            'cancelled'   => '已取消',
        ];

        $summary = [
            'draft'       => 0,
            'in_progress' => 0,
            'completed'   => 0,
        ];

        $items = array_map(function ($row) use ($statusMap, &$summary) {
            $status = $row['status'] ?? 'draft';
            if (!isset($statusMap[$status])) {
                $status = 'in_progress';
            }
            if (isset($summary[$status])) {
                $summary[$status] += 1;
            }
            $row['status_label'] = $statusMap[$status];
            return $row;
        }, $orders);

        return $this->success([
            'items'   => $items,
            'summary' => $summary,
        ]);
    }

    public function todos()
    {
        $user = $this->user();
        $userId = (int)$user['id'];
        $userDeptId = isset($user['dept_id']) ? (int)$user['dept_id'] : null;
        $isAdminDept = \user_belongs_to_admin_dept($user);
        $limit = (int)Request::get('limit', 10);

        $typeMap = [
            'annual' => '年假',
            'sick' => '病假',
            'personal' => '事假',
            'other' => '其他'
        ];

        $expenseTypeMap = [
            'travel' => '差旅费',
            'meal' => '餐费',
            'transport' => '交通费',
            'office' => '办公用品',
            'other' => '其他'
        ];

        $taskTypeMap = [
            'order' => '订单任务',
            'inspection' => '客户验厂',
            'temporary' => '临时任务',
            'factory_order' => '工厂任务',
            'procurement' => '采购任务',
            'shipping' => '发货任务',
            'installation' => '安装任务'
        ];

        $statusMap = [
            'pending' => '待处理',
            'in_progress' => '进行中',
            'waiting_audit' => '待审核',
            'approved' => '已通过',
            'rejected' => '已拒绝'
        ];

        $todos = [];

        $taskService = new TaskService();

        $taskQuery = Db::table('tasks')->alias('t')
            ->leftJoin('orders o', 'o.id = t.order_id')
            ->leftJoin('users au', 'au.id = t.assigned_to')
            ->leftJoin('users cu', 'cu.id = t.created_by');

        $taskQuery->where(function ($q) use ($userId, $isAdminDept) {
            if ($isAdminDept) {
                $q->where('t.need_audit', 1)
                    ->where('t.status', 'waiting_audit');
            } else {
                $q->where('t.assigned_to', $userId)
                    ->whereNotIn('t.status', ['completed', 'cancelled']);
            }
        });

        $taskCountQuery = clone $taskQuery;
        $taskCount = $taskCountQuery->count();

        $tasks = $taskQuery->field([
            't.*',
            'o.pi_number as order_pi_number',
            'o.customer_name as order_customer_name',
            'au.name as assignee_name',
            'cu.name as creator_name',
        ])
            ->order('t.due_at', 'asc')
            ->order('t.id', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();

        $tasks = $taskService->formatTaskList($tasks, $isAdminDept);

        foreach ($tasks as $task) {
            $todos[] = [
                'id' => 'task_' . $task['id'],
                'type' => 'task',
                'type_label' => $taskTypeMap[$task['type']] ?? $task['type_label'] ?? '任务',
                'title' => $task['title'],
                'desc' => $task['description'] ?? $task['requirement'] ?? '',
                'status' => $task['status'],
                'status_label' => $task['status_label'] ?? $statusMap[$task['status']] ?? '未知',
                'created_at' => $task['created_at'],
                'sort_time' => $task['due_at'] ?? $task['created_at'],
                'extra' => [
                    'task_id' => $task['id'],
                    'order_id' => $task['order_id'] ?? null,
                    'pi_no' => $task['order_pi_number'] ?? null,
                    'deadline' => $task['due_at'] ?? null
                ]
            ];
        }

        $leaveQuery = Db::table('leave_requests');

        if ($isAdminDept) {
            $leaveQuery->where('status', 'pending');
        } else {
            $leaveQuery->where('user_id', $userId)
                ->where('status', 'pending');
        }

        $leaveCount = $leaveQuery->count();

        $leaves = $leaveQuery->order('created_at', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();

        foreach ($leaves as $leave) {
            $userName = Db::table('users')->where('id', $leave['user_id'])->value('name');
            $todos[] = [
                'id' => 'leave_' . $leave['id'],
                'type' => 'leave',
                'type_label' => $typeMap[$leave['leave_type']] ?? '请假',
                'title' => $isAdminDept ? ($userName . '的请假申请') : '我的请假申请',
                'desc' => ($typeMap[$leave['leave_type']] ?? '请假') . '：' . substr($leave['start_at'], 0, 10) . ' 至 ' . substr($leave['end_at'], 0, 10),
                'status' => $leave['status'],
                'status_label' => '待审批',
                'created_at' => $leave['created_at'],
                'sort_time' => $leave['created_at'],
                'extra' => [
                    'leave_id' => $leave['id'],
                    'leave_type' => $leave['leave_type'],
                    'start_at' => $leave['start_at'],
                    'end_at' => $leave['end_at']
                ]
            ];
        }

        $reimburseQuery = Db::table('expense_reports');

        if ($isAdminDept) {
            $reimburseQuery->where('status', 'pending');
        } else {
            $reimburseQuery->where('user_id', $userId)
                ->where('status', 'pending');
        }

        $reimburseCount = $reimburseQuery->count();

        $reimburses = $reimburseQuery->order('created_at', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();

        foreach ($reimburses as $reimburse) {
            $userName = Db::table('users')->where('id', $reimburse['user_id'])->value('name');
            $todos[] = [
                'id' => 'reimburse_' . $reimburse['id'],
                'type' => 'reimburse',
                'type_label' => '报销',
                'title' => $isAdminDept ? ($userName . '的报销申请') : '我的报销申请',
                'desc' => ($expenseTypeMap[$reimburse['type']] ?? '报销') . ' ¥' . number_format($reimburse['amount'], 2),
                'status' => $reimburse['status'],
                'status_label' => '待审批',
                'created_at' => $reimburse['created_at'],
                'sort_time' => $reimburse['created_at'],
                'extra' => [
                    'reimburse_id' => $reimburse['id'],
                    'amount' => $reimburse['amount'],
                    'expense_type' => $reimburse['type']
                ]
            ];
        }

        $announcementQuery = Db::table('announcements')->alias('a')
            ->leftJoin('announcement_reads ar', 'ar.announcement_id = a.id AND ar.user_id = ' . $userId)
            ->where('a.publish_status', 'published')
            ->whereNull('ar.id');

        $announcementQuery->where(function ($q) use ($userDeptId) {
            $q->whereNotExists(function ($subQ) {
                $subQ->table('announcement_targets')
                    ->whereColumn('announcement_id', 'a.id');
            });
            if ($userDeptId) {
                $q->whereOrExists(function ($subQ) use ($userDeptId) {
                    $subQ->table('announcement_targets')
                        ->whereColumn('announcement_id', 'a.id')
                        ->where('dept_id', $userDeptId);
                });
            }
        });

        $announcementCountQuery = clone $announcementQuery;
        $announcementCount = $announcementCountQuery->count();

        $announcements = $announcementQuery->field([
            'a.*',
            'ar.read_at as read_at',
        ])
            ->order('a.published_at', 'desc')
            ->order('a.id', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();

        foreach ($announcements as $announcement) {
            $categoryMap = ['factory' => '工厂公告', 'sales' => '销售公告', 'general' => '通用公告'];
            $todos[] = [
                'id' => 'announcement_' . $announcement['id'],
                'type' => 'announcement',
                'type_label' => $categoryMap[$announcement['category']] ?? '公告',
                'title' => $announcement['title'],
                'desc' => mb_substr(strip_tags($announcement['content']), 0, 50) . '...',
                'status' => 'unread',
                'status_label' => '未读',
                'created_at' => $announcement['published_at'] ?? $announcement['created_at'],
                'sort_time' => $announcement['published_at'] ?? $announcement['created_at'],
                'extra' => [
                    'announcement_id' => $announcement['id'],
                    'category' => $announcement['category']
                ]
            ];
        }

        usort($todos, function ($a, $b) {
            return strtotime($b['sort_time']) - strtotime($a['sort_time']);
        });

        $totalCount = $taskCount + $leaveCount + $reimburseCount + $announcementCount;
        $todos = array_slice($todos, 0, $limit);

        $counts = [
            'task' => $taskCount,
            'leave' => $leaveCount,
            'reimburse' => $reimburseCount,
            'announcement' => $announcementCount,
            'total' => $totalCount
        ];

        return $this->success([
            'items' => $todos,
            'counts' => $counts
        ]);
    }
}
