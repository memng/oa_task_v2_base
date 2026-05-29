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
            'file_type'    => $this->detectType($file->getMime()),
            'file_size'    => $file->getSize(),
            'storage_path' => $path,
        ], '上传成功', 201);
    }

    public function receipt()
    {
        $file = Request::file('file');
        if (!$file) {
            $this->errorResponse('请上传文件');
        }

        $maxSize = 1024 * 1024;
        $fileSize = $file->getSize();
        if ($fileSize > $maxSize) {
            $this->errorResponse('文件大小不能超过1MB');
        }

        $allowedMimes = [
            'image/jpeg',
            'image/jpg',
            'image/png',
            'application/pdf',
        ];
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
        
        $fileMime = $file->getMime();
        $originalName = $file->getOriginalName();
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        $isValidMime = in_array($fileMime, $allowedMimes);
        $isValidExtension = in_array($extension, $allowedExtensions);

        if (!$isValidMime || !$isValidExtension) {
            $this->errorResponse('仅支持 jpg、png、pdf 格式的文件');
        }

        $path = Filesystem::disk('public')->putFile('uploads/receipts/' . date('Ymd'), $file);
        $mediaId = Db::table('media_assets')->insertGetId([
            'file_name'   => $originalName,
            'mime_type'   => $fileMime,
            'file_type'   => $this->detectType($fileMime),
            'storage_path'=> $path,
            'file_size'   => $fileSize,
            'uploaded_by' => $this->user()['id'],
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
        $url = '/storage/' . $path;
        return $this->success([
            'media_id'     => $mediaId,
            'url'          => $url,
            'file_name'    => $originalName,
            'file_type'    => $this->detectType($fileMime),
            'file_size'    => $fileSize,
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
        if (
            str_contains($mime, 'pdf') ||
            str_contains($mime, 'msword') ||
            str_contains($mime, 'officedocument') ||
            str_contains($mime, 'spreadsheet') ||
            str_contains($mime, 'presentation') ||
            str_contains($mime, 'wordprocessingml')
        ) {
            return 'document';
        }
        if (
            str_contains($mime, 'zip') ||
            str_contains($mime, 'rar') ||
            str_contains($mime, '7z') ||
            str_contains($mime, 'x-tar') ||
            str_contains($mime, 'gzip') ||
            str_contains($mime, 'compressed')
        ) {
            return 'document';
        }
        return 'other';
    }
}
