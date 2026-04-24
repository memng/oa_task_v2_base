<?php

namespace app\api\controller;

use app\common\controller\ApiController;
use think\facade\Db;
use think\facade\Request;

class IntentOrder extends ApiController
{
    protected const STAGES = [
        'new' => [
            'label' => '新建',
            'order' => 1,
            'is_final' => false,
        ],
        'initial_review' => [
            'label' => '初评',
            'order' => 2,
            'is_final' => false,
        ],
        'requirement_confirm' => [
            'label' => '需求确认',
            'order' => 3,
            'is_final' => false,
        ],
        'proposal' => [
            'label' => '方案',
            'order' => 4,
            'is_final' => false,
        ],
        'business_negotiation' => [
            'label' => '商务谈判',
            'order' => 5,
            'is_final' => false,
        ],
        'contract_review' => [
            'label' => '合同评审',
            'order' => 6,
            'is_final' => false,
        ],
        'won' => [
            'label' => '成交',
            'order' => 7,
            'is_final' => true,
        ],
        'lost' => [
            'label' => '失败关闭',
            'order' => 8,
            'is_final' => true,
        ],
    ];

    protected const STAGE_ORDER = [
        'new',
        'initial_review',
        'requirement_confirm',
        'proposal',
        'business_negotiation',
        'contract_review',
        'won',
    ];

    protected const LEGACY_STATUS_MAP = [
        'pending' => 'new',
        'done' => 'won',
    ];

    public function index()
    {
        $query = Db::table('intent_orders');
        if ($status = Request::get('status')) {
            $query->where('status', $status);
        }
        if ($keyword = Request::get('keyword')) {
            $query->whereLike('customer_name|product_name', "%{$keyword}%");
        }
        if ($limit = (int)Request::get('limit')) {
            $query->limit($limit);
        }
        $items = $query->order('created_at', 'desc')->select()->toArray();

        $summaryRows = Db::table('intent_orders')
            ->field('status, COUNT(*) AS total')
            ->group('status')
            ->select()
            ->toArray();
        $summary = [
            'new' => 0,
            'initial_review' => 0,
            'requirement_confirm' => 0,
            'proposal' => 0,
            'business_negotiation' => 0,
            'contract_review' => 0,
            'won' => 0,
            'lost' => 0,
        ];
        foreach ($summaryRows as $row) {
            $key = $row['status'] ?? '';
            if (isset($summary[$key])) {
                $summary[$key] = (int)$row['total'];
            }
        }

        return $this->success([
            'items'   => $items,
            'summary' => $summary,
            'stages'  => $this->formatStages(),
        ]);
    }

    public function save()
    {
        $data = $this->requestData();
        if (empty($data['customer_name']) || empty($data['product_name'])) {
            $this->errorResponse('请填写客户名称与产品信息');
        }
        $id = Db::table('intent_orders')->insertGetId([
            'salesperson_id'       => $this->user()['id'],
            'customer_name'        => $data['customer_name'],
            'product_name'         => $data['product_name'],
            'model'                => $data['model'] ?? null,
            'voltage'              => $data['voltage'] ?? null,
            'quantity'             => $data['quantity'] ?? 1,
            'customer_requirements'=> $data['customer_requirements'] ?? null,
            'status'               => 'new',
            'expected_close_date'  => $data['expected_close_date'] ?? null,
            'created_at'           => date('Y-m-d H:i:s'),
        ]);

        $this->recordTransition($id, null, 'new', 'forward', '新建意向单');

        return $this->success(['id' => $id], '意向单已创建', 201);
    }

    public function update($id)
    {
        $item = Db::table('intent_orders')->where('id', $id)->find();
        if (!$item) {
            $this->errorResponse('意向订单不存在', 404);
        }

        $data = $this->requestData();
        
        $allowedFields = [
            'customer_name', 'product_name', 'model', 'voltage', 'quantity',
            'customer_requirements', 'expected_close_date',
        ];
        
        if (isset($data['status'])) {
            $this->errorResponse('状态变更请使用专门的流转接口 POST /intent-orders/:id/transition');
        }

        $update = array_intersect_key($data, array_flip($allowedFields));
        if ($update) {
            $update['updated_at'] = date('Y-m-d H:i:s');
            Db::table('intent_orders')->where('id', $id)->update($update);
        }
        return $this->success([], '更新成功');
    }

    public function read($id)
    {
        $item = Db::table('intent_orders')->where('id', $id)->find();
        if (!$item) {
            $this->errorResponse('意向订单不存在', 404);
        }

        $transitions = Db::table('intent_order_transitions')
            ->alias('t')
            ->leftJoin('users u', 'u.id = t.operator_id')
            ->field([
                't.*',
                'u.name as operator_name',
            ])
            ->where('t.intent_order_id', $id)
            ->order('t.id', 'asc')
            ->select()
            ->toArray();

        $availableTransitions = $this->getAvailableTransitions($item['status']);

        $normalizedStatus = $this->normalizeStatus($item['status']);
        $stageOrder = array_search($normalizedStatus, self::STAGE_ORDER);
        $progress = $stageOrder !== false ? round(($stageOrder / (count(self::STAGE_ORDER) - 1)) * 100) : 0;

        return $this->success([
            'item' => $item,
            'transitions' => $transitions,
            'available_transitions' => $availableTransitions,
            'progress' => $progress,
            'stages' => $this->formatStages(),
        ]);
    }

