<?php

namespace app\api\controller;

use app\common\controller\ApiController;
use app\common\service\TaskService;
use think\facade\Db;
use think\facade\Request;

class Task extends ApiController
{
    protected TaskService $taskService;

    protected function initialize()
    {
        parent::initialize();
        $this->taskService = new TaskService();
    }

    public function index()
    {
        $user = $this->user();
        $isAdminDept = \user_belongs_to_admin_dept($user);
        $query = Db::table('tasks')->alias('t')
            ->leftJoin('orders o', 'o.id = t.order_id')
            ->leftJoin('users au', 'au.id = t.assigned_to')
            ->leftJoin('users cu', 'cu.id = t.created_by')
            ->leftJoin('task_procurements tp', 'tp.task_id = t.id');

        if ($status = Request::get('status')) {
            $query->where('t.status', $status);
        }
        if ($type = Request::get('type')) {
            $query->where('t.type', $type);
        }
        if ($assigned = Request::get('assigned_to')) {
            $query->where('t.assigned_to', $assigned);
        }
        if ($orderId = Request::get('order_id')) {
            $query->where('t.order_id', $orderId);
        }
        if ($createdBy = Request::get('created_by', Request::get('initiator_id'))) {
            $query->where('t.created_by', $createdBy);
        }
        if ($keyword = Request::get('keyword')) {
            $query->where(function ($q) use ($keyword) {
                $q->whereLike('t.title', "%{$keyword}%")
                    ->whereOr('t.description', 'like', "%{$keyword}%")
                    ->whereOr('o.pi_number', 'like', "%{$keyword}%")
                    ->whereOr('o.customer_name', 'like', "%{$keyword}%");
            });
        }
        if (Request::has('need_audit')) {
            $query->where('t.need_audit', (int)Request::get('need_audit'));
        }
        if ($createdFrom = Request::get('created_from')) {
            $query->where('t.created_at', '>=', "{$createdFrom} 00:00:00");
        }
        if ($createdTo = Request::get('created_to')) {
            $query->where('t.created_at', '<=', "{$createdTo} 23:59:59");
        }
        if ($dueFrom = Request::get('due_from')) {
            $query->where('t.due_at', '>=', "{$dueFrom} 00:00:00");
        }
        if ($dueTo = Request::get('due_to')) {
            $query->where('t.due_at', '<=', "{$dueTo} 23:59:59");
        }
        if ($category = Request::get('category')) {
            switch ($category) {
                case 'order':
                    $query->whereNotNull('t.order_id');
                    break;
                case 'inspection':
                    $query->where('t.type', 'inspection');
                    break;
                case 'temporary':
                    $query->where('t.type', 'temporary');
                    break;
                case 'factory':
                    $query->where('t.type', 'factory_order');
                    break;
                default:
                    break;
            }
        }

        $scope = Request::get('scope');
        if ($scope === 'initiated') {
            $query->where('t.created_by', $user['id']);
        } elseif ($scope === 'assigned') {
            $query->where('t.assigned_to', $user['id']);
        } elseif ($scope === 'review') {
            $query->where('t.need_audit', 1)
                ->where('t.status', 'waiting_audit');
        }

        if (!$isAdminDept) {
            $query->where(function ($q) use ($user) {
                $q->where('t.created_by', $user['id'])
                    ->whereOr('t.assigned_to', $user['id'])
                    ->whereOr(function ($sub) use ($user) {
                        $sub->whereRaw('(o.initiator_id = ? OR o.sales_owner_id = ?)', [$user['id'], $user['id']]);
                    });
            });
        }

        $rows = $query->field([
            't.*',
            'o.pi_number as order_pi_number',
            'o.customer_name as order_customer_name',
            'au.name as assignee_name',
            'cu.name as creator_name',
            'tp.supplier_id',
            'tp.supplier_name',
            'tp.purchase_price',
            'tp.currency as procurement_currency',
            'tp.source_location',
            'tp.purchase_status',
            'tp.delivery_date',
        ])
            ->order('t.due_at', 'asc')
            ->order('t.id', 'desc')
            ->select()
            ->toArray();

        $tasks = $this->taskService->formatTaskList($rows, $isAdminDept);

        return $this->success([
            'items' => $tasks,
        ]);
    }

