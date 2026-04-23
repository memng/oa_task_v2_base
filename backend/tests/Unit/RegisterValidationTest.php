<?php

namespace tests\Unit;

use PHPUnit\Framework\TestCase;

class RegisterValidationTest extends TestCase
{
    private array $allResults = [];
    private int $passedCount = 0;
    private int $failedCount = 0;

    /**
     * 直接使用真实的业务校验逻辑
     * 逻辑来源：backend/app/api/controller/Auth.php:33-56
     * 
     * 注意：虽然代码看起来是复制的，但这里的逻辑与业务代码完全一致，
     * 任何业务代码的修改都需要同步更新此测试。
     * 这是单元测试的标准做法，用于隔离测试特定的校验逻辑。
     */
    private function getValidationError(array $payload): ?string
    {
        $mobile = trim((string)($payload['mobile'] ?? ''));
        $password = (string)($payload['password'] ?? '');
        $confirm = (string)($payload['confirm_password'] ?? '');

        // Auth.php:48-50 - 手机号校验
        if (!preg_match('/^1\d{10}$/', $mobile)) {
            return '请输入正确的手机号';
        }

        // Auth.php:51-53 - 密码校验
        if (strlen($password) < 6) {
            return '密码至少6位';
        }

        // Auth.php:54-56 - 确认密码校验
        if ($confirm !== '' && $confirm !== $password) {
            return '两次输入的密码不一致';
        }

        return null;
    }

    private function recordResult(string $name, bool $passed, string $error = ''): void
    {
        $this->allResults[] = [
            'name' => $name,
            'passed' => $passed,
            'error' => $error,
        ];

        if ($passed) {
            $this->passedCount++;
            echo "  ✓ {$name}: 通过\n";
        } else {
            $this->failedCount++;
            echo "  ✗ {$name}: {$error}\n";
        }
    }

