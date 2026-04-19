<?php

namespace app\api\controller;

use app\common\controller\ApiController;
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
