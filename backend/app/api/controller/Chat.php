<?php

namespace app\api\controller;

use app\common\controller\ApiController;
use think\facade\Db;
use think\facade\Request;

class Chat extends ApiController
{
    public function conversations()
    {
        $userId = (int)$this->user()['id'];
        $type = Request::get('type');
        $keyword = trim((string)Request::get('keyword', ''));

        $query = Db::table('chat_members')->alias('cm')
            ->leftJoin('chat_rooms r', 'r.id = cm.room_id')
            ->where('cm.user_id', $userId);
        if (in_array($type, ['direct', 'group'], true)) {
            $query->where('r.type', $type);
        }
        $memberships = $query->field([
            'cm.room_id',
            'cm.role',
            'cm.last_read_message_id',
            'r.type as room_type',
            'r.name as room_name',
            'r.created_by',
        ])
            ->select()
            ->toArray();

        if (empty($memberships)) {
            return $this->success(['items' => []]);
        }

        $roomIds = array_column($memberships, 'room_id');
        $participants = Db::table('chat_members')->alias('cm')
            ->leftJoin('users u', 'u.id = cm.user_id')
            ->whereIn('cm.room_id', $roomIds)
            ->field([
                'cm.room_id',
                'cm.user_id',
                'u.name',
                'u.avatar_url',
            ])
            ->select()
            ->toArray();
        $participantMap = [];
        foreach ($participants as $participant) {
            $roomId = (int)$participant['room_id'];
            if (!isset($participantMap[$roomId])) {
                $participantMap[$roomId] = [];
            }
            $participantMap[$roomId][] = [
                'id'     => (int)$participant['user_id'],
                'name'   => $participant['name'] ?? '成员',
                'avatar' => $participant['avatar_url'] ?? null,
            ];
        }

        $items = [];
        foreach ($memberships as $membership) {
            $roomId = (int)$membership['room_id'];
            $roomType = $membership['room_type'] ?? 'direct';
            $members = $participantMap[$roomId] ?? [];
            $displayName = $membership['room_name'] ?: $this->deriveConversationName($roomType, $members, $userId);
            $latest = $this->fetchLatestMessage($roomId);
            $unread = $this->countUnreadMessages($roomId, (int)($membership['last_read_message_id'] ?? 0));
            if ($keyword !== '' && stripos($displayName, $keyword) === false) {
                $matched = false;
                foreach ($members as $member) {
                    if (stripos($member['name'] ?? '', $keyword) !== false) {
                        $matched = true;
                        break;
                    }
                }
                if (!$matched) {
                    continue;
                }
            }
            $items[] = [
                'room_id'     => $roomId,
                'type'        => $roomType,
                'name'        => $displayName,
                'members'     => $members,
                'last_message'=> $latest,
                'unread'      => $unread,
                'last_at'     => $latest['created_at'] ?? null,
            ];
        }

        usort($items, function ($a, $b) {
            $timeA = $a['last_at'] ? strtotime($a['last_at']) : 0;
            $timeB = $b['last_at'] ? strtotime($b['last_at']) : 0;
            return $timeB <=> $timeA;
        });

        return $this->success(['items' => $items]);
    }

