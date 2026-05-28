<?php

namespace app\api\service;

use app\common\service\TaskService;
use app\common\service\NotificationService;
use think\facade\Db;

class OrderService
{
    public const ORDER_STAGES = [
        'procurement'  => ['label' => '采购', 'order' => 1, 'task_types' => ['procurement', 'factory_order']],
        'nameplate'    => ['label' => '铭牌制作', 'order' => 2, 'task_types' => ['nameplate']],
        'machine_data' => ['label' => '机器数据', 'order' => 3, 'task_types' => ['machine_data']],
        'acceptance'   => ['label' => '机器验收', 'order' => 4, 'task_types' => ['acceptance']],
        'packaging'    => ['label' => '打包唛头', 'order' => 5, 'task_types' => ['packaging']],
        'shipment'     => ['label' => '装柜发货', 'order' => 6, 'task_types' => ['shipment']],
    ];

    public const STAGE_ORDER = ['procurement', 'nameplate', 'machine_data', 'acceptance', 'packaging', 'shipment'];

    protected TaskService $taskService;
    protected NotificationService $notificationService;

    public static function getStages(): array
    {
        $result = [];
        foreach (self::ORDER_STAGES as $key => $stage) {
            $result[] = [
                'value'      => $key,
                'label'      => $stage['label'],
                'order'      => $stage['order'],
                'task_types' => $stage['task_types'],
            ];
        }
        return $result;
    }

    public function detectCurrentStage(int $orderId): ?string
    {
        $result = $this->resolveStageStatus($orderId);
        return $result['current_stage'];
    }

    public function resolveStageStatus(int $orderId): array
    {
        $tasks = Db::table('tasks')
            ->where('order_id', $orderId)
            ->whereNotIn('status', ['cancelled'])
            ->select()
            ->toArray();

        $hasAnyTask = !empty($tasks);
        $now = date('Y-m-d H:i:s');
        $stageDetails = [];
        $completedStages = 0;
        $effectiveCompletedStages = 0;
        $currentStageKey = null;
        $currentStageProgress = 0;
        $allStagesCompleted = true;

        foreach (self::STAGE_ORDER as $stageKey) {
            $stageDef = self::ORDER_STAGES[$stageKey];
            $stageTypes = $stageDef['task_types'];
            $stageTasks = array_values(array_filter($tasks, function ($task) use ($stageTypes) {
                return in_array($task['type'], $stageTypes, true);
            }));

            if (empty($stageTasks)) {
                if ($hasAnyTask) {
                    $stageDetails[] = [
                        'stage'              => $stageKey,
                        'label'              => $stageDef['label'],
                        'order'              => $stageDef['order'],
                        'total_tasks'        => 0,
                        'completed_tasks'    => 0,
                        'progress_percent'   => 100,
                        'status'             => 'auto_completed',
                        'is_overdue'         => false,
                        'has_delay_reason'   => false,
                        'has_tasks'          => false,
                    ];
                    $effectiveCompletedStages++;
                } else {
                    $stageDetails[] = [
                        'stage'              => $stageKey,
                        'label'              => $stageDef['label'],
                        'order'              => $stageDef['order'],
                        'total_tasks'        => 0,
                        'completed_tasks'    => 0,
                        'progress_percent'   => 0,
                        'status'             => 'no_tasks',
                        'is_overdue'         => false,
                        'has_delay_reason'   => false,
                        'has_tasks'          => false,
                    ];
                    $allStagesCompleted = false;
                    if ($currentStageKey === null) {
                        $currentStageKey = $stageKey;
                        $currentStageProgress = 0;
                    }
                }
                continue;
            }

            $total = count($stageTasks);
            $completed = count(array_filter($stageTasks, function ($t) {
                return $t['status'] === 'completed';
            }));
            $progressPercent = $total > 0 ? round($completed / $total * 100, 2) : 0;

            $isOverdue = false;
            $hasDelayReason = false;
            foreach ($stageTasks as $t) {
                if (!empty($t['due_at']) && $t['due_at'] < $now && $t['status'] !== 'completed') {
                    $isOverdue = true;
                }
                if (!empty($t['delay_reason'])) {
                    $hasDelayReason = true;
                }
            }

            $status = 'pending';
            $isStageCompleted = false;
            if ($completed === $total) {
                $status = 'completed';
                $completedStages++;
                $effectiveCompletedStages++;
                $isStageCompleted = true;
            } elseif ($completed > 0) {
                $status = 'in_progress';
            }
            if ($isOverdue && $status !== 'completed') {
                $status = 'overdue';
            }

            if (!$isStageCompleted && $currentStageKey === null) {
                $currentStageKey = $stageKey;
                $currentStageProgress = $progressPercent;
            }

            if (!$isStageCompleted) {
                $allStagesCompleted = false;
            }

            $stageDetails[] = [
                'stage'              => $stageKey,
                'label'              => $stageDef['label'],
                'order'              => $stageDef['order'],
                'total_tasks'        => $total,
                'completed_tasks'    => $completed,
                'progress_percent'   => $progressPercent,
                'status'             => $status,
                'is_overdue'         => $isOverdue,
                'has_delay_reason'   => $hasDelayReason,
                'has_tasks'          => true,
            ];
        }

        $totalStages = count(self::STAGE_ORDER);
        if ($allStagesCompleted) {
            $currentStageKey = null;
            $currentStageProgress = 0;
        }

        $overallProgress = 0;
        if ($totalStages > 0) {
            if ($allStagesCompleted) {
                $overallProgress = 100;
            } elseif ($currentStageKey !== null) {
                $overallProgress = round(($effectiveCompletedStages * 100 + $currentStageProgress) / $totalStages, 2);
            }
        }
        $overallProgress = min(max($overallProgress, 0), 100);

        $currentStageLabel = null;
        if ($currentStageKey !== null) {
            $currentStageLabel = self::ORDER_STAGES[$currentStageKey]['label'] ?? null;
        }

        return [
            'stages'                     => $stageDetails,
            'current_stage'              => $currentStageKey,
            'current_stage_label'        => $currentStageLabel,
            'current_stage_progress'     => $currentStageProgress,
            'completed_stages'           => $completedStages,
            'effective_completed_stages' => $effectiveCompletedStages,
            'total_stages'               => $totalStages,
            'overall_progress'           => $overallProgress,
            'is_all_completed'           => $allStagesCompleted,
            'has_any_task'               => $hasAnyTask,
        ];
    }

