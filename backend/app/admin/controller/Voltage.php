<?php

namespace app\admin\controller;

use app\common\controller\AdminApiController;
use think\facade\Db;
use think\facade\Request;

class Voltage extends AdminApiController
{
    public function index()
    {
        $keyword = trim((string)Request::get('keyword', ''));
        $query = Db::table('voltages')
            ->order('sort_order', 'asc')
            ->order('id', 'desc');
        
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->whereLike('label', "%{$keyword}%")
                    ->whereOr('value', 'like', "%{$keyword}%");
            });
        }
        
        $items = $query->select()->toArray();
        
        return $this->success([
            'items' => array_map([$this, 'formatVoltage'], $items),
        ]);
    }

    public function save()
    {
        $payload = $this->requestData();
        $label = trim((string)($payload['label'] ?? ''));
        $value = trim((string)($payload['value'] ?? ''));
        
        if ($label === '' || $value === '') {
            $this->errorResponse('请填写名称和取值');
        }
        
        $exists = Db::table('voltages')->where('value', $value)->find();
        if ($exists) {
            $this->errorResponse('该取值已存在');
        }
        
        $now = date('Y-m-d H:i:s');
        $id = Db::table('voltages')->insertGetId([
            'label'         => $label,
            'value'         => $value,
            'description'   => $payload['description'] ?? null,
            'sort_order'    => isset($payload['sort_order']) ? (int)$payload['sort_order'] : 0,
            'status'        => isset($payload['status']) ? (int)$payload['status'] : 1,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);
        
        $voltage = Db::table('voltages')->find($id);
        return $this->success([
            'voltage' => $this->formatVoltage($voltage),
        ], '电压已创建');
    }

    public function update(int $id)
    {
        $voltage = Db::table('voltages')->find($id);
        if (!$voltage) {
            $this->errorResponse('电压不存在', 404);
        }
        
        $payload = $this->requestData();
        $data = [];
        
        if (array_key_exists('label', $payload)) {
            $label = trim((string)$payload['label']);
            if ($label === '') {
                $this->errorResponse('名称不能为空');
            }
            $data['label'] = $label;
        }
        
        if (array_key_exists('value', $payload)) {
            $value = trim((string)$payload['value']);
            if ($value === '') {
                $this->errorResponse('取值不能为空');
            }
            $exists = Db::table('voltages')
                ->where('value', $value)
                ->where('id', '<>', $id)
                ->find();
            if ($exists) {
                $this->errorResponse('该取值已存在');
            }
            $data['value'] = $value;
        }
        
        if (array_key_exists('description', $payload)) {
            $data['description'] = $payload['description'] ?: null;
        }
        
        if (array_key_exists('sort_order', $payload)) {
            $data['sort_order'] = (int)$payload['sort_order'];
        }
        
        if (array_key_exists('status', $payload)) {
            $data['status'] = (int)$payload['status'] ? 1 : 0;
        }
        
        if (!$data) {
            return $this->success([
                'voltage' => $this->formatVoltage($voltage),
            ]);
        }
        
        $data['updated_at'] = date('Y-m-d H:i:s');
        Db::table('voltages')->where('id', $id)->update($data);
        
        $voltage = Db::table('voltages')->find($id);
        return $this->success([
            'voltage' => $this->formatVoltage($voltage),
        ], '电压已更新');
    }

    public function delete(int $id)
    {
        $voltage = Db::table('voltages')->find($id);
        if (!$voltage) {
            $this->errorResponse('电压不存在', 404);
        }
        
        Db::table('voltages')->where('id', $id)->delete();
        return $this->success([], '电压已删除');
    }

    protected function formatVoltage(array $voltage): array
    {
        return [
            'id'            => (int)$voltage['id'],
            'label'         => $voltage['label'],
            'value'         => $voltage['value'],
            'description'   => $voltage['description'],
            'sort_order'    => (int)$voltage['sort_order'],
            'status'        => (int)$voltage['status'],
            'created_at'    => $voltage['created_at'],
            'updated_at'    => $voltage['updated_at'],
        ];
    }
}
