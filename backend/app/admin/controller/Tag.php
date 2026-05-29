<?php

namespace app\admin\controller;

use app\common\service\TagService;
use think\facade\Db;
use think\facade\Request;

class Tag extends BaseAdminController
{
    protected $tagService;

    public function __construct()
    {
        parent::__construct();
        $this->tagService = new TagService();
    }

    public function index()
    {
        $list = TagService::getTagList(true);
        return $this->success([
            'items' => $list,
            'total' => count($list),
        ]);
    }

    public function create()
    {
        $data = $this->requestData();

        if (empty($data['key'])) {
            return $this->errorResponse('标签键名不能为空');
        }
        if (!preg_match('/^[a-z_][a-z0-9_]*$/', $data['key'])) {
            return $this->errorResponse('标签键名只能包含小写字母、数字和下划线，且必须以字母或下划线开头');
        }
        if (empty($data['label'])) {
            return $this->errorResponse('标签名称不能为空');
        }
        if (!empty($data['color']) && !preg_match('/^#[0-9a-fA-F]{6}$/', $data['color'])) {
            return $this->errorResponse('颜色格式不正确，请使用 HEX 格式（如 #ff4d4f）');
        }

        $exists = Db::table('tags')->where('key', $data['key'])->find();
        if ($exists) {
            return $this->errorResponse('标签键名已存在');
        }

        try {
            $tagId = $this->tagService->createTag([
                'key' => $data['key'],
                'label' => $data['label'],
                'color' => $data['color'] ?? '#1677ff',
                'sort' => $data['sort'] ?? 0,
            ]);
            return $this->success(['id' => $tagId], '标签创建成功', 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function update($id)
    {
        $id = (int)$id;
        if ($id <= 0) {
            return $this->errorResponse('标签ID无效');
        }

        $data = $this->requestData();

        if (isset($data['key']) && !preg_match('/^[a-z_][a-z0-9_]*$/', $data['key'])) {
            return $this->errorResponse('标签键名只能包含小写字母、数字和下划线，且必须以字母或下划线开头');
        }
        if (isset($data['color']) && !preg_match('/^#[0-9a-fA-F]{6}$/', $data['color'])) {
            return $this->errorResponse('颜色格式不正确，请使用 HEX 格式（如 #ff4d4f）');
        }

        try {
            $updateData = [];
            if (isset($data['label'])) {
                $updateData['label'] = $data['label'];
            }
            if (isset($data['color'])) {
                $updateData['color'] = $data['color'];
            }
            if (isset($data['sort'])) {
                $updateData['sort'] = (int)$data['sort'];
            }
            if (isset($data['key'])) {
                $updateData['key'] = $data['key'];
            }

            $this->tagService->updateTag($id, $updateData);
            return $this->success([], '标签更新成功');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function delete($id)
    {
        $id = (int)$id;
        if ($id <= 0) {
            return $this->errorResponse('标签ID无效');
        }

        try {
            $this->tagService->deleteTag($id);
            return $this->success([], '标签删除成功');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
}
