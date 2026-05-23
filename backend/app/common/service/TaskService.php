<?php

namespace app\common\service;

use think\facade\Db;

class TaskService
{
    protected NotificationService $notificationService;

    public function __construct()
    {
        $this->notificationService = new NotificationService();
    }

    public function createTask(array $data, array $extra = []): int
    {
        $now = date('Y-m-d H:i:s');
        $taskId = Db::table('tasks')->insertGetId([
            'order_id'        => $data['order_id'] ?? null,
            'order_product_id'=> $data['order_product_id'] ?? null,
            'parent_task_id'  => $data['parent_task_id'] ?? null,
            'type'            => $data['type'],
            'title'           => $data['title'],
            'description'     => $data['description'] ?? '',
            'assigned_to'     => $data['assigned_to'] ?? null,
            'created_by'      => $data['created_by'],
            'start_at'        => $data['start_at'] ?? null,
            'due_at'          => $data['due_at'] ?? null,
            'status'          => $data['status'] ?? 'pending',
            'need_audit'      => $data['need_audit'] ?? 0,
            'priority'        => $data['priority'] ?? 3,
            'payload'         => isset($data['payload']) ? json_encode($data['payload'], JSON_UNESCAPED_UNICODE) : null,
            'created_at'      => $data['created_at'] ?? $now,
            'updated_at'      => $data['updated_at'] ?? $now,
        ]);

        if (!empty($extra)) {
            $this->syncTaskExtensions($taskId, $data['type'], $extra);
        }

        if (!empty($data['attachments'])) {
            $this->syncAttachments($taskId, $data['attachments']);
        }

        $this->addLog($taskId, $data['created_by'], 'created', $data['title']);
        if (!empty($data['order_id'])) {
            $this->refreshOrderStatus((int)$data['order_id']);
        }

        $task = Db::table('tasks')->where('id', $taskId)->find();
        if ($task) {
            $assignedTo = (int)($task['assigned_to'] ?? 0);
            $createdBy = (int)$task['created_by'];
            if ($assignedTo > 0) {
                $taskData = [
                    'id' => $taskId,
                    'type' => $task['type'],
                    'title' => $task['title'],
                    'order_id' => $task['order_id'] ?? null,
                    'due_at' => $task['due_at'] ?? null,
                ];
                $this->notificationService->sendTaskAssigned($assignedTo, $taskData, $createdBy);
            }
        }

        return $taskId;
    }

    public function updateTask(int $taskId, array $changes, int $operatorId, array $logContext = []): void
    {
        $existingTask = Db::table('tasks')->where('id', $taskId)->find();
        if (!$existingTask) {
            return;
        }

        $update = $changes;
        $update['updated_at'] = date('Y-m-d H:i:s');
        Db::table('tasks')->where('id', $taskId)->update($update);
        $logPayload = array_merge($changes, $logContext);
        if (!$logPayload) {
            $logPayload = $changes;
        }
        $this->addLog($taskId, $operatorId, 'updated', json_encode($logPayload, JSON_UNESCAPED_UNICODE));

        $newAssignedTo = isset($changes['assigned_to']) ? (int)$changes['assigned_to'] : 0;
        $oldAssignedTo = (int)($existingTask['assigned_to'] ?? 0);
        if ($newAssignedTo > 0 && $newAssignedTo !== $oldAssignedTo) {
            $task = [
                'id' => $taskId,
                'type' => $existingTask['type'],
                'title' => $existingTask['title'],
                'order_id' => $existingTask['order_id'] ?? null,
                'due_at' => $existingTask['due_at'] ?? null,
            ];
            $this->notificationService->sendTaskAssigned($newAssignedTo, $task, $operatorId);
        }

        $orderId = $existingTask['order_id'] ?? null;
        if ($orderId) {
            $this->refreshOrderStatus((int)$orderId);
        }

        $notifyKeys = ['status', 'assigned_to', 'due_at', 'start_at', 'priority', 'completed_at'];
        $hasNotifyChange = false;
        foreach ($notifyKeys as $key) {
            if (array_key_exists($key, $changes)) {
                $hasNotifyChange = true;
                break;
            }
        }
        if ($hasNotifyChange) {
            $this->notifyFollowersOnUpdate($taskId, $existingTask, $changes, $operatorId);
        }
    }

