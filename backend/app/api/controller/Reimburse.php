<?php

namespace app\api\controller;

use app\common\controller\ApiController;
use think\facade\Db;

class Reimburse extends ApiController
{
    protected array $mediaCache = [];

    public function index()
    {
        $items = Db::table('expense_reports')
            ->where('user_id', $this->user()['id'])
            ->order('id', 'desc')
            ->select()
            ->toArray();
        return $this->success([
            'items' => array_map([$this, 'formatReport'], $items),
        ]);
    }

    public function save()
    {
        $payload = $this->requestData();
        $type = trim((string)($payload['type'] ?? ''));
        if ($type === '') {
            $this->errorResponse('请选择报销类型');
        }
        $amount = isset($payload['amount']) ? (float)$payload['amount'] : 0;
        if ($amount <= 0) {
            $this->errorResponse('请输入正确的金额');
        }
        $data = [
            'user_id'          => $this->user()['id'],
            'type'             => $type,
            'amount'           => round($amount, 2),
            'remark'           => $payload['remark'] ?? null,
            'receipt_media_id' => !empty($payload['receipt_media_id']) ? (int)$payload['receipt_media_id'] : null,
            'status'           => 'pending',
            'created_at'       => date('Y-m-d H:i:s'),
        ];
        $id = Db::table('expense_reports')->insertGetId($data);
        $report = Db::table('expense_reports')->find($id);
        return $this->success([
            'report' => $this->formatReport($report),
        ], '报销申请已提交', 201);
    }

    protected function formatReport(array $report): array
    {
        $receipt = null;
        if (!empty($report['receipt_media_id'])) {
            $receipt = $this->fetchMedia((int)$report['receipt_media_id']);
        }
        return [
            'id'               => (int)$report['id'],
            'type'             => $report['type'],
            'amount'           => (float)$report['amount'],
            'remark'           => $report['remark'],
            'status'           => $report['status'],
            'approver_id'      => $report['approver_id'] ? (int)$report['approver_id'] : null,
            'approved_at'      => $report['approved_at'],
            'created_at'       => $report['created_at'],
            'receipt_media_id' => $report['receipt_media_id'] ? (int)$report['receipt_media_id'] : null,
            'receipt_url'      => $receipt['url'] ?? null,
            'receipt_name'     => $receipt['file_name'] ?? null,
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
            $path = ltrim($media['storage_path'], '/');
            $url = '/storage/' . $path;
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
