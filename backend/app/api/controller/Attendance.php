<?php

namespace app\api\controller;

use app\common\controller\ApiController;
use think\facade\Db;
use think\facade\Request;

class Attendance extends ApiController
{
    public function rules()
    {
        $rules = Db::table('attendance_rules')
            ->order('id desc')
            ->select()
            ->toArray();
        return $this->success(['items' => $rules]);
    }

    public function checkin()
    {
        $data = $this->requestData();
        $checkIndicator = strtolower((string)($data['check_type'] ?? $data['status'] ?? 'check_in'));
        $checkType = in_array($checkIndicator, ['check_in', 'check_out', 'checkin', 'checkout'], true)
            ? ($checkIndicator === 'checkout' ? 'check_out' : ($checkIndicator === 'checkin' ? 'check_in' : $checkIndicator))
            : 'check_in';

        $allowedStatuses = ['normal', 'late', 'early', 'absent'];
        $resultStatus = $data['result_status'] ?? $data['attendance_status'] ?? null;
        if (!in_array($resultStatus, $allowedStatuses, true)) {
            $resultStatus = 'normal';
        }

        $record = [
            'rule_id'  => $data['rule_id'] ?? null,
            'user_id'  => $this->user()['id'],
            'check_type' => $checkType,
            'method'     => $data['method'] ?? 'gps',
            'lat'        => $data['lat'] ?? null,
            'lng'        => $data['lng'] ?? null,
            'location_text' => $data['location_text'] ?? null,
            'wifi_ssid'  => $data['wifi_ssid'] ?? null,
            'wifi_bssid' => $data['wifi_bssid'] ?? null,
            'status'     => $resultStatus,
            'checked_at' => $data['checked_at'] ?? date('Y-m-d H:i:s'),
            'remark'     => $data['remark'] ?? null,
        ];
        Db::table('attendance_records')->insert($record);
        return $this->success([], '打卡成功');
    }

    public function records()
    {
        $items = Db::table('attendance_records')
            ->where('user_id', $this->user()['id'])
            ->order('checked_at', 'desc')
            ->limit(50)
            ->select()
            ->toArray();
        return $this->success(['items' => $items]);
    }
}
