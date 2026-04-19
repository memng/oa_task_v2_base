<?php

namespace app\admin\controller;

use app\common\controller\AdminApiController;
use think\facade\Db;
use think\facade\Request;

class Reimburse extends AdminApiController
{
    protected array $mediaCache = [];

    public function index()
    {
        $status = trim((string)Request::get('status', ''));
        $query = Db::table('expense_reports')->alias('e')
            ->leftJoin('users u', 'u.id = e.user_id')
            ->order('e.id', 'desc')
            ->field('e.*, u.name as user_name, u.mobile as user_mobile');
        if ($status !== '') {
            $query->where('e.status', $status);
        }
        $items = $query->select()->toArray();
        return $this->success([
            'items' => array_map([$this, 'formatReport'], $items),
        ]);
    }

    public function updateStatus($id)
    {
        $report = Db::table('expense_reports')->find($id);
        if (!$report) {
            $this->errorResponse('报销单不存在', 404);
        }
        $payload = $this->requestData();
        $status = $payload['status'] ?? 'approved';
        if (!in_array($status, ['approved', 'rejected'], true)) {
            $this->errorResponse('状态非法');
        }
        $update = [
            'status'      => $status,
            'approver_id' => $this->currentUser['id'] ?? null,
            'approved_at' => date('Y-m-d H:i:s'),
        ];
        if (isset($payload['remark'])) {
            $update['remark'] = $payload['remark'];
        }
        Db::table('expense_reports')->where('id', $id)->update($update);
        $report = Db::table('expense_reports')->alias('e')
            ->leftJoin('users u', 'u.id = e.user_id')
            ->field('e.*, u.name as user_name, u.mobile as user_mobile')
            ->where('e.id', $id)
            ->find();
        return $this->success([
            'report' => $this->formatReport($report),
        ], '状态已更新');
    }

    protected function formatReport(array $report): array
    {
        $receipt = null;
        if (!empty($report['receipt_media_id'])) {
            $receipt = $this->fetchMedia((int)$report['receipt_media_id']);
        }
        return [
            'id'          => (int)$report['id'],
            'user_id'     => (int)$report['user_id'],
            'user_name'   => $report['user_name'] ?? null,
            'user_mobile' => $report['user_mobile'] ?? null,
            'type'        => $report['type'],
            'amount'      => (float)$report['amount'],
            'remark'      => $report['remark'],
            'status'      => $report['status'],
            'created_at'  => $report['created_at'],
            'approved_at' => $report['approved_at'],
            'receipt_url' => $receipt['url'] ?? null,
            'receipt_name'=> $receipt['file_name'] ?? null,
        ];
    }

    protected function fetchMedia(int $mediaId): ?array
    {
        if (isset($this->mediaCache[$mediaId])) {
            return $this->mediaCache[$mediaId];
        }
        $media = Db::table('media_assets')->find($mediaId);
        if (!$media) {
            return null;
        }
        $url = null;
        if (!empty($media['storage_path'])) {
            $url = '/storage/' . ltrim($media['storage_path'], '/');
        }
        $result = [
            'id'        => (int)$media['id'],
            'file_name' => $media['file_name'],
            'file_type' => $media['file_type'],
            'url'       => $url,
        ];
        $this->mediaCache[$mediaId] = $result;
        return $result;
    }
}
