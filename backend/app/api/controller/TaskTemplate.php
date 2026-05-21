<?php

namespace app\api\controller;

use app\common\controller\ApiController;
use app\common\service\TaskTemplateService;
use think\facade\Request;

class TaskTemplate extends ApiController
{
    protected TaskTemplateService $service;

    protected function initialize()
    {
        parent::initialize();
        $this->service = new TaskTemplateService();
    }

    public function index()
    {
        $user = $this->user();
        $isAdmin = \user_belongs_to_admin_dept($user);
        $type = Request::get('type');
        $keyword = Request::get('keyword');
        $items = $this->service->list((int)$user['id'], $isAdmin, $type, $keyword);
        return $this->success(['items' => $items]);
    }

    public function read($id)
    {
        $user = $this->user();
        $isAdmin = \user_belongs_to_admin_dept($user);
        $item = $this->service->read((int)$id, (int)$user['id'], $isAdmin);
        if (!$item) {
            $this->errorResponse('模板不存在或无权限查看', 404);
        }
        return $this->success(['item' => $item]);
    }

    public function save()
    {
        $data = $this->requestData();
        if (empty($data['name']) || empty($data['title'])) {
            $this->errorResponse('模板名称和任务标题不能为空');
        }
        $user = $this->user();
        $payload = [
            'name'        => trim((string)$data['name']),
            'type'        => $data['type'] ?? 'procurement',
            'title'       => trim((string)$data['title']),
            'description' => $data['description'] ?? '',
            'assigned_to' => $data['assigned_to'] ?? null,
            'need_audit'  => $data['need_audit'] ?? 0,
            'extra'       => $data['extra'] ?? null,
            'is_global'   => $data['is_global'] ?? 0,
        ];
        $id = $this->service->create($payload, (int)$user['id']);
        return $this->success(['id' => $id], '模板已创建', 201);
    }

    public function update($id)
    {
        $data = $this->requestData();
        $user = $this->user();
        $isAdmin = \user_belongs_to_admin_dept($user);
        $ok = $this->service->update((int)$id, $data, (int)$user['id'], $isAdmin);
        if (!$ok) {
            $this->errorResponse('模板不存在或无权限修改', 404);
        }
        return $this->success([], '模板已更新');
    }

    public function delete($id)
    {
        $user = $this->user();
        $isAdmin = \user_belongs_to_admin_dept($user);
        $ok = $this->service->delete((int)$id, (int)$user['id'], $isAdmin);
        if (!$ok) {
            $this->errorResponse('模板不存在或无权限删除', 404);
        }
        return $this->success([], '模板已删除');
    }
}