    public function create()
    {
        $userId = (int)$this->user()['id'];
        $data = $this->requestData();
        $typeInput = $data['type'] ?? 'direct';
        $type = in_array($typeInput, ['direct', 'group'], true) ? $typeInput : 'direct';
        $memberIds = array_unique(array_filter(array_map('intval', $data['member_ids'] ?? [])));
        $memberIds = array_values(array_filter($memberIds, fn($id) => $id > 0 && $id !== $userId));
        if ($type === 'direct') {
            if (count($memberIds) !== 1) {
                $this->errorResponse('单聊需要选择一位成员');
            }
            $existing = $this->findDirectRoom($userId, $memberIds[0]);
            if ($existing) {
                return $this->success(['room_id' => (int)$existing['room_id'], 'created' => false], '已存在会话');
            }
        } elseif (count($memberIds) === 0) {
            $this->errorResponse('请至少选择一位群成员');
        }

        $roomId = Db::table('chat_rooms')->insertGetId([
            'type'       => $type,
            'name'       => $data['name'] ?? null,
            'created_by' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $members = array_unique(array_merge($memberIds, [$userId]));
        $payload = [];
        foreach ($members as $memberId) {
            $payload[] = [
                'room_id'                 => $roomId,
                'user_id'                 => $memberId,
                'role'                    => $memberId === $userId ? 'owner' : 'member',
                'joined_at'               => date('Y-m-d H:i:s'),
                'last_read_message_id'    => null,
            ];
        }
        Db::table('chat_members')->insertAll($payload);

        return $this->success(['room_id' => $roomId, 'created' => true], '会话已创建', 201);
    }

    public function messages($id)
    {
        $roomId = (int)$id;
        $userId = (int)$this->user()['id'];
        $member = $this->ensureMembership($roomId, $userId);
        $roomType = $member['room_type'] ?? 'direct';
        $limit = (int)Request::get('limit', 50);
        if ($limit <= 0) {
            $limit = 50;
        }
        $messages = Db::table('chat_messages')->alias('m')
            ->leftJoin('users u', 'u.id = m.sender_id')
            ->leftJoin('media_assets ma', 'ma.id = m.media_id')
            ->where('m.room_id', $roomId)
            ->order('m.id', 'desc')
            ->limit($limit)
            ->field([
                'm.id',
                'm.room_id',
                'm.sender_id',
                'm.message_type',
                'm.content',
                'm.media_id',
                'm.created_at',
                'u.name as sender_name',
                'u.avatar_url as sender_avatar',
                'ma.file_name',
                'ma.mime_type',
                'ma.file_type',
                'ma.storage_path',
                'ma.file_size',
            ])
            ->select()
            ->toArray();
        $messages = array_reverse($messages);

        $rawMembers = Db::table('chat_members')->alias('cm')
            ->leftJoin('users u', 'u.id = cm.user_id')
            ->where('cm.room_id', $roomId)
            ->field([
                'cm.user_id',
                'cm.last_read_message_id',
                'u.name',
                'u.avatar_url',
            ])
            ->select()
            ->toArray();
        $memberList = array_map(function ($item) {
            return [
                'id'     => (int)$item['user_id'],
                'name'   => $item['name'] ?? '成员',
                'avatar' => $item['avatar_url'] ?? null,
            ];
        }, $rawMembers);

        $memberReadMap = [];
        foreach ($rawMembers as $rm) {
            $memberReadMap[(int)$rm['user_id']] = (int)($rm['last_read_message_id'] ?? 0);
        }

        foreach ($messages as &$msg) {
            $msgId = (int)$msg['id'];
            $senderId = (int)$msg['sender_id'];
            
            if ($roomType === 'direct') {
                $peerReadId = 0;
                foreach ($memberReadMap as $mId => $readId) {
                    if ($mId !== $userId) {
                        $peerReadId = $readId;
                        break;
                    }
                }
                $msg['read_status'] = $peerReadId >= $msgId ? 'read' : 'unread';
            } else {
                $readCount = 0;
                $unreadCount = 0;
                foreach ($memberReadMap as $mId => $readId) {
                    if ($mId === $senderId) {
                        continue;
                    }
                    if ($readId >= $msgId) {
                        $readCount++;
                    } else {
                        $unreadCount++;
                    }
                }
                $msg['read_count'] = $readCount;
                $msg['unread_count'] = $unreadCount;
            }
        }
        unset($msg);

        $roomName = $member['room_name'] ?: $this->deriveConversationName($roomType, $memberList, $userId);

        return $this->success([
            'items' => $messages,
            'room'  => [
                'id'   => $member['room_id'],
                'type' => $roomType,
                'name' => $roomName,
                'members' => $memberList,
            ],
        ]);
    }

    public function messageReaders($roomId, $messageId)
    {
        $roomId = (int)$roomId;
        $messageId = (int)$messageId;
        $userId = (int)$this->user()['id'];
        
        $this->ensureMembership($roomId, $userId);

        $rawMembers = Db::table('chat_members')->alias('cm')
            ->leftJoin('users u', 'u.id = cm.user_id')
            ->where('cm.room_id', $roomId)
            ->field([
                'cm.user_id',
                'cm.last_read_message_id',
                'u.name',
                'u.avatar_url',
            ])
            ->select()
            ->toArray();

        $readers = [];
        $unreaders = [];
        
        foreach ($rawMembers as $item) {
            $memberId = (int)$item['user_id'];
            $lastReadId = (int)($item['last_read_message_id'] ?? 0);
            
            $memberInfo = [
                'id'     => $memberId,
                'name'   => $item['name'] ?? '成员',
                'avatar' => $item['avatar_url'] ?? null,
            ];
            
            if ($lastReadId >= $messageId) {
                $readers[] = $memberInfo;
            } else {
                $unreaders[] = $memberInfo;
            }
        }

        return $this->success([
            'readers' => $readers,
            'unreaders' => $unreaders,
        ]);
    }

    public function sendMessage($id)
    {
        $roomId = (int)$id;
        $userId = (int)$this->user()['id'];
        $member = $this->ensureMembership($roomId, $userId);
        $data = $this->requestData();
        $type = in_array($data['message_type'] ?? 'text', ['text', 'image', 'video', 'file', 'audio'], true)
            ? $data['message_type']
            : 'text';
        $content = trim((string)($data['content'] ?? ''));
        if ($type === 'text' && $content === '') {
            $this->errorResponse('消息内容不能为空');
        }
        $messageId = Db::table('chat_messages')->insertGetId([
            'room_id'      => $roomId,
            'sender_id'    => $userId,
            'message_type' => $type,
            'content'      => $content,
            'media_id'     => $data['media_id'] ?? null,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        Db::table('chat_members')
            ->where('room_id', $roomId)
            ->where('user_id', $userId)
            ->update(['last_read_message_id' => $messageId]);

        $message = Db::table('chat_messages')->alias('m')
            ->leftJoin('users u', 'u.id = m.sender_id')
            ->leftJoin('media_assets ma', 'ma.id = m.media_id')
            ->where('m.id', $messageId)
            ->field([
                'm.id',
                'm.room_id',
                'm.sender_id',
                'm.message_type',
                'm.content',
                'm.media_id',
                'm.created_at',
                'u.name as sender_name',
                'u.avatar_url as sender_avatar',
                'ma.file_name',
                'ma.mime_type',
                'ma.file_type',
                'ma.storage_path',
                'ma.file_size',
            ])
            ->find();

        return $this->success(['message' => $message], '消息已发送', 201);
    }

    public function markRead($id)
    {
        $roomId = (int)$id;
        $userId = (int)$this->user()['id'];
        $this->ensureMembership($roomId, $userId);
        $messageId = (int)Request::post('message_id', 0);
        if ($messageId <= 0) {
            $messageId = (int)Db::table('chat_messages')
                ->where('room_id', $roomId)
                ->order('id', 'desc')
                ->value('id');
        }
        if ($messageId <= 0) {
            return $this->success();
        }
        Db::table('chat_members')
            ->where('room_id', $roomId)
            ->where('user_id', $userId)
            ->update(['last_read_message_id' => $messageId]);

        return $this->success();
    }

    protected function ensureMembership(int $roomId, int $userId): array
    {
        $record = Db::table('chat_members')->alias('cm')
            ->leftJoin('chat_rooms r', 'r.id = cm.room_id')
            ->where('cm.room_id', $roomId)
            ->where('cm.user_id', $userId)
            ->field([
                'cm.*',
                'r.type as room_type',
                'r.name as room_name',
            ])
            ->find();
        if (!$record) {
            $this->errorResponse('会话不存在或无权访问', 404);
        }
        return $record;
    }

    protected function deriveConversationName(string $type, array $members, int $userId): string
    {
        if ($type === 'group') {
            $names = array_slice(array_map(fn($member) => $member['name'] ?? '成员', $members), 0, 3);
            return $names ? implode('、', $names) : '群聊';
        }
        foreach ($members as $member) {
            if (($member['id'] ?? 0) !== $userId) {
                return $member['name'] ?? '单聊';
            }
        }
        return '单聊';
    }

    protected function fetchLatestMessage(int $roomId): ?array
    {
        $message = Db::table('chat_messages')->alias('m')
            ->leftJoin('users u', 'u.id = m.sender_id')
            ->where('m.room_id', $roomId)
            ->order('m.id', 'desc')
            ->field([
                'm.id',
                'm.room_id',
                'm.sender_id',
                'm.message_type',
                'm.content',
                'm.media_id',
                'm.created_at',
                'u.name as sender_name',
            ])
            ->find();
        return $message ?: null;
    }

    protected function countUnreadMessages(int $roomId, int $lastReadId): int
    {
        $query = Db::table('chat_messages')
            ->where('room_id', $roomId);
        if ($lastReadId > 0) {
            $query->where('id', '>', $lastReadId);
        }
        return (int)$query->count();
    }

    protected function findDirectRoom(int $userId, int $peerId): ?array
    {
        $record = Db::table('chat_members')->alias('cm1')
            ->leftJoin('chat_members cm2', 'cm1.room_id = cm2.room_id')
            ->leftJoin('chat_rooms r', 'r.id = cm1.room_id')
            ->where('cm1.user_id', $userId)
            ->where('cm2.user_id', $peerId)
            ->where('r.type', 'direct')
            ->field([
                'cm1.room_id',
            ])
            ->find();
        return $record ?: null;
    }
}
