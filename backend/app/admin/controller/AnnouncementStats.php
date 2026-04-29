<?php

namespace app\admin\controller;

use app\common\controller\AdminApiController;
use think\facade\Db;
use think\facade\Request;

class AnnouncementStats extends AdminApiController
{
    public function summary()
    {
        $announcementId = Request::get('announcement_id');
        $publishedOnly = (int)Request::get('published_only', 1);

        $baseQuery = Db::table('announcements');
        if ($publishedOnly) {
            $baseQuery->where('publish_status', 'published');
        }
        if ($announcementId) {
            $baseQuery->where('id', (int)$announcementId);
        }

        $announcements = $baseQuery
            ->field(['id', 'title', 'publish_status', 'published_at'])
            ->order('published_at', 'desc')
            ->select()
            ->toArray();

        if (empty($announcements)) {
            return $this->success([
                'total_users' => 0,
                'read_users' => 0,
                'unread_users' => 0,
                'read_rate' => 0,
                'total_announcements' => 0,
                'by_department' => [],
                'announcements' => [],
            ]);
        }

        $announcementIds = array_column($announcements, 'id');
        $announcementIdStr = implode(',', array_map('intval', $announcementIds));

        $totalUsers = Db::table('users')
            ->where('status', 'active')
            ->count();

        $readUsers = Db::table('announcement_reads')
            ->whereIn('announcement_id', $announcementIds)
            ->distinct(true)
            ->field('user_id')
            ->count();

        $unreadUsers = $totalUsers - $readUsers;
        $readRate = $totalUsers > 0 ? round($readUsers / $totalUsers * 100, 2) : 0;

        $deptStats = $this->getDeptStats($announcementIds);

        return $this->success([
            'total_users' => $totalUsers,
            'read_users' => $readUsers,
            'unread_users' => $unreadUsers,
            'read_rate' => $readRate,
            'total_announcements' => count($announcements),
            'by_department' => $deptStats,
            'announcements' => array_map(function ($a) {
                return [
                    'id' => (int)$a['id'],
                    'title' => $a['title'],
                    'publish_status' => $a['publish_status'],
                    'published_at' => $a['published_at'],
                ];
            }, $announcements),
        ]);
    }

