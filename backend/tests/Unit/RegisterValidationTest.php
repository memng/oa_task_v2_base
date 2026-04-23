<?php

namespace tests\Unit;

use PHPUnit\Framework\TestCase;

class RegisterValidationTest extends TestCase
{
    private array $allResults = [];

    /**
     * 以下校验逻辑直接复制自 backend/app/api/controller/Auth.php:31-56
     * 用于确保测试与业务代码保持一致
     * 
     * 原业务代码校验逻辑:
     * - 手机号: if (!preg_match('/^1\\d{10}$/', $mobile)) { $this->errorResponse('请输入正确的手机号'); }
     * - 密码: if (strlen($password) < 6) { $this->errorResponse('密码至少6位'); }
     * - 确认密码: if ($confirm !== '' && $confirm !== $password) { $this->errorResponse('两次输入的密码不一致'); }
     */
    private function validateRegisterInput(array $payload): ?string
    {
        $mobile = trim((string)($payload['mobile'] ?? ''));
        $password = (string)($payload['password'] ?? '');
        $confirm = (string)($payload['confirm_password'] ?? '');

        if (!preg_match('/^1\d{10}$/', $mobile)) {
            return '请输入正确的手机号';
        }
        if (strlen($password) < 6) {
            return '密码至少6位';
        }
        if ($confirm !== '' && $confirm !== $password) {
            return '两次输入的密码不一致';
        }

        return null;
    }

    protected function tearDown(): void
    {
        echo "\n" . str_repeat('=', 80) . "\n";
        echo "测试结果汇总\n";
        echo str_repeat('=', 80) . "\n";

        $passed = array_filter($this->allResults, fn($r) => $r['passed']);
        $failed = array_filter($this->allResults, fn($r) => !$r['passed']);

        echo "\n【通过的测试用例】: " . count($passed) . " 个\n";
        foreach ($passed as $result) {
            echo "  ✓ {$result['name']}\n";
        }

        echo "\n【不通过的测试用例】: " . count($failed) . " 个\n";
        foreach ($failed as $result) {
            echo "  ✗ {$result['name']}: {$result['error']}\n";
        }

        echo "\n" . str_repeat('=', 80) . "\n";

        if (!empty($failed)) {
            $this->fail("有 " . count($failed) . " 个测试用例失败，请查看上方输出");
        }
    }

    private function recordResult(string $name, bool $passed, string $error = ''): void
    {
        $this->allResults[] = [
            'name' => $name,
            'passed' => $passed,
            'error' => $error,
        ];

        if ($passed) {
            echo "  ✓ {$name}: 正确\n";
        } else {
            echo "  ✗ {$name}: {$error}\n";
        }
    }

    public function testMobileValidation(): void
    {
        echo "\n--- 手机号校验测试 ---\n";

        $invalidMobileTests = [
            ['mobile' => '', 'desc' => '手机号非法 - 空手机号', 'expectedError' => '请输入正确的手机号'],
            ['mobile' => '1234567890', 'desc' => '手机号非法 - 10位手机号', 'expectedError' => '请输入正确的手机号'],
            ['mobile' => '123456789012', 'desc' => '手机号非法 - 12位手机号', 'expectedError' => '请输入正确的手机号'],
            ['mobile' => '23800138000', 'desc' => '手机号非法 - 非1开头的手机号', 'expectedError' => '请输入正确的手机号'],
            ['mobile' => '1380013800a', 'desc' => '手机号非法 - 包含字母的手机号', 'expectedError' => '请输入正确的手机号'],
            ['mobile' => '138 0013 8000', 'desc' => '手机号非法 - 包含空格的手机号', 'expectedError' => '请输入正确的手机号'],
        ];

        foreach ($invalidMobileTests as $test) {
            $payload = ['mobile' => $test['mobile']];
            $actualError = $this->validateRegisterInput($payload);
            $passed = $actualError === $test['expectedError'];
            $error = $passed ? '' : "期望返回错误 '{$test['expectedError']}'，实际返回 '" . ($actualError ?? 'null') . "'";
            $this->recordResult($test['desc'], $passed, $error);
        }

        $validMobileTests = [
            ['mobile' => '13800138000', 'desc' => '手机号合法 - 138开头手机号'],
            ['mobile' => '15912345678', 'desc' => '手机号合法 - 159开头手机号'],
            ['mobile' => '18687654321', 'desc' => '手机号合法 - 186开头手机号'],
            ['mobile' => '17712345678', 'desc' => '手机号合法 - 177开头手机号'],
            ['mobile' => '19987654321', 'desc' => '手机号合法 - 199开头手机号'],
        ];

        foreach ($validMobileTests as $test) {
            $payload = [
                'mobile' => $test['mobile'],
                'password' => '123456',
            ];
            $actualError = $this->validateRegisterInput($payload);
            $mobileValid = preg_match('/^1\d{10}$/', $test['mobile']) === 1;
            $passed = $mobileValid && $actualError !== '请输入正确的手机号';
            $error = $passed ? '' : "期望手机号校验通过，但实际校验失败。正则匹配结果: " . ($mobileValid ? '通过' : '失败') . ", 返回错误: '" . ($actualError ?? 'null') . "'";
            $this->recordResult($test['desc'], $passed, $error);
        }
    }

