<?php

namespace app\common\service;

class SecurityService
{
    public static function sanitizeXss(string $value): string
    {
        $value = trim($value);

        $patterns = [
            '/<script\b[^>]*>(.*?)<\/script>/is' => '',
            '/<script\b[^>]*>/i' => '',
            '/<\/script>/i' => '',
            '/on\w+\s*=/i' => 'onremoved=',
            '/javascript\s*:/i' => 'javascript_removed:',
            '/<iframe\b[^>]*>(.*?)<\/iframe>/is' => '',
            '/<object\b[^>]*>(.*?)<\/object>/is' => '',
            '/<embed\b[^>]*>/i' => '',
            '/<\/embed>/i' => '',
            '/<link\b[^>]*>/i' => '',
            '/<style\b[^>]*>(.*?)<\/style>/is' => '',
            '/eval\s*\(/i' => 'eval_removed(',
            '/expression\s*\(/i' => 'expression_removed(',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $value = preg_replace($pattern, $replacement, $value);
        }

        $value = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return $value;
    }

    public static function sanitizeArray(array $data, array $fields): array
    {
        foreach ($fields as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = self::sanitizeXss($data[$field]);
            }
        }
        return $data;
    }

    public static function sanitizeRecursive($data)
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = self::sanitizeRecursive($value);
            }
            return $data;
        } elseif (is_string($data)) {
            return self::sanitizeXss($data);
        }
        return $data;
    }
}
