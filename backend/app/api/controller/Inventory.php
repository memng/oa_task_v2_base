<?php

namespace app\api\controller;

use app\common\controller\ApiController;
use think\facade\Db;
use think\facade\Request;

class Inventory extends ApiController
{
    public function index()
    {
        $keyword = trim((string)Request::get('keyword', ''));
        $supplierId = Request::get('supplier_id');
        $availableOnly = (int)Request::get('available_only', 0);

        $query = Db::table('inventory')->alias('i')
            ->leftJoin('suppliers s', 's.id = i.supplier_id')
            ->field([
                'i.*',
                's.name as supplier_name',
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
            'items' => array_map(function ($item) {
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
                    'created_at' => $item['created_at'],
                    'updated_at' => $item['updated_at'],
                ];
            }, $items),
        ]);
    }

    public function consume($id)
    {
        $inventoryId = (int)$id;
        $user = $this->user();
        $data = $this->requestData();

        $quantity = (int)($data['quantity'] ?? 1);
        if ($quantity <= 0) {
            $this->errorResponse('使用数量必须大于0');
        }

        $inventory = Db::table('inventory')->where('id', $inventoryId)->find();
        if (!$inventory) {
            $this->errorResponse('库存不存在', 404);
        }

        if ($inventory['quantity'] < $quantity) {
            $this->errorResponse('库存不足，当前可用：' . $inventory['quantity']);
        }

        Db::startTrans();
        try {
            $taskId = !empty($data['task_id']) ? (int)$data['task_id'] : null;

            if ($taskId) {
                $existingUsage = Db::table('inventory_usages')
                    ->where('inventory_id', $inventoryId)
                    ->where('task_id', $taskId)
                    ->find();

                if ($existingUsage) {
                    Db::rollback();
                    $this->errorResponse('该任务已扣减过此库存');
                }
            }

            $updateResult = Db::table('inventory')
                ->where('id', $inventoryId)
                ->where('quantity', '>=', $quantity)
                ->dec('quantity', $quantity)
                ->update([
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            if ($updateResult === 0) {
                Db::rollback();
                $this->errorResponse('库存扣减失败，可能库存已被其他操作扣减');
            }

            $updatedInventory = Db::table('inventory')->where('id', $inventoryId)->find();
            $newQuantity = $updatedInventory['quantity'];

            Db::table('inventory_usages')->insert([
                'inventory_id' => $inventoryId,
                'task_id' => $taskId,
                'user_id' => $user['id'],
                'quantity' => $quantity,
                'remaining_quantity' => $newQuantity,
                'note' => $data['note'] ?? null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->errorResponse('扣减库存失败：' . $e->getMessage());
        }

        return $this->success([
            'inventory_id' => $inventoryId,
            'consumed_quantity' => $quantity,
            'remaining_quantity' => $newQuantity,
        ], '库存扣减成功');
    }
}
