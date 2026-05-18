<?php

namespace app\api\controller;

use app\common\controller\ApiController;
use think\facade\Db;
use think\facade\Request;
use think\exception\PDOException;

class UserProfile extends ApiController
{
    protected const SEND_CODE_INTERVAL_SECONDS = 60;
    protected const MAX_SEND_CODES_PER_DAY = 10;
    protected const MAX_VERIFY_ERRORS = 5;
    protected const VERIFY_LOCK_MINUTES = 30;

    public function emergencyContacts()
    {
        $contacts = Db::table('emergency_contacts')
            ->where('user_id', $this->user()['id'])
            ->order('is_primary', 'desc')
            ->order('id', 'asc')
            ->select()
            ->toArray();

        return $this->success([
            'contacts' => array_map([$this, 'formatContact'], $contacts),
        ]);
    }

    public function addEmergencyContact()
    {
        $payload = $this->requestData();
        $name = trim((string)($payload['name'] ?? ''));
        $mobile = trim((string)($payload['mobile'] ?? ''));
        $relationship = trim((string)($payload['relationship'] ?? ''));
        $isPrimary = (bool)($payload['is_primary'] ?? false);

        if (empty($name)) {
            $this->errorResponse('请输入联系人姓名');
        }
        if (!preg_match('/^1\\d{10}$/', $mobile)) {
            $this->errorResponse('请输入正确的手机号');
        }

        $now = date('Y-m-d H:i:s');
        $userId = $this->user()['id'];

        $existing = Db::table('emergency_contacts')
            ->where('user_id', $userId)
            ->count();

        Db::startTrans();
        try {
            if ($isPrimary || $existing === 0) {
                Db::table('emergency_contacts')
                    ->where('user_id', $userId)
                    ->update(['is_primary' => 0, 'updated_at' => $now]);
                $isPrimary = true;
            }

            $id = Db::table('emergency_contacts')->insertGetId([
                'user_id'      => $userId,
                'name'         => $name,
                'mobile'       => $mobile,
                'relationship' => $relationship ?: null,
                'is_primary'   => $isPrimary ? 1 : 0,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }

        $contact = Db::table('emergency_contacts')->find($id);
        return $this->success([
            'contact' => $this->formatContact($contact),
        ], '添加成功');
    }

    public function updateEmergencyContact($id)
    {
        $id = (int)$id;
        $userId = $this->user()['id'];

        $contact = Db::table('emergency_contacts')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->find();
        if (!$contact) {
            $this->errorResponse('联系人不存在', 404);
        }

        $payload = $this->requestData();
        $name = trim((string)($payload['name'] ?? ''));
        $mobile = trim((string)($payload['mobile'] ?? ''));
        $relationship = isset($payload['relationship']) ? trim((string)$payload['relationship']) : null;
        $isPrimary = isset($payload['is_primary']) ? (bool)$payload['is_primary'] : null;

        $updates = [];
        if ($name !== '') {
            $updates['name'] = $name;
        }
        if ($mobile !== '') {
            if (!preg_match('/^1\\d{10}$/', $mobile)) {
                $this->errorResponse('请输入正确的手机号');
            }
            $updates['mobile'] = $mobile;
        }
        if ($relationship !== null) {
            $updates['relationship'] = $relationship ?: null;
        }

        $now = date('Y-m-d H:i:s');
        $becomeNotPrimary = ($isPrimary === false && $contact['is_primary'] == 1);

        Db::startTrans();
        try {
            if ($isPrimary === true) {
                Db::table('emergency_contacts')
                    ->where('user_id', $userId)
                    ->update(['is_primary' => 0, 'updated_at' => $now]);
                $updates['is_primary'] = 1;
            }

            if (!empty($updates)) {
                $updates['updated_at'] = $now;
                Db::table('emergency_contacts')->where('id', $id)->update($updates);
            }

            if ($becomeNotPrimary) {
                $nextPrimary = Db::table('emergency_contacts')
                    ->where('user_id', $userId)
                    ->where('id', '<>', $id)
                    ->where('is_primary', 0)
                    ->order('id', 'asc')
                    ->find();
                if ($nextPrimary) {
                    Db::table('emergency_contacts')
                        ->where('id', $nextPrimary['id'])
                        ->update(['is_primary' => 1, 'updated_at' => $now]);
                } else {
                    Db::table('emergency_contacts')
                        ->where('id', $id)
                        ->update(['is_primary' => 1, 'updated_at' => $now]);
                }
            }
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }

        $contact = Db::table('emergency_contacts')->find($id);
        return $this->success([
            'contact' => $this->formatContact($contact),
        ], '更新成功');
    }

    public function deleteEmergencyContact($id)
    {
        $id = (int)$id;
        $userId = $this->user()['id'];

        $contact = Db::table('emergency_contacts')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->find();
        if (!$contact) {
            $this->errorResponse('联系人不存在', 404);
        }

        $wasPrimary = $contact['is_primary'] == 1;

        Db::startTrans();
        try {
            Db::table('emergency_contacts')->where('id', $id)->delete();

            if ($wasPrimary) {
                $nextPrimary = Db::table('emergency_contacts')
                    ->where('user_id', $userId)
                    ->order('id', 'asc')
                    ->find();
                if ($nextPrimary) {
                    Db::table('emergency_contacts')
                        ->where('id', $nextPrimary['id'])
                        ->update(['is_primary' => 1, 'updated_at' => date('Y-m-d H:i:s')]);
                }
            }
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }

        return $this->success([], '删除成功');
    }

    public function sendChangeMobileCode()
    {
        $payload = $this->requestData();
        $mobile = trim((string)($payload['mobile'] ?? ''));

        if (!preg_match('/^1\\d{10}$/', $mobile)) {
            $this->errorResponse('请输入正确的手机号');
        }

        $exists = Db::table('users')->where('mobile', $mobile)->find();
        if ($exists && (int)$exists['id'] !== (int)$this->user()['id']) {
            $this->errorResponse('该手机号已被其他账号绑定');
        }

        $now = time();
        $nowStr = date('Y-m-d H:i:s', $now);
        $todayStart = date('Y-m-d 00:00:00');
        $todayEnd = date('Y-m-d 23:59:59');

        $lastSend = Db::table('verification_codes')
            ->where('mobile', $mobile)
            ->where('type', 'change_mobile')
            ->order('id', 'desc')
            ->find();

        if ($lastSend && strtotime($lastSend['created_at']) > $now - self::SEND_CODE_INTERVAL_SECONDS) {
            $remaining = self::SEND_CODE_INTERVAL_SECONDS - ($now - strtotime($lastSend['created_at']));
            $this->errorResponse(sprintf('发送太频繁，请%d秒后再试', $remaining));
        }

        if ($lastSend && !empty($lastSend['locked_until']) && strtotime($lastSend['locked_until']) > $now) {
            $remainingMinutes = ceil((strtotime($lastSend['locked_until']) - $now) / 60);
            $this->errorResponse(sprintf('该手机号已被锁定，请%d分钟后再试', $remainingMinutes));
        }

        $todayCount = Db::table('verification_codes')
            ->where('mobile', $mobile)
            ->where('type', 'change_mobile')
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->count();

        if ($todayCount >= self::MAX_SEND_CODES_PER_DAY) {
            $this->errorResponse('今日发送次数已达上限，请明日再试');
        }

        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiredAt = date('Y-m-d H:i:s', $now + 600);

        Db::table('verification_codes')->insert([
            'mobile'             => $mobile,
            'code'               => $code,
            'type'               => 'change_mobile',
            'expired_at'         => $expiredAt,
            'verify_error_count' => 0,
            'locked_until'       => null,
            'created_at'         => $nowStr,
        ]);

        return $this->success([
            'debug_code'       => $code,
            'expires_in'       => 600,
            'interval_seconds' => self::SEND_CODE_INTERVAL_SECONDS,
        ], '验证码已发送');
    }

    public function changeMobile()
    {
        $payload = $this->requestData();
        $mobile = trim((string)($payload['mobile'] ?? ''));
        $code = trim((string)($payload['code'] ?? ''));
        $userId = $this->user()['id'];

        if (!preg_match('/^1\\d{10}$/', $mobile)) {
            $this->errorResponse('请输入正确的手机号');
        }
        if (strlen($code) !== 6) {
            $this->errorResponse('请输入6位验证码');
        }

        $now = time();
        $nowStr = date('Y-m-d H:i:s', $now);
        $lockedUntil = null;
        $profile = null;

        try {
            Db::startTrans();
            try {
                $latestRecord = Db::table('verification_codes')
                    ->where('mobile', $mobile)
                    ->where('type', 'change_mobile')
                    ->lock(true)
                    ->order('id', 'desc')
                    ->find();

                if (!$latestRecord) {
                    Db::commit();
                    $this->errorResponse('请先获取验证码');
                }

                if (!empty($latestRecord['locked_until']) && strtotime($latestRecord['locked_until']) > $now) {
                    $remainingMinutes = ceil((strtotime($latestRecord['locked_until']) - $now) / 60);
                    Db::commit();
                    $this->errorResponse(sprintf('验证码错误次数过多，请%d分钟后重新获取', $remainingMinutes));
                }

                if (!empty($latestRecord['used_at'])) {
                    Db::commit();
                    $this->errorResponse('验证码已被使用，请重新获取');
                }

                if (strtotime($latestRecord['expired_at']) < $now) {
                    Db::commit();
                    $this->errorResponse('验证码已过期');
                }

                if ($latestRecord['code'] !== $code) {
                    $errorCount = (int)($latestRecord['verify_error_count'] ?? 0) + 1;
                    $updates = ['verify_error_count' => $errorCount];

                    if ($errorCount >= self::MAX_VERIFY_ERRORS) {
                        $lockedUntil = date('Y-m-d H:i:s', $now + self::VERIFY_LOCK_MINUTES * 60);
                        $updates['locked_until'] = $lockedUntil;
                        $updates['used_at'] = $nowStr;
                    }

                    Db::table('verification_codes')
                        ->where('id', $latestRecord['id'])
                        ->update($updates);
                    Db::commit();

                    if ($lockedUntil) {
                        $this->errorResponse(sprintf('错误次数过多，请%d分钟后重新获取验证码', self::VERIFY_LOCK_MINUTES));
                    }

                    $remaining = self::MAX_VERIFY_ERRORS - $errorCount;
                    $this->errorResponse(sprintf('验证码错误，还剩%d次机会', $remaining));
                }

                $existing = Db::table('users')
                    ->where('mobile', $mobile)
                    ->lock(true)
                    ->find();

                if ($existing && (int)$existing['id'] !== $userId) {
                    Db::commit();
                    $this->errorResponse('该手机号已被其他账号绑定');
                }

                Db::table('verification_codes')
                    ->where('id', $latestRecord['id'])
                    ->update(['used_at' => $nowStr]);

                Db::table('users')
                    ->where('id', $userId)
                    ->update([
                        'mobile'     => $mobile,
                        'updated_at' => $nowStr,
                    ]);

                Db::commit();
            } catch (\Throwable $e) {
                Db::rollback();
                throw $e;
            }
        } catch (PDOException $e) {
            $errorInfo = $e->errorInfo;
            if (!empty($errorInfo) && isset($errorInfo[1]) && (int)$errorInfo[1] === 1062) {
                $this->errorResponse('该手机号已被其他账号绑定，请更换后重试');
            }
            throw $e;
        }

        $profile = Db::table('users')->where('id', $userId)->find();
        return $this->success([
            'profile' => $this->formatUser($profile),
        ], '手机号已更新');
    }

    protected function formatContact(array $contact): array
    {
        return [
            'id'           => (int)$contact['id'],
            'user_id'      => (int)$contact['user_id'],
            'name'         => $contact['name'],
            'mobile'       => $contact['mobile'],
            'relationship' => $contact['relationship'],
            'is_primary'   => (bool)$contact['is_primary'],
            'created_at'   => $contact['created_at'],
            'updated_at'   => $contact['updated_at'],
        ];
    }

    protected function formatUser(array $user): array
    {
        $dept = [];
        if (!empty($user['dept_id'])) {
            $dept = Db::table('departments')->find($user['dept_id']) ?: [];
        }
        $contacts = Db::table('emergency_contacts')
            ->where('user_id', $user['id'])
            ->order('is_primary', 'desc')
            ->order('id', 'asc')
            ->select()
            ->toArray();

        return [
            'id'                => (int)$user['id'],
            'name'              => $user['name'],
            'nickname'          => $user['nickname'],
            'mobile'            => $user['mobile'],
            'email'             => $user['email'],
            'address'           => $user['address'],
            'id_card'           => $user['id_card'],
            'bank_account_name' => $user['bank_account_name'],
            'bank_name'         => $user['bank_name'],
            'bank_card_no'      => $user['bank_card_no'],
            'dept'              => $dept ? [
                'id'   => (int)$dept['id'],
                'name' => $dept['name'],
                'type' => $dept['type'],
            ] : null,
            'status'            => $user['status'],
            'avatar_url'        => $user['avatar_url'],
            'openid'            => $user['openid'],
            'last_login'        => $user['last_login_at'],
            'hire_date'         => $user['hire_date'],
            'emergency_contacts'=> array_map([$this, 'formatContact'], $contacts),
        ];
    }
}
