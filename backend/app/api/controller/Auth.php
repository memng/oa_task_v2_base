<?php

namespace app\api\controller;

use app\common\controller\ApiController;
use think\facade\Db;
use think\facade\Request;

class Auth extends ApiController
{
    protected array $publicActions = ['login', 'register'];

    protected const MAX_LOGIN_FAILURES = 5;
    protected const LOCK_DURATION_MINUTES = 10;

    public function login()
    {
        $payload = $this->requestData();
        $loginType = strtolower((string)($payload['login_type'] ?? ''));
        $accountLoginTypes = ['password', 'mobile', 'account'];

        if ($loginType === 'wechat' || (!empty($payload['code']) && !in_array($loginType, $accountLoginTypes, true))) {
            $user = $this->loginByWeChat($payload);
        } else {
            $user = $this->loginByPassword($payload);
        }

        return $this->issueTokenResponse($user);
    }

    public function register()
    {
        $payload = $this->requestData();
        $mobile = trim((string)($payload['mobile'] ?? ''));
        $password = (string)($payload['password'] ?? '');
        $confirm = (string)($payload['confirm_password'] ?? '');
        $name = trim((string)($payload['name'] ?? ''));
        $idCard = trim((string)($payload['id_card'] ?? ''));
        $address = trim((string)($payload['address'] ?? ''));
        $bankAccountName = trim((string)($payload['bank_account_name'] ?? ''));
        $bankName = trim((string)($payload['bank_name'] ?? ''));
        $bankCardNo = trim((string)($payload['bank_card_no'] ?? ''));
        $deptId = (int)($payload['dept_id'] ?? 0);
        $nickname = trim((string)($payload['nickname'] ?? ''));
        $avatarUrl = (string)($payload['avatar_url'] ?? '');
        $code = trim((string)($payload['code'] ?? ''));

        if (!preg_match('/^1\\d{10}$/', $mobile)) {
            $this->errorResponse('请输入正确的手机号');
        }
        if (strlen($password) < 6) {
            $this->errorResponse('密码至少6位');
        }
        if ($confirm !== '' && $confirm !== $password) {
            $this->errorResponse('两次输入的密码不一致');
        }
        if (empty($name) || empty($idCard) || empty($address) || empty($bankAccountName) || empty($bankName) || empty($bankCardNo)) {
            $this->errorResponse('请完善资料信息');
        }
        if (empty($deptId)) {
            $this->errorResponse('请选择部门');
        }
        if (empty($code)) {
            $this->errorResponse('请完成微信绑定授权');
        }

        $dept = Db::table('departments')
            ->where('status', 1)
            ->where('id', $deptId)
            ->find();
        if (!$dept) {
            $this->errorResponse('选择的部门不存在或已停用');
        }

        $exists = Db::table('users')->where('mobile', $mobile)->find();
        if ($exists) {
            $this->errorResponse('手机号已注册，请直接登录');
        }

        $session = $this->fetchWeChatSession($code);
        $openid = $session['openid'] ?? '';
        $unionId = $session['unionid'] ?? null;
        if (empty($openid)) {
            $this->errorResponse('微信授权失败，请重新绑定');
        }

        $openUser = Db::table('users')->where('openid', $openid)->find();
        if ($openUser) {
            $this->errorResponse('该微信已绑定其他账号');
        }

        $now = date('Y-m-d H:i:s');
        Db::table('users')->insert([
            'name'               => $name,
            'nickname'           => $nickname ?: null,
            'mobile'             => $mobile,
            'password'           => password_hash($password, PASSWORD_BCRYPT),
            'dept_id'            => $deptId,
            'id_card'            => $idCard,
            'address'            => $address,
            'bank_account_name'  => $bankAccountName,
            'bank_name'          => $bankName,
            'bank_card_no'       => $bankCardNo,
            'openid'             => $openid,
            'unionid'            => $unionId ?: null,
            'avatar_url'         => $avatarUrl ?: null,
            'status'             => 'pending',
            'created_at'         => $now,
            'updated_at'         => $now,
        ]);

        return $this->success([
            'pending' => true,
        ], '注册成功，请等待后台审核');
    }