    public function calculateStageProgress(int $orderId): array
    {
        return $this->resolveStageStatus($orderId);
    }

    public function transitionStage(int $orderId, string $toStage, ?string $delayReason, int $operatorId, bool $allowSkip = false): void
    {
        $order = Db::table('orders')->where('id', $orderId)->find();
        if (!$order) {
            throw new \RuntimeException('订单不存在');
        }
        if (!isset(self::ORDER_STAGES[$toStage])) {
            throw new \InvalidArgumentException('无效的目标阶段');
        }

        $fromStage = $order['current_stage'] ?? null;
        $stageStatus = $this->resolveStageStatus($orderId);

        if ($fromStage === null) {
            if ($stageStatus['is_all_completed']) {
                throw new \InvalidArgumentException('订单所有阶段已完成，无需推进');
            }
            if (!$stageStatus['has_any_task']) {
                throw new \InvalidArgumentException('订单暂无任务，无法推进阶段，请先创建任务');
            }
            $fromStage = $stageStatus['current_stage'];
        }

        $fromOrder = self::ORDER_STAGES[$fromStage]['order'] ?? 0;
        $toOrder = self::ORDER_STAGES[$toStage]['order'];

        if ($toOrder <= $fromOrder) {
            throw new \InvalidArgumentException('只能向前推进阶段，不能回退或停留在当前阶段');
        }

        $fromIdx = array_search($fromStage, self::STAGE_ORDER);
        $toIdx = array_search($toStage, self::STAGE_ORDER);
        if ($fromIdx === false || $toIdx === false) {
            throw new \InvalidArgumentException('无效的阶段标识');
        }

        $isSkip = $toIdx > $fromIdx + 1;
        if ($isSkip && !$allowSkip) {
            $nextStage = self::STAGE_ORDER[$fromIdx + 1] ?? null;
            $nextStageLabel = $nextStage ? (self::ORDER_STAGES[$nextStage]['label'] ?? $nextStage) : '下一阶段';
            throw new \InvalidArgumentException(sprintf(
                '不允许跳阶段推进，当前阶段为"%s"，请先推进到"%s"，如需跳阶段请联系管理员',
                self::ORDER_STAGES[$fromStage]['label'] ?? $fromStage,
                $nextStageLabel
            ));
        }

        $allTasks = Db::table('tasks')
            ->where('order_id', $orderId)
            ->whereNotIn('status', ['cancelled'])
            ->select()
            ->toArray();

        $now = date('Y-m-d H:i:s');
        $anyOverdue = false;

        $stagesToCheck = [$fromStage];
        if ($isSkip && $allowSkip) {
            for ($i = $fromIdx + 1; $i < $toIdx; $i++) {
                $stagesToCheck[] = self::STAGE_ORDER[$i];
            }
        }

        foreach ($stagesToCheck as $idx => $checkStage) {
            $isFromStage = ($idx === 0);
            $stageTypes = self::ORDER_STAGES[$checkStage]['task_types'];
            $stageTasks = array_filter($allTasks, function ($task) use ($stageTypes) {
                return in_array($task['type'], $stageTypes, true);
            });

            if (empty($stageTasks)) {
                continue;
            }

            $incompleteTitles = [];
            $stageOverdue = false;
            $stageHasDelayReason = false;

            foreach ($stageTasks as $task) {
                if ($task['status'] !== 'completed') {
                    $incompleteTitles[] = $task['title'];
                }
                if (!empty($task['due_at']) && $task['due_at'] < $now && $task['status'] !== 'completed') {
                    $stageOverdue = true;
                    $anyOverdue = true;
                }
                if (!empty($task['delay_reason'])) {
                    $stageHasDelayReason = true;
                }
            }

            if (!empty($incompleteTitles)) {
                if ($isFromStage && !$allowSkip) {
                    throw new \InvalidArgumentException(sprintf(
                        '当前阶段「%s」还有未完成任务：%s，请先完成所有任务后再推进阶段',
                        self::ORDER_STAGES[$checkStage]['label'] ?? $checkStage,
                        implode('、', array_slice($incompleteTitles, 0, 3)) . (count($incompleteTitles) > 3 ? '等' : '')
                    ));
                }
                if ($isFromStage && $allowSkip) {
                    if (empty(trim($delayReason ?? ''))) {
                        throw new \InvalidArgumentException(sprintf(
                            '当前阶段「%s」还有未完成任务，跳阶段前必须填写延期原因',
                            self::ORDER_STAGES[$checkStage]['label'] ?? $checkStage
                        ));
                    }
                }
                if (!$isFromStage && $allowSkip) {
                    if ($stageOverdue && !$stageHasDelayReason && empty(trim($delayReason ?? ''))) {
                        throw new \InvalidArgumentException(sprintf(
                            '跳过的阶段「%s」有未完成任务且已超期，跳阶段前必须填写延期原因',
                            self::ORDER_STAGES[$checkStage]['label'] ?? $checkStage
                        ));
                    }
                }
            }
        }

        $transitionType = 'forward';
        if ($isSkip) {
            $transitionType = 'skip';
        }

        $dbFromStage = $order['current_stage'] ?? null;
        Db::transaction(function () use ($orderId, $dbFromStage, $toStage, $transitionType, $delayReason, $anyOverdue, $operatorId) {
            Db::table('orders')->where('id', $orderId)->update([
                'current_stage' => $toStage,
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);

            Db::table('order_stage_transitions')->insert([
                'order_id'        => $orderId,
                'from_stage'      => $dbFromStage,
                'to_stage'        => $toStage,
                'transition_type' => $transitionType,
                'delay_reason'    => $delayReason,
                'is_overdue'      => $anyOverdue ? 1 : 0,
                'operator_id'     => $operatorId,
                'created_at'      => date('Y-m-d H:i:s'),
            ]);
        });
    }

    public function recordTaskDelayReason(int $taskId, string $delayReason, int $operatorId): void
    {
        $task = Db::table('tasks')->where('id', $taskId)->find();
        if (!$task) {
            throw new \RuntimeException('任务不存在');
        }

        $now = date('Y-m-d H:i:s');
        $oldReason = $task['delay_reason'] ?? '';
        $isUpdate = !empty($oldReason);

        Db::transaction(function () use ($taskId, $delayReason, $operatorId, $now, $oldReason, $isUpdate) {
            Db::table('tasks')->where('id', $taskId)->update([
                'delay_reason'             => $delayReason,
                'delay_reason_updated_at'  => $now,
                'delay_reason_updated_by'  => $operatorId,
                'updated_at'               => $now,
            ]);

            $logMessage = $isUpdate
                ? sprintf('更新延期原因：原因为「%s」→「%s」', mb_substr($oldReason, 0, 50, 'UTF-8') . (mb_strlen($oldReason, 'UTF-8') > 50 ? '...' : ''), mb_substr($delayReason, 0, 50, 'UTF-8') . (mb_strlen($delayReason, 'UTF-8') > 50 ? '...' : ''))
                : sprintf('设置延期原因：「%s」', mb_substr($delayReason, 0, 50, 'UTF-8') . (mb_strlen($delayReason, 'UTF-8') > 50 ? '...' : ''));

            Db::table('task_logs')->insert([
                'task_id'    => $taskId,
                'user_id'    => $operatorId,
                'action'     => 'delay_reason_' . ($isUpdate ? 'updated' : 'set'),
                'message'    => $logMessage,
                'created_at' => $now,
            ]);
        });

        if (!empty($task['order_id'])) {
            $this->syncCurrentStage((int)$task['order_id']);
        }
    }

    public function syncCurrentStage(int $orderId): void
    {
        $detected = $this->detectCurrentStage($orderId);
        $order = Db::table('orders')->where('id', $orderId)->find();
        if (!$order) {
            return;
        }
        $oldStage = $order['current_stage'] ?? null;
        if ($oldStage !== $detected) {
            Db::table('orders')->where('id', $orderId)->update([
                'current_stage' => $detected,
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function getStageTransitions(int $orderId): array
    {
        return Db::table('order_stage_transitions')
            ->alias('st')
            ->leftJoin('users u', 'u.id = st.operator_id')
            ->field(['st.*', 'u.name as operator_name'])
            ->where('st.order_id', $orderId)
            ->order('st.id', 'asc')
            ->select()
            ->toArray();
    }

    public function __construct()
    {
        $this->taskService = new TaskService();
        $this->notificationService = new NotificationService();
    }

    public function create(array $payload, array $user): array
    {
        $isDraft = ($payload['status'] ?? '') === 'draft';
        
        if (!$isDraft) {
            if (empty($payload['pi_number']) && empty($payload['pi_numbers'])) {
                throw new \InvalidArgumentException('PI 号码不能为空');
            }
            if (empty($payload['customer_name'])) {
                throw new \InvalidArgumentException('客户名称不能为空');
            }
            if (empty($payload['products']) || !is_array($payload['products'])) {
                throw new \InvalidArgumentException('至少需要一个产品');
            }
        }

        $now = date('Y-m-d H:i:s');
        $orderId = Db::transaction(function () use ($payload, $user, $now, $isDraft) {
            $piNumbers = $payload['pi_numbers'] ?? [];
            $piNumber = $payload['pi_number'] ?? ($piNumbers[0] ?? '');
            
            $orderId = Db::table('orders')->insertGetId([
                'pi_number'          => $piNumber,
                'pi_numbers'         => !empty($piNumbers) ? json_encode($piNumbers, JSON_UNESCAPED_UNICODE) : null,
                'customer_id'        => $payload['customer_id'] ?? null,
                'customer_name'      => $payload['customer_name'] ?? '',
                'status'             => $isDraft ? 'draft' : 'in_progress',
                'current_stage'      => $isDraft ? null : 'procurement',
                'initiator_id'       => $user['id'],
                'sales_owner_id'     => $payload['sales_owner_id'] ?? $user['id'],
                'currency'           => $payload['currency'] ?? 'CNY',
                'delivery_period_days' => $payload['delivery_period_days'] ?? null,
                'expected_delivery_at'=> $payload['expected_delivery_at'] ?? null,
                'sea_freight'        => $payload['sea_freight'] ?? 0,
                'discount_amount'    => $payload['discount_amount'] ?? 0,
                'grand_total'        => $payload['grand_total'] ?? 0,
                'requirement_text'   => $payload['requirement_text'] ?? null,
                'remark'             => $payload['remark'] ?? null,
                'attachment_count'   => !empty($payload['attachments']) ? count($payload['attachments']) : 0,
                'created_at'         => $now,
                'updated_at'         => $now,
            ]);

            if (!empty($payload['products']) && is_array($payload['products'])) {
                $this->createProducts($orderId, $payload['products']);
            }

            if (!$isDraft && !empty($payload['products']) && is_array($payload['products'])) {
                $this->createInitialTasks($orderId, $payload, $user);
            }

            if (!empty($payload['attachments'])) {
                $this->syncDocuments($orderId, $payload['attachments'], $user['id']);
            }

            $this->recordStatusChange(
                $orderId,
                null,
                $isDraft ? 'draft' : 'in_progress',
                $user['id'],
                $payload['currency'] ?? 'CNY',
                (float)($payload['grand_total'] ?? 0)
            );

            return $orderId;
        });

        $detail = $this->fetchDetail($orderId, $user);
        
        if (!$isDraft && !empty($detail['order'])) {
            $order = $detail['order'];
            $salesOwnerId = (int)($order['sales_owner_id'] ?? 0);
            $initiatorId = (int)($order['initiator_id'] ?? 0);
            
            if ($initiatorId > 0) {
                $this->notificationService->sendOrderCreated($initiatorId, $order, $user['id']);
            }
            
            if ($salesOwnerId > 0 && $salesOwnerId !== $initiatorId) {
                $this->notificationService->sendOrderCreated($salesOwnerId, $order, $user['id']);
            }
        }
        
        return $detail;
    }

    public function updateDraft(int $orderId, array $payload, array $user): array
    {
        $order = Db::table('orders')->where('id', $orderId)->find();
        if (!$order) {
            throw new \RuntimeException('订单不存在');
        }
        if ($order['status'] !== 'draft') {
            throw new \RuntimeException('只能编辑草稿状态的订单');
        }

        $now = date('Y-m-d H:i:s');
        $isSubmit = ($payload['status'] ?? '') === 'in_progress';

        Db::transaction(function () use ($orderId, $payload, $user, $now, $isSubmit, $order) {
            $piNumbers = $payload['pi_numbers'] ?? [];
            $existingPiNumbers = json_decode($order['pi_numbers'] ?? '[]', true) ?: [];
            if (!empty($payload['pi_numbers_add'])) {
                $piNumbers = array_merge($existingPiNumbers, $payload['pi_numbers_add']);
            }
            $piNumber = $payload['pi_number'] ?? ($order['pi_number'] ?? ($piNumbers[0] ?? ''));

            $update = [
                'pi_number'          => $piNumber,
                'customer_name'      => $payload['customer_name'] ?? $order['customer_name'],
                'currency'           => $payload['currency'] ?? $order['currency'],
                'delivery_period_days' => $payload['delivery_period_days'] ?? $order['delivery_period_days'],
                'expected_delivery_at'=> $payload['expected_delivery_at'] ?? $order['expected_delivery_at'],
                'sea_freight'        => $payload['sea_freight'] ?? $order['sea_freight'],
                'discount_amount'    => $payload['discount_amount'] ?? $order['discount_amount'],
                'grand_total'        => $payload['grand_total'] ?? $order['grand_total'],
                'remark'             => $payload['remark'] ?? $order['remark'],
                'updated_at'         => $now,
            ];

            if (!empty($piNumbers)) {
                $update['pi_numbers'] = json_encode($piNumbers, JSON_UNESCAPED_UNICODE);
            }

            if ($isSubmit) {
                if (empty($piNumber) && empty($piNumbers)) {
                    throw new \InvalidArgumentException('PI 号码不能为空');
                }
                if (empty($update['customer_name'])) {
                    throw new \InvalidArgumentException('客户名称不能为空');
                }
                if (empty($payload['products']) || !is_array($payload['products'])) {
                    throw new \InvalidArgumentException('至少需要一个产品');
                }
                $update['status'] = 'in_progress';
                $update['current_stage'] = 'procurement';
            }

            Db::table('orders')->where('id', $orderId)->update($update);

            if (!empty($payload['products']) && is_array($payload['products'])) {
                Db::table('order_products')->where('order_id', $orderId)->delete();
                $this->createProducts($orderId, $payload['products']);
            }

            if ($isSubmit && !empty($payload['products']) && is_array($payload['products'])) {
                $this->createInitialTasks($orderId, $payload, $user);
            }

            if (!empty($payload['attachments'])) {
                Db::table('order_documents')->where('order_id', $orderId)->delete();
                $this->syncDocuments($orderId, $payload['attachments'], $user['id']);
            }

            if ($isSubmit) {
                $this->recordStatusChange(
                    $orderId,
                    'draft',
                    'in_progress',
                    $user['id'],
                    $update['currency'] ?? $order['currency'] ?? null,
                    (float)($update['grand_total'] ?? $order['grand_total'] ?? 0)
                );
            }
        });

        $detail = $this->fetchDetail($orderId, $user);
        
        if ($isSubmit && !empty($detail['order'])) {
            $order = $detail['order'];
            $salesOwnerId = (int)($order['sales_owner_id'] ?? 0);
            $initiatorId = (int)($order['initiator_id'] ?? 0);
            
            if ($initiatorId > 0) {
                $this->notificationService->sendOrderCreated($initiatorId, $order, $user['id']);
            }
            
            if ($salesOwnerId > 0 && $salesOwnerId !== $initiatorId) {
                $this->notificationService->sendOrderCreated($salesOwnerId, $order, $user['id']);
            }
        }
        
        return $detail;
    }

    protected function createProducts(int $orderId, array $products): void
    {
        $rows = [];
        foreach ($products as $product) {
            $assigneeId = isset($product['assignee_id']) ? (int)$product['assignee_id'] : null;
            $rows[] = [
                'order_id'      => $orderId,
                'product_name'  => $product['product_name'] ?? '',
                'model'         => $product['model'] ?? null,
                'voltage'       => $product['voltage'] ?? null,
                'power'         => $product['power'] ?? null,
                'processing_length' => $product['processing_length'] ?? null,
                'dimensions'    => $product['dimensions'] ?? null,
                'quantity'      => $product['quantity'] ?? 1,
                'unit_price'    => $product['unit_price'] ?? 0,
                'total_price'   => $product['total_price'] ?? null,
                'currency'      => $product['currency'] ?? 'CNY',
                'assignee_id'   => $assigneeId ?: null,
                'requirements'  => $product['requirements'] ?? null,
                'notes'         => $product['notes'] ?? null,
            ];
        }
        Db::table('order_products')->insertAll($rows);
    }

    protected function createInitialTasks(int $orderId, array $payload, array $user): void
    {
        $products = Db::table('order_products')->where('order_id', $orderId)->select()->toArray();
        $productPayloads = array_values($payload['products']);
        foreach ($products as $index => $product) {
            $productPayload = $productPayloads[$index] ?? [];
            $procurementPayload = [
                'supplier_type'    => $productPayload['supplier_type'] ?? null,
                'factory_owner_id' => $productPayload['factory_owner_id'] ?? null,
            ];
            $procurementExtra = [
                'supplier_id'     => $productPayload['supplier_id'] ?? null,
                'supplier_name'   => $productPayload['supplier_name'] ?? null,
                'purchase_price'  => $productPayload['purchase_price'] ?? null,
                'currency'        => $productPayload['currency'] ?? ($payload['currency'] ?? 'CNY'),
                'source_location' => $productPayload['source_location'] ?? ($payload['source_location'] ?? null),
            ];
            $procurementTaskId = $this->taskService->createTask([
                'order_id'        => $orderId,
                'order_product_id'=> $product['id'],
                'type'            => 'procurement',
                'title'           => sprintf('采购任务-%s', $product['product_name']),
                'description'     => $product['requirements'] ?? '',
                'assigned_to'     => $productPayload['assignee_id'] ?? null,
                'created_by'      => $user['id'],
                'need_audit'      => 0,
                'status'          => 'pending',
                'due_at'          => $productPayload['delivery_date'] ?? ($payload['delivery_date'] ?? null),
                'payload'         => array_filter($procurementPayload, static fn($value) => !is_null($value)),
            ], array_merge($procurementExtra, [
                'purchase_status' => 'not_ordered',
            ]));

            if (($procurementPayload['supplier_type'] ?? 'external') === 'internal' && !empty($procurementPayload['factory_owner_id'])) {
                $this->taskService->createTask([
                    'order_id'        => $orderId,
                    'order_product_id'=> $product['id'],
                    'parent_task_id'  => $procurementTaskId,
                    'type'            => 'factory_order',
                    'title'           => sprintf('工厂订单-%s', $product['product_name']),
                    'description'     => $productPayload['factory_instruction'] ?? '请同步工厂负责人跟进生产排期',
                    'assigned_to'     => $procurementPayload['factory_owner_id'],
                    'created_by'      => $user['id'],
                    'status'          => 'pending',
                    'start_at'        => $productPayload['production_start_at'] ?? null,
                    'due_at'          => $productPayload['production_due_at'] ?? null,
                    'payload'         => [
                        'source'               => 'auto_factory_task',
                        'procurement_task_id'  => $procurementTaskId,
                        'supplier_type'        => $procurementPayload['supplier_type'],
                    ],
                ]);
            }
        }

        $workflow = $payload['workflow'] ?? [];
        $defaultFlow = [
            [
                'type'        => 'nameplate',
                'title'       => '订单铭牌制作',
                'need_audit'  => 1,
            ],
            [
                'type'       => 'machine_data',
                'title'      => '机器数据上传',
                'need_audit' => 1,
            ],
            [
                'type'       => 'acceptance',
                'title'      => '机器验收任务',
                'need_audit' => 1,
            ],
            [
                'type'       => 'packaging',
                'title'      => '打包及唛头',
                'need_audit' => 0,
            ],
            [
                'type'       => 'shipment',
                'title'      => '装柜发货',
                'need_audit' => 1,
            ],
        ];
        $flowDefinition = $defaultFlow;
        if (!empty($workflow) && is_array($workflow)) {
            foreach ($workflow as $taskDefinition) {
                if (!is_array($taskDefinition) || empty($taskDefinition['type'])) {
                    continue;
                }
                $type = $taskDefinition['type'];
                $index = null;
                foreach ($flowDefinition as $idx => $definition) {
                    if (($definition['type'] ?? null) === $type) {
                        $index = $idx;
                        break;
                    }
                }
                if ($index !== null) {
                    $flowDefinition[$index] = array_merge($flowDefinition[$index], $taskDefinition);
                } else {
                    $flowDefinition[] = $taskDefinition;
                }
            }
        }
        foreach ($flowDefinition as $taskDefinition) {
            $this->taskService->createTask([
                'order_id'    => $orderId,
                'type'        => $taskDefinition['type'],
                'title'       => $taskDefinition['title'],
                'description' => $taskDefinition['description'] ?? '',
                'assigned_to' => $taskDefinition['assigned_to'] ?? null,
                'start_at'    => $taskDefinition['start_at'] ?? null,
                'due_at'      => $taskDefinition['due_at'] ?? null,
                'status'      => 'pending',
                'need_audit'  => $taskDefinition['need_audit'] ?? 0,
                'created_by'  => $user['id'],
            ], $taskDefinition['extra'] ?? []);
        }
    }

    protected function syncDocuments(int $orderId, array $attachments, int $userId): void
    {
        $rows = [];
        foreach ($attachments as $doc) {
            if (empty($doc['media_id']) || empty($doc['doc_type'])) {
                continue;
            }
            $rows[] = [
                'order_id'   => $orderId,
                'doc_type'   => $doc['doc_type'],
                'media_id'   => $doc['media_id'],
                'uploaded_by'=> $userId,
                'uploaded_at'=> date('Y-m-d H:i:s'),
            ];
        }
        if ($rows) {
            Db::table('order_documents')->insertAll($rows);
        }
    }

    public function fetchDetail(int $orderId, ?array $viewer = null): array
    {
        $order = Db::table('orders')->alias('o')
            ->leftJoin('users iu', 'iu.id = o.initiator_id')
            ->leftJoin('users su', 'su.id = o.sales_owner_id')
            ->field('o.*, iu.name as initiator_name, su.name as sales_owner_name')
            ->where('o.id', $orderId)
            ->find();
        if (!$order) {
            throw new \RuntimeException('订单不存在');
        }

        if (!empty($order['pi_numbers'])) {
            $order['pi_numbers'] = json_decode($order['pi_numbers'], true) ?: [];
        } else {
            $order['pi_numbers'] = $order['pi_number'] ? [$order['pi_number']] : [];
        }

        $products = Db::table('order_products')->where('order_id', $orderId)->select()->toArray();
        $taskRows = Db::table('tasks')->alias('t')
            ->leftJoin('orders o', 'o.id = t.order_id')
            ->leftJoin('users au', 'au.id = t.assigned_to')
            ->leftJoin('users cu', 'cu.id = t.created_by')
            ->leftJoin('users dru', 'dru.id = t.delay_reason_updated_by')
            ->leftJoin('task_procurements tp', 'tp.task_id = t.id')
            ->field(TaskService::getFullTaskFields())
            ->where('t.order_id', $orderId)
            ->order('t.id asc')
            ->select()
            ->toArray();
        $canSeeProcurement = $viewer ? \user_belongs_to_admin_dept($viewer) : true;
        $tasks = $this->taskService->formatTaskList($taskRows, $canSeeProcurement);
        $costs = Db::table('order_costs')->where('order_id', $orderId)->select()->toArray();
        $documents = Db::table('order_documents')
            ->where('order_id', $orderId)
            ->select()
            ->toArray();

        return [
            'order'     => $order,
            'products'  => $products,
            'tasks'     => $tasks,
            'costs'     => $costs,
            'documents' => $documents,
            'stage_progress' => $this->calculateStageProgress($orderId),
            'permissions'=> [
                'can_view_procurement' => $canSeeProcurement,
            ],
        ];
    }

    public function recordStatusChange(int $orderId, ?string $oldStatus, string $newStatus, ?int $changedBy, ?string $currencySnapshot = null, ?float $grandTotalSnapshot = null, bool $throwOnError = true): void
    {
        if ($oldStatus !== null && $oldStatus === $newStatus) {
            return;
        }

        try {
            Db::table('order_status_history')->insert([
                'order_id'   => $orderId,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'changed_by' => $changedBy,
                'changed_at' => date('Y-m-d H:i:s'),
                'currency_snapshot'   => $currencySnapshot,
                'grand_total_snapshot'=> $grandTotalSnapshot,
            ]);
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            $isDuplicate = strpos($msg, 'Duplicate entry') !== false
                || strpos($msg, 'SQLSTATE[23000]') !== false
                || strpos($msg, 'uk_osh_idempotent') !== false;

            if ($isDuplicate) {
                \think\facade\Log::info('order_status_history duplicate insert skipped', [
                    'order_id' => $orderId,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                ]);
                return;
            }

            \think\facade\Log::error('order_status_history insert failed: ' . $msg, [
                'order_id' => $orderId,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ]);
            if ($throwOnError) {
                throw $e;
            }
        }
    }
}