    protected function syncTaskExtensions(int $taskId, string $type, array $extra): void
    {
        switch ($type) {
            case 'procurement':
                Db::table('task_procurements')->insert([
                    'task_id'         => $taskId,
                    'supplier_id'     => $extra['supplier_id'] ?? null,
                    'supplier_name'   => $extra['supplier_name'] ?? null,
                    'purchase_status' => $extra['purchase_status'] ?? 'not_ordered',
                    'purchase_date'   => $extra['purchase_date'] ?? null,
                    'delivery_date'   => $extra['delivery_date'] ?? null,
                    'source_location' => $extra['source_location'] ?? null,
                    'purchase_price'  => $extra['purchase_price'] ?? null,
                    'currency'        => $extra['currency'] ?? 'CNY',
                    'is_confidential' => $extra['is_confidential'] ?? 1,
                ]);
                break;
            case 'nameplate':
                Db::table('task_nameplates')->insert([
                    'task_id'          => $taskId,
                    'template_version' => $extra['template_version'] ?? null,
                    'requirement'      => $extra['requirement'] ?? null,
                ]);
                break;
            case 'machine_data':
                Db::table('task_machine_data')->insert([
                    'task_id'     => $taskId,
                    'requirement' => $extra['requirement'] ?? null,
                ]);
                break;
            case 'acceptance':
                Db::table('task_acceptances')->insert([
                    'task_id'     => $taskId,
                    'requirement' => $extra['requirement'] ?? null,
                ]);
                break;
            case 'packaging':
                Db::table('task_packaging')->insert([
                    'task_id'      => $taskId,
                    'requirement'  => $extra['requirement'] ?? null,
                    'reviewer_id'  => $extra['reviewer_id'] ?? null,
                ]);
                break;
            case 'shipment':
                Db::table('task_shipments')->insert([
                    'task_id'      => $taskId,
                    'requirement'  => $extra['requirement'] ?? null,
                    'container_no' => $extra['container_no'] ?? null,
                    'seal_no'      => $extra['seal_no'] ?? null,
                ]);
                break;
            default:
                break;
        }
    }

    protected function syncAttachments(int $taskId, array $attachments): void
    {
        $rows = [];
        foreach ($attachments as $attachmentId) {
            $rows[] = [
                'task_id'    => $taskId,
                'media_id'   => $attachmentId,
                'created_at' => date('Y-m-d H:i:s'),
            ];
        }
        if ($rows) {
            Db::table('task_attachments')->insertAll($rows);
        }
    }

    public function appendAttachments(int $taskId, array $attachments): void
    {
        if (!$attachments) {
            return;
        }
        $this->syncAttachments($taskId, $attachments);
    }