    protected function loginByPassword(array $payload): array
    {
        $mobile = trim((string)($payload['mobile'] ?? ''));
        $password = (string)($payload['password'] ?? '');

        if (empty($mobile) || empty($password)) {
            $this->errorResponse('请输入手机号和密码');
        }

        $lockInfo = $this->checkLoginLock($mobile);
        if ($lockInfo['locked']) {
            $this->errorResponse(
                sprintf('账号已锁定，请%d分钟后再试', (int)ceil($lockInfo['remaining_seconds'] / 60)),
                400,
                [
                    'locked' => true,
                    'remaining_seconds' => $lockInfo['remaining_seconds'],
                ]
            );
        }

        $user = Db::table('users')->where('mobile', $mobile)->find();
        if (!$user) {
            $this->recordLoginFailure($mobile, null);
            $remaining = self::MAX_LOGIN_FAILURES - 1;
            $this->errorResponse(
                sprintf('登录失败，密码错误%d次您的账号会被锁定%d分钟，您还有%d次机会', self::MAX_LOGIN_FAILURES, self::LOCK_DURATION_MINUTES, $remaining),
                400,
                [
                    'remaining_attempts' => $remaining,
                    'max_attempts' => self::MAX_LOGIN_FAILURES,
                ]
            );
        }

        if (empty($user['password']) || !password_verify($password, $user['password'])) {
            $failureInfo = $this->recordLoginFailure($mobile, $user['id']);
            $remaining = self::MAX_LOGIN_FAILURES - $failureInfo['failure_count'];
            
            if ($remaining <= 0) {
                $this->errorResponse(
                    sprintf('登录失败，账号已被锁定%d分钟', self::LOCK_DURATION_MINUTES),
                    400,
                    [
                        'locked' => true,
                        'remaining_seconds' => self::LOCK_DURATION_MINUTES * 60,
                    ]
                );
            }
            
            $this->errorResponse(
                sprintf('登录失败，密码错误%d次您的账号会被锁定%d分钟，您还有%d次机会', self::MAX_LOGIN_FAILURES, self::LOCK_DURATION_MINUTES, $remaining),
                400,
                [
                    'remaining_attempts' => $remaining,
                    'max_attempts' => self::MAX_LOGIN_FAILURES,
                ]
            );
        }

        $this->resetLoginFailure($mobile);
        $this->assertUserStatus($user);
        return $user;
    }

    protected function checkLoginLock(string $mobile): array
    {
        $record = Db::table('login_failure_records')
            ->where('mobile', $mobile)
            ->find();

        if (!$record) {
            return ['locked' => false, 'remaining_seconds' => 0];
        }

        if (empty($record['locked_until'])) {
            return ['locked' => false, 'remaining_seconds' => 0];
        }

        $lockUntil = strtotime($record['locked_until']);
        $now = time();

        if ($lockUntil > $now) {
            return [
                'locked' => true,
                'remaining_seconds' => $lockUntil - $now,
            ];
        }

        $this->resetLoginFailure($mobile);
        return ['locked' => false, 'remaining_seconds' => 0];
    }

    protected function recordLoginFailure(string $mobile, ?int $userId): array
    {
        $now = time();
        $record = Db::table('login_failure_records')
            ->where('mobile', $mobile)
            ->find();

        if ($record) {
            $newFailureCount = $record['failure_count'] + 1;
            $updateData = [
                'failure_count' => $newFailureCount,
                'last_failure_at' => date('Y-m-d H:i:s', $now),
                'updated_at' => date('Y-m-d H:i:s', $now),
            ];

            if ($userId !== null && empty($record['user_id'])) {
                $updateData['user_id'] = $userId;
            }

            if ($newFailureCount >= self::MAX_LOGIN_FAILURES) {
                $updateData['locked_until'] = date('Y-m-d H:i:s', $now + self::LOCK_DURATION_MINUTES * 60);
            }

            Db::table('login_failure_records')
                ->where('id', $record['id'])
                ->update($updateData);

            return [
                'failure_count' => $newFailureCount,
                'locked' => $newFailureCount >= self::MAX_LOGIN_FAILURES,
            ];
        }

        Db::table('login_failure_records')->insert([
            'mobile' => $mobile,
            'user_id' => $userId,
            'failure_count' => 1,
            'last_failure_at' => date('Y-m-d H:i:s', $now),
            'locked_until' => null,
            'created_at' => date('Y-m-d H:i:s', $now),
            'updated_at' => date('Y-m-d H:i:s', $now),
        ]);

        return [
            'failure_count' => 1,
            'locked' => false,
        ];
    }

    protected function resetLoginFailure(string $mobile): void
    {
        Db::table('login_failure_records')
            ->where('mobile', $mobile)
            ->delete();
    }

