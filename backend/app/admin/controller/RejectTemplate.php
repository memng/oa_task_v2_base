<?php

namespace app\admin\controller;

use app\common\controller\AdminApiController;
use think\facade\Db;
use think\facade\Request;

class RejectTemplate extends AdminApiController
{
    public function index()
    {
        $isActive = Request::param('is_active');
        $query = Db::table('reject_templates')->order('sort_order', 'asc')->order('id', 'asc');
        if ($isActive !== null && $isActive !== '') {
            $query->where('is_active', (int)$isActive);
        }
        $items = $query->select()->toArray();
        return $this->success([
            'items' => array_map(fn($item) => $this->formatTemplate($item), $items),
        ]);
    }

    public function active()
    {
        $items = Db::table('reject_templates')
            ->where('is_active', 1)
            ->order('sort_order', 'asc')
            ->order('id', 'asc')
            ->select()
            ->toArray();
        return $this->success([
            'items' => array_map(fn($item) => $this->formatTemplate($item), $items),
        ]);
    }

    public function store()
    {
        $payload = $this->requestData();
        $content = trim((string)($payload['content'] ?? ''));
        $sortOrder = (int)($payload['sort_order'] ?? 0);

        if (empty($content)) {
            $this->errorResponse('模板内容不能为空');
        }
        if (mb_strlen($content) > 500) {
            $this->errorResponse('模板内容不能超过500个字符');
        }

        $now = date('Y-m-d H:i:s');
        Db::table('reject_templates')->insert([
            'content' => $content,
            'sort_order' => $sortOrder,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->success([], '模板创建成功');
    }

    public function update(int $id)
    {
        $template = Db::table('reject_templates')->find($id);
        if (!$template) {
            $this->errorResponse('模板不存在');
        }

        $payload = $this->requestData();
        $updates = [];

        if (isset($payload['content'])) {
            $content = trim((string)$payload['content']);
            if (empty($content)) {
                $this->errorResponse('模板内容不能为空');
            }
            if (mb_strlen($content) > 500) {
                $this->errorResponse('模板内容不能超过500个字符');
            }
            $updates['content'] = $content;
        }

        if (isset($payload['sort_order'])) {
            $updates['sort_order'] = (int)$payload['sort_order'];
        }

        if (isset($payload['is_active'])) {
            $updates['is_active'] = (int)$payload['is_active'] ? 1 : 0;
        }

        if (empty($updates)) {
            return $this->success([
                'item' => $this->formatTemplate($template),
            ], '无需更新');
        }

        $updates['updated_at'] = date('Y-m-d H:i:s');
        Db::table('reject_templates')->where('id', $id)->update($updates);

        $updated = Db::table('reject_templates')->find($id);
        return $this->success([
            'item' => $this->formatTemplate($updated),
        ], '模板更新成功');
    }

    public function delete(int $id)
    {
        $template = Db::table('reject_templates')->find($id);
        if (!$template) {
            $this->errorResponse('模板不存在');
        }

        Db::table('reject_templates')->where('id', $id)->delete();
        return $this->success([], '模板已删除');
    }

    protected function formatTemplate(array $template): array
    {
        return [
            'id' => (int)$template['id'],
            'content' => $template['content'],
            'sort_order' => (int)$template['sort_order'],
            'is_active' => (bool)$template['is_active'],
            'created_at' => $template['created_at'],
            'updated_at' => $template['updated_at'],
        ];
    }
}
