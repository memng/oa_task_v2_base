<?php

namespace app\api\controller;

use app\common\controller\ApiController;
use think\facade\Db;
use think\facade\Request;

class Attendance extends ApiController
{
    public function rules()
    {
        $userId = $this->user()['id'];
        $user = Db::table('users')->find($userId);
        
        $query = Db::table('attendance_rules')
            ->where('status', 1)
            ->order('id', 'desc');
        
        if ($user && !empty($user['dept_id'])) {
            $query->where(function ($q) use ($user) {
                $q->where('dept_id', $user['dept_id'])
                    ->whereOr('dept_id', null);
            });
        }
        
        $rules = $query->select()->toArray();
        
        $formattedRules = array_map([$this, 'formatRule'], $rules);
        
        return $this->success(['items' => $formattedRules]);
    }

    public function myRule()
    {
        $userId = $this->user()['id'];
        $user = Db::table('users')->find($userId);
        
        $rule = null;
        
        if ($user && !empty($user['dept_id'])) {
            $rule = Db::table('attendance_rules')
                ->where('dept_id', $user['dept_id'])
                ->where('status', 1)
                ->find();
        }
        
        if (!$rule) {
            $rule = Db::table('attendance_rules')
                ->whereNull('dept_id')
                ->where('status', 1)
                ->order('id', 'asc')
                ->find();
        }
        
        if (!$rule) {
            return $this->success([
                'rule' => null,
                'is_workday' => true,
                'message' => '未设置班次规则，请联系管理员'
            ]);
        }
        
        $today = date('Y-m-d');
        $dayOfWeek = date('N');
        
        $isWorkday = $this->isWorkday($rule, $dayOfWeek);
        
        return $this->success([
            'rule' => $this->formatRule($rule),
            'is_workday' => $isWorkday,
            'today' => $today,
            'day_of_week' => (int)$dayOfWeek,
        ]);
    }

    public function checkin()
    {
        $data = $this->requestData();
        $userId = $this->user()['id'];
        $user = Db::table('users')->find($userId);
        
        $checkIndicator = strtolower((string)($data['check_type'] ?? $data['status'] ?? 'check_in'));
        $checkType = in_array($checkIndicator, ['check_in', 'check_out', 'checkin', 'checkout'], true)
            ? ($checkIndicator === 'checkout' ? 'check_out' : ($checkIndicator === 'checkin' ? 'check_in' : $checkIndicator))
            : 'check_in';
        
        $checkedAt = $data['checked_at'] ?? date('Y-m-d H:i:s');
        $checkDate = date('Y-m-d', strtotime($checkedAt));
        $checkTime = date('H:i:s', strtotime($checkedAt));
        $dayOfWeek = date('N', strtotime($checkedAt));
        
        $rule = $this->getUserRule($user);
        
        $isWorkday = true;
        if ($rule) {
            $isWorkday = $this->isWorkday($rule, $dayOfWeek);
        }
        
        if (!$isWorkday) {
            return $this->success([
                'is_workday' => false,
                'message' => '今天是非工作日，无需打卡'
            ], '今天是非工作日');
        }
        
        $resultStatus = $this->calculateAttendanceStatus($rule, $checkType, $checkTime);
        
        if ($rule && $checkType === 'check_in') {
            $absentAfterMinutes = (int)($rule['absent_after_minutes'] ?? 60);
            $startTimeObj = \DateTime::createFromFormat('H:i:s', $rule['start_time'] . ':00');
            if (!$startTimeObj) {
                $startTimeObj = \DateTime::createFromFormat('H:i', $rule['start_time']);
            }
            if ($startTimeObj) {
                $absentThreshold = clone $startTimeObj;
                $absentThreshold->add(new \DateInterval("PT{$absentAfterMinutes}M"));
                $checkTimeObj = \DateTime::createFromFormat('H:i:s', $checkTime);
                if ($checkTimeObj && $checkTimeObj > $absentThreshold) {
                    $resultStatus = 'absent';
                }
            }
        }
        
        $record = [
            'rule_id'  => $rule ? $rule['id'] : null,
            'user_id'  => $userId,
            'check_type' => $checkType,
            'method'     => $data['method'] ?? 'gps',
            'lat'        => $data['lat'] ?? null,
            'lng'        => $data['lng'] ?? null,
            'location_text' => $data['location_text'] ?? null,
            'wifi_ssid'  => $data['wifi_ssid'] ?? null,
            'wifi_bssid' => $data['wifi_bssid'] ?? null,
            'status'     => $resultStatus,
            'checked_at' => $checkedAt,
            'check_date' => $checkDate,
            'remark'     => $data['remark'] ?? null,
        ];
        
        Db::table('attendance_records')->insert($record);
        
        $statusLabels = [
            'normal' => '正常',
            'late' => '迟到',
            'early' => '早退',
            'absent' => '旷工',
        ];
        
        $message = '打卡成功';
        if ($resultStatus !== 'normal') {
            $message = '打卡成功 (' . ($statusLabels[$resultStatus] ?? $resultStatus) . ')';
        }
        
        return $this->success([
            'status' => $resultStatus,
            'status_label' => $statusLabels[$resultStatus] ?? $resultStatus,
            'check_type' => $checkType,
            'checked_at' => $checkedAt,
            'is_workday' => true,
        ], $message);
    }

