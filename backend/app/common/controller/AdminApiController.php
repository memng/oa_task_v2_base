<?php

namespace app\common\controller;

use think\facade\Db;
use think\facade\Request;

class AdminApiController extends ApiController
{
    protected function authenticate(): void
    {
        $authHeader = Request::header('Authorization');
        $token = '';
        if ($authHeader && stripos($authHeader, 'Bearer ') === 0) {
            $token = trim(substr($authHeader, 7));
        } else {
            $token = (string)Request::param('token', '');
        }

        if (empty($token)) {
            $this->abortUnauthorized('缺少访问令牌');
        }

        $admin = Db::table('admin_users')
            ->where('api_token', $token)
            ->find();

        if (!$admin) {
            $this->abortUnauthorized('令牌无效');
        }
        if (!empty($admin['token_expires_at']) && strtotime($admin['token_expires_at']) < time()) {
            $this->abortUnauthorized('令牌已过期');
        }
        if (($admin['status'] ?? 1) !== 1) {
            $this->abortUnauthorized('账号已禁用');
        }

        $this->usingAdminToken = true;
        $this->currentUser = $admin;
    }
}
