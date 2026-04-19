<?php

namespace app\api\controller;

use app\common\controller\ApiController;
use think\facade\Db;

class Department extends ApiController
{
    protected bool $requireLogin = false;

    public function index()
    {
        $departments = Db::table('departments')
            ->where('status', 1)
            ->order('sort_order', 'asc')
            ->field('id,name,parent_id,type')
            ->select()
            ->toArray();

        $data = array_map(static function ($dept) {
            return [
                'id'        => (int)$dept['id'],
                'name'      => $dept['name'],
                'type'      => $dept['type'],
                'parent_id' => $dept['parent_id'] ? (int)$dept['parent_id'] : null,
            ];
        }, $departments);

        return $this->success([
            'departments' => $data,
        ]);
    }
}