    public function records()
    {
        $userId = $this->user()['id'];
        $date = Request::get('date');
        $startDate = Request::get('start_date');
        $endDate = Request::get('end_date');
        
        $query = Db::table('attendance_records')
            ->where('user_id', $userId);
        
        if ($date) {
            $query->where('check_date', $date);
        }
        
        if ($startDate) {
            $query->where('check_date', '>=', $startDate);
        }
        
        if ($endDate) {
            $query->where('check_date', '<=', $endDate);
        }
        
        $items = $query
            ->order('checked_at', 'desc')
            ->limit(100)
            ->select()
            ->toArray();
        
        return $this->success(['items' => array_map([$this, 'formatRecord'], $items)]);
    }

    public function todayStatus()
    {
        $userId = $this->user()['id'];
        $user = Db::table('users')->find($userId);
        $today = date('Y-m-d');
        $dayOfWeek = date('N');
        
        $rule = $this->getUserRule($user);
        
        $isWorkday = true;
        if ($rule) {
            $isWorkday = $this->isWorkday($rule, $dayOfWeek);
        }
        
        $todaysRecords = Db::table('attendance_records')
            ->where('user_id', $userId)
            ->where('check_date', $today)
            ->order('checked_at', 'asc')
            ->select()
            ->toArray();
        
        $checkInRecord = null;
        $checkOutRecord = null;
        
        foreach ($todaysRecords as $record) {
            $checkType = strtolower($record['check_type'] ?? '');
            if ($checkType === 'check_in' || $checkType === 'checkin') {
                $checkInRecord = $record;
            } elseif ($checkType === 'check_out' || $checkType === 'checkout') {
                $checkOutRecord = $record;
            }
        }
        
        $currentStatus = 'not_checked';
        if ($checkInRecord && !$checkOutRecord) {
            $currentStatus = 'working';
        } elseif ($checkInRecord && $checkOutRecord) {
            $currentStatus = 'checked_out';
        }
        
        $daySummary = $this->calculateDaySummary($rule, $checkInRecord, $checkOutRecord, $isWorkday);
        
        return $this->success([
            'today' => $today,
            'day_of_week' => (int)$dayOfWeek,
            'is_workday' => $isWorkday,
            'rule' => $rule ? $this->formatRule($rule) : null,
            'current_status' => $currentStatus,
            'check_in_record' => $checkInRecord ? $this->formatRecord($checkInRecord) : null,
            'check_out_record' => $checkOutRecord ? $this->formatRecord($checkOutRecord) : null,
            'day_summary' => $daySummary,
        ]);
    }

