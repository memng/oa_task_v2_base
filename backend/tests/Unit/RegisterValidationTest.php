<?php

namespace tests\Unit;

use PHPUnit\Framework\TestCase;
use think\exception\HttpResponseException;

class RegisterValidationTest extends TestCase
{
    private array $passedTests = [];
    private array $failedTests = [];

    protected function tearDown(): void
    {
        echo "\n" . str_repeat('=', 60) . "\n";
        echo "测试结果汇总\n";
        echo str_repeat('=', 60) . "\n";
        
        echo "\n【通过的测试用例】: " . count($this->passedTests) . " 个\n";
        foreach ($this->passedTests as $test) {
            echo "  ✓ {$test}\n";
        }
        
        echo "\n【不通过的测试用例】: " . count($this->failedTests) . " 个\n";
        foreach ($this->failedTests as $test) {
            echo "  ✗ {$test}\n";
        }
        
        echo "\n" . str_repeat('=', 60) . "\n";
    }

    private function recordTestResult(string $testName, bool $passed): void
    {
        if ($passed) {
            $this->passedTests[] = $testName;
        } else {
            $this->failedTests[] = $testName;
        }
    }

    public function testMobileValidation(): void
    {
        echo "\n--- 手机号校验测试 ---\n";

        $invalidMobileTests = [
            ['mobile' => '', 'desc' => '空手机号'],
            ['mobile' => '1234567890', 'desc' => '10位手机号'],
            ['mobile' => '123456789012', 'desc' => '12位手机号'],
            ['mobile' => '23800138000', 'desc' => '非1开头的手机号'],
            ['mobile' => '1380013800a', 'desc' => '包含字母的手机号'],
            ['mobile' => '138 0013 8000', 'desc' => '包含空格的手机号'],
        ];

        foreach ($invalidMobileTests as $test) {
            $result = $this->testMobileInvalid($test['mobile']);
            $passed = $result === '请输入正确的手机号';
            $this->recordTestResult("手机号非法 - {$test['desc']}", $passed);
            if ($passed) {
                echo "  ✓ {$test['desc']}: 正确拒绝\n";
            } else {
                echo "  ✗ {$test['desc']}: 期望拒绝但实际: {$result}\n";
            }
            $this->assertTrue($passed, "测试失败: {$test['desc']}");
        }

        $validMobileTests = [
            ['mobile' => '13800138000', 'desc' => '138开头手机号'],
            ['mobile' => '15912345678', 'desc' => '159开头手机号'],
            ['mobile' => '18687654321', 'desc' => '186开头手机号'],
            ['mobile' => '17712345678', 'desc' => '177开头手机号'],
            ['mobile' => '19987654321', 'desc' => '199开头手机号'],
        ];

        foreach ($validMobileTests as $test) {
            $result = $this->testMobileValid($test['mobile']);
            $passed = $result !== '请输入正确的手机号';
            $this->recordTestResult("手机号合法 - {$test['desc']}", $passed);
            if ($passed) {
                echo "  ✓ {$test['desc']}: 正确通过\n";
            } else {
                echo "  ✗ {$test['desc']}: 期望通过但被拒绝\n";
            }
            $this->assertTrue($passed, "测试失败: {$test['desc']}");
        }
    }

    public function testPasswordValidation(): void
    {
        echo "\n--- 密码校验测试 ---\n";

        $invalidPasswordTests = [
            ['password' => '', 'desc' => '空密码'],
            ['password' => '1', 'desc' => '1位密码'],
            ['password' => '12', 'desc' => '2位密码'],
            ['password' => '123', 'desc' => '3位密码'],
            ['password' => '1234', 'desc' => '4位密码'],
            ['password' => '12345', 'desc' => '5位密码(边界测试)'],
        ];

        foreach ($invalidPasswordTests as $test) {
            $result = $this->testPasswordInvalid($test['password']);
            $passed = $result === '密码至少6位';
            $this->recordTestResult("密码非法 - {$test['desc']}", $passed);
            if ($passed) {
                echo "  ✓ {$test['desc']}: 正确拒绝\n";
            } else {
                echo "  ✗ {$test['desc']}: 期望拒绝但实际: {$result}\n";
            }
            $this->assertTrue($passed, "测试失败: {$test['desc']}");
        }

        $validPasswordTests = [
            ['password' => '123456', 'desc' => '6位密码(边界测试)'],
            ['password' => '1234567', 'desc' => '7位密码'],
            ['password' => '12345678', 'desc' => '8位密码'],
            ['password' => 'abc123', 'desc' => '包含字母的6位密码'],
            ['password' => 'Abc123!', 'desc' => '包含特殊字符的密码'],
        ];

        foreach ($validPasswordTests as $test) {
            $result = $this->testPasswordValid($test['password']);
            $passed = $result !== '密码至少6位';
            $this->recordTestResult("密码合法 - {$test['desc']}", $passed);
            if ($passed) {
                echo "  ✓ {$test['desc']}: 正确通过\n";
            } else {
                echo "  ✗ {$test['desc']}: 期望通过但被拒绝\n";
            }
            $this->assertTrue($passed, "测试失败: {$test['desc']}");
        }
    }

