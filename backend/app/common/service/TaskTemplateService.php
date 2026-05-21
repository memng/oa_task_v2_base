<?php

namespace app\common\service;

use think\facade\Db;

class TaskTemplateService
{
    public function list(int $userId, bool $isAdmin = false, ?string $type = null, ?string $keyword = null): array
    {
        $query = Db::table('task_templates')->alias('tt')
            ->leftJoin('users u', 'u.id = tt.created_by')
            ->field([
                'tt.*',
                'u.name as creator_name',
            ]);

        $query->where(function ($q) use ($userId) {
            $q->where('tt.created_by', $userId)
                ->whereOr('tt.is_global', 1);
        });

        if ($type) {
            $query->where('tt.type', $type);
        }

        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->whereLike('tt.name', "%{$keyword}%")
                    ->whereOr('tt.title', 'like', "%{$keyword}%");
            });
        }

        $rows = $query->order('tt.id', 'desc')->select()->toArray();

        return array_map(function ($row) {
            $extra = [];
            if (!empty($row['extra'])) {
                $decoded = json_decode($row['extra'], true);
                if (is_array($decoded)) {
                    $extra = $decoded;
                }
            }
            return [
                'id'           => (int)$row['id'],
                'name'         => $row['name'],
                'type'         => $row['type'],
                'title'        => $row['title'],
                'description'  => $row['description'] ?? '',
                'assigned_to'  => $row['assigned_to'] ? (int)$row['assigned_to'] : null,
                'need_audit'   => (int)($row['need_audit'] ?? 0),
                'extra'        => $extra,
                'created_by'   => (int)$row['created_by'],
                'creator_name' => $row['creator_name'] ?? null,
                'is_global'    => (int)($row['is_global'] ?? 0),
                'created_at'   => $row['created_at'],
                'updated_at'   => $row['updated_at'],
            ];
        }, $rows);
    }

    public function read(int $id, int $userId, bool $isAdmin = false): ?array
    {
        $row = Db::table('task_templates')->alias('tt')
            ->leftJoin('users u', 'u.id = tt.created_by')
            ->field([
                'tt.*',
                'u.name as creator_name',
            ])
            ->where('tt.id', $id)
            ->find();

        if (!$row) {
            return null;
        }

        $ownerOk = (int)$row['created_by'] === $userId;
        $globalOk = (int)($row['is_global'] ?? 0) === 1;
        if (!$ownerOk && !$globalOk && !$isAdmin) {
            return null;
        }

        $extra = [];
        if (!empty($row['extra'])) {
            $decoded = json_decode($row['extra'], true);
            if (is_array($decoded)) {
                $extra = $decoded;
            }
        }

        return [
            'id'           => (int)$row['id'],
            'name'         => $row['name'],
            'type'         => $row['type'],
            'title'        => $row['title'],
            'description'  => $row['description'] ?? '',
            'assigned_to'  => $row['assigned_to'] ? (int)$row['assigned_to'] : null,
            'need_audit'   => (int)($row['need_audit'] ?? 0),
            'extra'        => $extra,
            'created_by'   => (int)$row['created_by'],
            'creator_name' => $row['creator_name'] ?? null,
            'is_global'    => (int)($row['is_global'] ?? 0),
            'created_at'   => $row['created_at'],
            'updated_at'   => $row['updated_at'],
        ];
    }

    public function create(array $payload, int $userId, bool $isAdmin = false): int
    {
        $now = date('Y-m-d H:i:s');
        $extra = !empty($payload['extra']) && is_array($payload['extra'])
            ? json_encode($payload['extra'], JSON_UNESCAPED_UNICODE)
            : null;

        $isGlobal = $isAdmin ? (int)($payload['is_global'] ?? 0) : 0;

        return Db::table('task_templates')->insertGetId([
            'name'        => $payload['name'],
            'type'        => $payload['type'] ?? 'procurement',
            'title'       => $payload['title'],
            'description' => $payload['description'] ?? '',
            'assigned_to' => !empty($payload['assigned_to']) ? (int)$payload['assigned_to'] : null,
            'need_audit'  => (int)($payload['need_audit'] ?? 0),
            'extra'       => $extra,
            'created_by'  => $userId,
            'is_global'   => $isGlobal,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);
    }

    public function update(int $id, array $payload, int $userId, bool $isAdmin = false): bool
    {
        $row = Db::table('task_templates')->where('id', $id)->find();
        if (!$row) {
            return false;
        }
        if ((int)$row['created_by'] !== $userId && !$isAdmin) {
            return false;
        }

        $update = [];
        if (array_key_exists('name', $payload)) {
            $update['name'] = $payload['name'];
        }
        if (array_key_exists('type', $payload)) {
            $update['type'] = $payload['type'];
        }
        if (array_key_exists('title', $payload)) {
            $update['title'] = $payload['title'];
        }
        if (array_key_exists('description', $payload)) {
            $update['description'] = $payload['description'];
        }
        if (array_key_exists('assigned_to', $payload)) {
            $update['assigned_to'] = $payload['assigned_to'] ? (int)$payload['assigned_to'] : null;
        }
        if (array_key_exists('need_audit', $payload)) {
            $update['need_audit'] = (int)$payload['need_audit'];
        }
        if (array_key_exists('extra', $payload)) {
            $update['extra'] = !empty($payload['extra']) && is_array($payload['extra'])
                ? json_encode($payload['extra'], JSON_UNESCAPED_UNICODE)
                : null;
        }
        if (array_key_exists('is_global', $payload)) {
            $update['is_global'] = $isAdmin ? (int)$payload['is_global'] : (int)($row['is_global'] ?? 0);
        }

        if (!$update) {
            return true;
        }
        $update['updated_at'] = date('Y-m-d H:i:s');
        Db::table('task_templates')->where('id', $id)->update($update);
        return true;
    }

    public function delete(int $id, int $userId, bool $isAdmin = false): bool
    {
        $row = Db::table('task_templates')->where('id', $id)->find();
        if (!$row) {
            return false;
        }
        if ((int)$row['created_by'] !== $userId && !$isAdmin) {
            return false;
        }
        Db::table('task_templates')->where('id', $id)->delete();
        return true;
    }
}