    public function monthlyStats()
    {
        $userId = $this->user()['id'];
        $user = Db::table('users')->find($userId);
        $year = (int)Request::get('year', date('Y'));
        $month = (int)Request::get('month', date('m'));
        
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = date('Y-m-t', strtotime($startDate));
        
        $records = Db::table('attendance_records')
            ->where('user_id', $userId)
            ->where('check_date', '>=', $startDate)
            ->where('check_date', '<=', $endDate)
            ->order('checked_at', 'asc')
            ->select()
            ->toArray();
        
        $grouped = [];
        foreach ($records as $record) {
            $date = $record['check_date'];
            if (!isset($grouped[$date])) {
                $grouped[$date] = ['check_in' => null, 'check_out' => null];
            }
            $checkType = strtolower($record['check_type'] ?? '');
            if ($checkType === 'check_in' || $checkType === 'checkin') {
                $grouped[$date]['check_in'] = $record;
            } elseif ($checkType === 'check_out' || $checkType === 'checkout') {
                $grouped[$date]['check_out'] = $record;
            }
        }
        
        $rule = $this->getUserRule($user);
        
        $stats = [
            'total_days' => 0,
            'work_days' => 0,
            'present_days' => 0,
            'late_days' => 0,
            'early_days' => 0,
            'absent_days' => 0,
            'leave_days' => 0,
            'details' => [],
        ];
        
        $currentDate = strtotime($startDate);
        $endTimestamp = strtotime($endDate);
        
        while ($currentDate <= $endTimestamp) {
            $dateStr = date('Y-m-d', $currentDate);
            $dayOfWeek = date('N', $currentDate);
            
            $isWorkday = true;
            if ($rule) {
                $isWorkday = $this->isWorkday($rule, $dayOfWeek);
            }
            
            $stats['total_days']++;
            
            if ($isWorkday) {
                $stats['work_days']++;
            }
            
            $dayData = [
                'date' => $dateStr,
                'day_of_week' => (int)$dayOfWeek,
                'is_workday' => $isWorkday,
                'check_in' => null,
                'check_out' => null,
                'day_status' => 'normal',
            ];
            
            if (isset($grouped[$dateStr])) {
                $checkIn = $grouped[$dateStr]['check_in'];
                $checkOut = $grouped[$dateStr]['check_out'];
                
                if ($checkIn) {
                    $dayData['check_in'] = $this->formatRecord($checkIn);
                }
                if ($checkOut) {
                    $dayData['check_out'] = $this->formatRecord($checkOut);
                }
                
                $daySummary = $this->calculateDaySummary($rule, $checkIn, $checkOut, $isWorkday);
                $dayData['day_status'] = $daySummary['day_status'];
                
                if ($isWorkday) {
                    if ($daySummary['day_status'] === 'absent') {
                        $stats['absent_days']++;
                    } else {
                        $stats['present_days']++;
                        if ($daySummary['is_late']) {
                            $stats['late_days']++;
                        }
                        if ($daySummary['is_early']) {
                            $stats['early_days']++;
                        }
                    }
                }
            } elseif ($isWorkday) {
                $dayData['day_status'] = 'absent';
                $stats['absent_days']++;
            }
            
            $stats['details'][] = $dayData;
            
            $currentDate = strtotime('+1 day', $currentDate);
        }
        
        return $this->success([
            'year' => $year,
            'month' => $month,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'stats' => $stats,
        ]);
    }

    protected function getUserRule($user): ?array
    {
        if (!$user) {
            return null;
        }
        
        if (!empty($user['dept_id'])) {
            $rule = Db::table('attendance_rules')
                ->where('dept_id', $user['dept_id'])
                ->where('status', 1)
                ->find();
            if ($rule) {
                return $rule;
            }
        }
        
        $rule = Db::table('attendance_rules')
            ->whereNull('dept_id')
            ->where('status', 1)
            ->order('id', 'asc')
            ->find();
        
        return $rule ?: null;
    }