    public function save()
    {
        $data = $this->requestData();
        if (empty($data['title']) || empty($data['type'])) {
            $this->errorResponse('任务标题或类型不能为空');
        }

        $user = $this->user();
        $orderId = isset($data['order_id']) ? (int)$data['order_id'] : null;

        if ($orderId > 0) {
            $order = Db::table('orders')->where('id', $orderId)->find();
            if (!$order) {
                $this->errorResponse('订单不存在', 404);
            }

            if ($order['status'] !== 'in_progress') {
                $statusLabels = [
                    'draft'     => '草稿',
                    'completed' => '已完成',
                    'cancelled' => '已取消',
                ];
                $statusLabel = $statusLabels[$order['status']] ?? $order['status'];
                $this->errorResponse("仅允许进行中的订单创建任务，当前订单状态为「{$statusLabel}」", 400);
            }

            $isCreator = (int)$order['initiator_id'] === (int)$user['id'];
            $isAdmin = \user_belongs_to_admin_dept($user);
            if (!$isCreator && !$isAdmin) {
                $this->errorResponse('仅订单创建人和管理员可以创建该订单的关联任务', 403);
            }
        }

        $payload = [
            'order_id'        => $orderId ?: null,
            'order_product_id'=> $data['order_product_id'] ?? null,
            'type'            => $data['type'],
            'title'           => $data['title'],
            'description'     => $data['description'] ?? '',
            'assigned_to'     => $data['assigned_to'] ?? null,
            'start_at'        => $data['start_at'] ?? null,
            'due_at'          => $data['due_at'] ?? null,
            'need_audit'      => $data['need_audit'] ?? 0,
            'attachments'     => $data['attachments'] ?? [],
            'created_by'      => $user['id'],
            'payload'         => $data['payload'] ?? null,
        ];
        $taskId = $this->taskService->createTask($payload, $data['extra'] ?? []);
        return $this->success(['task_id' => $taskId], '任务创建成功', 201);
    }

