<?php

namespace app\api\controller;

use app\common\controller\ApiController;
use think\facade\Db;
use think\facade\Request;

class Leave extends ApiController
{
    public function index()
    {
        $page = (int)Request::get('page', 1);
        $pageSize = (int)Request::get('page_size', 2);
        $user = $this->user();
        $query = Db::table('leave_requests');

        $query->where('user_id', $user['id']);

        if ($status = Request::get('status')) {
            $query->where('status', $status);
        }

        if ($startDate = Request::get('start_date')) {
            $query->where('start_at', '>=', "{$startDate} 00:00:00");
        }
        if ($endDate = Request::get('end_date')) {
            $query->where('end_at', '<=', "{$endDate} 23:59:59");
        }

        $countQuery = clone $query;
        $total = $countQuery->count();
        $list = $query
            ->order('created_at', 'desc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();

        return $this->success([
            'items' => $list,
            'meta'  => [
                'page'       => $page,
                'page_size'  => $pageSize,
                'total'      => $total,
            ],
        ]);
    }

    public function save()
    {
        $data = $this->requestData();
        if (empty($data['leave_type']) || empty($data['start_at']) || empty($data['end_at'])) {
            $this->errorResponse('请完善请假信息');
        }
        
        $userId = $this->user()['id'];
        $startAt = $data['start_at'];
        $endAt = $data['end_at'];
        
        $conflictingRecords = Db::table('leave_requests')
            ->where('user_id', $userId)
            ->where('status', 'in', ['pending', 'approved'])
            ->where(function ($query) use ($startAt, $endAt) {
                $query->where('start_at', '<', $endAt)
                      ->where('end_at', '>', $startAt);
            })
            ->select()
            ->toArray();
        
        if (!empty($conflictingRecords)) {
            $conflicts = array_map(function ($record) {
                return [
                    'id' => $record['id'],
                    'leave_type' => $record['leave_type'],
                    'start_at' => $record['start_at'],
                    'end_at' => $record['end_at'],
                    'duration_hours' => $record['duration_hours'],
                    'status' => $record['status'],
                    'conflict_range' => $this->formatConflictRange($record['start_at'], $record['end_at'])
                ];
            }, $conflictingRecords);
            
            $this->errorResponse('与已申请记录时间冲突', 409, [
                'conflicts' => $conflicts,
                'message' => $this->buildConflictMessage($conflictingRecords)
            ]);
        }
        
        $id = Db::table('leave_requests')->insertGetId([
            'user_id'       => $userId,
            'leave_type'    => $data['leave_type'],
            'start_at'      => $startAt,
            'end_at'        => $endAt,
            'duration_hours'=> $data['duration_hours'] ?? 0,
            'reason'        => $data['reason'] ?? null,
            'status'        => 'pending',
            'created_at'    => date('Y-m-d H:i:s'),
        ]);
        $this->logAudit($id, 'create', null, 'pending', $userId, $data['reason'] ?? null);
        return $this->success(['id' => $id], '请假申请已提交', 201);
    }
    
    private function formatConflictRange($startAt, $endAt)
    {
        $start = date('m月d日 H:i', strtotime($startAt));
        $end = date('m月d日 H:i', strtotime($endAt));
        return "{$start} ~ {$end}";
    }
    
    private function buildConflictMessage($conflictingRecords)
    {
        $count = count($conflictingRecords);
        
        $typeMap = [
            'annual' => '年假',
            'sick' => '病假',
            'personal' => '事假',
            'other' => '其他'
        ];
        
        $statusMap = [
            'pending' => '审批中',
            'approved' => '已通过'
        ];
        
        $details = [];
        foreach ($conflictingRecords as $record) {
            $typeLabel = $typeMap[$record['leave_type']] ?? '其他';
            $statusLabel = $statusMap[$record['status']] ?? '未知';
            $details[] = "{$typeLabel}({$statusLabel})：{$this->formatConflictRange($record['start_at'], $record['end_at'])}";
        }
        
        return "您已有 {$count} 条请假记录与当前申请时间冲突：\n" . implode("\n", $details);
    }

    public function approve($id)
    {
        $data = $this->requestData();
        $status = $data['status'] ?? 'approved';
        $userId = $this->user()['id'];

        if (!in_array($status, ['approved', 'rejected'], true)) {
            $this->errorResponse('无效的审批状态');
        }

        Db::startTrans();
        try {
            $leaveRequest = Db::table('leave_requests')
                ->where('id', $id)
                ->lock(true)
                ->find();

            if (!$leaveRequest) {
                Db::rollback();
                $this->errorResponse('请假申请不存在');
            }
            if ($leaveRequest['status'] !== 'pending') {
                Db::rollback();
                $this->errorResponse('仅待审批状态的申请可审批');
            }

            $updated = Db::table('leave_requests')
                ->where('id', $id)
                ->where('status', 'pending')
                ->update([
                    'status'      => $status,
                    'approver_id' => $userId,
                    'approved_at' => date('Y-m-d H:i:s'),
                ]);

            if ($updated !== 1) {
                Db::rollback();
                $this->errorResponse('审批失败，请重试');
            }

            $action = $status === 'approved' ? 'approve' : 'reject';
            $this->logAudit($id, $action, 'pending', $status, $userId, $data['reason'] ?? null);

            Db::commit();
            return $this->success([], '审批完成');
        } catch (\Exception $e) {
            Db::rollback();
            $this->errorResponse('审批失败：' . $e->getMessage());
        }
    }

    public function cancel($id)
    {
        $data = $this->requestData();
        $userId = $this->user()['id'];

        Db::startTrans();
        try {
            $leaveRequest = Db::table('leave_requests')
                ->where('id', $id)
                ->lock(true)
                ->find();

            if (!$leaveRequest) {
                Db::rollback();
                $this->errorResponse('请假申请不存在');
            }
            if ($leaveRequest['user_id'] != $userId) {
                Db::rollback();
                $this->errorResponse('仅申请人本人可撤回申请');
            }
            if ($leaveRequest['status'] !== 'pending') {
                Db::rollback();
                $this->errorResponse('仅待审批状态的申请可撤回');
            }

            $updated = Db::table('leave_requests')
                ->where('id', $id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'cancelled',
                ]);

            if ($updated !== 1) {
                Db::rollback();
                $this->errorResponse('撤回失败，请重试');
            }

            $this->logAudit($id, 'cancel', 'pending', 'cancelled', $userId, $data['reason'] ?? null);

            Db::commit();
            return $this->success([], '申请已撤回');
        } catch (\Exception $e) {
            Db::rollback();
            $this->errorResponse('撤回失败：' . $e->getMessage());
        }
    }

    private function logAudit($leaveRequestId, $action, $fromStatus, $toStatus, $operatorId, $reason = null)
    {
        Db::table('leave_request_audits')->insert([
            'leave_request_id' => $leaveRequestId,
            'action'            => $action,
            'from_status'       => $fromStatus,
            'to_status'         => $toStatus,
            'operator_id'       => $operatorId,
            'reason'            => $reason,
            'created_at'        => date('Y-m-d H:i:s'),
        ]);
    }
}
