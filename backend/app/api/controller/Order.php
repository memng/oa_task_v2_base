<?php

namespace app\api\controller;

use app\api\service\OrderService;
use app\common\controller\ApiController;
use think\facade\Db;
use think\facade\Request;

class Order extends ApiController
{
    protected OrderService $service;

    protected function initialize()
    {
        parent::initialize();
        $this->service = new OrderService();
    }

    public function index()
    {
        $page = (int)Request::get('page', 1);
        $pageSize = (int)Request::get('page_size', 15);
        $user = $this->user();
        $query = Db::table('orders')->alias('o')
            ->leftJoin('users iu', 'iu.id = o.initiator_id')
            ->leftJoin('users su', 'su.id = o.sales_owner_id');

        if ($status = Request::get('status')) {
            $query->where('o.status', $status);
        }
        if ($keyword = Request::get('keyword')) {
            $query->where(function ($q) use ($keyword) {
                $q->whereLike('o.pi_number', "%{$keyword}%")
                    ->whereOr('o.customer_name', 'like', "%{$keyword}%");
            });
        }
        $salesOwnerId = Request::get('sales_owner_id', Request::get('owner_id'));
        if ($salesOwnerId) {
            $query->where('o.sales_owner_id', (int)$salesOwnerId);
        }
        if ($initiatorId = Request::get('initiator_id')) {
            $query->where('o.initiator_id', (int)$initiatorId);
        }
        if ($salesKeyword = Request::get('sales_keyword')) {
            $query->where(function ($q) use ($salesKeyword) {
                $q->whereLike('su.name', "%{$salesKeyword}%")
                    ->whereOr('su.nickname', 'like', "%{$salesKeyword}%");
            });
        }
        if ($startDate = Request::get('start_date')) {
            $query->where('o.created_at', '>=', "{$startDate} 00:00:00");
        }
        if ($endDate = Request::get('end_date')) {
            $query->where('o.created_at', '<=', "{$endDate} 23:59:59");
        }
        if (!\user_belongs_to_admin_dept($user)) {
            $userId = (int)$user['id'];
            $query->where('o.initiator_id', $userId);
        }

        $countQuery = clone $query;
        $total = $countQuery->count();
        $list = $query->field('o.*, iu.name as initiator_name, su.name as sales_owner_name')
            ->order('o.id', 'desc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();

        $orderIds = array_column($list, 'id');
        $documentsByOrder = [];
        $productNames = [];
        if ($orderIds) {
            $documentRows = Db::table('order_documents')
                ->whereIn('order_id', $orderIds)
                ->select()
                ->toArray();
            foreach ($documentRows as $row) {
                $orderId = (int)$row['order_id'];
                if (!isset($documentsByOrder[$orderId])) {
                    $documentsByOrder[$orderId] = [];
                }
                $documentsByOrder[$orderId][] = $row;
            }
            $productRows = Db::table('order_products')
                ->whereIn('order_id', $orderIds)
                ->order('id asc')
                ->select()
                ->toArray();
            foreach ($productRows as $product) {
                $orderId = (int)$product['order_id'];
                if (!isset($productNames[$orderId])) {
                    $productNames[$orderId] = $product['product_name'];
                }
            }
        }

        foreach ($list as &$item) {
            $orderId = (int)$item['id'];
            $documents = $documentsByOrder[$orderId] ?? [];
            $item['document_summary'] = $this->buildDocumentSummary($documents, $item['status'] ?? '');
            $item['product_name'] = $productNames[$orderId] ?? null;
            if (!empty($item['pi_numbers'])) {
                $item['pi_numbers'] = json_decode($item['pi_numbers'], true) ?: [];
            } else {
                $item['pi_numbers'] = $item['pi_number'] ? [$item['pi_number']] : [];
            }
        }
        unset($item);

        return $this->success([
            'items' => $list,
            'meta'  => [
                'page'       => $page,
                'page_size'  => $pageSize,
                'total'      => $total,
            ],
        ]);
    }

    public function save()
    {
        $data = $this->requestData();
        try {
            $detail = $this->service->create($data, $this->user());
        } catch (\Throwable $e) {
            $this->errorResponse($e->getMessage(), 422);
        }

        $isDraft = ($data['status'] ?? '') === 'draft';
        $message = $isDraft ? '草稿已保存' : '订单已创建';
        return $this->success($detail, $message, 201);
    }

    public function read($id)
    {
        try {
            $detail = $this->service->fetchDetail((int)$id, $this->user());
        } catch (\Throwable $e) {
            $this->errorResponse($e->getMessage(), 404);
        }
        $this->assertOrderPermission($detail['order']);
        $detail['document_summary'] = $this->buildDocumentSummary($detail['documents'], $detail['order']['status'] ?? '');
        return $this->success($detail);
    }

    public function update($id)
    {
        $data = $this->requestData();
        $orderId = (int)$id;

        $order = Db::table('orders')->where('id', $orderId)->find();
        if (!$order) {
            $this->errorResponse('订单不存在', 404);
        }

        if ($order['status'] === 'draft') {
            try {
                $detail = $this->service->updateDraft($orderId, $data, $this->user());
            } catch (\Throwable $e) {
                $this->errorResponse($e->getMessage(), 422);
            }
            $isSubmit = ($data['status'] ?? '') === 'in_progress';
            $message = $isSubmit ? '订单已提交' : '草稿已保存';
            return $this->success($detail, $message);
        }

        $update = [
            'customer_name'      => $data['customer_name'] ?? null,
            'status'             => $data['status'] ?? null,
            'expected_delivery_at'=> $data['expected_delivery_at'] ?? null,
            'requirement_text'   => $data['requirement_text'] ?? null,
            'remark'             => $data['remark'] ?? null,
            'updated_at'         => date('Y-m-d H:i:s'),
        ];
        $update = array_filter($update, fn($value) => !is_null($value));
        if ($update) {
            Db::table('orders')->where('id', $id)->update($update);
        }
        return $this->read($id);
    }

    public function addCost($id)
    {
        $data = $this->requestData();
        if (empty($data['cost_scope']) || empty($data['category'])) {
            $this->errorResponse('费用类型不完整');
        }
        $row = [
            'order_id'        => (int)$id,
            'order_product_id'=> $data['order_product_id'] ?? null,
            'cost_scope'      => $data['cost_scope'],
            'category'        => $data['category'],
            'amount'          => $data['amount'] ?? 0,
            'currency'        => $data['currency'] ?? 'CNY',
            'description'     => $data['description'] ?? null,
            'created_by'      => $this->user()['id'],
            'created_at'      => date('Y-m-d H:i:s'),
        ];
        Db::table('order_costs')->insert($row);
        return $this->success([], '费用已记录');
    }

    public function addDocument($id)
    {
        $order = Db::table('orders')->where('id', (int)$id)->find();
        if (!$order) {
            $this->errorResponse('订单不存在', 404);
        }
        $data = $this->requestData();
        if (empty($data['doc_type']) || empty($data['media_id'])) {
            $this->errorResponse('请选择文件和类型');
        }
        $docType = (string)$data['doc_type'];
        $row = [
            'doc_type'    => $docType,
            'media_id'    => $data['media_id'],
            'uploaded_by' => $this->user()['id'],
            'uploaded_at' => date('Y-m-d H:i:s'),
        ];
        $existing = Db::table('order_documents')
            ->where('order_id', (int)$id)
            ->where('doc_type', $docType)
            ->find();
        if ($existing) {
            Db::table('order_documents')->where('id', $existing['id'])->update($row);
        } else {
            $row['order_id'] = (int)$id;
            Db::table('order_documents')->insert($row);
        }
        $documents = Db::table('order_documents')
            ->where('order_id', (int)$id)
            ->select()
            ->toArray();
        return $this->success([
            'documents' => $documents,
            'document_summary' => $this->buildDocumentSummary($documents, $order['status'] ?? ''),
        ], '文件已上传');
    }

    public function progress($id)
    {
        try {
            $detail = $this->service->fetchDetail((int)$id, $this->user());
        } catch (\Throwable $e) {
            $this->errorResponse('订单不存在', 404);
        }
        $this->assertOrderPermission($detail['order']);
        $tasks = $detail['tasks'];
        $completed = array_sum(array_map(function ($task) {
            return $task['status'] === 'completed' ? 1 : 0;
        }, $tasks));
        $progress = $tasks ? round($completed / count($tasks) * 100, 2) : 0;
        return $this->success([
            'order_status' => $detail['order']['status'],
            'tasks'    => $tasks,
            'progress' => $progress,
        ]);
    }

    protected function assertOrderPermission(array $order): void
    {
        if (\user_belongs_to_admin_dept($this->user())) {
            return;
        }
        $userId = (int)$this->user()['id'];
        if ((int)$order['initiator_id'] === $userId) {
            return;
        }
        $this->errorResponse('暂无权限查看该订单', 403);
    }

    protected function documentTypes(): array
    {
        return ['pi', 'commercial_invoice', 'customs_declaration', 'bill_of_lading', 'freight_invoice', 'payment_receipt'];
    }

    protected function buildDocumentSummary(array $documents, string $orderStatus = ''): array
    {
        $types = $this->documentTypes();
        $uploadedTypes = array_values(array_unique(array_filter(array_map(static function ($doc) {
            return (string)($doc['doc_type'] ?? '');
        }, $documents))));
        $uploadedCount = count($uploadedTypes);
        $totalRequired = count($types);
        $status = 'upload';
        $statusLabel = $uploadedCount > 0 ? '继续上传' : '待上传文件';
        if ($totalRequired > 0 && $uploadedCount >= $totalRequired) {
            $status = 'audit';
            $statusLabel = '待管理员审核';
        }
        if ($orderStatus === 'cancelled') {
            $status = 'reupload';
            $statusLabel = '审核驳回';
        }
        return [
            'required_total' => $totalRequired,
            'uploaded'       => $uploadedCount,
            'status'         => $status,
            'status_label'   => $statusLabel,
            'types'          => $uploadedTypes,
        ];
    }
}
