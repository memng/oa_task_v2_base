<?php

namespace app\api\controller;

use app\common\controller\ApiController;
use app\common\service\NotificationService;
use think\facade\Db;

class Message extends ApiController
{
    public function unreadCount()
    {
        $userId = (int)$this->user()['id'];

        $notificationCount = (int)Db::table('notifications')
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->count();

        $taskTemplateCodes = NotificationService::TASK_TEMPLATE_CODES;
        $approvalTemplateCodes = NotificationService::APPROVAL_TEMPLATE_CODES;

        $taskUnread = (int)Db::table('notifications')
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->where(function ($query) use ($taskTemplateCodes) {
                $query->whereIn('template_code', $taskTemplateCodes)
                    ->whereOr('template_code', 'like', '%task%')
                    ->whereOr(Db::raw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.type'))"), 'like', '%task%')
                    ->whereOr(Db::raw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.type'))"), '=', 'order_created');
            })
            ->count();

        $approvalUnread = (int)Db::table('notifications')
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->where(function ($query) use ($approvalTemplateCodes) {
                $query->whereIn('template_code', $approvalTemplateCodes)
                    ->whereOr('template_code', 'like', '%leave%')
                    ->whereOr('template_code', 'like', '%reimburse%')
                    ->whereOr(Db::raw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.type'))"), 'like', '%leave%')
                    ->whereOr(Db::raw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.type'))"), 'like', '%reimburse%');
            })
            ->count();

        $systemUnread = $notificationCount - $taskUnread - $approvalUnread;
        if ($systemUnread < 0) {
            $systemUnread = 0;
        }

        $announcementCount = (int)Db::table('announcements')->alias('a')
            ->leftJoin('announcement_reads ar', 'ar.announcement_id = a.id AND ar.user_id = ' . $userId)
            ->where('a.publish_status', 'published')
            ->whereNull('ar.id')
            ->count();

        $memberships = Db::table('chat_members')->alias('cm')
            ->leftJoin('chat_rooms r', 'r.id = cm.room_id')
            ->where('cm.user_id', $userId)
            ->field([
                'cm.room_id',
                'cm.last_read_message_id',
                'r.type as room_type',
            ])
            ->select()
            ->toArray();

        $chatCounts = [
            'total'  => 0,
            'direct' => 0,
            'group'  => 0,
        ];
        foreach ($memberships as $membership) {
            $roomId = (int)$membership['room_id'];
            $lastRead = (int)($membership['last_read_message_id'] ?? 0);
            $unread = $this->countUnreadMessages($roomId, $lastRead);
            $chatCounts['total'] += $unread;
            $bucket = ($membership['room_type'] ?? 'direct') === 'group' ? 'group' : 'direct';
            $chatCounts[$bucket] += $unread;
        }

        return $this->success([
            'total'         => $notificationCount + $announcementCount + $chatCounts['total'],
            'notifications' => [
                'personal'     => $notificationCount,
                'announcements'=> $announcementCount,
                'by_business'  => [
                    'task'     => $taskUnread,
                    'approval' => $approvalUnread,
                    'system'   => $systemUnread,
                ],
            ],
            'chats'         => $chatCounts,
        ]);
    }

    protected function countUnreadMessages(int $roomId, int $lastReadId): int
    {
        if ($roomId <= 0) {
            return 0;
        }
        $query = Db::table('chat_messages')
            ->where('room_id', $roomId);
        if ($lastReadId > 0) {
            $query->where('id', '>', $lastReadId);
        }
        return (int)$query->count();
    }
}
