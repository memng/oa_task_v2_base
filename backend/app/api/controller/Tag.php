<?php

namespace app\api\controller;

use app\common\service\TagService;

class Tag extends BaseApiController
{
    public function index()
    {
        $list = TagService::getTagList();
        $options = [];
        foreach ($list as $tag) {
            $options[] = [
                'value' => $tag['key'],
                'label' => $tag['label'],
                'color' => $tag['color'],
            ];
        }
        return $this->success([
            'items' => $options,
            'total' => count($options),
        ]);
    }

    public function options()
    {
        $options = TagService::getTagOptions();
        return $this->success([
            'options' => $options,
        ]);
    }
}
