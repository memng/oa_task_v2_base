<?php

namespace app\api\controller;

use app\common\controller\ApiController;
use think\facade\Db;
use think\facade\Request;

class IntentOrder extends ApiController
{
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
            'pending' => 0,
            'done'    => 0,
            'lost'    => 0,
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
            'status'               => $data['status'] ?? 'pending',
            'expected_close_date'  => $data['expected_close_date'] ?? null,
            'created_at'           => date('Y-m-d H:i:s'),
        ]);
        return $this->success(['id' => $id], '意向单已创建', 201);
    }

    public function update($id)
    {
        $data = $this->requestData();
        $update = array_intersect_key($data, array_flip([
            'customer_name', 'product_name', 'model', 'voltage', 'quantity',
            'customer_requirements', 'status', 'expected_close_date',
        ]));
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
        return $this->success(['item' => $item]);
    }
}