    public function listAnnouncements()
    {
        $page = (int)Request::get('page', 1);
        $pageSize = (int)Request::get('page_size', 20);
        $status = Request::get('status');

        $query = Db::table('announcements')
            ->field([
                'id', 'title', 'content', 'category', 'publish_status',
                'published_at', 'created_at', 'created_by'
            ]);

        if ($status) {
            $query->where('publish_status', $status);
        }

        $total = $query->count();
        $items = $query
            ->order('published_at', 'desc')
            ->order('id', 'desc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();

        $announcementIds = array_column($items, 'id');
        $readCounts = [];
        if (!empty($announcementIds)) {
            $readCountsData = Db::table('announcement_reads')
                ->whereIn('announcement_id', $announcementIds)
                ->field(['announcement_id', 'COUNT(DISTINCT user_id) as read_count'])
                ->group('announcement_id')
                ->select()
                ->toArray();
            foreach ($readCountsData as $row) {
                $readCounts[$row['announcement_id']] = (int)$row['read_count'];
            }
        }

        $totalActiveUsers = Db::table('users')->where('status', 'active')->count();

        $items = array_map(function ($item) use ($readCounts, $totalActiveUsers) {
            $readCount = $readCounts[$item['id']] ?? 0;
            return [
                'id' => (int)$item['id'],
                'title' => $item['title'],
                'category' => $item['category'],
                'publish_status' => $item['publish_status'],
                'published_at' => $item['published_at'],
                'created_at' => $item['created_at'],
                'read_count' => $readCount,
                'unread_count' => $totalActiveUsers - $readCount,
                'read_rate' => $totalActiveUsers > 0 
                    ? round($readCount / $totalActiveUsers * 100, 2) 
                    : 0,
                'total_users' => $totalActiveUsers,
            ];
        }, $items);

        return $this->success([
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
            'items' => $items,
        ]);
    }

    public function detail($id)
    {
        $announcement = Db::table('announcements')
            ->where('id', (int)$id)
            ->find();

        if (!$announcement) {
            $this->errorResponse('公告不存在', 404);
        }

        $totalActiveUsers = Db::table('users')
            ->where('status', 'active')
            ->count();

        $readUsers = Db::table('announcement_reads')
            ->where('announcement_id', (int)$id)
            ->distinct(true)
            ->field('user_id')
            ->count();

        $unreadUsers = $totalActiveUsers - $readUsers;
        $readRate = $totalActiveUsers > 0 ? round($readUsers / $totalActiveUsers * 100, 2) : 0;

        $deptStats = $this->getDeptStats([(int)$id]);

        $readUsersList = Db::table('announcement_reads')
            ->alias('ar')
            ->leftJoin('users u', 'u.id = ar.user_id')
            ->leftJoin('departments d', 'd.id = u.dept_id')
            ->where('ar.announcement_id', (int)$id)
            ->field([
                'u.id as user_id',
                'u.name as user_name',
                'd.name as dept_name',
                'ar.read_at',
            ])
            ->order('ar.read_at', 'desc')
            ->select()
            ->toArray();

        $readUserIds = Db::table('announcement_reads')
            ->where('announcement_id', (int)$id)
            ->column('user_id');

        $unreadUsersQuery = Db::table('users')
            ->alias('u')
            ->leftJoin('departments d', 'd.id = u.dept_id')
            ->where('u.status', 'active')
            ->field([
                'u.id as user_id',
                'u.name as user_name',
                'd.name as dept_name',
            ]);

        if (!empty($readUserIds)) {
            $unreadUsersQuery->whereNotIn('u.id', $readUserIds);
        }

        $unreadUsersList = $unreadUsersQuery
            ->order('u.id', 'asc')
            ->select()
            ->toArray();

        return $this->success([
            'announcement' => [
                'id' => (int)$announcement['id'],
                'title' => $announcement['title'],
                'content' => $announcement['content'],
                'category' => $announcement['category'],
                'publish_status' => $announcement['publish_status'],
                'published_at' => $announcement['published_at'],
                'created_at' => $announcement['created_at'],
            ],
            'summary' => [
                'total_users' => $totalActiveUsers,
                'read_users' => $readUsers,
                'unread_users' => $unreadUsers,
                'read_rate' => $readRate,
            ],
            'by_department' => $deptStats,
            'read_users' => array_map(function ($row) {
                return [
                    'user_id' => (int)$row['user_id'],
                    'user_name' => $row['user_name'],
                    'dept_name' => $row['dept_name'],
                    'read_at' => $row['read_at'],
                ];
            }, $readUsersList),
            'unread_users' => array_map(function ($row) {
                return [
                    'user_id' => (int)$row['user_id'],
                    'user_name' => $row['user_name'],
                    'dept_name' => $row['dept_name'],
                ];
            }, $unreadUsersList),
        ]);
    }

    protected function getDeptStats(array $announcementIds): array
    {
        if (empty($announcementIds)) {
            return [];
        }

        $announcementIdStr = implode(',', array_map('intval', $announcementIds));

        $sql = "SELECT 
                    d.id as dept_id,
                    d.name as dept_name,
                    COUNT(DISTINCT u.id) as total_users,
                    COUNT(DISTINCT ar.user_id) as read_users
                FROM users u
                LEFT JOIN departments d ON d.id = u.dept_id
                LEFT JOIN announcement_reads ar ON ar.user_id = u.id AND ar.announcement_id IN ({$announcementIdStr})
                WHERE u.status = 'active'
                GROUP BY d.id, d.name
                ORDER BY d.sort_order ASC";

        $deptStats = Db::query($sql);

        $result = [];
        $hasNoDept = false;

        foreach ($deptStats as $row) {
            $unread = $row['total_users'] - $row['read_users'];
            if ($row['dept_id'] === null) {
                $hasNoDept = true;
            }
            $result[] = [
                'dept_id' => $row['dept_id'] ? (int)$row['dept_id'] : null,
                'dept_name' => $row['dept_name'] ?: '未分配部门',
                'total_users' => (int)$row['total_users'],
                'read_users' => (int)$row['read_users'],
                'unread_users' => $unread,
                'read_rate' => $row['total_users'] > 0 
                    ? round($row['read_users'] / $row['total_users'] * 100, 2) 
                    : 0,
            ];
        }

        return $result;
    }
}
