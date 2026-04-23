<?php

namespace tests\Feature;

use PHPUnit\Framework\TestCase;

class AnnouncementApiTest extends TestCase
{
    private const API_BASE_URL = 'http://localhost:80/api';
    private const ADMIN_TOKEN = 'effcafdea7ca93ef97a0beef48b760f08c2344b82e45dcf9b96f1f4e63a47dab';

    private function post(string $endpoint, array $data): array
    {
        $ch = curl_init();
        $url = self::API_BASE_URL . $endpoint;

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . self::ADMIN_TOKEN,
            'Content-Type: application/json',
            'Accept: application/json',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return [
                'success' => false,
                'error' => $error,
                'http_code' => $httpCode,
            ];
        }

        $decoded = json_decode($response, true);

        return [
            'success' => true,
            'http_code' => $httpCode,
            'response' => $decoded,
            'raw_response' => $response,
        ];
    }

    public function testPublishNormalAnnouncement(): void
    {
        $payload = [
            'title' => 'Test Announcement - ' . date('Y-m-d H:i:s'),
            'content' => 'This is a normal test announcement content without any XSS.',
            'category' => 'general',
            'publish_status' => 'published',
        ];

        $result = $this->post('/announcements', $payload);

        $this->assertTrue($result['success'], 'CURL error: ' . ($result['error'] ?? 'unknown'));
        $this->assertEquals(201, $result['http_code'], 'Expected HTTP 201, got ' . $result['http_code'] . '. Response: ' . ($result['raw_response'] ?? 'none'));
        $this->assertArrayHasKey('data', $result['response']);
        $this->assertArrayHasKey('id', $result['response']['data']);
        $this->assertGreaterThan(0, $result['response']['data']['id']);
    }

    public function testPublishAnnouncementWithScriptTagXss(): void
    {
        $payload = [
            'title' => 'XSS Test <script>alert(1)</script>',
            'content' => 'Content with <script>document.location="http://evil.com"</script> XSS',
            'category' => 'general',
            'publish_status' => 'published',
        ];

        $result = $this->post('/announcements', $payload);

        $this->assertTrue($result['success'], 'CURL error: ' . ($result['error'] ?? 'unknown'));
        $this->assertEquals(201, $result['http_code'], 'Expected HTTP 201, got ' . $result['http_code'] . '. Response: ' . ($result['raw_response'] ?? 'none'));
        $this->assertArrayHasKey('data', $result['response']);
        $this->assertArrayHasKey('id', $result['response']['data']);
    }

    public function testPublishAnnouncementWithOnEventXss(): void
    {
        $payload = [
            'title' => 'Image XSS <img src=x onerror=alert(1)>',
            'content' => 'Body with <svg onload=alert("xss")> test',
            'category' => 'general',
            'publish_status' => 'published',
        ];

        $result = $this->post('/announcements', $payload);

        $this->assertTrue($result['success'], 'CURL error: ' . ($result['error'] ?? 'unknown'));
        $this->assertEquals(201, $result['http_code'], 'Expected HTTP 201, got ' . $result['http_code'] . '. Response: ' . ($result['raw_response'] ?? 'none'));
    }

    public function testPublishAnnouncementWithJavascriptProtocolXss(): void
    {
        $payload = [
            'title' => 'Javascript Protocol Test',
            'content' => 'Click <a href="javascript:alert(1)">here</a>',
            'category' => 'general',
            'publish_status' => 'published',
        ];

        $result = $this->post('/announcements', $payload);

        $this->assertTrue($result['success'], 'CURL error: ' . ($result['error'] ?? 'unknown'));
        $this->assertEquals(201, $result['http_code'], 'Expected HTTP 201, got ' . $result['http_code'] . '. Response: ' . ($result['raw_response'] ?? 'none'));
    }

    public function testPublishAnnouncementWithIframeXss(): void
    {
        $payload = [
            'title' => 'Iframe Test',
            'content' => '<iframe src="http://evil.com/steal.php"></iframe>',
            'category' => 'general',
            'publish_status' => 'published',
        ];

        $result = $this->post('/announcements', $payload);

        $this->assertTrue($result['success'], 'CURL error: ' . ($result['error'] ?? 'unknown'));
        $this->assertEquals(201, $result['http_code'], 'Expected HTTP 201, got ' . $result['http_code'] . '. Response: ' . ($result['raw_response'] ?? 'none'));
    }

    public function testPublishAnnouncementWithMultipleXssAttempts(): void
    {
        $payload = [
            'title' => '<SCRIPT>alert(1)</SCRIPT><IMG SRC=javascript:alert(1)>',
            'content' => '<style>body{background:url(javascript:alert(1))}</style>' .
                '<object data=javascript:alert(1)>' .
                '<embed src=javascript:alert(1)>' .
                '<link rel="stylesheet" href="javascript:alert(1)">',
            'category' => 'general',
            'publish_status' => 'published',
        ];

        $result = $this->post('/announcements', $payload);

        $this->assertTrue($result['success'], 'CURL error: ' . ($result['error'] ?? 'unknown'));
        $this->assertEquals(201, $result['http_code'], 'Expected HTTP 201, got ' . $result['http_code'] . '. Response: ' . ($result['raw_response'] ?? 'none'));
    }

    public function testPublishAnnouncementMissingTitle(): void
    {
        $payload = [
            'content' => 'Content without title',
            'category' => 'general',
            'publish_status' => 'published',
        ];

        $result = $this->post('/announcements', $payload);

        $this->assertTrue($result['success'], 'CURL error: ' . ($result['error'] ?? 'unknown'));
        $this->assertEquals(400, $result['http_code'], 'Expected HTTP 400 for missing title');
    }

    public function testPublishAnnouncementMissingContent(): void
    {
        $payload = [
            'title' => 'Title without content',
            'category' => 'general',
            'publish_status' => 'published',
        ];

        $result = $this->post('/announcements', $payload);

        $this->assertTrue($result['success'], 'CURL error: ' . ($result['error'] ?? 'unknown'));
        $this->assertEquals(400, $result['http_code'], 'Expected HTTP 400 for missing content');
    }
}
