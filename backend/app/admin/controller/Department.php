<?php

namespace app\admin\controller;

use app\common\controller\AdminApiController;
use think\facade\Db;

class Department extends AdminApiController
{
    public function index()
    {
        $departments = Db::table('departments')
            ->order('sort_order', 'asc')
            ->order('id', 'asc')
            ->select()
            ->toArray();

        return $this->success([
            'items' => array_map([$this, 'formatDepartment'], $departments),
        ]);
    }

    public function save()
    {
        $payload = $this->requestData();
        $name = trim((string)($payload['name'] ?? ''));
        if ($name === '') {
            $this->errorResponse('请输入部门名称');
        }
        $code = trim((string)($payload['code'] ?? ''));
        if ($code !== '') {
            $this->ensureCodeUnique($code, null);
        }
        $parentId = $this->normalizeParentId($payload['parent_id'] ?? null);

        $now = date('Y-m-d H:i:s');
        $id = Db::table('departments')->insertGetId([
            'name'       => $name,
            'code'       => $code ?: null,
            'type'       => $this->normalizeType($payload['type'] ?? null),
            'parent_id'  => $parentId,
            'sort_order' => (int)($payload['sort_order'] ?? 0),
            'status'     => (int)($payload['status'] ?? 1),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $department = Db::table('departments')->find($id);
        return $this->success([
            'department' => $this->formatDepartment($department),
        ], '部门已创建');
    }

    public function update(int $id)
    {
        $department = Db::table('departments')->find($id);
        if (!$department) {
            $this->errorResponse('部门不存在');
        }
        $payload = $this->requestData();
        $data = [];

        if (array_key_exists('name', $payload)) {
            $name = trim((string)$payload['name']);
            if ($name === '') {
                $this->errorResponse('部门名称不能为空');
            }
            $data['name'] = $name;
        }

        if (array_key_exists('code', $payload)) {
            $code = trim((string)$payload['code']);
            if ($code !== '') {
                $this->ensureCodeUnique($code, $id);
                $data['code'] = $code;
            } else {
                $data['code'] = null;
            }
        }

        if (array_key_exists('type', $payload)) {
            $data['type'] = $this->normalizeType($payload['type']);
        }

        if (array_key_exists('sort_order', $payload)) {
            $data['sort_order'] = (int)$payload['sort_order'];
        }

        if (array_key_exists('status', $payload)) {
            $data['status'] = (int)$payload['status'] ? 1 : 0;
        }

        if (array_key_exists('parent_id', $payload)) {
            $parentId = $this->normalizeParentId($payload['parent_id']);
            if ($parentId && $parentId === $id) {
                $this->errorResponse('上级部门不能为自身');
            }
            $data['parent_id'] = $parentId;
        }

        if (empty($data)) {
            return $this->success([
                'department' => $this->formatDepartment($department),
            ], '无需更新');
        }

        $data['updated_at'] = date('Y-m-d H:i:s');
        Db::table('departments')->where('id', $id)->update($data);
        $department = Db::table('departments')->find($id);
        return $this->success([
            'department' => $this->formatDepartment($department),
        ], '部门已更新');
    }

    public function delete(int $id)
    {
        $department = Db::table('departments')->find($id);
        if (!$department) {
            $this->errorResponse('部门不存在');
        }

        Db::table('departments')->where('parent_id', $id)->update(['parent_id' => null]);
        Db::table('users')->where('dept_id', $id)->update(['dept_id' => null]);
        Db::table('departments')->where('id', $id)->delete();

        return $this->success([], '部门已删除');
    }

    protected function formatDepartment(array $dept): array
    {
        return [
            'id'         => (int)$dept['id'],
            'name'       => $dept['name'],
            'code'       => $dept['code'],
            'type'       => $dept['type'],
            'parent_id'  => $dept['parent_id'] ? (int)$dept['parent_id'] : null,
            'sort_order' => (int)$dept['sort_order'],
            'status'     => (int)$dept['status'],
        ];
    }

    protected function normalizeType(?string $type): string
    {
        $allowed = ['sales', 'factory', 'finance', 'operation', 'other'];
        $type = $type ? strtolower($type) : 'other';
        return in_array($type, $allowed, true) ? $type : 'other';
    }

    protected function normalizeParentId($parentId): ?int
    {
        $parentId = $parentId !== null ? (int)$parentId : null;
        if (empty($parentId)) {
            return null;
        }
        $parent = Db::table('departments')->find($parentId);
        if (!$parent) {
            $this->errorResponse('上级部门不存在');
        }
        return (int)$parent['id'];
    }

    protected function ensureCodeUnique(string $code, ?int $excludeId): void
    {
        $query = Db::table('departments')->where('code', $code);
        if (!empty($excludeId)) {
            $query->where('id', '<>', $excludeId);
        }
        $exists = $query->find();
        if ($exists) {
            $this->errorResponse('部门编码已存在');
        }
    }
}
