<?php

namespace app\api\controller;

use app\common\controller\ApiController;
use think\facade\Db;
use think\facade\Request;

class Supplier extends ApiController
{
    public function index()
    {
        $keyword = trim((string)Request::get('keyword', ''));
        $query = Db::table('suppliers')->alias('s')
            ->leftJoin('users u', 'u.id = s.factory_owner_id')
            ->where('s.status', 1)
            ->order('s.name', 'asc')
            ->field('s.*, u.name as factory_owner_name');
        if ($keyword !== '') {
            $query->whereLike('s.name', "%{$keyword}%");
        }
        $items = $query->select()->toArray();
        return $this->success([
            'items' => array_map(function ($supplier) {
                return [
                    'id'            => (int)$supplier['id'],
                    'name'          => $supplier['name'],
                    'contact_name'  => $supplier['contact_name'],
                    'contact_phone' => $supplier['contact_phone'],
                    'is_internal'   => (int)($supplier['is_internal'] ?? 0),
                    'factory_owner_id' => !empty($supplier['factory_owner_id']) ? (int)$supplier['factory_owner_id'] : null,
                    'factory_owner_name' => $supplier['factory_owner_name'] ?? null,
                ];
            }, $items),
        ]);
    }
}
