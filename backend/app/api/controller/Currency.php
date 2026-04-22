<?php

namespace app\api\controller;

use app\common\controller\ApiController;
use think\facade\Db;

class Currency extends ApiController
{
    protected array $publicActions = ['index'];

    public function index()
    {
        $items = Db::table('currencies')
            ->where('status', 1)
            ->order('sort_order', 'asc')
            ->order('id', 'asc')
            ->select()
            ->toArray();

        $result = array_map(static function ($item) {
            return [
                'id'            => (int)$item['id'],
                'code'          => $item['code'],
                'name'          => $item['name'],
                'symbol'        => $item['symbol'],
                'sort_order'    => (int)$item['sort_order'],
                'is_default'    => (int)$item['is_default'],
            ];
        }, $items);

        return $this->success([
            'items' => $result,
        ]);
    }
}
