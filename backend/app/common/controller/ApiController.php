<?php

namespace app\common\controller;

use app\BaseController;
use think\exception\HttpResponseException;
use think\facade\Db;
use think\facade\Request;

class ApiController extends BaseController
{
    protected bool $requireLogin = true;
    protected array $currentUser = [];
    protected array $publicActions = [];
    protected bool $usingAdminToken = false;

    protected function initialize()
    {
        $action = $this->resolveActionName();
        if (!empty($this->publicActions)) {
            $allowed = array_map('strtolower', $this->publicActions);
            if (in_array($action, $allowed, true)) {
                $this->requireLogin = false;
            }
        }
        parent::initialize();
        if ($this->requireLogin) {
            $this->authenticate();
        }
    }

    protected function authenticate(): void
    {
        $authHeader = Request::header('Authorization');
        $token = '';
        if ($authHeader && stripos($authHeader, 'Bearer ') === 0) {
            $token = trim(substr($authHeader, 7));
        } else {
            $token = $token ?: (string)Request::param('token', '');
        }

        if (empty($token)) {
            $this->abortUnauthorized('缺少访问令牌');
        }

        $user = Db::table('users')
            ->where('api_token', $token)
            ->find();

        if ($user) {
            if (!empty($user['token_expires_at']) && strtotime($user['token_expires_at']) < time()) {
                $this->abortUnauthorized('令牌已过期');
            }
            if (($user['status'] ?? 'pending') === 'disabled') {
                $this->abortUnauthorized('账号已禁用');
            }
            $this->usingAdminToken = false;
            $this->currentUser = $user;
            return;
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
        $this->currentUser = $this->mapAdminToUser($admin);
    }

    protected function abortUnauthorized(string $message): void
    {
        $this->errorResponse($message, 401);
    }

    protected function errorResponse(string $message, int $status = 400, array $data = []): void
    {
        $response = json([
            'code'    => $status,
            'message' => $message,
            'data'    => (object)$data,
        ], $status);
        throw new HttpResponseException($response);
    }

    protected function success(array $data = [], string $message = 'OK', int $status = 200)
    {
        return json([
            'code'    => $status,
            'message' => $message,
            'data'    => $data,
        ], $status);
    }

    protected function user(): array
    {
        return $this->currentUser;
    }

    protected function resolveActionName(): string
    {
        $action = strtolower((string)$this->request->action());
        if ($action !== '') {
            return $action;
        }

        $rule = $this->request->rule();
        if (!$rule) {
            return $action;
        }

        $route = $rule->getRoute();
        if (!is_string($route) || $route === '') {
            return $action;
        }

        if (strpos($route, '@') !== false) {
            $action = substr($route, strrpos($route, '@') + 1);
        } elseif (strpos($route, '::') !== false) {
            $action = substr($route, strrpos($route, '::') + 2);
        } elseif (strpos($route, '/') !== false) {
            $segments = explode('/', trim($route, '/'));
            $action = array_pop($segments) ?: '';
        }

        return strtolower((string)$action);
    }

    protected function requestData(): array
    {
        $data = Request::post();
        if (empty($data)) {
            $content = Request::getContent();
            if (!empty($content)) {
                $json = json_decode($content, true);
                if (is_array($json)) {
                    $data = $json;
                }
            }
        }
        return $data;
    }

    protected function mapAdminToUser(array $admin): array
    {
        return [
            'id'            => 0,
            'name'          => $admin['name'],
            'nickname'      => $admin['username'],
            'mobile'        => null,
            'email'         => null,
            'dept_id'       => null,
            'status'        => 'active',
            'avatar_url'    => null,
            'openid'        => null,
            'last_login_at' => null,
            'hire_date'     => null,
            'admin_user_id' => (int)$admin['id'],
            'is_admin'      => true,
        ];
    }
}
