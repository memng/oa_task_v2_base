<?php

namespace app\admin\controller;

use app\common\controller\AdminApiController;
use think\facade\Db;
use think\facade\Request;

class Currency extends AdminApiController
{
    public function index()
    {
        $keyword = trim((string)Request::get('keyword', ''));
        $query = Db::table('currencies')
            ->order('sort_order', 'asc')
            ->order('id', 'desc');
        
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->whereLike('code', "%{$keyword}%")
                    ->whereOr('name', 'like', "%{$keyword}%");
            });
        }
        
        $items = $query->select()->toArray();
        
        return $this->success([
            'items' => array_map([$this, 'formatCurrency'], $items),
        ]);
    }

    public function save()
    {
        $payload = $this->requestData();
        $code = trim((string)($payload['code'] ?? ''));
        $name = trim((string)($payload['name'] ?? ''));
        
        if ($code === '' || $name === '') {
            $this->errorResponse('请完善代码和名称');
        }
        
        $exists = Db::table('currencies')->where('code', $code)->find();
        if ($exists) {
            $this->errorResponse('该币种代码已存在');
        }
        
        $now = date('Y-m-d H:i:s');
        $isDefault = !empty($payload['is_default']) ? 1 : 0;
        
        if ($isDefault) {
            Db::table('currencies')->where('is_default', 1)->update(['is_default' => 0]);
        }
        
        $id = Db::table('currencies')->insertGetId([
            'code'          => $code,
            'name'          => $name,
            'symbol'        => $payload['symbol'] ?? null,
            'sort_order'    => isset($payload['sort_order']) ? (int)$payload['sort_order'] : 0,
            'is_default'    => $isDefault,
            'status'        => isset($payload['status']) ? (int)$payload['status'] : 1,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);
        
        $currency = Db::table('currencies')->find($id);
        return $this->success([
            'currency' => $this->formatCurrency($currency),
        ], '币种已创建');
    }

    public function update(int $id)
    {
        $currency = Db::table('currencies')->find($id);
        if (!$currency) {
            $this->errorResponse('币种不存在', 404);
        }
        
        $payload = $this->requestData();
        $data = [];
        
        if (array_key_exists('code', $payload)) {
            $code = trim((string)$payload['code']);
            if ($code === '') {
                $this->errorResponse('代码不能为空');
            }
            $exists = Db::table('currencies')
                ->where('code', $code)
                ->where('id', '<>', $id)
                ->find();
            if ($exists) {
                $this->errorResponse('该币种代码已存在');
            }
            $data['code'] = $code;
        }
        
        if (array_key_exists('name', $payload)) {
            $name = trim((string)$payload['name']);
            if ($name === '') {
                $this->errorResponse('名称不能为空');
            }
            $data['name'] = $name;
        }
        
        if (array_key_exists('symbol', $payload)) {
            $data['symbol'] = $payload['symbol'] ?: null;
        }
        
        if (array_key_exists('sort_order', $payload)) {
            $data['sort_order'] = (int)$payload['sort_order'];
        }
        
        if (array_key_exists('is_default', $payload)) {
            $isDefault = (int)$payload['is_default'] ? 1 : 0;
            if ($isDefault) {
                Db::table('currencies')->where('is_default', 1)->update(['is_default' => 0]);
            }
            $data['is_default'] = $isDefault;
        }
        
        if (array_key_exists('status', $payload)) {
            $data['status'] = (int)$payload['status'] ? 1 : 0;
        }
        
        if (!$data) {
            return $this->success([
                'currency' => $this->formatCurrency($currency),
            ]);
        }
        
        $data['updated_at'] = date('Y-m-d H:i:s');
        Db::table('currencies')->where('id', $id)->update($data);
        
        $currency = Db::table('currencies')->find($id);
        return $this->success([
            'currency' => $this->formatCurrency($currency),
        ], '币种已更新');
    }

    public function delete(int $id)
    {
        $currency = Db::table('currencies')->find($id);
        if (!$currency) {
            $this->errorResponse('币种不存在', 404);
        }
        
        Db::table('currencies')->where('id', $id)->delete();
        return $this->success([], '币种已删除');
    }

    protected function formatCurrency(array $currency): array
    {
        return [
            'id'            => (int)$currency['id'],
            'code'          => $currency['code'],
            'name'          => $currency['name'],
            'symbol'        => $currency['symbol'],
            'sort_order'    => (int)$currency['sort_order'],
            'is_default'    => (int)$currency['is_default'],
            'status'        => (int)$currency['status'],
            'created_at'    => $currency['created_at'],
            'updated_at'    => $currency['updated_at'],
        ];
    }
}
