<?php

namespace app\common\service;

use think\facade\Db;

class TagService
{
    protected static $tagCache = null;
    protected static $tagCacheTime = 0;
    protected const CACHE_TTL = 300;

    protected const DEFAULT_TAGS = [
        [
            'id' => 0,
            'key' => 'urgent',
            'label' => '紧急',
            'color' => '#ff4d4f',
            'sort' => 1,
            'is_default' => 1,
        ],
        [
            'id' => 0,
            'key' => 'customer',
            'label' => '客户',
            'color' => '#1677ff',
            'sort' => 2,
            'is_default' => 1,
        ],
        [
            'id' => 0,
            'key' => 'internal',
            'label' => '内部',
            'color' => '#722ed1',
            'sort' => 3,
            'is_default' => 1,
        ],
    ];

    public static function getTagList(bool $forceRefresh = false): array
    {
        $now = time();
        if (!$forceRefresh && self::$tagCache !== null && ($now - self::$tagCacheTime) < self::CACHE_TTL) {
            return self::$tagCache;
        }

        try {
            $rows = Db::table('tags')
                ->order('sort', 'asc')
                ->order('id', 'asc')
                ->select()
                ->toArray();
        } catch (\Exception $e) {
            $rows = self::DEFAULT_TAGS;
        }

        if (empty($rows)) {
            $rows = self::DEFAULT_TAGS;
        }

        self::$tagCache = $rows;
        self::$tagCacheTime = $now;

        return $rows;
    }

    public static function getTagMap(bool $forceRefresh = false): array
    {
        $list = self::getTagList($forceRefresh);
        $map = [];
        foreach ($list as $tag) {
            $map[$tag['key']] = [
                'label' => $tag['label'],
                'color' => $tag['color'],
                'sort' => (int)$tag['sort'],
                'is_default' => (bool)$tag['is_default'],
            ];
        }
        return $map;
    }

    public static function getTagOptions(bool $forceRefresh = false): array
    {
        $list = self::getTagList($forceRefresh);
        $options = [];
        foreach ($list as $tag) {
            $options[$tag['key']] = [
                'label' => $tag['label'],
                'color' => $tag['color'],
            ];
        }
        return $options;
    }

    public static function formatTags(?string $tagsJson, bool $forceRefresh = false): array
    {
        if (empty($tagsJson)) {
            return [];
        }

        $tags = json_decode($tagsJson, true);
        if (!is_array($tags) || empty($tags)) {
            return [];
        }

        $tagMap = self::getTagMap($forceRefresh);
        $result = [];
        foreach ($tags as $tagKey) {
            if (isset($tagMap[$tagKey])) {
                $result[] = [
                    'key' => $tagKey,
                    'label' => $tagMap[$tagKey]['label'],
                    'color' => $tagMap[$tagKey]['color'],
                ];
            }
        }
        return $result;
    }

    public static function validateTags(array $tags, bool $forceRefresh = false): array
    {
        if (empty($tags)) {
            return [];
        }

        $tagMap = self::getTagMap($forceRefresh);
        $validKeys = array_keys($tagMap);
        return array_values(array_intersect($tags, $validKeys));
    }

    public function createTag(array $data): int
    {
        $now = date('Y-m-d H:i:s');
        $tagId = Db::table('tags')->insertGetId([
            'key' => $data['key'],
            'label' => $data['label'],
            'color' => $data['color'] ?? '#1677ff',
            'sort' => (int)($data['sort'] ?? 0),
            'is_default' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        self::clearCache();
        return $tagId;
    }

    public function updateTag(int $id, array $data): bool
    {
        $tag = Db::table('tags')->where('id', $id)->find();
        if (!$tag) {
            return false;
        }

        if ((bool)($tag['is_default'] ?? false)) {
            if (isset($data['key']) && $data['key'] !== $tag['key']) {
                throw new \Exception('系统默认标签的 key 不能修改');
            }
            if (isset($data['is_default']) && !(bool)$data['is_default']) {
                throw new \Exception('系统默认标签不能取消默认状态');
            }
        }

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
        if (isset($data['key']) && !(bool)($tag['is_default'] ?? false)) {
            $updateData['key'] = $data['key'];
        }

        if (empty($updateData)) {
            return true;
        }

        $updateData['updated_at'] = date('Y-m-d H:i:s');

        Db::table('tags')->where('id', $id)->update($updateData);

        self::clearCache();
        return true;
    }

    public function deleteTag(int $id): bool
    {
        $tag = Db::table('tags')->where('id', $id)->find();
        if (!$tag) {
            return false;
        }

        if ((bool)($tag['is_default'] ?? false)) {
            throw new \Exception('系统默认标签不能删除');
        }

        $tagKey = $tag['key'];

        Db::startTrans();
        try {
            Db::table('tags')->where('id', $id)->delete();

            $tasks = Db::table('tasks')
                ->whereRaw('JSON_CONTAINS(tags, ?)', ['"' . $tagKey . '"'])
                ->select()
                ->toArray();

            foreach ($tasks as $task) {
                $tags = json_decode($task['tags'], true);
                if (is_array($tags)) {
                    $newTags = array_values(array_filter($tags, function ($t) use ($tagKey) {
                        return $t !== $tagKey;
                    }));
                    Db::table('tasks')
                        ->where('id', $task['id'])
                        ->update([
                            'tags' => empty($newTags) ? null : json_encode($newTags, JSON_UNESCAPED_UNICODE),
                            'updated_at' => date('Y-m-d H:i:s'),
                        ]);
                }
            }

            Db::commit();
            self::clearCache();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }
    }

    public static function clearCache(): void
    {
        self::$tagCache = null;
        self::$tagCacheTime = 0;
    }
}