    protected function loginByWeChat(array $payload): array
    {
        $openid = trim((string)($payload['openid'] ?? ''));
        $code = trim((string)($payload['code'] ?? ''));
        $unionId = trim((string)($payload['unionid'] ?? ''));
        $nickname = trim((string)($payload['nickname'] ?? ''));
        $avatarUrl = (string)($payload['avatar_url'] ?? '');

        if (empty($openid) && !empty($code)) {
            $session = $this->fetchWeChatSession($code);
            $openid = $session['openid'] ?? '';
            if (empty($unionId)) {
                $unionId = $session['unionid'] ?? '';
            }
        }

        if (empty($openid)) {
            $this->errorResponse('微信授权失败，请稍后重试');
        }

        $user = Db::table('users')->where('openid', $openid)->find();
        if (!$user) {
            $this->errorResponse('该微信尚未绑定账号，请先注册');
        }

        $this->assertUserStatus($user);

        $updates = [];
        if (!empty($nickname) && $nickname !== $user['nickname']) {
            $updates['nickname'] = $nickname;
        }
        if (!empty($avatarUrl) && $avatarUrl !== $user['avatar_url']) {
            $updates['avatar_url'] = $avatarUrl;
        }
        if (!empty($unionId) && empty($user['unionid'])) {
            $updates['unionid'] = $unionId;
        }
        if (!empty($updates)) {
            $updates['updated_at'] = date('Y-m-d H:i:s');
            Db::table('users')
                ->where('id', $user['id'])
                ->update($updates);
            $user = Db::table('users')->find($user['id']);
        }

        return $user;
    }

    protected function issueTokenResponse(array $user)
    {
        $token = bin2hex(random_bytes(32));
        $expireAt = date('Y-m-d H:i:s', strtotime('+7 days'));
        Db::table('users')->where('id', $user['id'])->update([
            'api_token'        => $token,
            'token_expires_at' => $expireAt,
            'last_login_at'    => date('Y-m-d H:i:s'),
        ]);

        $profile = Db::table('users')->where('id', $user['id'])->find();
        return $this->success([
            'token'       => $token,
            'expired_at'  => $expireAt,
            'profile'     => $this->formatUser($profile),
            'permissions' => $this->resolvePermissions($profile['id']),
        ]);
    }

    protected function assertUserStatus(array $user): void
    {
        $status = $user['status'] ?? 'pending';
        if ($status === 'pending') {
            $this->errorResponse('账号正在审核中，请耐心等待');
        }
        if ($status === 'disabled') {
            $this->errorResponse('账号已禁用，请联系管理员');
        }
    }

    public function profile()
    {
        return $this->success([
            'profile'     => $this->formatUser($this->user()),
            'permissions' => $this->resolvePermissions($this->user()['id']),
        ]);
    }

    public function updateProfile()
    {
        $fields = [
            'name', 'nickname', 'email', 'address', 'bank_account_name',
            'bank_name', 'bank_card_no', 'id_card', 'avatar_url',
        ];
        $data = [];
        foreach ($fields as $field) {
            if (Request::has($field)) {
                $data[$field] = Request::post($field);
            }
        }
        if (empty($data)) {
            return $this->success([
                'profile' => $this->formatUser($this->user()),
            ], '无需更新');
        }

        $data['updated_at'] = date('Y-m-d H:i:s');
        Db::table('users')->where('id', $this->user()['id'])->update($data);
        $profile = Db::table('users')->where('id', $this->user()['id'])->find();
        return $this->success([
            'profile' => $this->formatUser($profile),
        ], '资料已更新');
    }

    public function logout()
    {
        Db::table('users')->where('id', $this->user()['id'])->update([
            'api_token'        => null,
            'token_expires_at' => null,
        ]);
        return $this->success([], '已退出');
    }

    protected function fetchWeChatSession(string $code): array
    {
        $appid = config('wechat.appid');
        $secret = config('wechat.secret');
        if (empty($appid) || empty($secret) || empty($code)) {
            return [];
        }

        $url = sprintf(
            'https://api.weixin.qq.com/sns/jscode2session?appid=%s&secret=%s&js_code=%s&grant_type=authorization_code',
            urlencode($appid),
            urlencode($secret),
            urlencode($code)
        );
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
            ],
        ]);

        try {
            $response = file_get_contents($url, false, $context);
            if ($response === false) {
                return [];
            }
            $data = json_decode($response, true);
            if (!is_array($data) || !empty($data['errcode'])) {
                return [];
            }
            return $data;
        } catch (\Throwable $e) {
            return [];
        }
    }

    protected function formatUser(array $user): array
    {
        $dept = [];
        if (!empty($user['dept_id'])) {
            $dept = Db::table('departments')->find($user['dept_id']) ?: [];
        }
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
        ];
    }

    protected function resolvePermissions(int $userId): array
    {
        $roles = Db::table('user_roles')
            ->where('user_id', $userId)
            ->column('role_id');
        if (empty($roles)) {
            return [];
        }
        return Db::table('role_permissions')
            ->alias('rp')
            ->join('permissions p', 'p.id = rp.permission_id')
            ->whereIn('rp.role_id', $roles)
            ->column('p.code');
    }
}