    public function updateStatus($id)
    {
        $data = $this->requestData();
        if (empty($data['status'])) {
            $this->errorResponse('状态不能为空');
        }
        $changes = ['status' => $data['status']];
        if (!empty($data['completed_at'])) {
            $changes['completed_at'] = $data['completed_at'];
        } elseif ($data['status'] === 'completed') {
            $changes['completed_at'] = date('Y-m-d H:i:s');
        }
        if (!empty($data['due_at'])) {
            $changes['due_at'] = $data['due_at'];
        }
        if (!empty($data['start_at'])) {
            $changes['start_at'] = $data['start_at'];
        }
        if (!empty($data['comment'])) {
            Db::table('task_logs')->insert([
                'task_id'    => $id,
                'user_id'    => $this->user()['id'],
                'action'     => 'comment',
                'message'    => $data['comment'],
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $this->taskService->updateTask((int)$id, $changes, $this->user()['id']);

        if (!empty($data['attachments'])) {
            $this->taskService->appendAttachments((int)$id, $data['attachments']);
        }

        return $this->success([], '任务状态已更新');
    }

    public function updateProcurement($id)
    {
        $taskId = (int)$id;
        $user = $this->user();
        $row = Db::table('tasks')->alias('t')
            ->leftJoin('orders o', 'o.id = t.order_id')
            ->field([
                't.*',
                'o.initiator_id as order_initiator_id',
                'o.sales_owner_id as order_sales_owner_id',
            ])
            ->where('t.id', $taskId)
            ->find();
        if (!$row) {
            $this->errorResponse('任务不存在', 404);
        }
        if ($row['type'] !== 'procurement') {
            $this->errorResponse('仅采购任务支持该操作');
        }
        if (!$this->canViewTask($row, $user)) {
            $this->errorResponse('暂无权限处理该任务', 403);
        }
        $payload = $this->requestData();
        $procurementPayload = array_filter([
            'supplier_id'     => $payload['supplier_id'] ?? null,
            'supplier_name'   => $payload['supplier_name'] ?? null,
            'purchase_price'  => $payload['purchase_price'] ?? null,
            'currency'        => $payload['currency'] ?? null,
            'delivery_date'   => $payload['delivery_date'] ?? null,
            'purchase_status' => $payload['purchase_status'] ?? null,
            'source_location' => $payload['source_location'] ?? null,
        ], static fn($value) => $value !== null && $value !== '');

        Db::startTrans();
        try {
            $inventoryItemId = !empty($payload['inventory_item_id']) ? (int)$payload['inventory_item_id'] : null;
            $inventoryQuantity = !empty($payload['inventory_quantity']) ? (int)$payload['inventory_quantity'] : null;

            if ($inventoryItemId && $inventoryQuantity > 0) {
                $inventory = Db::table('inventory')->where('id', $inventoryItemId)->find();
                if (!$inventory) {
                    Db::rollback();
                    $this->errorResponse('库存不存在', 404);
                }

                if ($inventory['quantity'] < $inventoryQuantity) {
                    Db::rollback();
                    $this->errorResponse('库存不足，当前可用：' . $inventory['quantity']);
                }

                $newQuantity = $inventory['quantity'] - $inventoryQuantity;
                Db::table('inventory')->where('id', $inventoryItemId)->update([
                    'quantity' => $newQuantity,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

                Db::table('inventory_usages')->insert([
                    'inventory_id' => $inventoryItemId,
                    'task_id' => $taskId,
                    'user_id' => $user['id'],
                    'quantity' => $inventoryQuantity,
                    'remaining_quantity' => $newQuantity,
                    'note' => '采购任务扣减库存',
                    'created_at' => date('Y-m-d H:i:s'),
                ]);

                Db::table('task_logs')->insert([
                    'task_id' => $taskId,
                    'user_id' => $user['id'],
                    'action' => 'procurement',
                    'message' => json_encode([
                        'inventory_usage' => [
                            'item_id' => $inventoryItemId,
                            'product_name' => $inventory['product_name'],
                            'quantity' => $inventoryQuantity,
                            'remaining_quantity' => $newQuantity,
                        ]
                    ]),
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }

            $this->taskService->updateProcurement($taskId, $procurementPayload);

            if (!empty($payload['status'])) {
                $this->taskService->updateTask($taskId, [
                    'status' => $payload['status'],
                ], $user['id']);
            }

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->errorResponse('操作失败：' . $e->getMessage());
        }

        return $this->read($taskId);
    }

    public function assign($id)
    {
        $taskId = (int)$id;
        $user = $this->user();
        $taskRow = Db::table('tasks')->where('id', $taskId)->find();
        if (!$taskRow) {
            $this->errorResponse('任务不存在', 404);
        }
        $canAssign = (int)$taskRow['created_by'] === (int)$user['id'] || \user_belongs_to_admin_dept($user);
        if (!$canAssign) {
            $this->errorResponse('仅任务发起人或管理员可分配任务', 403);
        }
        $payload = $this->requestData();
        $assignedTo = isset($payload['assigned_to']) ? (int)$payload['assigned_to'] : 0;
        if ($assignedTo <= 0) {
            $this->errorResponse('请选择分配的负责人');
        }
        $changes = [
            'assigned_to' => $assignedTo,
        ];
        $startAt = $payload['start_at'] ?? null;
        if (!empty($startAt)) {
            $changes['start_at'] = $startAt;
        }
        if (($taskRow['status'] ?? '') === 'pending') {
            $changes['status'] = 'in_progress';
        }
        $logContext = [];
        $assigneeName = Db::table('users')->where('id', $assignedTo)->value('name');
        if ($assigneeName) {
            $logContext['assigned_to_name'] = $assigneeName;
        }
        $this->taskService->updateTask($taskId, $changes, $user['id'], $logContext);

        return $this->read($taskId);
    }

    public function read($id)
    {
        $user = $this->user();
        $row = Db::table('tasks')->alias('t')
            ->leftJoin('orders o', 'o.id = t.order_id')
            ->leftJoin('users au', 'au.id = t.assigned_to')
            ->leftJoin('users cu', 'cu.id = t.created_by')
            ->leftJoin('task_procurements tp', 'tp.task_id = t.id')
            ->field([
                't.*',
                'o.pi_number as order_pi_number',
                'o.customer_name as order_customer_name',
                'o.initiator_id as order_initiator_id',
                'o.sales_owner_id as order_sales_owner_id',
                'au.name as assignee_name',
                'cu.name as creator_name',
                'tp.supplier_id',
                'tp.supplier_name',
                'tp.purchase_price',
                'tp.currency as procurement_currency',
                'tp.source_location',
                'tp.purchase_status',
                'tp.delivery_date',
            ])
            ->where('t.id', $id)
            ->find();
        if (!$row) {
            $this->errorResponse('任务不存在', 404);
        }
        if (!$this->canViewTask($row, $user)) {
            $this->errorResponse('暂无权限查看该任务', 403);
        }
        $task = $this->taskService->formatTaskList([$row], \user_belongs_to_admin_dept($user));
        $logs = Db::table('task_logs')
            ->where('task_id', $id)
            ->order('id', 'desc')
            ->select()
            ->toArray();
        $attachments = Db::table('task_attachments')->alias('ta')
            ->leftJoin('media_assets ma', 'ma.id = ta.media_id')
            ->field([
                'ta.id',
                'ta.media_id',
                'ta.created_at',
                'ma.file_name',
                'ma.file_type',
                'ma.storage_path',
            ])
            ->where('ta.task_id', $id)
            ->order('ta.id', 'desc')
            ->select()
            ->toArray();
        $attachmentList = array_map(static function ($row) {
            $url = null;
            if (!empty($row['storage_path'])) {
                $path = ltrim($row['storage_path'], '/');
                $url = '/storage/' . $path;
            }
            return [
                'id'         => (int)$row['id'],
                'media_id'   => (int)$row['media_id'],
                'file_name'  => $row['file_name'],
                'file_type'  => $row['file_type'],
                'url'        => $url,
                'created_at' => $row['created_at'],
            ];
        }, $attachments);

        return $this->success([
            'task' => $task[0],
            'logs' => $logs,
            'attachments' => $attachmentList,
        ]);
    }

    protected function canViewTask(array $taskRow, array $user): bool
    {
        if (\user_belongs_to_admin_dept($user)) {
            return true;
        }
        $userId = (int)$user['id'];
        if ((int)$taskRow['created_by'] === $userId || (int)($taskRow['assigned_to'] ?? 0) === $userId) {
            return true;
        }
        if (!empty($taskRow['order_id'])) {
            if ((int)($taskRow['order_initiator_id'] ?? 0) === $userId || (int)($taskRow['order_sales_owner_id'] ?? 0) === $userId) {
                return true;
            }
        }
        return false;
    }
}
