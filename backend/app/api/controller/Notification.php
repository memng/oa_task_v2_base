<?php

namespace app\api\controller;

use app\common\controller\ApiController;
use think\facade\Db;
use think\facade\Request;

class Notification extends ApiController
{
    public function index()
    {
        $status = Request::get('status', 'all');
        $channel = Request::get('channel');
        $keyword = trim((string)Request::get('keyword', ''));
        $limit = (int)Request::get('limit', 20);
        $query = Db::table('notifications')
            ->where('user_id', $this->user()['id']);
        if ($status === 'unread') {
            $query->whereNull('read_at');
        } elseif ($status === 'read') {
            $query->whereNotNull('read_at');
        }
        if ($channel) {
            $query->where('channel', $channel);
        }
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->whereLike('title', "%{$keyword}%")
                    ->whereOr('content', 'like', "%{$keyword}%");
            });
        }
        $query->order('created_at', 'desc')
            ->order('id', 'desc');
        if ($limit > 0) {
            $query->limit($limit);
        }
        $items = $query->select()->toArray();
        $items = array_map(function ($item) {
            $item['is_read'] = !empty($item['read_at']);
            if (!empty($item['payload']) && is_string($item['payload'])) {
                $decoded = json_decode($item['payload'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $item['payload'] = $decoded;
                }
            }
            return $item;
        }, $items);
        return $this->success(['items' => $items]);
    }

    public function markRead($id)
    {
        Db::table('notifications')
            ->where('id', $id)
            ->where('user_id', $this->user()['id'])
            ->update(['read_at' => date('Y-m-d H:i:s')]);
        return $this->success([], '已标记为已读');
    }

    public function save()
    {
        $actor = $this->user();
        if (empty($actor['is_admin'])) {
            $this->errorResponse('仅管理员可发送通知', 403);
        }
        $data = $this->requestData();
        if (empty($data['title']) || empty($data['content'])) {
            $this->errorResponse('通知标题与内容不能为空');
        }
        $targets = $this->resolveTargetUserIds($data);
        if (empty($targets)) {
            $this->errorResponse('未找到可推送的用户');
        }
        $channel = $data['channel'] ?? 'system';
        $now = date('Y-m-d H:i:s');
        $payload = null;
        if (!empty($data['payload']) && is_array($data['payload'])) {
            $payload = json_encode($data['payload'], JSON_UNESCAPED_UNICODE);
        }
        $rows = [];
        foreach ($targets as $userId) {
            $rows[] = [
                'user_id'       => $userId,
                'channel'       => $channel,
                'template_code' => $data['template_code'] ?? null,
                'title'         => $data['title'],
                'content'       => $data['content'],
                'payload'       => $payload,
                'status'        => 'sent',
                'created_at'    => $now,
            ];
        }
        Db::table('notifications')->insertAll($rows);

        return $this->success([
            'recipients' => count($rows),
        ], '通知已推送', 201);
    }

    protected function resolveTargetUserIds(array $data): array
    {
        $type = $data['target_type'] ?? 'all';
        if ($type === 'users') {
            $ids = array_filter(array_map('intval', $data['user_ids'] ?? []));
            if (empty($ids)) {
                return [];
            }
            $users = Db::table('users')
                ->where('status', 'active')
                ->whereIn('id', $ids)
                ->field('id')
                ->select()
                ->toArray();
            return array_column($users, 'id');
        }
        if ($type === 'department') {
            $deptId = (int)($data['dept_id'] ?? 0);
            if ($deptId <= 0) {
                return [];
            }
            $users = Db::table('users')
                ->where('status', 'active')
                ->where('dept_id', $deptId)
                ->field('id')
                ->select()
                ->toArray();
            return array_column($users, 'id');
        }
        $users = Db::table('users')
            ->where('status', 'active')
            ->field('id')
            ->select()
            ->toArray();
        return array_column($users, 'id');
    }
}
