<?php

namespace app\admin\controller;

use app\common\controller\AdminApiController;
use think\facade\Db;
use think\facade\Request;

class ShiftSchedule extends AdminApiController
{
    public function index()
    {
        $keyword = trim((string)Request::get('keyword', ''));
        $deptId = Request::get('dept_id');
        
        $query = Db::table('attendance_rules')
            ->field('attendance_rules.*, departments.name as dept_name')
            ->leftJoin('departments', 'attendance_rules.dept_id = departments.id')
            ->order('attendance_rules.id', 'desc');
        
        if ($keyword !== '') {
            $query->whereLike('attendance_rules.name', "%{$keyword}%");
        }
        
        if ($deptId !== null && $deptId !== '') {
            $deptIdInt = (int)$deptId;
            if ($deptIdInt === 0) {
                $query->whereNull('attendance_rules.dept_id');
            } else {
                $query->where('attendance_rules.dept_id', $deptIdInt);
            }
        }
        
        $items = $query->select()->toArray();
        
        return $this->success([
            'items' => array_map([$this, 'formatShiftSchedule'], $items),
        ]);
    }

    public function read(int $id)
    {
        $shift = Db::table('attendance_rules')
            ->field('attendance_rules.*, departments.name as dept_name')
            ->leftJoin('departments', 'attendance_rules.dept_id = departments.id')
            ->where('attendance_rules.id', $id)
            ->find();
        
        if (!$shift) {
            $this->errorResponse('班次不存在', 404);
        }
        
        return $this->success([
            'shift_schedule' => $this->formatShiftSchedule($shift),
        ]);
    }

    public function save()
    {
        $payload = $this->requestData();
        $name = trim((string)($payload['name'] ?? ''));
        
        if ($name === '') {
            $this->errorResponse('请输入班次名称');
        }
        
        $startTime = trim((string)($payload['start_time'] ?? ''));
        $endTime = trim((string)($payload['end_time'] ?? ''));
        
        if ($startTime === '' || $endTime === '') {
            $this->errorResponse('请设置上班时间和下班时间');
        }
        
        $deptId = isset($payload['dept_id']) ? (int)$payload['dept_id'] : null;
        if ($deptId !== null) {
            $dept = Db::table('departments')->find($deptId);
            if (!$dept) {
                $this->errorResponse('部门不存在');
            }
        }
        
        $this->ensureNoDuplicateDeptRule($deptId, null);
        
        $id = Db::table('attendance_rules')->insertGetId([
            'name'                  => $name,
            'dept_id'               => $deptId,
            'workday'               => $this->normalizeWorkday($payload['workday'] ?? 'weekday'),
            'start_time'            => $startTime,
            'end_time'              => $endTime,
            'saturday_off'          => isset($payload['saturday_off']) ? (int)$payload['saturday_off'] : 1,
            'sunday_off'            => isset($payload['sunday_off']) ? (int)$payload['sunday_off'] : 1,
            'check_in_type'         => $this->normalizeCheckInType($payload['check_in_type'] ?? 'gps'),
            'wifi_ssid'             => isset($payload['wifi_ssid']) ? trim((string)$payload['wifi_ssid']) : null,
            'wifi_bssid'            => isset($payload['wifi_bssid']) ? trim((string)$payload['wifi_bssid']) : null,
            'gps_lat'               => isset($payload['gps_lat']) ? (float)$payload['gps_lat'] : null,
            'gps_lng'               => isset($payload['gps_lng']) ? (float)$payload['gps_lng'] : null,
            'gps_radius'            => isset($payload['gps_radius']) ? (int)$payload['gps_radius'] : 200,
            'allow_late_minutes'    => isset($payload['allow_late_minutes']) ? (int)$payload['allow_late_minutes'] : 0,
            'allow_early_minutes'   => isset($payload['allow_early_minutes']) ? (int)$payload['allow_early_minutes'] : 0,
            'late_threshold_minutes' => isset($payload['late_threshold_minutes']) ? (int)$payload['late_threshold_minutes'] : 30,
            'early_threshold_minutes' => isset($payload['early_threshold_minutes']) ? (int)$payload['early_threshold_minutes'] : 30,
            'absent_after_minutes'  => isset($payload['absent_after_minutes']) ? (int)$payload['absent_after_minutes'] : 60,
            'status'                => isset($payload['status']) ? (int)$payload['status'] : 1,
        ]);
        
        $shift = Db::table('attendance_rules')
            ->field('attendance_rules.*, departments.name as dept_name')
            ->leftJoin('departments', 'attendance_rules.dept_id = departments.id')
            ->where('attendance_rules.id', $id)
            ->find();
        
        return $this->success([
            'shift_schedule' => $this->formatShiftSchedule($shift),
        ], '班次已创建');
    }

