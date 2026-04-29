<?php

namespace app\api\controller;

use app\common\controller\ApiController;
use think\facade\Db;
use think\facade\Request;

class Reimburse extends ApiController
{
    protected array $mediaCache = [];

    public function index()
    {
        $userId = $this->user()['id'];
        $params = Request::param();
        
        $page = isset($params['page']) ? max(1, (int)$params['page']) : 1;
        $pageSize = isset($params['pageSize']) ? max(1, (int)$params['pageSize']) : 2;
        
        $query = Db::table('expense_reports')
            ->where('user_id', $userId);
        
        if (!empty($params['type'])) {
            $query->where('type', $params['type']);
        }
        
        if (!empty($params['startDate'])) {
            $query->where('created_at', '>=', $params['startDate'] . ' 00:00:00');
        }
        
        if (!empty($params['endDate'])) {
            $query->where('created_at', '<=', $params['endDate'] . ' 23:59:59');
        }
        
        $total = $query->count();
        $items = $query
            ->order('id', 'desc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();
        
        return $this->success([
            'items' => array_map([$this, 'formatReport'], $items),
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
            'totalPages' => ceil($total / $pageSize),
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
        
        $receiptMediaIds = $this->parseReceiptMediaIds($payload);
        
        if (!empty($receiptMediaIds)) {
            if (count($receiptMediaIds) > 9) {
                $this->errorResponse('最多只能上传9个票据附件');
            }
            
            $validIds = $this->validateAndFilterMediaIds($receiptMediaIds);
            if (count($validIds) !== count($receiptMediaIds)) {
                $this->errorResponse('部分票据附件ID无效，请重新上传');
            }
            
            $receiptMediaIds = $validIds;
        }
        
        $firstMediaId = !empty($receiptMediaIds) ? $receiptMediaIds[0] : null;
        
        $data = [
            'user_id'           => $this->user()['id'],
            'type'              => $type,
            'amount'            => round($amount, 2),
            'remark'            => $payload['remark'] ?? null,
            'receipt_media_id'  => $firstMediaId,
            'receipt_media_ids' => !empty($receiptMediaIds) ? json_encode($receiptMediaIds) : null,
            'status'            => 'pending',
            'created_at'        => date('Y-m-d H:i:s'),
        ];
        $id = Db::table('expense_reports')->insertGetId($data);
        $report = Db::table('expense_reports')->find($id);
        return $this->success([
            'report' => $this->formatReport($report),
        ], '报销申请已提交', 201);
    }

    protected function validateAndFilterMediaIds(array $mediaIds): array
    {
        if (empty($mediaIds)) {
            return [];
        }
        
        $ids = array_map('intval', $mediaIds);
        $ids = array_unique(array_filter($ids, fn($id) => $id > 0));
        
        if (empty($ids)) {
            return [];
        }
        
        $existingMedia = Db::table('media_assets')
            ->whereIn('id', $ids)
            ->column('id');
        
        return array_values(array_intersect($ids, $existingMedia));
    }

    protected function parseReceiptMediaIds(array $payload): array
    {
        $ids = [];
        
        if (!empty($payload['receipt_media_ids']) && is_array($payload['receipt_media_ids'])) {
            foreach ($payload['receipt_media_ids'] as $id) {
                $intId = (int)$id;
                if ($intId > 0 && !in_array($intId, $ids, true)) {
                    $ids[] = $intId;
                }
            }
        }
        
        if (empty($ids) && !empty($payload['receipt_media_id'])) {
            $intId = (int)$payload['receipt_media_id'];
            if ($intId > 0) {
                $ids[] = $intId;
            }
        }
        
        return $ids;
    }

    protected function formatReport(array $report): array
    {
        $mediaIds = $this->extractReceiptMediaIds($report);
        $receipts = [];
        $firstReceipt = null;
        
        foreach ($mediaIds as $mediaId) {
            $media = $this->fetchMedia($mediaId);
            if ($media) {
                $receipts[] = $media;
                if ($firstReceipt === null) {
                    $firstReceipt = $media;
                }
            }
        }
        
        $firstMediaId = !empty($mediaIds) ? $mediaIds[0] : null;
        
        return [
            'id'                 => (int)$report['id'],
            'type'               => $report['type'],
            'amount'             => (float)$report['amount'],
            'remark'             => $report['remark'],
            'status'             => $report['status'],
            'approver_id'        => $report['approver_id'] ? (int)$report['approver_id'] : null,
            'approved_at'        => $report['approved_at'],
            'created_at'         => $report['created_at'],
            'receipt_media_id'   => $firstMediaId,
            'receipt_media_ids'  => $mediaIds,
            'receipt_url'        => $firstReceipt['url'] ?? null,
            'receipt_name'       => $firstReceipt['file_name'] ?? null,
            'receipts'           => $receipts,
        ];
    }

    protected function extractReceiptMediaIds(array $report): array
    {
        $ids = [];
        
        if (!empty($report['receipt_media_ids'])) {
            $parsed = json_decode($report['receipt_media_ids'], true);
            if (is_array($parsed)) {
                foreach ($parsed as $id) {
                    $intId = (int)$id;
                    if ($intId > 0 && !in_array($intId, $ids, true)) {
                        $ids[] = $intId;
                    }
                }
            }
        }
        
        if (empty($ids) && !empty($report['receipt_media_id'])) {
            $intId = (int)$report['receipt_media_id'];
            if ($intId > 0) {
                $ids[] = $intId;
            }
        }
        
        return $ids;
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

    public function read($id)
    {
        $userId = $this->user()['id'];
        
        $report = Db::table('expense_reports')
            ->where('id', (int)$id)
            ->find();

        if (!$report) {
            $this->errorResponse('报销申请不存在', 404);
        }

        if ($report['user_id'] != $userId) {
            $this->errorResponse('无权限查看该报销申请', 403);
        }

        $typeMap = [
            'travel' => '差旅费',
            'meal' => '餐费',
            'transport' => '交通费',
            'office' => '办公用品',
            'other' => '其他'
        ];

        $statusMap = [
            'pending' => '审批中',
            'approved' => '已通过',
            'rejected' => '已拒绝'
        ];

        $approverName = null;
        if ($report['approver_id']) {
            $approver = Db::table('users')
                ->where('id', (int)$report['approver_id'])
                ->field('name, nickname')
                ->find();
            if ($approver) {
                $approverName = $approver['name'] ?: $approver['nickname'];
            }
        }

        $mediaIds = $this->extractReceiptMediaIds($report);
        $receipts = [];
        foreach ($mediaIds as $mediaId) {
            $media = $this->fetchMedia($mediaId);
            if ($media) {
                $receipts[] = $media;
            }
        }

        return $this->success([
            'id' => (int)$report['id'],
            'user_id' => (int)$report['user_id'],
            'type' => $report['type'],
            'type_label' => $typeMap[$report['type']] ?? '其他',
            'amount' => (float)$report['amount'],
            'remark' => $report['remark'],
            'status' => $report['status'],
            'status_label' => $statusMap[$report['status']] ?? '未知',
            'approver_id' => $report['approver_id'] ? (int)$report['approver_id'] : null,
            'approver_name' => $approverName,
            'approved_at' => $report['approved_at'],
            'created_at' => $report['created_at'],
            'receipt_media_ids' => $mediaIds,
            'receipts' => $receipts
        ]);
    }
}