    protected function isWorkday(array $rule, $dayOfWeek): bool
    {
        $dayOfWeek = (int)$dayOfWeek;
        
        $saturdayOff = (int)($rule['saturday_off'] ?? 1);
        $sundayOff = (int)($rule['sunday_off'] ?? 1);
        
        if ($dayOfWeek === 6 && $saturdayOff) {
            return false;
        }
        if ($dayOfWeek === 7 && $sundayOff) {
            return false;
        }
        
        $workday = $rule['workday'] ?? 'weekday';
        if ($workday === 'everyday') {
            return true;
        }
        if ($workday === 'weekday') {
            return $dayOfWeek >= 1 && $dayOfWeek <= 5;
        }
        
        return true;
    }

    protected function calculateAttendanceStatus(?array $rule, string $checkType, string $checkTime): string
    {
        if (!$rule) {
            return 'normal';
        }
        
        $allowLateMinutes = (int)($rule['allow_late_minutes'] ?? 0);
        $allowEarlyMinutes = (int)($rule['allow_early_minutes'] ?? 0);
        
        $checkTimeObj = \DateTime::createFromFormat('H:i:s', $checkTime);
        if (!$checkTimeObj) {
            return 'normal';
        }
        
        if ($checkType === 'check_in') {
            $startTime = $rule['start_time'];
            $startTimeObj = \DateTime::createFromFormat('H:i:s', $startTime . ':00');
            if (!$startTimeObj) {
                $startTimeObj = \DateTime::createFromFormat('H:i', $startTime);
            }
            if (!$startTimeObj) {
                return 'normal';
            }
            
            $allowLateInterval = new \DateInterval("PT{$allowLateMinutes}M");
            $lateThreshold = clone $startTimeObj;
            $lateThreshold->add($allowLateInterval);
            
            if ($checkTimeObj > $lateThreshold) {
                return 'late';
            }
        }
        
        if ($checkType === 'check_out') {
            $endTime = $rule['end_time'];
            $endTimeObj = \DateTime::createFromFormat('H:i:s', $endTime . ':00');
            if (!$endTimeObj) {
                $endTimeObj = \DateTime::createFromFormat('H:i', $endTime);
            }
            if (!$endTimeObj) {
                return 'normal';
            }
            
            $allowEarlyInterval = new \DateInterval("PT{$allowEarlyMinutes}M");
            $earlyThreshold = clone $endTimeObj;
            $earlyThreshold->sub($allowEarlyInterval);
            
            if ($checkTimeObj < $earlyThreshold) {
                return 'early';
            }
        }
        
        return 'normal';
    }

    protected function calculateDaySummary(?array $rule, ?array $checkIn, ?array $checkOut, bool $isWorkday): array
    {
        $statusLabels = [
            'normal' => '正常',
            'late' => '迟到',
            'early' => '早退',
            'absent' => '旷工',
            'leave' => '请假',
        ];
        
        if (!$isWorkday) {
            return [
                'day_status' => 'off_day',
                'day_status_label' => '休息日',
                'is_late' => false,
                'is_early' => false,
                'is_absent' => false,
                'work_duration_minutes' => 0,
            ];
        }
        
        if (!$checkIn && !$checkOut) {
            return [
                'day_status' => 'absent',
                'day_status_label' => '旷工',
                'is_late' => false,
                'is_early' => false,
                'is_absent' => true,
                'work_duration_minutes' => 0,
            ];
        }
        
        $isLate = false;
        $isEarly = false;
        $isAbsent = false;
        $dayStatus = 'normal';
        
        if ($checkIn) {
            $inStatus = $checkIn['status'] ?? 'normal';
            if ($inStatus === 'late') {
                $isLate = true;
                $dayStatus = 'late';
            }
            if ($inStatus === 'absent') {
                $isAbsent = true;
                $dayStatus = 'absent';
            }
        } else {
            $isLate = true;
            $isAbsent = true;
            $dayStatus = 'absent';
        }
        
        if ($checkOut) {
            $outStatus = $checkOut['status'] ?? 'normal';
            if ($outStatus === 'early') {
                $isEarly = true;
                if ($dayStatus === 'normal') {
                    $dayStatus = 'early';
                } elseif ($dayStatus === 'late') {
                    $dayStatus = 'late_early';
                }
            }
        }
        
        $workDurationMinutes = 0;
        if ($checkIn && $checkOut) {
            $inTime = strtotime($checkIn['checked_at']);
            $outTime = strtotime($checkOut['checked_at']);
            if ($outTime > $inTime) {
                $workDurationMinutes = (int)(($outTime - $inTime) / 60);
            }
        }
        
        $statusLabelMap = [
            'normal' => '正常',
            'late' => '迟到',
            'early' => '早退',
            'late_early' => '迟到+早退',
            'absent' => '旷工',
        ];
        
        return [
            'day_status' => $dayStatus,
            'day_status_label' => $statusLabelMap[$dayStatus] ?? $dayStatus,
            'is_late' => $isLate,
            'is_early' => $isEarly,
            'is_absent' => $isAbsent,
            'work_duration_minutes' => $workDurationMinutes,
        ];
    }