    public function update(int $id)
    {
        $shift = Db::table('attendance_rules')->find($id);
        if (!$shift) {
            $this->errorResponse('班次不存在', 404);
        }
        
        $payload = $this->requestData();
        $data = [];
        
        if (array_key_exists('name', $payload)) {
            $name = trim((string)$payload['name']);
            if ($name === '') {
                $this->errorResponse('班次名称不能为空');
            }
            $data['name'] = $name;
        }
        
        if (array_key_exists('dept_id', $payload)) {
            $deptId = $payload['dept_id'] !== null ? (int)$payload['dept_id'] : null;
            if ($deptId !== null) {
                $dept = Db::table('departments')->find($deptId);
                if (!$dept) {
                    $this->errorResponse('部门不存在');
                }
            }
            $this->ensureNoDuplicateDeptRule($deptId, $id);
            $data['dept_id'] = $deptId;
        }
        
        if (array_key_exists('workday', $payload)) {
            $data['workday'] = $this->normalizeWorkday($payload['workday']);
        }
        
        if (array_key_exists('start_time', $payload)) {
            $data['start_time'] = trim((string)$payload['start_time']);
        }
        
        if (array_key_exists('end_time', $payload)) {
            $data['end_time'] = trim((string)$payload['end_time']);
        }
        
        if (array_key_exists('saturday_off', $payload)) {
            $data['saturday_off'] = (int)$payload['saturday_off'] ? 1 : 0;
        }
        
        if (array_key_exists('sunday_off', $payload)) {
            $data['sunday_off'] = (int)$payload['sunday_off'] ? 1 : 0;
        }
        
        if (array_key_exists('check_in_type', $payload)) {
            $data['check_in_type'] = $this->normalizeCheckInType($payload['check_in_type']);
        }
        
        if (array_key_exists('wifi_ssid', $payload)) {
            $data['wifi_ssid'] = $payload['wifi_ssid'] ? trim((string)$payload['wifi_ssid']) : null;
        }
        
        if (array_key_exists('wifi_bssid', $payload)) {
            $data['wifi_bssid'] = $payload['wifi_bssid'] ? trim((string)$payload['wifi_bssid']) : null;
        }
        
        if (array_key_exists('gps_lat', $payload)) {
            $data['gps_lat'] = $payload['gps_lat'] !== null ? (float)$payload['gps_lat'] : null;
        }
        
        if (array_key_exists('gps_lng', $payload)) {
            $data['gps_lng'] = $payload['gps_lng'] !== null ? (float)$payload['gps_lng'] : null;
        }
        
        if (array_key_exists('gps_radius', $payload)) {
            $data['gps_radius'] = (int)$payload['gps_radius'];
        }
        
        if (array_key_exists('allow_late_minutes', $payload)) {
            $data['allow_late_minutes'] = (int)$payload['allow_late_minutes'];
        }
        
        if (array_key_exists('allow_early_minutes', $payload)) {
            $data['allow_early_minutes'] = (int)$payload['allow_early_minutes'];
        }
        
        if (array_key_exists('late_threshold_minutes', $payload)) {
            $data['late_threshold_minutes'] = (int)$payload['late_threshold_minutes'];
        }
        
        if (array_key_exists('early_threshold_minutes', $payload)) {
            $data['early_threshold_minutes'] = (int)$payload['early_threshold_minutes'];
        }
        
        if (array_key_exists('absent_after_minutes', $payload)) {
            $data['absent_after_minutes'] = (int)$payload['absent_after_minutes'];
        }
        
        if (array_key_exists('status', $payload)) {
            $data['status'] = (int)$payload['status'] ? 1 : 0;
        }
        
        if (empty($data)) {
            $shift = Db::table('attendance_rules')
                ->field('attendance_rules.*, departments.name as dept_name')
                ->leftJoin('departments', 'attendance_rules.dept_id = departments.id')
                ->where('attendance_rules.id', $id)
                ->find();
            return $this->success([
                'shift_schedule' => $this->formatShiftSchedule($shift),
            ]);
        }
        
        Db::table('attendance_rules')->where('id', $id)->update($data);
        
        $shift = Db::table('attendance_rules')
            ->field('attendance_rules.*, departments.name as dept_name')
            ->leftJoin('departments', 'attendance_rules.dept_id = departments.id')
            ->where('attendance_rules.id', $id)
            ->find();
        
        return $this->success([
            'shift_schedule' => $this->formatShiftSchedule($shift),
        ], '班次已更新');
    }

