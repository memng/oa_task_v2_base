<?php

namespace tests\Unit;

use app\common\service\SecurityService;
use PHPUnit\Framework\TestCase;

class SecurityServiceTest extends TestCase
{
    public function testSanitizeXssRemovesScriptTags(): void
    {
        $input = '<script>alert("XSS")</script>';
        $output = SecurityService::sanitizeXss($input);
        $this->assertStringNotContainsString('<script>', $output);
        $this->assertStringNotContainsString('</script>', $output);
        $this->assertStringNotContainsString('alert("XSS")', $output);
    }

    public function testSanitizeXssRemovesScriptWithAttributes(): void
    {
        $input = '<script type="text/javascript">alert("XSS")</script>';
        $output = SecurityService::sanitizeXss($input);
        $this->assertStringNotContainsString('<script', $output);
    }

    public function testSanitizeXssRemovesIframeTags(): void
    {
        $input = '<iframe src="http://evil.com"></iframe>';
        $output = SecurityService::sanitizeXss($input);
        $this->assertStringNotContainsString('<iframe', $output);
    }

    public function testSanitizeXssRemovesOnEventHandlers(): void
    {
        $input = '<img src="test.jpg" onerror="alert(1)">';
        $output = SecurityService::sanitizeXss($input);
        $this->assertStringNotContainsString('onerror=', $output);
        $this->assertStringContainsString('onremoved=', $output);
    }

    public function testSanitizeXssRemovesJavascriptProtocol(): void
    {
        $input = '<a href="javascript:alert(1)">Click me</a>';
        $output = SecurityService::sanitizeXss($input);
        $this->assertStringNotContainsString('javascript:', $output);
    }

    public function testSanitizeXssEscapesHtmlEntities(): void
    {
        $input = '<div>Test</div>';
        $output = SecurityService::sanitizeXss($input);
        $this->assertEquals('&lt;div&gt;Test&lt;/div&gt;', $output);
    }

    public function testSanitizeXssEscapesQuotes(): void
    {
        $input = 'test "quote" \'single\'';
        $output = SecurityService::sanitizeXss($input);
        $this->assertStringContainsString('&quot;quote&quot;', $output);
        $this->assertStringContainsString('&apos;single&apos;', $output);
    }

    public function testSanitizeArrayFiltersSpecifiedFields(): void
    {
        $input = [
            'title' => '<script>alert(1)</script>Test',
            'content' => '<img onerror=xss>',
            'category' => 'general',
        ];
        $output = SecurityService::sanitizeArray($input, ['title', 'content']);
        $this->assertStringNotContainsString('<script>', $output['title']);
        $this->assertStringNotContainsString('onerror=', $output['content']);
        $this->assertEquals('general', $output['category']);
    }

    public function testSanitizeRecursiveFiltersNestedArrays(): void
    {
        $input = [
            'title' => '<script>alert(1)</script>',
            'nested' => [
                'content' => '<img onerror=xss>',
                'deep' => [
                    'value' => '<style>body{}</style>'
                ]
            ]
        ];
        $output = SecurityService::sanitizeRecursive($input);
        $this->assertStringNotContainsString('<script>', $output['title']);
        $this->assertStringNotContainsString('onerror=', $output['nested']['content']);
        $this->assertStringNotContainsString('<style>', $output['nested']['deep']['value']);
    }

    public function testNormalTextRemainsUntouched(): void
    {
        $input = 'This is a normal text without any XSS.';
        $output = SecurityService::sanitizeXss($input);
        $this->assertEquals($input, $output);
    }

    public function testMultipleXssAttemptsAreCleaned(): void
    {
        $input = '<script>alert(1)</script> and <SCRIPT>alert(2)</SCRIPT>';
        $output = SecurityService::sanitizeXss($input);
        $this->assertStringNotContainsString('<script', $output);
        $this->assertStringNotContainsString('alert', $output);
    }
}
