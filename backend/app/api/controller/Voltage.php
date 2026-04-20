<?php

namespace app\api\controller;

use app\common\controller\ApiController;
use think\facade\Db;

class Voltage extends ApiController
{
    protected $publicActions = ['index'];

    public function index()
    {
        $items = Db::table('voltages')
            ->where('status', 1)
            ->order('sort_order', 'asc')
            ->order('id', 'asc')
            ->select()
            ->toArray();

        $result = array_map(static function ($item) {
            return [
                'id'            => (int)$item['id'],
                'label'         => $item['label'],
                'value'         => $item['value'],
                'description'   => $item['description'],
                'sort_order'    => (int)$item['sort_order'],
            ];
        }, $items);

        return $this->success([
            'items' => $result,
        ]);
    }
}