    public function delete(int $id)
    {
        $shift = Db::table('attendance_rules')->find($id);
        if (!$shift) {
            $this->errorResponse('班次不存在', 404);
        }
        
        Db::table('attendance_records')->where('rule_id', $id)->update(['rule_id' => null]);
        Db::table('attendance_rules')->where('id', $id)->delete();
        
        return $this->success([], '班次已删除');
    }

    protected function formatShiftSchedule(array $shift): array
    {
        return [
            'id'                      => (int)$shift['id'],
            'name'                    => $shift['name'],
            'dept_id'                 => $shift['dept_id'] ? (int)$shift['dept_id'] : null,
            'dept_name'               => $shift['dept_name'] ?? null,
            'workday'                 => $shift['workday'],
            'start_time'              => $shift['start_time'],
            'end_time'                => $shift['end_time'],
            'saturday_off'            => (int)$shift['saturday_off'],
            'sunday_off'              => (int)$shift['sunday_off'],
            'check_in_type'           => $shift['check_in_type'],
            'wifi_ssid'               => $shift['wifi_ssid'],
            'wifi_bssid'              => $shift['wifi_bssid'],
            'gps_lat'                 => $shift['gps_lat'] ? (float)$shift['gps_lat'] : null,
            'gps_lng'                 => $shift['gps_lng'] ? (float)$shift['gps_lng'] : null,
            'gps_radius'              => (int)$shift['gps_radius'],
            'allow_late_minutes'      => (int)$shift['allow_late_minutes'],
            'allow_early_minutes'     => (int)$shift['allow_early_minutes'],
            'late_threshold_minutes'  => (int)($shift['late_threshold_minutes'] ?? 30),
            'early_threshold_minutes' => (int)($shift['early_threshold_minutes'] ?? 30),
            'absent_after_minutes'    => (int)($shift['absent_after_minutes'] ?? 60),
            'status'                  => (int)$shift['status'],
        ];
    }

    protected function normalizeWorkday($workday): string
    {
        $allowed = ['weekday', 'everyday', 'custom'];
        $workday = $workday ? strtolower($workday) : 'weekday';
        return in_array($workday, $allowed, true) ? $workday : 'weekday';
    }

    protected function normalizeCheckInType($type): string
    {
        $allowed = ['wifi', 'gps', 'both'];
        $type = $type ? strtolower($type) : 'gps';
        return in_array($type, $allowed, true) ? $type : 'gps';
    }

    protected function ensureNoDuplicateDeptRule(?int $deptId, ?int $excludeId): void
    {
        if ($deptId === null) {
            return;
        }
        
        $query = Db::table('attendance_rules')
            ->where('dept_id', $deptId)
            ->where('status', 1);
        
        if ($excludeId !== null) {
            $query->where('id', '<>', $excludeId);
        }
        
        $exists = $query->find();
        if ($exists) {
            $this->errorResponse('该部门已存在启用的班次设置');
        }
    }
}
