<?php

namespace app\admin\controller;

use app\common\controller\AdminApiController;
use think\facade\Db;
use think\facade\Request;

class Supplier extends AdminApiController
{
    public function index()
    {
        $keyword = trim((string)Request::get('keyword', ''));
        $query = Db::table('suppliers')->alias('s')
            ->leftJoin('users u', 'u.id = s.factory_owner_id')
            ->order('s.id', 'desc')
            ->field('s.*, u.name as factory_owner_name');
        if ($keyword !== '') {
            $query->whereLike('s.name', "%{$keyword}%");
        }
        $items = $query->select()->toArray();
        return $this->success([
            'items' => array_map([$this, 'formatSupplier'], $items),
        ]);
    }

    public function save()
    {
        $payload = $this->requestData();
        $name = trim((string)($payload['name'] ?? ''));
        if ($name === '') {
            $this->errorResponse('请输入供应商名称');
        }
        $now = date('Y-m-d H:i:s');
        $isInternal = !empty($payload['is_internal']) ? 1 : 0;
        $factoryOwnerId = $isInternal && !empty($payload['factory_owner_id']) ? (int)$payload['factory_owner_id'] : null;
        $id = Db::table('suppliers')->insertGetId([
            'name'          => $name,
            'contact_name'  => $payload['contact_name'] ?? null,
            'contact_phone' => $payload['contact_phone'] ?? null,
            'contact_email' => $payload['contact_email'] ?? null,
            'address'       => $payload['address'] ?? null,
            'payment_terms' => $payload['payment_terms'] ?? null,
            'rating'        => isset($payload['rating']) ? (int)$payload['rating'] : null,
            'is_internal'   => $isInternal,
            'factory_owner_id' => $factoryOwnerId,
            'status'        => isset($payload['status']) ? (int)$payload['status'] : 1,
            'created_by'    => $this->currentUser['id'] ?? null,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);
        $supplier = Db::table('suppliers')->find($id);
        return $this->success([
            'supplier' => $this->formatSupplier($supplier),
        ], '供应商已创建');
    }

    public function update(int $id)
    {
        $supplier = Db::table('suppliers')->find($id);
        if (!$supplier) {
            $this->errorResponse('供应商不存在', 404);
        }
        $payload = $this->requestData();
        $data = [];
        if (array_key_exists('name', $payload)) {
            $name = trim((string)$payload['name']);
            if ($name === '') {
                $this->errorResponse('供应商名称不能为空');
            }
            $data['name'] = $name;
        }
        foreach (['contact_name', 'contact_phone', 'contact_email', 'address', 'payment_terms'] as $field) {
            if (array_key_exists($field, $payload)) {
                $data[$field] = $payload[$field] ?: null;
            }
        }
        if (array_key_exists('rating', $payload)) {
            $data['rating'] = $payload['rating'] === '' || $payload['rating'] === null ? null : (int)$payload['rating'];
        }
        if (array_key_exists('status', $payload)) {
            $data['status'] = (int)$payload['status'] ? 1 : 0;
        }
        if (array_key_exists('is_internal', $payload)) {
            $data['is_internal'] = (int)$payload['is_internal'] ? 1 : 0;
        }
        if (array_key_exists('factory_owner_id', $payload)) {
            $factoryOwnerId = $payload['factory_owner_id'] === '' || $payload['factory_owner_id'] === null
                ? null
                : (int)$payload['factory_owner_id'];
            if (empty($data['is_internal']) && !array_key_exists('is_internal', $payload)) {
                $factoryOwnerId = null;
            } elseif (array_key_exists('is_internal', $payload) && !(int)$payload['is_internal']) {
                $factoryOwnerId = null;
            }
            $data['factory_owner_id'] = $factoryOwnerId;
        } elseif (isset($data['is_internal']) && !$data['is_internal']) {
            $data['factory_owner_id'] = null;
        }
        if (!$data) {
            return $this->success([
                'supplier' => $this->formatSupplier($supplier),
            ]);
        }
        $data['updated_at'] = date('Y-m-d H:i:s');
        Db::table('suppliers')->where('id', $id)->update($data);
        $supplier = Db::table('suppliers')->find($id);
        return $this->success([
            'supplier' => $this->formatSupplier($supplier),
        ], '供应商已更新');
    }

    public function delete(int $id)
    {
        $supplier = Db::table('suppliers')->find($id);
        if (!$supplier) {
            $this->errorResponse('供应商不存在', 404);
        }
        Db::table('suppliers')->where('id', $id)->delete();
        return $this->success([], '供应商已删除');
    }

    protected function formatSupplier(array $supplier): array
    {
        if (!isset($supplier['factory_owner_name']) && !empty($supplier['factory_owner_id'])) {
            $supplier['factory_owner_name'] = Db::table('users')->where('id', $supplier['factory_owner_id'])->value('name');
        }
        return [
            'id'            => (int)$supplier['id'],
            'name'          => $supplier['name'],
            'contact_name'  => $supplier['contact_name'],
            'contact_phone' => $supplier['contact_phone'],
            'contact_email' => $supplier['contact_email'],
            'address'       => $supplier['address'],
            'payment_terms' => $supplier['payment_terms'],
            'rating'        => $supplier['rating'] !== null ? (int)$supplier['rating'] : null,
            'status'        => (int)($supplier['status'] ?? 1),
            'is_internal'   => (int)($supplier['is_internal'] ?? 0),
            'factory_owner_id' => !empty($supplier['factory_owner_id']) ? (int)$supplier['factory_owner_id'] : null,
            'factory_owner_name' => $supplier['factory_owner_name'] ?? null,
            'created_at'    => $supplier['created_at'],
        ];
    }
}