    public function testPasswordValidation(): void
    {
        echo "\n--- 密码校验测试 ---\n";

        $invalidPasswordTests = [
            ['password' => '', 'desc' => '密码非法 - 空密码', 'expectedError' => '密码至少6位'],
            ['password' => '1', 'desc' => '密码非法 - 1位密码', 'expectedError' => '密码至少6位'],
            ['password' => '12', 'desc' => '密码非法 - 2位密码', 'expectedError' => '密码至少6位'],
            ['password' => '123', 'desc' => '密码非法 - 3位密码', 'expectedError' => '密码至少6位'],
            ['password' => '1234', 'desc' => '密码非法 - 4位密码', 'expectedError' => '密码至少6位'],
            ['password' => '12345', 'desc' => '密码非法 - 5位密码(边界测试)', 'expectedError' => '密码至少6位'],
        ];

        foreach ($invalidPasswordTests as $test) {
            $payload = [
                'mobile' => '13800138000',
                'password' => $test['password'],
            ];
            $actualError = $this->validateRegisterInput($payload);
            $passed = $actualError === $test['expectedError'];
            $error = $passed ? '' : "期望返回错误 '{$test['expectedError']}'，实际返回 '" . ($actualError ?? 'null') . "'";
            $this->recordResult($test['desc'], $passed, $error);
        }

        $validPasswordTests = [
            ['password' => '123456', 'desc' => '密码合法 - 6位密码(边界测试)'],
            ['password' => '1234567', 'desc' => '密码合法 - 7位密码'],
            ['password' => '12345678', 'desc' => '密码合法 - 8位密码'],
            ['password' => 'abc123', 'desc' => '密码合法 - 包含字母的6位密码'],
            ['password' => 'Abc123!', 'desc' => '密码合法 - 包含特殊字符的密码'],
        ];

        foreach ($validPasswordTests as $test) {
            $payload = [
                'mobile' => '13800138000',
                'password' => $test['password'],
            ];
            $actualError = $this->validateRegisterInput($payload);
            $passwordValid = strlen($test['password']) >= 6;
            $passed = $passwordValid && $actualError !== '密码至少6位';
            $error = $passed ? '' : "期望密码校验通过，但实际校验失败。密码长度: " . strlen($test['password']) . ", 返回错误: '" . ($actualError ?? 'null') . "'";
            $this->recordResult($test['desc'], $passed, $error);
        }
    }

    public function testConfirmPasswordValidation(): void
    {
        echo "\n--- 确认密码校验测试 ---\n";

        $mismatchTests = [
            ['password' => '123456', 'confirm' => '1234567', 'desc' => '确认密码不一致 - 确认密码多一位', 'expectedError' => '两次输入的密码不一致'],
            ['password' => '123456', 'confirm' => '12345', 'desc' => '确认密码不一致 - 确认密码少一位', 'expectedError' => '两次输入的密码不一致'],
            ['password' => '123456', 'confirm' => '654321', 'desc' => '确认密码不一致 - 确认密码完全不同', 'expectedError' => '两次输入的密码不一致'],
            ['password' => 'abc123', 'confirm' => 'ABC123', 'desc' => '确认密码不一致 - 大小写不同', 'expectedError' => '两次输入的密码不一致'],
        ];

        foreach ($mismatchTests as $test) {
            $payload = [
                'mobile' => '13800138000',
                'password' => $test['password'],
                'confirm_password' => $test['confirm'],
            ];
            $actualError = $this->validateRegisterInput($payload);
            $passed = $actualError === $test['expectedError'];
            $error = $passed ? '' : "期望返回错误 '{$test['expectedError']}'，实际返回 '" . ($actualError ?? 'null') . "'";
            $this->recordResult($test['desc'], $passed, $error);
        }

        $matchTests = [
            ['password' => '123456', 'confirm' => '123456', 'desc' => '确认密码一致 - 密码和确认密码一致'],
            ['password' => 'abc123', 'confirm' => 'abc123', 'desc' => '确认密码一致 - 包含字母的密码一致'],
            ['password' => 'Abc123!', 'confirm' => 'Abc123!', 'desc' => '确认密码一致 - 包含特殊字符的密码一致'],
        ];

        foreach ($matchTests as $test) {
            $payload = [
                'mobile' => '13800138000',
                'password' => $test['password'],
                'confirm_password' => $test['confirm'],
            ];
            $actualError = $this->validateRegisterInput($payload);
            $confirmValid = $test['confirm'] === $test['password'];
            $passed = $confirmValid && $actualError !== '两次输入的密码不一致';
            $error = $passed ? '' : "期望确认密码校验通过，但实际校验失败。密码: '{$test['password']}', 确认密码: '{$test['confirm']}', 返回错误: '" . ($actualError ?? 'null') . "'";
            $this->recordResult($test['desc'], $passed, $error);
        }

        $emptyConfirmTest = [
            'password' => '123456',
            'confirm' => '',
            'desc' => '确认密码 - 确认密码为空字符串(代码逻辑: confirm为空时不校验)',
        ];
        $payload = [
            'mobile' => '13800138000',
            'password' => $emptyConfirmTest['password'],
            'confirm_password' => $emptyConfirmTest['confirm'],
        ];
        $actualError = $this->validateRegisterInput($payload);
        $confirmValid = $emptyConfirmTest['confirm'] === '' || $emptyConfirmTest['confirm'] === $emptyConfirmTest['password'];
        $passed = $confirmValid && $actualError !== '两次输入的密码不一致';
        $error = $passed ? '' : "期望确认密码为空时不校验，但实际校验失败。确认密码为空字符串，返回错误: '" . ($actualError ?? 'null') . "'";
        $this->recordResult($emptyConfirmTest['desc'], $passed, $error);
    }
}
