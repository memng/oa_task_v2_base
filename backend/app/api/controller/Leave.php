<?php

namespace app\api\controller;

use app\common\controller\ApiController;
use think\facade\Db;

class Leave extends ApiController
{
    public function index()
    {
        $items = Db::table('leave_requests')
            ->where('user_id', $this->user()['id'])
            ->order('created_at', 'desc')
            ->select()
            ->toArray();
        return $this->success(['items' => $items]);
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
        Db::table('leave_requests')->where('id', $id)->update([
            'status'      => $status,
            'approver_id' => $this->user()['id'],
            'approved_at' => date('Y-m-d H:i:s'),
        ]);
        return $this->success([], '审批完成');
    }
}