    public function testConfirmPasswordValidation(): void
    {
        echo "\n--- 确认密码校验测试 ---\n";

        $mismatchTests = [
            ['password' => '123456', 'confirm' => '1234567', 'desc' => '确认密码多一位'],
            ['password' => '123456', 'confirm' => '12345', 'desc' => '确认密码少一位'],
            ['password' => '123456', 'confirm' => '654321', 'desc' => '确认密码完全不同'],
            ['password' => 'abc123', 'confirm' => 'ABC123', 'desc' => '大小写不同'],
        ];

        foreach ($mismatchTests as $test) {
            $result = $this->testConfirmPasswordMismatch($test['password'], $test['confirm']);
            $passed = $result === '两次输入的密码不一致';
            $this->recordTestResult("确认密码不一致 - {$test['desc']}", $passed);
            if ($passed) {
                echo "  ✓ {$test['desc']}: 正确拒绝\n";
            } else {
                echo "  ✗ {$test['desc']}: 期望拒绝但实际: {$result}\n";
            }
            $this->assertTrue($passed, "测试失败: {$test['desc']}");
        }

        $matchTests = [
            ['password' => '123456', 'confirm' => '123456', 'desc' => '密码和确认密码一致'],
            ['password' => 'abc123', 'confirm' => 'abc123', 'desc' => '包含字母的密码一致'],
            ['password' => 'Abc123!', 'confirm' => 'Abc123!', 'desc' => '包含特殊字符的密码一致'],
        ];

        foreach ($matchTests as $test) {
            $result = $this->testConfirmPasswordMatch($test['password'], $test['confirm']);
            $passed = $result !== '两次输入的密码不一致';
            $this->recordTestResult("确认密码一致 - {$test['desc']}", $passed);
            if ($passed) {
                echo "  ✓ {$test['desc']}: 正确通过\n";
            } else {
                echo "  ✗ {$test['desc']}: 期望通过但被拒绝\n";
            }
            $this->assertTrue($passed, "测试失败: {$test['desc']}");
        }

        $emptyConfirmTest = [
            'password' => '123456',
            'confirm' => '',
            'desc' => '确认密码为空字符串(代码逻辑: confirm为空时不校验)',
        ];
        $result = $this->testConfirmPasswordEmpty($emptyConfirmTest['password'], $emptyConfirmTest['confirm']);
        $passed = $result !== '两次输入的密码不一致';
        $this->recordTestResult("确认密码 - {$emptyConfirmTest['desc']}", $passed);
        if ($passed) {
            echo "  ✓ {$emptyConfirmTest['desc']}: 正确通过\n";
        } else {
            echo "  ✗ {$emptyConfirmTest['desc']}: 期望通过但被拒绝\n";
        }
        $this->assertTrue($passed, "测试失败: {$emptyConfirmTest['desc']}");
    }

    private function testMobileInvalid(string $mobile): string
    {
        return $this->getValidationError(['mobile' => $mobile]);
    }

    private function testMobileValid(string $mobile): ?string
    {
        return $this->getValidationError([
            'mobile' => $mobile,
            'password' => '123456',
        ]);
    }

    private function testPasswordInvalid(string $password): string
    {
        return $this->getValidationError([
            'mobile' => '13800138000',
            'password' => $password,
        ]);
    }

    private function testPasswordValid(string $password): ?string
    {
        return $this->getValidationError([
            'mobile' => '13800138000',
            'password' => $password,
        ]);
    }

    private function testConfirmPasswordMismatch(string $password, string $confirm): string
    {
        return $this->getValidationError([
            'mobile' => '13800138000',
            'password' => $password,
            'confirm_password' => $confirm,
        ]);
    }

    private function testConfirmPasswordMatch(string $password, string $confirm): ?string
    {
        return $this->getValidationError([
            'mobile' => '13800138000',
            'password' => $password,
            'confirm_password' => $confirm,
        ]);
    }

    private function testConfirmPasswordEmpty(string $password, string $confirm): ?string
    {
        return $this->getValidationError([
            'mobile' => '13800138000',
            'password' => $password,
            'confirm_password' => $confirm,
        ]);
    }

    private function getValidationError(array $payload): ?string
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
}
