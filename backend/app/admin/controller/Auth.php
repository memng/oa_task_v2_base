<?php

namespace app\admin\controller;

use app\common\controller\AdminApiController;
use think\facade\Db;

class Auth extends AdminApiController
{
    protected array $publicActions = ['login'];

    public function login()
    {
        $payload = $this->requestData();
        $username = trim((string)($payload['username'] ?? ''));
        $password = (string)($payload['password'] ?? '');

        if ($username === '' || $password === '') {
            $this->errorResponse('请输入账号和密码');
        }

        $this->ensureDefaultAdmin();

        $admin = Db::table('admin_users')->where('username', $username)->find();
        if (!$admin || !password_verify($password, $admin['password'])) {
            $this->errorResponse('账号或密码错误');
        }
        if ((int)$admin['status'] !== 1) {
            $this->errorResponse('账号已禁用');
        }

        $token = bin2hex(random_bytes(32));
        $expireAt = date('Y-m-d H:i:s', strtotime('+7 days'));
        Db::table('admin_users')
            ->where('id', $admin['id'])
            ->update([
                'api_token'        => $token,
                'token_expires_at' => $expireAt,
                'updated_at'       => date('Y-m-d H:i:s'),
            ]);

        $profile = $this->formatAdmin(Db::table('admin_users')->find($admin['id']));
        return $this->success([
            'token'      => $token,
            'expired_at' => $expireAt,
            'profile'    => $profile,
        ], '登录成功');
    }

    public function profile()
    {
        return $this->success([
            'profile' => $this->formatAdmin($this->user()),
        ]);
    }

    public function logout()
    {
        Db::table('admin_users')
            ->where('id', $this->user()['id'])
            ->update([
                'api_token'        => null,
                'token_expires_at' => null,
            ]);
        return $this->success([], '已退出');
    }

    protected function ensureDefaultAdmin(): void
    {
        $exists = Db::table('admin_users')->where('username', 'admin')->find();
        if ($exists) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        Db::table('admin_users')->insert([
            'username'   => 'admin',
            'name'       => '系统管理员',
            'password'   => password_hash('123456', PASSWORD_BCRYPT),
            'status'     => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    protected function formatAdmin(array $admin): array
    {
        return [
            'id'       => (int)$admin['id'],
            'username' => $admin['username'],
            'name'     => $admin['name'],
        ];
    }
}
