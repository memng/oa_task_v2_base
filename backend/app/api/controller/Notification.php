<?php

namespace app\api\controller;

use app\common\controller\ApiController;
use app\common\service\NotificationService;
use think\facade\Db;
use think\facade\Request;

class Notification extends ApiController
{
    public function index()
    {
        $status = Request::get('status', 'all');
        $channel = Request::get('channel');
        $businessType = Request::get('business_type');
        $keyword = trim((string)Request::get('keyword', ''));
        $limit = (int)Request::get('limit', 20);
        $offset = (int)Request::get('offset', 0);
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
        if ($businessType && $businessType !== 'all') {
            $taskTemplateCodes = NotificationService::TASK_TEMPLATE_CODES;
            $approvalTemplateCodes = NotificationService::APPROVAL_TEMPLATE_CODES;

            if ($businessType === NotificationService::BUSINESS_TYPE_TASK) {
                $query->where(function ($q) use ($taskTemplateCodes) {
                    $q->whereIn('template_code', $taskTemplateCodes)
                        ->whereOr('template_code', 'like', '%task%')
                        ->whereOr(Db::raw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.type'))"), 'like', '%task%')
                        ->whereOr(Db::raw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.type'))"), '=', 'order_created');
                });
            } elseif ($businessType === NotificationService::BUSINESS_TYPE_APPROVAL) {
                $query->where(function ($q) use ($approvalTemplateCodes) {
                    $q->whereIn('template_code', $approvalTemplateCodes)
                        ->whereOr('template_code', 'like', '%leave%')
                        ->whereOr('template_code', 'like', '%reimburse%')
                        ->whereOr(Db::raw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.type'))"), 'like', '%leave%')
                        ->whereOr(Db::raw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.type'))"), 'like', '%reimburse%');
                });
            } elseif ($businessType === NotificationService::BUSINESS_TYPE_SYSTEM) {
                $query->whereNot(function ($q) use ($taskTemplateCodes, $approvalTemplateCodes) {
                    $q->where(function ($subQ) use ($taskTemplateCodes) {
                        $subQ->whereIn('template_code', $taskTemplateCodes)
                            ->whereOr('template_code', 'like', '%task%')
                            ->whereOr(Db::raw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.type'))"), 'like', '%task%')
                            ->whereOr(Db::raw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.type'))"), '=', 'order_created');
                    })->whereOr(function ($subQ) use ($approvalTemplateCodes) {
                        $subQ->whereIn('template_code', $approvalTemplateCodes)
                            ->whereOr('template_code', 'like', '%leave%')
                            ->whereOr('template_code', 'like', '%reimburse%')
                            ->whereOr(Db::raw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.type'))"), 'like', '%leave%')
                            ->whereOr(Db::raw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.type'))"), 'like', '%reimburse%');
                    });
                });
            }
        }
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->whereLike('title', "%{$keyword}%")
                    ->whereOr('content', 'like', "%{$keyword}%");
            });
        }
        $total = $query->count();
        $query->order('created_at', 'desc')
            ->order('id', 'desc');
        if ($limit > 0) {
            $query->limit($offset, $limit);
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
        return $this->success([
            'items' => $items,
            'total' => $total,
            'offset' => $offset,
            'limit' => $limit,
        ]);
    }

    public function markRead($id)
    {
        Db::table('notifications')
            ->where('id', $id)
            ->where('user_id', $this->user()['id'])
            ->update(['read_at' => date('Y-m-d H:i:s')]);
        return $this->success([], '已标记为已读');
    }

    public function markAllRead()
    {
        $userId = (int)$this->user()['id'];
        $now = date('Y-m-d H:i:s');
        $affected = Db::table('notifications')
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => $now]);
        return $this->success([
            'affected' => (int)$affected,
        ], '已全部标记为已读');
    }

    public function markGroupRead()
    {
        $userId = (int)$this->user()['id'];
        $businessType = Request::post('business_type');
        $ids = Request::post('ids');

        $hasBusinessType = !empty($businessType) && $businessType !== 'all';
        $hasIds = !empty($ids) && is_array($ids) && !empty(array_filter(array_map('intval', $ids)));
        if (!$hasBusinessType && !$hasIds) {
            return $this->errorResponse('至少需要指定 business_type 或 ids');
        }

        $now = date('Y-m-d H:i:s');

        $query = Db::table('notifications')
            ->where('user_id', $userId)
            ->whereNull('read_at');

        if ($hasBusinessType) {
            $taskTemplateCodes = NotificationService::TASK_TEMPLATE_CODES;
            $approvalTemplateCodes = NotificationService::APPROVAL_TEMPLATE_CODES;

            if ($businessType === NotificationService::BUSINESS_TYPE_TASK) {
                $query->where(function ($q) use ($taskTemplateCodes) {
                    $q->whereIn('template_code', $taskTemplateCodes)
                        ->whereOr('template_code', 'like', '%task%')
                        ->whereOr(Db::raw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.type'))"), 'like', '%task%')
                        ->whereOr(Db::raw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.type'))"), '=', 'order_created');
                });
            } elseif ($businessType === NotificationService::BUSINESS_TYPE_APPROVAL) {
                $query->where(function ($q) use ($approvalTemplateCodes) {
                    $q->whereIn('template_code', $approvalTemplateCodes)
                        ->whereOr('template_code', 'like', '%leave%')
                        ->whereOr('template_code', 'like', '%reimburse%')
                        ->whereOr(Db::raw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.type'))"), 'like', '%leave%')
                        ->whereOr(Db::raw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.type'))"), 'like', '%reimburse%');
                });
            } elseif ($businessType === NotificationService::BUSINESS_TYPE_SYSTEM) {
                $query->where(function ($q) use ($taskTemplateCodes, $approvalTemplateCodes) {
                    $q->whereNot(function ($subQ) use ($taskTemplateCodes, $approvalTemplateCodes) {
                        $subQ->where(function ($subA) use ($taskTemplateCodes) {
                            $subA->whereIn('template_code', $taskTemplateCodes)
                                ->whereOr('template_code', 'like', '%task%')
                                ->whereOr(Db::raw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.type'))"), 'like', '%task%')
                                ->whereOr(Db::raw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.type'))"), '=', 'order_created');
                        })->whereOr(function ($subA) use ($approvalTemplateCodes) {
                            $subA->whereIn('template_code', $approvalTemplateCodes)
                                ->whereOr('template_code', 'like', '%leave%')
                                ->whereOr('template_code', 'like', '%reimburse%')
                                ->whereOr(Db::raw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.type'))"), 'like', '%leave%')
                                ->whereOr(Db::raw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.type'))"), 'like', '%reimburse%');
                        });
                    });
                });
            }
        }

        if (!empty($ids) && is_array($ids)) {
            $idList = array_values(array_filter(array_map('intval', $ids)));
            if (!empty($idList)) {
                $query->whereIn('id', $idList);
            }
        }

        $affected = $query->update(['read_at' => $now]);
        return $this->success([
            'affected' => (int)$affected,
            'business_type' => $businessType,
        ], '已标记为已读');
    }

    public function readSummary()
    {
        $userId = (int)$this->user()['id'];
        $totalUnread = (int)Db::table('notifications')
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->count();
        $taskUnread = $this->countBusinessUnread($userId, NotificationService::BUSINESS_TYPE_TASK);
        $approvalUnread = $this->countBusinessUnread($userId, NotificationService::BUSINESS_TYPE_APPROVAL);
        $systemUnread = max(0, $totalUnread - $taskUnread - $approvalUnread);

        return $this->success([
            'total' => $totalUnread,
            'task' => $taskUnread,
            'approval' => $approvalUnread,
            'system' => $systemUnread,
        ]);
    }

    protected function countBusinessUnread(int $userId, string $businessType): int
    {
        $taskTemplateCodes = NotificationService::TASK_TEMPLATE_CODES;
        $approvalTemplateCodes = NotificationService::APPROVAL_TEMPLATE_CODES;

        $query = Db::table('notifications')
            ->where('user_id', $userId)
            ->whereNull('read_at');

        if ($businessType === NotificationService::BUSINESS_TYPE_TASK) {
            $query->where(function ($q) use ($taskTemplateCodes) {
                $q->whereIn('template_code', $taskTemplateCodes)
                    ->whereOr('template_code', 'like', '%task%')
                    ->whereOr(Db::raw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.type'))"), 'like', '%task%')
                    ->whereOr(Db::raw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.type'))"), '=', 'order_created');
            });
        } elseif ($businessType === NotificationService::BUSINESS_TYPE_APPROVAL) {
            $query->where(function ($q) use ($approvalTemplateCodes) {
                $q->whereIn('template_code', $approvalTemplateCodes)
                    ->whereOr('template_code', 'like', '%leave%')
                    ->whereOr('template_code', 'like', '%reimburse%')
                    ->whereOr(Db::raw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.type'))"), 'like', '%leave%')
                    ->whereOr(Db::raw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.type'))"), 'like', '%reimburse%');
            });
        } else {
            return 0;
        }
        return (int)$query->count();
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
