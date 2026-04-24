<?php

namespace app\api\controller;

use app\common\controller\ApiController;
use think\facade\Db;

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
}