    protected function formatRule(array $rule): array
    {
        return [
            'id'                      => (int)$rule['id'],
            'name'                    => $rule['name'],
            'dept_id'                 => $rule['dept_id'] ? (int)$rule['dept_id'] : null,
            'workday'                 => $rule['workday'],
            'start_time'              => $rule['start_time'],
            'end_time'                => $rule['end_time'],
            'saturday_off'            => (int)($rule['saturday_off'] ?? 1),
            'sunday_off'              => (int)($rule['sunday_off'] ?? 1),
            'check_in_type'           => $rule['check_in_type'],
            'gps_lat'                 => $rule['gps_lat'] ? (float)$rule['gps_lat'] : null,
            'gps_lng'                 => $rule['gps_lng'] ? (float)$rule['gps_lng'] : null,
            'gps_radius'              => (int)$rule['gps_radius'],
            'allow_late_minutes'      => (int)$rule['allow_late_minutes'],
            'allow_early_minutes'     => (int)$rule['allow_early_minutes'],
            'late_threshold_minutes'  => (int)($rule['late_threshold_minutes'] ?? 30),
            'early_threshold_minutes' => (int)($rule['early_threshold_minutes'] ?? 30),
            'absent_after_minutes'    => (int)($rule['absent_after_minutes'] ?? 60),
            'status'                  => (int)$rule['status'],
        ];
    }

    protected function formatRecord(array $record): array
    {
        $statusLabels = [
            'normal' => '正常',
            'late' => '迟到',
            'early' => '早退',
            'absent' => '旷工',
            'leave' => '请假',
            'overtime' => '加班',
            'lack' => '缺卡',
        ];
        
        $typeLabels = [
            'check_in' => '上班打卡',
            'check_out' => '下班打卡',
            'checkin' => '上班打卡',
            'checkout' => '下班打卡',
        ];
        
        $methodLabels = [
            'wifi' => 'WiFi打卡',
            'gps' => 'GPS打卡',
            'manual' => '手动补卡',
        ];
        
        $status = $record['status'] ?? 'normal';
        $checkType = $record['check_type'] ?? 'check_in';
        
        return [
            'id'               => (int)$record['id'],
            'rule_id'          => $record['rule_id'] ? (int)$record['rule_id'] : null,
            'user_id'          => (int)$record['user_id'],
            'check_type'       => $checkType,
            'check_type_label' => $typeLabels[$checkType] ?? $checkType,
            'method'           => $record['method'],
            'method_label'     => $methodLabels[$record['method'] ?? 'gps'] ?? $record['method'],
            'lat'              => $record['lat'] ? (float)$record['lat'] : null,
            'lng'              => $record['lng'] ? (float)$record['lng'] : null,
            'location_text'    => $record['location_text'],
            'wifi_ssid'        => $record['wifi_ssid'],
            'status'           => $status,
            'status_label'     => $statusLabels[$status] ?? $status,
            'checked_at'       => $record['checked_at'],
            'check_date'       => $record['check_date'] ?? date('Y-m-d', strtotime($record['checked_at'])),
            'remark'           => $record['remark'],
        ];
    }
}