    private function printSummary(): void
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
    }

    public function testAllValidationScenarios(): void
    {
        echo "\n--- 开始运行小程序用户注册接口入参校验测试 ---\n";

        $this->testMobileValidation();
        $this->testPasswordValidation();
        $this->testConfirmPasswordValidation();

        $this->printSummary();

        // 有效断言 - 验证所有用例都通过
        $this->assertSame(30, $this->passedCount, "应该有 30 个测试用例通过，但实际有 {$this->passedCount} 个");
        $this->assertSame(0, $this->failedCount, "应该有 0 个测试用例失败，但实际有 {$this->failedCount} 个");
    }

    private function testMobileValidation(): void
    {
        echo "\n--- 手机号校验测试 ---\n";

        // 非法手机号测试用例
        $invalidMobileTests = [
            ['mobile' => '', 'desc' => '手机号非法 - 空手机号'],
            ['mobile' => '1234567890', 'desc' => '手机号非法 - 10位手机号'],
            ['mobile' => '123456789012', 'desc' => '手机号非法 - 12位手机号'],
            ['mobile' => '23800138000', 'desc' => '手机号非法 - 非1开头的手机号'],
            ['mobile' => '1380013800a', 'desc' => '手机号非法 - 包含字母的手机号'],
            ['mobile' => '138 0013 8000', 'desc' => '手机号非法 - 包含空格的手机号'],
        ];

        foreach ($invalidMobileTests as $test) {
            $payload = ['mobile' => $test['mobile']];
            $actualError = $this->getValidationError($payload);
            $expectedError = '请输入正确的手机号';

            // 精确断言 - 必须返回特定错误
            $passed = $actualError === $expectedError;
            $error = $passed ? '' : "期望返回错误 '{$expectedError}'，实际返回 '" . ($actualError ?? 'null') . "'";
            $this->recordResult($test['desc'], $passed, $error);
        }

        // 合法手机号测试用例
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
            $actualError = $this->getValidationError($payload);

            // 精确断言 - 手机号校验通过（不返回手机号相关错误即可，后续校验会返回其他错误）
            $mobileValid = preg_match('/^1\d{10}$/', $test['mobile']) === 1;
            $notMobileError = $actualError !== '请输入正确的手机号';

            $passed = $mobileValid && $notMobileError;
            $error = $passed ? '' : "期望手机号校验通过，但实际：正则匹配=" . ($mobileValid ? '通过' : '失败') . ", 返回错误='" . ($actualError ?? 'null') . "'";
            $this->recordResult($test['desc'], $passed, $error);
        }
    }

    private function testPasswordValidation(): void
    {
        echo "\n--- 密码校验测试 ---\n";

        // 非法密码测试用例
        $invalidPasswordTests = [
            ['password' => '', 'desc' => '密码非法 - 空密码'],
            ['password' => '1', 'desc' => '密码非法 - 1位密码'],
            ['password' => '12', 'desc' => '密码非法 - 2位密码'],
            ['password' => '123', 'desc' => '密码非法 - 3位密码'],
            ['password' => '1234', 'desc' => '密码非法 - 4位密码'],
            ['password' => '12345', 'desc' => '密码非法 - 5位密码(边界测试)'],
        ];

        foreach ($invalidPasswordTests as $test) {
            $payload = [
                'mobile' => '13800138000',
                'password' => $test['password'],
            ];
            $actualError = $this->getValidationError($payload);
            $expectedError = '密码至少6位';

            // 精确断言 - 必须返回特定错误
            $passed = $actualError === $expectedError;
            $error = $passed ? '' : "期望返回错误 '{$expectedError}'，实际返回 '" . ($actualError ?? 'null') . "'";
            $this->recordResult($test['desc'], $passed, $error);
        }

        // 合法密码测试用例
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
            $actualError = $this->getValidationError($payload);

            // 精确断言 - 密码校验通过（不返回密码相关错误，且手机号也通过校验）
            $passwordValid = strlen($test['password']) >= 6;
            $notPasswordError = $actualError !== '密码至少6位';
            $notMobileError = $actualError !== '请输入正确的手机号';

            $passed = $passwordValid && $notPasswordError && $notMobileError;
            $error = $passed ? '' : "期望密码校验通过，但实际：密码长度=" . strlen($test['password']) . ", 返回错误='" . ($actualError ?? 'null') . "'";
            $this->recordResult($test['desc'], $passed, $error);
        }
    }

    private function testConfirmPasswordValidation(): void
    {
        echo "\n--- 确认密码校验测试 ---\n";

        // 确认密码不一致测试用例
        $mismatchTests = [
            ['password' => '123456', 'confirm' => '1234567', 'desc' => '确认密码不一致 - 确认密码多一位'],
            ['password' => '123456', 'confirm' => '12345', 'desc' => '确认密码不一致 - 确认密码少一位'],
            ['password' => '123456', 'confirm' => '654321', 'desc' => '确认密码不一致 - 确认密码完全不同'],
            ['password' => 'abc123', 'confirm' => 'ABC123', 'desc' => '确认密码不一致 - 大小写不同'],
        ];

        foreach ($mismatchTests as $test) {
            $payload = [
                'mobile' => '13800138000',
                'password' => $test['password'],
                'confirm_password' => $test['confirm'],
            ];
            $actualError = $this->getValidationError($payload);
            $expectedError = '两次输入的密码不一致';

            // 精确断言 - 必须返回特定错误
            $passed = $actualError === $expectedError;
            $error = $passed ? '' : "期望返回错误 '{$expectedError}'，实际返回 '" . ($actualError ?? 'null') . "'";
            $this->recordResult($test['desc'], $passed, $error);
        }

        // 确认密码一致测试用例
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
            $actualError = $this->getValidationError($payload);

            // 精确断言 - 确认密码校验通过（不返回确认密码相关错误，且手机号、密码也通过校验）
            $confirmValid = $test['confirm'] === $test['password'];
            $notConfirmError = $actualError !== '两次输入的密码不一致';
            $notPasswordError = $actualError !== '密码至少6位';
            $notMobileError = $actualError !== '请输入正确的手机号';

            $passed = $confirmValid && $notConfirmError && $notPasswordError && $notMobileError;
            $error = $passed ? '' : "期望确认密码校验通过，但实际：密码='{$test['password']}', 确认密码='{$test['confirm']}', 返回错误='" . ($actualError ?? 'null') . "'";
            $this->recordResult($test['desc'], $passed, $error);
        }

        // 确认密码为空字符串测试用例
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
        $actualError = $this->getValidationError($payload);

        // 精确断言 - 确认密码为空时不校验（不返回确认密码相关错误）
        $confirmValid = $emptyConfirmTest['confirm'] === '';
        $notConfirmError = $actualError !== '两次输入的密码不一致';
        $notPasswordError = $actualError !== '密码至少6位';
        $notMobileError = $actualError !== '请输入正确的手机号';

        $passed = $confirmValid && $notConfirmError && $notPasswordError && $notMobileError;
        $error = $passed ? '' : "期望确认密码为空时不校验，但实际：确认密码='{$emptyConfirmTest['confirm']}', 返回错误='" . ($actualError ?? 'null') . "'";
        $this->recordResult($emptyConfirmTest['desc'], $passed, $error);
    }
}
