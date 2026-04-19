<?php

namespace app\api\controller;

use app\common\controller\ApiController;
use think\facade\Db;
use think\facade\Request;

class FactoryVisit extends ApiController
{
    public function index()
    {
        $query = Db::table('customer_factory_visits');
        if ($status = Request::get('status')) {
            $query->where('status', $status);
        }
        $items = $query->order('visit_date', 'desc')->select()->toArray();
        return $this->success(['items' => $items]);
    }

    public function save()
    {
        $data = $this->requestData();
        if (empty($data['title']) || empty($data['assigned_to'])) {
            $this->errorResponse('请填写标题与任务人');
        }
        $id = Db::table('customer_factory_visits')->insertGetId([
            'title'        => $data['title'],
            'order_id'     => $data['order_id'] ?? null,
            'requirements' => $data['requirements'] ?? null,
            'visit_date'   => $data['visit_date'] ?? null,
            'assigned_to'  => $data['assigned_to'],
            'status'       => 'pending',
            'created_by'   => $this->user()['id'],
            'created_at'   => date('Y-m-d H:i:s'),
        ]);
        return $this->success(['id' => $id], '已创建验厂任务', 201);
    }

    public function update($id)
    {
        $data = $this->requestData();
        $update = array_intersect_key($data, array_flip(['requirements', 'visit_date', 'assigned_to', 'feedback', 'status']));
        if ($update) {
            Db::table('customer_factory_visits')->where('id', $id)->update($update);
        }
        return $this->success([], '验厂任务已更新');
    }
}