    protected function addLog(int $taskId, int $userId, string $action, string $message = ''): void
    {
        Db::table('task_logs')->insert([
            'task_id'    => $taskId,
            'user_id'    => $userId,
            'action'     => $action,
            'message'    => $message,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function updateProcurement(int $taskId, array $payload, ?int $operatorId = null): void
    {
        $record = Db::table('task_procurements')->where('task_id', $taskId)->find();
        $update = [];
        $selectedSupplier = null;
        if (array_key_exists('supplier_id', $payload)) {
            $supplierId = $payload['supplier_id'] ? (int)$payload['supplier_id'] : null;
            $update['supplier_id'] = $supplierId;
            if ($supplierId) {
                $selectedSupplier = Db::table('suppliers')->where('id', $supplierId)->find();
                $update['supplier_name'] = $payload['supplier_name'] ?? ($selectedSupplier['name'] ?? null);
            } else {
                $update['supplier_name'] = $payload['supplier_name'] ?? null;
            }
        }
        if (array_key_exists('purchase_price', $payload)) {
            $price = $payload['purchase_price'];
            if ($price === '' || $price === null) {
                $update['purchase_price'] = null;
            } elseif (!is_numeric($price)) {
                throw new \InvalidArgumentException('采购价格格式不正确');
            } else {
                $update['purchase_price'] = round((float)$price, 2);
            }
        }
        if (array_key_exists('currency', $payload)) {
            $update['currency'] = $payload['currency'] ?: 'CNY';
        }
        if (array_key_exists('delivery_date', $payload)) {
            $update['delivery_date'] = $payload['delivery_date'] ?: null;
        }
        if (array_key_exists('purchase_status', $payload)) {
            $status = $payload['purchase_status'] ?: 'not_ordered';
            $allowed = ['not_ordered', 'ordered', 'arrived'];
            if (!in_array($status, $allowed, true)) {
                throw new \InvalidArgumentException('采购状态不正确');
            }
            $update['purchase_status'] = $status;
        }
        if (array_key_exists('source_location', $payload)) {
            $update['source_location'] = $payload['source_location'] ?: null;
        }
        if (empty($record)) {
            $update['task_id'] = $taskId;
            $update = array_merge([
                'supplier_id'     => null,
                'supplier_name'   => null,
                'purchase_status' => 'not_ordered',
                'currency'        => 'CNY',
                'is_confidential' => 1,
            ], $update);
            Db::table('task_procurements')->insert($update);
        } elseif (!empty($update)) {
            Db::table('task_procurements')->where('task_id', $taskId)->update($update);
        }

        if (!$selectedSupplier) {
            $supplierId = Db::table('task_procurements')->where('task_id', $taskId)->value('supplier_id');
            if ($supplierId) {
                $selectedSupplier = Db::table('suppliers')->where('id', $supplierId)->find();
            }
        }
        if ($selectedSupplier && (int)($selectedSupplier['is_internal'] ?? 0) === 1 && !empty($selectedSupplier['factory_owner_id'])) {
            $this->ensureFactoryOrderTask($taskId, (int)$selectedSupplier['factory_owner_id']);
        }

        if (!empty($update) && $operatorId !== null) {
            $task = Db::table('tasks')->where('id', $taskId)->find();
            if ($task) {
                $changeSummary = ['procurement_updated' => array_keys($update)];
                $this->notifyFollowersOnUpdate($taskId, $task, $changeSummary, $operatorId);
            }
        }
    }

    protected function refreshOrderStatus(?int $orderId): void
    {
        if (empty($orderId)) {
            return;
        }
        $unfinished = Db::table('tasks')
            ->where('order_id', $orderId)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->count();
        $newStatus = $unfinished > 0 ? 'in_progress' : 'completed';
        Db::table('orders')
            ->where('id', $orderId)
            ->update([
                'status'     => $newStatus,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    protected function ensureFactoryOrderTask(int $procurementTaskId, int $factoryOwnerId): void
    {
        if ($factoryOwnerId <= 0) {
            return;
        }
        $existing = Db::table('tasks')
            ->where('parent_task_id', $procurementTaskId)
            ->where('type', 'factory_order')
            ->find();
        if ($existing) {
            if ((int)($existing['assigned_to'] ?? 0) !== $factoryOwnerId) {
                Db::table('tasks')
                    ->where('id', $existing['id'])
                    ->update([
                        'assigned_to' => $factoryOwnerId,
                        'updated_at'  => date('Y-m-d H:i:s'),
                    ]);
            }
            return;
        }
        $procTask = Db::table('tasks')->where('id', $procurementTaskId)->find();
        if (!$procTask) {
            return;
        }
        $productName = null;
        if (!empty($procTask['order_product_id'])) {
            $productName = Db::table('order_products')->where('id', $procTask['order_product_id'])->value('product_name');
        }
        $title = '工厂订单';
        if ($productName) {
            $title .= '-' . $productName;
        }
        $this->createTask([
            'order_id'        => $procTask['order_id'],
            'order_product_id'=> $procTask['order_product_id'],
            'parent_task_id'  => $procurementTaskId,
            'type'            => 'factory_order',
            'title'           => $title,
            'description'     => '系统根据供应商信息自动创建，请跟进生产排期。',
            'assigned_to'     => $factoryOwnerId,
            'created_by'      => $procTask['created_by'],
            'status'          => 'pending',
            'payload'         => [
                'source'               => 'auto_factory_task',
                'procurement_task_id'  => $procurementTaskId,
                'supplier_type'        => 'internal',
            ],
        ]);
    }

    public function formatTaskList(array $rows, bool $canSeeProcurement = false): array
    {
        $statusMap = [
            'pending'       => '待开始',
            'in_progress'   => '进行中',
            'waiting_audit' => '待审核',
            'rejected'      => '已驳回',
            'completed'     => '已完成',
            'cancelled'     => '已取消',
        ];
        $typeMap = [
            'procurement'   => '采购任务',
            'nameplate'     => '铭牌制作',
            'machine_data'  => '机器数据',
            'acceptance'    => '机器验收',
            'packaging'     => '打包唛头',
            'shipment'      => '装柜发货',
            'inspection'    => '客户验厂',
            'temporary'     => '临时任务',
            'factory_order' => '工厂订单',
            'fee'           => '费用',
            'document'      => '资料',
            'announcement'  => '公告',
            'other'         => '其他任务',
        ];

        return array_map(function ($row) use ($statusMap, $typeMap, $canSeeProcurement) {
            $payload = [];
            if (!empty($row['payload'])) {
                $decoded = json_decode($row['payload'], true);
                $payload = is_array($decoded) ? $decoded : [];
            }
            $orderId = isset($row['order_id']) ? (int)$row['order_id'] : null;
            $orderProductId = isset($row['order_product_id']) ? (int)$row['order_product_id'] : null;
            $parentTaskId = isset($row['parent_task_id']) ? (int)$row['parent_task_id'] : null;
            $task = [
                'id'                => (int)$row['id'],
                'order_id'          => $orderId,
                'order_product_id'  => $orderProductId,
                'parent_task_id'    => $parentTaskId,
                'type'              => $row['type'],
                'title'             => $row['title'],
                'description'       => $row['description'],
                'assigned_to'       => $row['assigned_to'],
                'assignee_name'     => $row['assignee_name'] ?? null,
                'created_by'        => $row['created_by'],
                'creator_name'      => $row['creator_name'] ?? null,
                'start_at'          => $row['start_at'],
                'due_at'            => $row['due_at'],
                'completed_at'      => $row['completed_at'],
                'status'            => $row['status'],
                'need_audit'        => (int)($row['need_audit'] ?? 0),
                'priority'          => (int)($row['priority'] ?? 3),
                'payload'           => $payload,
                'status_label'      => $statusMap[$row['status']] ?? $row['status'],
                'type_label'        => $typeMap[$row['type']] ?? $row['type'],
                'pi_number'         => $row['order_pi_number'] ?? null,
                'customer_name'     => $row['order_customer_name'] ?? null,
                'created_at'        => $row['created_at'],
                'updated_at'        => $row['updated_at'],
                'order'             => $orderId ? [
                    'id'             => $orderId,
                    'pi_number'      => $row['order_pi_number'] ?? null,
                    'customer_name'  => $row['order_customer_name'] ?? null,
                ] : null,
            ];
            if (!empty($row['due_at'])) {
                $task['deadline'] = $row['due_at'];
            }
            $hasProcurement = in_array($row['type'], ['procurement'], true);
            if ($hasProcurement) {
                $procurement = [
                    'supplier_id'     => $row['supplier_id'] ?? null,
                    'supplier_name'   => $row['supplier_name'] ?? null,
                    'purchase_price'  => $row['purchase_price'] ?? null,
                    'currency'        => $row['procurement_currency'] ?? null,
                    'source_location' => $row['source_location'] ?? null,
                    'purchase_status' => $row['purchase_status'] ?? null,
                    'delivery_date'   => $row['delivery_date'] ?? null,
                ];
                $hasSensitive = ($procurement['supplier_name'] ?? null) || ($procurement['purchase_price'] !== null);
                if ($canSeeProcurement) {
                    $task['procurement'] = $procurement;
                    $task['procurement_hidden'] = false;
                } else {
                    $task['procurement_hidden'] = (bool)$hasSensitive;
                }
            }
            return $task;
        }, $rows);
    }

    public function urgeTask(int $taskId, int $operatorId): array
    {
        $task = Db::table('tasks')->where('id', $taskId)->find();
        if (!$task) {
            return ['success' => false, 'message' => '任务不存在'];
        }

        if (($task['status'] ?? '') === 'completed' || ($task['status'] ?? '') === 'cancelled') {
            return ['success' => false, 'message' => '已完成或已取消的任务无法催办'];
        }

        $assignedTo = (int)($task['assigned_to'] ?? 0);
        if ($assignedTo <= 0) {
            return ['success' => false, 'message' => '该任务尚未分配负责人，无法催办'];
        }

        $taskData = [
            'id' => $taskId,
            'type' => $task['type'],
            'title' => $task['title'],
            'order_id' => $task['order_id'] ?? null,
            'due_at' => $task['due_at'] ?? null,
        ];

        $this->notificationService->sendTaskUrged($assignedTo, $taskData, $operatorId);
        $this->addLog($taskId, $operatorId, 'urged', $task['title']);

        $this->notifyFollowersOnUpdate($taskId, $task, ['status' => 'urged'], $operatorId);

        return ['success' => true, 'message' => '催办成功'];
    }

    public function notifyFollowersOnUpdate(int $taskId, array $task, array $changes, ?int $operatorId = null): void
    {
        try {
            $followerIds = Db::table('task_followers')
                ->where('task_id', $taskId)
                ->column('user_id');
            if (empty($followerIds)) {
                return;
            }
            $excluded = [];
            if ($operatorId) {
                $excluded[] = (int)$operatorId;
            }
            $assigneeId = (int)($task['assigned_to'] ?? 0);
            if ($assigneeId > 0) {
                $excluded[] = $assigneeId;
            }
            $excluded = array_unique(array_filter(array_map('intval', $excluded)));
            $targets = [];
            foreach ($followerIds as $uid) {
                $uid = (int)$uid;
                if ($uid <= 0) {
                    continue;
                }
                if (!empty($excluded) && in_array($uid, $excluded, true)) {
                    continue;
                }
                $targets[] = $uid;
            }
            if (empty($targets)) {
                return;
            }
            $taskData = [
                'id' => $taskId,
                'type' => $task['type'] ?? null,
                'title' => $task['title'] ?? null,
                'order_id' => $task['order_id'] ?? null,
                'due_at' => $task['due_at'] ?? null,
            ];
            $this->notificationService->batchSendTaskUpdated($targets, $taskData, $changes, $operatorId);
        } catch (\Throwable $e) {
            error_log('notifyFollowersOnUpdate failed for task #' . $taskId . ': ' . $e->getMessage());
        }
    }

    public function notifyFollowed(int $userId, int $taskId, string $taskTitle): void
    {
        try {
            $this->notificationService->sendTaskFollowed($userId, $taskId, $taskTitle, $userId);
        } catch (\Throwable $e) {
            error_log('notifyFollowed failed for user #' . $userId . ': ' . $e->getMessage());
        }
    }

    public function isTaskFollowed(int $taskId, int $userId): bool
    {
        if ($taskId <= 0 || $userId <= 0) {
            return false;
        }
        return (bool)Db::table('task_followers')
            ->where('task_id', $taskId)
            ->where('user_id', $userId)
            ->find();
    }

    public function copyTask(int $sourceTaskId, int $operatorId): array
    {
        $source = Db::table('tasks')->where('id', $sourceTaskId)->find();
        if (!$source) {
            return ['success' => false, 'message' => '源任务不存在'];
        }

        $now = date('Y-m-d H:i:s');
        $baseTitle = preg_replace('/\s*\(副本\)\s*$/', '', $source['title']);
        $copyTitle = $baseTitle . ' (副本)';

        $newTaskId = 0;
        $copiedFiles = [];

        Db::startTrans();
        try {
            $newTaskId = Db::table('tasks')->insertGetId([
                'order_id'         => $source['order_id'],
                'order_product_id' => $source['order_product_id'],
                'parent_task_id'   => $source['parent_task_id'],
                'type'             => $source['type'],
                'title'            => $copyTitle,
                'description'      => $source['description'],
                'assigned_to'      => $source['assigned_to'],
                'created_by'       => $operatorId,
                'start_at'         => null,
                'due_at'           => $source['due_at'],
                'status'           => 'pending',
                'need_audit'       => $source['need_audit'],
                'priority'         => $source['priority'],
                'payload'          => $source['payload'],
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);

            $this->copyTaskExtensions($sourceTaskId, $newTaskId, $source['type']);

            $attachmentRows = Db::table('task_attachments')
                ->where('task_id', $sourceTaskId)
                ->select()
                ->toArray();
            if ($attachmentRows) {
                $mediaIds = array_column($attachmentRows, 'media_id');
                $mediaRows = Db::table('media_assets')
                    ->whereIn('id', $mediaIds)
                    ->select()
                    ->toArray();
                $mediaMap = [];
                foreach ($mediaRows as $mr) {
                    $mediaMap[$mr['id']] = $mr;
                }

                $newRows = [];
                foreach ($attachmentRows as $row) {
                    $sourceMedia = $mediaMap[$row['media_id']] ?? null;
                    $newMediaId = null;
                    if ($sourceMedia) {
                        $copyResult = $this->copyMediaAsset($sourceMedia, $operatorId);
                        $newMediaId = $copyResult['media_id'];
                        if (!empty($copyResult['copied_path'])) {
                            $copiedFiles[] = $copyResult['copied_path'];
                        }
                    } else {
                        $newMediaId = $row['media_id'];
                    }
                    $newRows[] = [
                        'task_id'    => $newTaskId,
                        'media_id'   => $newMediaId,
                        'created_at' => $now,
                    ];
                }
                Db::table('task_attachments')->insertAll($newRows);
            }

            $this->addLog($newTaskId, $operatorId, 'created', "从任务 #{$sourceTaskId} 复制：{$copyTitle}");

            if (!empty($source['order_id'])) {
                $this->refreshOrderStatus((int)$source['order_id']);
            }

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            foreach ($copiedFiles as $path) {
                try {
                    \think\facade\Filesystem::disk('public')->delete($path);
                } catch (\Exception $delErr) {
                    error_log('Failed to clean up copied file during task copy rollback: ' . $path . ' - ' . $delErr->getMessage());
                }
            }
            return ['success' => false, 'message' => '任务复制失败：' . $e->getMessage()];
        }

        $newTask = Db::table('tasks')->where('id', $newTaskId)->find();
        if ($newTask) {
            $assignedTo = (int)($newTask['assigned_to'] ?? 0);
            if ($assignedTo > 0) {
                $taskData = [
                    'id' => $newTaskId,
                    'type' => $newTask['type'],
                    'title' => $copyTitle,
                    'order_id' => $newTask['order_id'] ?? null,
                    'due_at' => $newTask['due_at'] ?? null,
                ];
                try {
                    $this->notificationService->sendTaskAssigned($assignedTo, $taskData, $operatorId);
                } catch (\Exception $e) {
                    error_log('Task copy notification failed for task #' . $newTaskId . ': ' . $e->getMessage());
                }
            }
        }

        return ['success' => true, 'message' => '任务复制成功', 'task_id' => $newTaskId];
    }

    protected function copyMediaAsset(array $sourceMedia, int $operatorId): array
    {
        $sourcePath = $sourceMedia['storage_path'];
        $extension = pathinfo($sourcePath, PATHINFO_EXTENSION);
        $dir = pathinfo($sourcePath, PATHINFO_DIRNAME);
        $newFilename = uniqid('copy_') . ($extension ? '.' . $extension : '');
        $newPath = $dir . '/' . $newFilename;

        $copied = \think\facade\Filesystem::disk('public')->copy($sourcePath, $newPath);
        if (!$copied) {
            throw new \RuntimeException('附件文件复制失败：' . $sourcePath);
        }

        $newMediaId = Db::table('media_assets')->insertGetId([
            'file_name'   => $sourceMedia['file_name'],
            'mime_type'   => $sourceMedia['mime_type'],
            'file_type'   => $sourceMedia['file_type'],
            'storage_path'=> $newPath,
            'file_size'   => $sourceMedia['file_size'],
            'uploaded_by' => $operatorId,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        return ['media_id' => $newMediaId, 'copied_path' => $newPath];
    }

    protected function copyTaskExtensions(int $sourceTaskId, int $newTaskId, string $type): void
    {
        switch ($type) {
            case 'procurement':
                $record = Db::table('task_procurements')->where('task_id', $sourceTaskId)->find();
                if ($record) {
                    Db::table('task_procurements')->insert([
                        'task_id'         => $newTaskId,
                        'supplier_id'     => $record['supplier_id'],
                        'supplier_name'   => $record['supplier_name'],
                        'purchase_status' => 'not_ordered',
                        'purchase_date'   => null,
                        'delivery_date'   => $record['delivery_date'],
                        'source_location' => $record['source_location'],
                        'purchase_price'  => $record['purchase_price'],
                        'currency'        => $record['currency'],
                        'is_confidential' => $record['is_confidential'],
                    ]);
                }
                break;
            case 'nameplate':
                $record = Db::table('task_nameplates')->where('task_id', $sourceTaskId)->find();
                if ($record) {
                    Db::table('task_nameplates')->insert([
                        'task_id'          => $newTaskId,
                        'template_version' => $record['template_version'],
                        'requirement'      => $record['requirement'],
                    ]);
                }
                break;
            case 'machine_data':
                $record = Db::table('task_machine_data')->where('task_id', $sourceTaskId)->find();
                if ($record) {
                    Db::table('task_machine_data')->insert([
                        'task_id'     => $newTaskId,
                        'requirement' => $record['requirement'],
                    ]);
                }
                break;
            case 'acceptance':
                $record = Db::table('task_acceptances')->where('task_id', $sourceTaskId)->find();
                if ($record) {
                    Db::table('task_acceptances')->insert([
                        'task_id'     => $newTaskId,
                        'requirement' => $record['requirement'],
                    ]);
                }
                break;
            case 'packaging':
                $record = Db::table('task_packaging')->where('task_id', $sourceTaskId)->find();
                if ($record) {
                    Db::table('task_packaging')->insert([
                        'task_id'      => $newTaskId,
                        'requirement'  => $record['requirement'],
                        'reviewer_id'  => $record['reviewer_id'],
                    ]);
                }
                break;
            case 'shipment':
                $record = Db::table('task_shipments')->where('task_id', $sourceTaskId)->find();
                if ($record) {
                    Db::table('task_shipments')->insert([
                        'task_id'      => $newTaskId,
                        'requirement'  => $record['requirement'],
                        'container_no' => null,
                        'seal_no'      => null,
                    ]);
                }
                break;
            default:
                break;
        }
    }
}
