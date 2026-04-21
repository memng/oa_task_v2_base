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
        $id = Db::table('leave_requests')->insertGetId([
            'user_id'       => $this->user()['id'],
            'leave_type'    => $data['leave_type'],
            'start_at'      => $data['start_at'],
            'end_at'        => $data['end_at'],
            'duration_hours'=> $data['duration_hours'] ?? 0,
            'reason'        => $data['reason'] ?? null,
            'status'        => 'pending',
            'created_at'    => date('Y-m-d H:i:s'),
        ]);
        return $this->success(['id' => $id], '请假申请已提交', 201);
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