    public function transition($id)
    {
        $item = Db::table('intent_orders')->where('id', $id)->find();
        if (!$item) {
            $this->errorResponse('意向订单不存在', 404);
        }

        $data = $this->requestData();
        $toStatus = $data['to_status'] ?? '';
        $reason = $data['reason'] ?? '';

        if (empty($toStatus)) {
            $this->errorResponse('请指定目标阶段');
        }

        if (!isset(self::STAGES[$toStatus])) {
            $this->errorResponse('无效的目标阶段');
        }

        $fromStatus = $item['status'];

        $validation = $this->validateTransition($fromStatus, $toStatus, $reason);
        if (!$validation['valid']) {
            $this->errorResponse($validation['message']);
        }

        Db::transaction(function () use ($id, $fromStatus, $toStatus, $validation, $reason) {
            Db::table('intent_orders')->where('id', $id)->update([
                'status' => $toStatus,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $this->recordTransition($id, $fromStatus, $toStatus, $validation['type'], $reason);
        });

        return $this->success([], '阶段流转成功');
    }

    public function availableTransitions($id)
    {
        $item = Db::table('intent_orders')->where('id', $id)->find();
        if (!$item) {
            $this->errorResponse('意向订单不存在', 404);
        }

        $availableTransitions = $this->getAvailableTransitions($item['status']);
        $normalizedStatus = $this->normalizeStatus($item['status']);

        return $this->success([
            'current_status' => $item['status'],
            'current_status_label' => self::STAGES[$normalizedStatus]['label'] ?? $item['status'],
            'available_transitions' => $availableTransitions,
        ]);
    }

    public function stages()
    {
        return $this->success([
            'stages' => $this->formatStages(),
        ]);
    }

    protected function normalizeStatus(?string $status): ?string
    {
        if ($status === null) {
            return null;
        }
        return self::LEGACY_STATUS_MAP[$status] ?? $status;
    }

    protected function isBlank(?string $value): bool
    {
        return $value === null || trim($value) === '';
    }

    protected function validateTransition(string $fromStatus, string $toStatus, string $reason): array
    {
        $normalizedFromStatus = $this->normalizeStatus($fromStatus);
        $fromStage = self::STAGES[$normalizedFromStatus] ?? null;
        $toStage = self::STAGES[$toStatus] ?? null;

        if (!$fromStage || !$toStage) {
            return ['valid' => false, 'message' => '无效的阶段'];
        }

        if ($fromStage['is_final']) {
            return ['valid' => false, 'message' => '终态不可再流转'];
        }

        if ($toStatus === 'lost') {
            if ($this->isBlank($reason)) {
                return ['valid' => false, 'message' => '失败关闭必须填写原因'];
            }
            return ['valid' => true, 'type' => 'lost'];
        }

        $fromOrder = array_search($normalizedFromStatus, self::STAGE_ORDER);
        $toOrder = array_search($toStatus, self::STAGE_ORDER);

        if ($fromOrder === false || $toOrder === false) {
            return ['valid' => false, 'message' => '无效的阶段顺序'];
        }

        if ($toOrder === $fromOrder + 1) {
            return ['valid' => true, 'type' => 'forward'];
        }

        if ($toOrder === $fromOrder - 1) {
            if ($this->isBlank($reason)) {
                return ['valid' => false, 'message' => '退回上一阶段必须填写原因'];
            }
            return ['valid' => true, 'type' => 'backward'];
        }

        if ($toOrder > $fromOrder + 1) {
            return ['valid' => false, 'message' => '不允许跳级流转'];
        }

        if ($toOrder < $fromOrder - 1) {
            return ['valid' => false, 'message' => '仅允许退回到上一阶段'];
        }

        return ['valid' => false, 'message' => '不支持的流转操作'];
    }

    protected function getAvailableTransitions(string $currentStatus): array
    {
        $normalizedStatus = $this->normalizeStatus($currentStatus);
        $currentStage = self::STAGES[$normalizedStatus] ?? null;
        if (!$currentStage || $currentStage['is_final']) {
            return [];
        }

        $currentOrder = array_search($normalizedStatus, self::STAGE_ORDER);
        $transitions = [];

        if ($currentOrder !== false && $currentOrder < count(self::STAGE_ORDER) - 1) {
            $nextStatus = self::STAGE_ORDER[$currentOrder + 1];
            $transitions[] = [
                'status' => $nextStatus,
                'label' => self::STAGES[$nextStatus]['label'],
                'type' => 'forward',
                'need_reason' => false,
            ];
        }

        if ($currentOrder !== false && $currentOrder > 0) {
            $prevStatus = self::STAGE_ORDER[$currentOrder - 1];
            $transitions[] = [
                'status' => $prevStatus,
                'label' => self::STAGES[$prevStatus]['label'],
                'type' => 'backward',
                'need_reason' => true,
            ];
        }

        $transitions[] = [
            'status' => 'lost',
            'label' => self::STAGES['lost']['label'],
            'type' => 'lost',
            'need_reason' => true,
        ];

        return $transitions;
    }

    protected function recordTransition(int $orderId, ?string $fromStatus, string $toStatus, string $type, ?string $reason): void
    {
        Db::table('intent_order_transitions')->insert([
            'intent_order_id' => $orderId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'transition_type' => $type,
            'reason' => $reason,
            'operator_id' => $this->user()['id'],
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    protected function formatStages(): array
    {
        $result = [];
        foreach (self::STAGES as $key => $stage) {
            $result[] = [
                'value' => $key,
                'label' => $stage['label'],
                'order' => $stage['order'],
                'is_final' => $stage['is_final'],
            ];
        }
        return $result;
    }
}
