<?php

namespace app\api\service;

use app\common\service\TaskService;
use think\facade\Db;

class OrderService
{
    protected TaskService $taskService;

    public function __construct()
    {
        $this->taskService = new TaskService();
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

            return $orderId;
        });

        return $this->fetchDetail($orderId, $user);
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

        Db::transaction(function () use ($orderId, $payload, $user, $now, $isSubmit) {
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
        });

        return $this->fetchDetail($orderId, $user);
    }

    protected function createProducts(int $orderId, array $products): void
    {
        $rows = [];
        foreach ($products as $product) {
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
            ->leftJoin('task_procurements tp', 'tp.task_id = t.id')
            ->field([
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
            'permissions'=> [
                'can_view_procurement' => $canSeeProcurement,
            ],
        ];
    }
}
