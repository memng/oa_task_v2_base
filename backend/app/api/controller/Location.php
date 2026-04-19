<?php

namespace app\api\controller;

use app\common\controller\ApiController;
use think\facade\Config;

class Location extends ApiController
{
    public function reverse()
    {
        $lat = $this->request->param('lat');
        $lng = $this->request->param('lng');
        if ($lat === null || $lng === null) {
            return $this->error('缺少经纬度参数');
        }
        if (!is_numeric($lat) || !is_numeric($lng)) {
            return $this->error('经纬度参数格式不正确');
        }
        $lat = (float)$lat;
        $lng = (float)$lng;

        $payload = $this->callGeocoder($lat, $lng);
        if (!$payload) {
            return $this->error('解析位置失败，请稍后再试');
        }

        $locationText = $this->formatResultText($payload);

        return $this->success([
            'text' => $locationText,
            'address' => $payload
        ]);
    }

    protected function callGeocoder(float $lat, float $lng): ?array
    {
        $config = Config::get('qqmap');
        $key = $config['key'] ?? '';
        $sk = $config['sk'] ?? '';
        if (!$key || !$sk) {
            return null;
        }

        $path = '/ws/geocoder/v1/';
        $params = [
            'key' => $key,
            'location' => sprintf('%.6f,%.6f', $lat, $lng),
            'get_poi' => 0
        ];
        ksort($params);
        $queryString = urldecode(http_build_query($params));
        $sig = md5($path . '?' . $queryString . $sk);
        $url = 'https://apis.map.qq.com' . $path . '?' . $queryString . '&sig=' . $sig;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        if ($response === false) {
            trace('qqmap request error: ' . $error, 'error');
            return null;
        }
        $data = json_decode($response, true);
        if (!is_array($data) || ($data['status'] ?? -1) !== 0) {
            trace('qqmap response invalid: ' . $response, 'error');
            return null;
        }
        return $data['result'] ?? null;
    }

    protected function formatResultText(array $payload): string
    {
        if (!empty($payload['formatted_addresses']['recommend'])) {
            return $payload['formatted_addresses']['recommend'];
        }
        if (!empty($payload['address'])) {
            return $payload['address'];
        }
        if (!empty($payload['location']['lat']) && !empty($payload['location']['lng'])) {
            return sprintf('纬度%.4f 经度%.4f', $payload['location']['lat'], $payload['location']['lng']);
        }
        return '未知位置';
    }
}
