<?php

namespace app\api\controller;

use app\common\controller\ApiController;
use think\facade\Filesystem;
use think\facade\Request;
use think\facade\Db;

class Upload extends ApiController
{
    public function save()
    {
        $file = Request::file('file');
        if (!$file) {
            $this->errorResponse('请上传文件');
        }
        $path = Filesystem::disk('public')->putFile('uploads/' . date('Ymd'), $file);
        $mediaId = Db::table('media_assets')->insertGetId([
            'file_name'   => $file->getOriginalName(),
            'mime_type'   => $file->getMime(),
            'file_type'   => $this->detectType($file->getMime()),
            'storage_path'=> $path,
            'file_size'   => $file->getSize(),
            'uploaded_by' => $this->user()['id'],
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
        $url = '/storage/' . $path;
        return $this->success([
            'media_id'     => $mediaId,
            'url'          => $url,
            'file_name'    => $file->getOriginalName(),
            'storage_path' => $path,
        ], '上传成功', 201);
    }

    protected function detectType(string $mime): string
    {
        if (str_contains($mime, 'image')) {
            return 'image';
        }
        if (str_contains($mime, 'video')) {
            return 'video';
        }
        if (str_contains($mime, 'audio')) {
            return 'audio';
        }
        if (str_contains($mime, 'pdf') || str_contains($mime, 'msword')) {
            return 'document';
        }
        return 'other';
    }
}
