<?php

namespace app\admin\controller;

use app\common\controller\AdminApiController;
use think\facade\Db;
use think\facade\Request;

class Inventory extends AdminApiController
{
    public function index()
    {
        $keyword = trim((string)Request::get('keyword', ''));
        $supplierId = Request::get('supplier_id');
        $availableOnly = (int)Request::get('available_only', 0);

        $query = Db::table('inventory')->alias('i')
            ->leftJoin('suppliers s', 's.id = i.supplier_id')
            ->leftJoin('tasks t', 't.id = i.source_task_id')
            ->field([
                'i.*',
                's.name as supplier_name',
                't.title as source_task_title',
            ])
            ->order('i.updated_at', 'desc')
            ->order('i.id', 'desc');

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->whereLike('i.product_name', "%{$keyword}%")
                    ->whereOr('i.model', 'like', "%{$keyword}%")
                    ->whereOr('i.voltage', 'like', "%{$keyword}%")
                    ->whereOr('s.name', 'like', "%{$keyword}%");
            });
        }

        if ($supplierId) {
            $query->where('i.supplier_id', (int)$supplierId);
        }

        if ($availableOnly === 1) {
            $query->where('i.quantity', '>', 0);
        }

        $items = $query->select()->toArray();

        return $this->success([
            'items' => array_map([$this, 'formatInventory'], $items),
        ]);
    }

    public function save()
    {
        $payload = $this->requestData();
        $productName = trim((string)($payload['product_name'] ?? ''));
        if ($productName === '') {
            $this->errorResponse('请输入产品名称');
        }

        $now = date('Y-m-d H:i:s');
        $id = Db::table('inventory')->insertGetId([
            'product_name' => $productName,
            'model' => $payload['model'] ?? null,
            'voltage' => $payload['voltage'] ?? null,
            'supplier_id' => !empty($payload['supplier_id']) ? (int)$payload['supplier_id'] : null,
            'quantity' => isset($payload['quantity']) ? (int)$payload['quantity'] : 0,
            'requirements' => $payload['requirements'] ?? null,
            'source_type' => $payload['source_type'] ?? 'manual',
            'source_task_id' => !empty($payload['source_task_id']) ? (int)$payload['source_task_id'] : null,
            'created_by' => $this->currentUser['id'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $inventory = Db::table('inventory')->alias('i')
            ->leftJoin('suppliers s', 's.id = i.supplier_id')
            ->field('i.*, s.name as supplier_name')
            ->where('i.id', $id)
            ->find();

        return $this->success([
            'inventory' => $this->formatInventory($inventory),
        ], '库存已创建');
    }

    public function update(int $id)
    {
        $inventory = Db::table('inventory')->find($id);
        if (!$inventory) {
            $this->errorResponse('库存不存在', 404);
        }

        $payload = $this->requestData();
        $data = [];

        if (array_key_exists('product_name', $payload)) {
            $productName = trim((string)$payload['product_name']);
            if ($productName === '') {
                $this->errorResponse('产品名称不能为空');
            }
            $data['product_name'] = $productName;
        }

        foreach (['model', 'voltage', 'requirements'] as $field) {
            if (array_key_exists($field, $payload)) {
                $data[$field] = $payload[$field] ?: null;
            }
        }

        if (array_key_exists('supplier_id', $payload)) {
            $data['supplier_id'] = empty($payload['supplier_id']) ? null : (int)$payload['supplier_id'];
        }

        if (array_key_exists('quantity', $payload)) {
            $data['quantity'] = (int)$payload['quantity'];
        }

        if (array_key_exists('source_type', $payload)) {
            $data['source_type'] = $payload['source_type'] ?: 'manual';
        }

        if (!$data) {
            $inventory = Db::table('inventory')->alias('i')
                ->leftJoin('suppliers s', 's.id = i.supplier_id')
                ->field('i.*, s.name as supplier_name')
                ->where('i.id', $id)
                ->find();
            return $this->success([
                'inventory' => $this->formatInventory($inventory),
            ]);
        }

        $data['updated_at'] = date('Y-m-d H:i:s');
        Db::table('inventory')->where('id', $id)->update($data);

        $inventory = Db::table('inventory')->alias('i')
            ->leftJoin('suppliers s', 's.id = i.supplier_id')
            ->field('i.*, s.name as supplier_name')
            ->where('i.id', $id)
            ->find();

        return $this->success([
            'inventory' => $this->formatInventory($inventory),
        ], '库存已更新');
    }

    protected function formatInventory(array $item): array
    {
        return [
            'id' => (int)$item['id'],
            'product_name' => $item['product_name'],
            'model' => $item['model'],
            'voltage' => $item['voltage'],
            'supplier_id' => !empty($item['supplier_id']) ? (int)$item['supplier_id'] : null,
            'supplier_name' => $item['supplier_name'],
            'quantity' => (int)$item['quantity'],
            'requirements' => $item['requirements'],
            'source_type' => $item['source_type'],
            'source_task_id' => !empty($item['source_task_id']) ? (int)$item['source_task_id'] : null,
            'source_task_title' => $item['source_task_title'] ?? null,
            'created_by' => !empty($item['created_by']) ? (int)$item['created_by'] : null,
            'created_at' => $item['created_at'],
            'updated_at' => $item['updated_at'],
        ];
    }
}
