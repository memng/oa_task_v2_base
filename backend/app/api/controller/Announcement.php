<?php

namespace app\api\controller;

use app\common\controller\ApiController;
use app\common\service\SecurityService;
use think\facade\Db;
use think\facade\Request;

class Announcement extends ApiController
{
    public function index()
    {
        $userId = (int)$this->user()['id'];
        $userDeptId = isset($this->user()['dept_id']) ? (int)$this->user()['dept_id'] : null;
        $category = Request::get('category');
        $keyword = trim((string)Request::get('keyword', ''));
        $onlyUnread = (int)Request::get('only_unread', 0);
        $limit = (int)Request::get('limit', 20);

        $query = Db::table('announcements')->alias('a')
            ->leftJoin('announcement_reads ar', 'ar.announcement_id = a.id AND ar.user_id = ' . $userId)
            ->where('a.publish_status', 'published');

        $query->where(function ($q) use ($userDeptId) {
            $q->whereNotExists(function ($subQ) {
                $subQ->table('announcement_targets')
                    ->whereColumn('announcement_id', 'a.id');
            });
            if ($userDeptId) {
                $q->whereOrExists(function ($subQ) use ($userDeptId) {
                    $subQ->table('announcement_targets')
                        ->whereColumn('announcement_id', 'a.id')
                        ->where('dept_id', $userDeptId);
                });
            }
        });

        if ($category) {
            $query->where('a.category', $category);
        }
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->whereLike('a.title', "%{$keyword}%")
                    ->whereOr('a.content', 'like', "%{$keyword}%");
            });
        }
        if ($onlyUnread) {
            $query->whereNull('ar.id');
        }

        $query->field([
            'a.*',
            'ar.read_at as read_at',
            'ar.id as read_id',
        ])
            ->order('a.published_at', 'desc')
            ->order('a.id', 'desc');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $items = $query->select()->toArray();
        $items = array_map(function ($item) {
            $item['is_read'] = !empty($item['read_at']);
            return $item;
        }, $items);

        return $this->success(['items' => $items]);
    }

    public function save()
    {
        if ($this->user()['status'] !== 'active') {
            $this->errorResponse('无权发布公告', 403);
        }
        $data = $this->requestData();
        if (empty($data['title']) || empty($data['content'])) {
            $this->errorResponse('标题与内容不能为空');
        }

        $data = SecurityService::sanitizeArray($data, ['title', 'content']);

        $status = $data['publish_status'] ?? 'draft';
        $id = Db::table('announcements')->insertGetId([
            'title'          => $data['title'],
            'content'        => $data['content'],
            'category'       => $data['category'] ?? 'general',
            'publish_status' => $status,
            'allow_comments' => $data['allow_comments'] ?? 0,
            'published_at'   => $status === 'published' ? date('Y-m-d H:i:s') : null,
            'created_by'     => $this->user()['id'],
            'created_at'     => date('Y-m-d H:i:s'),
        ]);

        $deptIds = $data['dept_ids'] ?? [];
        if (!empty($deptIds) && is_array($deptIds)) {
            $targetData = [];
            foreach ($deptIds as $deptId) {
                $targetData[] = [
                    'announcement_id' => $id,
                    'dept_id'         => (int)$deptId,
                    'role_id'         => null,
                ];
            }
            Db::table('announcement_targets')->insertAll($targetData);
        }

        return $this->success(['id' => $id], '公告已保存', 201);
    }

    public function markRead($id)
    {
        $announcement = Db::table('announcements')
            ->where('id', $id)
            ->where('publish_status', 'published')
            ->find();
        if (!$announcement) {
            $this->errorResponse('公告不存在', 404);
        }
        $userId = (int)$this->user()['id'];
        $existing = Db::table('announcement_reads')
            ->where('announcement_id', $id)
            ->where('user_id', $userId)
            ->find();
        if ($existing) {
            if (empty($existing['read_at'])) {
                Db::table('announcement_reads')
                    ->where('id', $existing['id'])
                    ->update(['read_at' => date('Y-m-d H:i:s')]);
            }
            return $this->success([], '已标记为已读');
        }
        Db::table('announcement_reads')->insert([
            'announcement_id' => $id,
            'user_id'         => $userId,
            'read_at'         => date('Y-m-d H:i:s'),
        ]);

        return $this->success([], '已标记为已读');
    }
}
