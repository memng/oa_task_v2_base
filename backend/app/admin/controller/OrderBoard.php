<?php

namespace app\admin\controller;

use app\common\controller\AdminApiController;
use think\facade\Db;
use think\facade\Request;

class OrderBoard extends AdminApiController
{
    protected array $statusMap = [
        'draft'     => '草稿',
        'in_progress' => '进行中',
        'completed' => '已完成',
        'cancelled' => '已取消',
    ];

    protected array $currencySymbols = [
        'CNY' => '¥',
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'JPY' => '¥',
        'HKD' => 'HK$',
    ];

    public function summary()
    {
        $startDate = Request::get('start_date');
        $endDate   = Request::get('end_date');
        $granularity = Request::get('granularity', 'day');

        $dateError = $this->validateDateRange($startDate, $endDate);
        if ($dateError !== null) {
            $this->errorResponse($dateError);
        }

        $totalOrders = $this->countTotalOrders($startDate, $endDate);
        $totalByCurrency = $this->sumAmountByCurrency(
            $this->buildOrderQuery($startDate, $endDate)
        );
        $primaryCurrency = $this->resolvePrimaryCurrency($totalByCurrency);

        $byStatus = $this->statsByStatus($startDate, $endDate);

        $recentTrend = $this->recentTrend($startDate, $endDate, $granularity);

        return $this->success([
            'total' => [
                'orders'      => $totalOrders,
                'by_currency' => $totalByCurrency,
                'primary_currency' => $primaryCurrency,
            ],
            'by_status'    => $byStatus,
            'recent_trend' => $recentTrend,
            'currency_symbols' => $this->currencySymbols,
        ]);
    }

    protected function validateDateRange(?string $startDate, ?string $endDate): ?string
    {
        if ($startDate !== null && $startDate !== '') {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
                return 'start_date 格式无效，应为 YYYY-MM-DD';
            }
            $ts = strtotime($startDate);
            if ($ts === false || date('Y-m-d', $ts) !== $startDate) {
                return 'start_date 不是有效日期';
            }
        }
        if ($endDate !== null && $endDate !== '') {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
                return 'end_date 格式无效，应为 YYYY-MM-DD';
            }
            $ts = strtotime($endDate);
            if ($ts === false || date('Y-m-d', $ts) !== $endDate) {
                return 'end_date 不是有效日期';
            }
        }
        if ($startDate && $endDate && strtotime($startDate) > strtotime($endDate)) {
            return 'start_date 不能晚于 end_date';
        }
        return null;
    }

    protected function buildOrderQuery(?string $startDate, ?string $endDate)
    {
        $query = Db::table('orders');
        if ($startDate) {
            $query->where('created_at', '>=', "{$startDate} 00:00:00");
        }
        if ($endDate) {
            $query->where('created_at', '<=', "{$endDate} 23:59:59");
        }
        return $query;
    }

    protected function countTotalOrders(?string $startDate, ?string $endDate): int
    {
        return (int)$this->buildOrderQuery($startDate, $endDate)->count();
    }

    protected function sumAmountByCurrency($query): array
    {
        $rows = (clone $query)
            ->field([
                'currency',
                'SUM(grand_total) as total_amount',
            ])
            ->group('currency')
            ->select()
            ->toArray();

        $result = [];
        foreach ($rows as $row) {
            $currency = (string)$row['currency'];
            $result[] = [
                'currency' => $currency,
                'symbol'   => $this->currencySymbols[$currency] ?? $currency,
                'amount'   => round((float)$row['total_amount'], 2),
            ];
        }
        return $result;
    }

    protected function resolvePrimaryCurrency(array $byCurrency): ?array
    {
        if (empty($byCurrency)) {
            return null;
        }
        $primary = $byCurrency[0];
        foreach ($byCurrency as $item) {
            if (($item['amount'] ?? 0) > ($primary['amount'] ?? 0)) {
                $primary = $item;
            }
        }
        return $primary;
    }

    protected function statsByStatus(?string $startDate, ?string $endDate): array
    {
        $query = $this->buildOrderQuery($startDate, $endDate);

        $rows = (clone $query)
            ->field([
                'status',
                'COUNT(*) as order_count',
            ])
            ->group('status')
            ->select()
            ->toArray();

        $countMap = [];
        foreach ($rows as $row) {
            $countMap[$row['status']] = (int)$row['order_count'];
        }

        $currencyRows = (clone $query)
            ->field([
                'status',
                'currency',
                'SUM(grand_total) as total_amount',
            ])
            ->group('status', 'currency')
            ->select()
            ->toArray();

        $currencyByStatus = [];
        foreach ($currencyRows as $row) {
            $status = $row['status'];
            if (!isset($currencyByStatus[$status])) {
                $currencyByStatus[$status] = [];
            }
            $currency = (string)$row['currency'];
            $currencyByStatus[$status][] = [
                'currency' => $currency,
                'symbol'   => $this->currencySymbols[$currency] ?? $currency,
                'amount'   => round((float)$row['total_amount'], 2),
            ];
        }

        $result = [];
        foreach ($this->statusMap as $status => $label) {
            $result[] = [
                'status'       => $status,
                'status_label' => $label,
                'order_count'  => $countMap[$status] ?? 0,
                'by_currency'  => $currencyByStatus[$status] ?? [],
            ];
        }

        return $result;
    }

    protected function recentTrend(?string $startDate, ?string $endDate, string $granularity): array
    {
        if ($granularity === 'month') {
            return $this->trendByMonth($startDate, $endDate);
        }
        if ($granularity === 'week') {
            return $this->trendByWeek($startDate, $endDate);
        }
        return $this->trendByDay($startDate, $endDate);
    }

    protected function trendByDay(?string $startDate, ?string $endDate): array
    {
        $days = 14;
        if ($startDate && $endDate) {
            $startTs = strtotime($startDate);
            $endTs   = strtotime($endDate);
            $days = max(1, min(365, (int)floor(($endTs - $startTs) / 86400) + 1));
        } elseif ($startDate) {
            $startTs = strtotime($startDate);
            $endTs   = time();
            $days = max(1, min(365, (int)floor(($endTs - $startTs) / 86400) + 1));
        } elseif ($endDate) {
            $endTs   = strtotime($endDate);
            $startTs = $endTs - ($days - 1) * 86400;
        } else {
            $endTs   = time();
            $startTs = $endTs - ($days - 1) * 86400;
        }

        $periods = $this->generateDailyPeriods($startTs, $endTs);

        $statuses = $this->getStatusesForPeriod($startTs, $endTs, 'day');

        return $this->buildTrendResult($periods, $statuses, 'day');
    }

    protected function trendByWeek(?string $startDate, ?string $endDate): array
    {
        $weeks = 8;
        if ($startDate && $endDate) {
            $startTs = strtotime($startDate);
            $endTs   = strtotime($endDate);
            $weeks = max(1, min(52, (int)floor(($endTs - $startTs) / (86400 * 7)) + 1));
        } elseif ($startDate) {
            $startTs = strtotime($startDate);
            $endTs   = time();
            $weeks = max(1, min(52, (int)floor(($endTs - $startTs) / (86400 * 7)) + 1));
        } elseif ($endDate) {
            $endTs   = strtotime($endDate);
            $startTs = $endTs - ($weeks - 1) * 7 * 86400;
        } else {
            $endTs   = time();
            $startTs = $endTs - ($weeks - 1) * 7 * 86400;
        }

        $periods = $this->generateWeeklyPeriods($startTs, $endTs);

        $statuses = $this->getStatusesForPeriod($startTs, $endTs, 'week');

        return $this->buildTrendResult($periods, $statuses, 'week');
    }

    protected function trendByMonth(?string $startDate, ?string $endDate): array
    {
        $months = 6;
        if ($startDate && $endDate) {
            $startTs = strtotime($startDate);
            $endTs   = strtotime($endDate);
            $months = max(1, min(24, (int)floor(($endTs - $startTs) / (86400 * 30)) + 1));
        } elseif ($startDate) {
            $startTs = strtotime($startDate);
            $endTs   = time();
            $months = max(1, min(24, (int)floor(($endTs - $startTs) / (86400 * 30)) + 1));
        } elseif ($endDate) {
            $endTs   = strtotime($endDate);
            $startTs = strtotime("-{$months} months", $endTs);
        } else {
            $endTs   = time();
            $startTs = strtotime("-{$months} months");
        }

        $periods = $this->generateMonthlyPeriods($startTs, $endTs);

        $statuses = $this->getStatusesForPeriod($startTs, $endTs, 'month');

        return $this->buildTrendResult($periods, $statuses, 'month');
    }

    protected function generateDailyPeriods(int $startTs, int $endTs): array
    {
        $periods = [];
        $current = strtotime(date('Y-m-d', $startTs));
        $end = strtotime(date('Y-m-d', $endTs));
        while ($current <= $end) {
            $key = date('Y-m-d', $current);
            $periods[$key] = [
                'period_key'   => $key,
                'period_label' => $key,
                'start_ts'     => $current,
                'end_ts'       => $current + 86399,
            ];
            $current = strtotime('+1 day', $current);
        }
        return $periods;
    }

    protected function generateWeeklyPeriods(int $startTs, int $endTs): array
    {
        $periods = [];
        $startDay = date('N', $startTs);
        $weekStart = strtotime('-'.($startDay - 1).' days', $startTs);
        $weekStart = strtotime(date('Y-m-d', $weekStart));

        $endDay = date('N', $endTs);
        $weekEnd = strtotime('+'.(7 - $endDay).' days', $endTs);
        $weekEnd = strtotime(date('Y-m-d', $weekEnd));

        $current = $weekStart;
        while ($current <= $weekEnd) {
            $year = date('o', $current);
            $week = date('W', $current);
            $key = "{$year}-W{$week}";
            $label = date('Y-m-d', $current) . ' ~ ' . date('Y-m-d', $current + 6 * 86400);
            $periods[$key] = [
                'period_key'   => $key,
                'period_label' => $label,
                'start_ts'     => $current,
                'end_ts'       => $current + 7 * 86400 - 1,
            ];
            $current = strtotime('+1 week', $current);
        }
        return $periods;
    }

    protected function generateMonthlyPeriods(int $startTs, int $endTs): array
    {
        $periods = [];
        $startMonth = strtotime(date('Y-m-01', $startTs));
        $endMonth = strtotime(date('Y-m-01', $endTs));

        $current = $startMonth;
        while ($current <= $endMonth) {
            $key = date('Y-m', $current);
            $label = $key;
            $periods[$key] = [
                'period_key'   => $key,
                'period_label' => $label,
                'start_ts'     => $current,
                'end_ts'       => strtotime('last day of this month', $current) + 86399,
            ];
            $current = strtotime('+1 month', $current);
        }
        return $periods;
    }

    protected function getStatusesForPeriod(int $startTs, int $endTs, string $granularity): array
    {
        $startDate = date('Y-m-d H:i:s', $startTs);
        $endDate = date('Y-m-d H:i:s', $endTs);

        $dateExpr = $this->getDateExpression($granularity);

        $countRows = Db::table('order_status_history')
            ->alias('h')
            ->field([
                "{$dateExpr} as period_key",
                'h.new_status as status',
                'COUNT(DISTINCT h.order_id) as order_count',
            ])
            ->where('h.changed_at', '>=', $startDate)
            ->where('h.changed_at', '<=', $endDate)
            ->group('period_key', 'h.new_status')
            ->order('period_key', 'asc')
            ->select()
            ->toArray();

        $countMap = [];
        foreach ($countRows as $row) {
            $key = (string)$row['period_key'];
            $status = $row['status'];
            if (!isset($countMap[$key])) {
                $countMap[$key] = [];
            }
            $countMap[$key][$status] = (int)$row['order_count'];
        }

        $rawRows = Db::table('order_status_history')
            ->alias('h')
            ->field([
                "{$dateExpr} as period_key",
                'h.new_status as status',
                'h.order_id',
                'h.currency_snapshot as currency',
                'h.grand_total_snapshot as grand_total',
            ])
            ->where('h.changed_at', '>=', $startDate)
            ->where('h.changed_at', '<=', $endDate)
            ->whereNotNull('h.currency_snapshot')
            ->order('h.changed_at', 'asc')
            ->select()
            ->toArray();

        $seen = [];
        $amountByKey = [];
        foreach ($rawRows as $row) {
            $key = (string)$row['period_key'];
            $status = (string)$row['status'];
            $orderId = (int)$row['order_id'];
            $currency = (string)$row['currency'];
            $dedupKey = "{$key}|{$status}|{$orderId}|{$currency}";
            if (isset($seen[$dedupKey])) {
                continue;
            }
            $seen[$dedupKey] = true;
            if (!isset($amountByKey[$key])) {
                $amountByKey[$key] = [];
            }
            if (!isset($amountByKey[$key][$status])) {
                $amountByKey[$key][$status] = [];
            }
            if (!isset($amountByKey[$key][$status][$currency])) {
                $amountByKey[$key][$status][$currency] = 0.0;
            }
            $amountByKey[$key][$status][$currency] += (float)$row['grand_total'];
        }

        $currencyMap = [];
        foreach ($amountByKey as $key => $statusMap) {
            if (!isset($currencyMap[$key])) {
                $currencyMap[$key] = [];
            }
            foreach ($statusMap as $status => $curMap) {
                if (!isset($currencyMap[$key][$status])) {
                    $currencyMap[$key][$status] = [];
                }
                foreach ($curMap as $currency => $amount) {
                    $currencyMap[$key][$status][] = [
                        'currency' => $currency,
                        'symbol'   => $this->currencySymbols[$currency] ?? $currency,
                        'amount'   => round($amount, 2),
                    ];
                }
            }
        }

        return ['counts' => $countMap, 'currencies' => $currencyMap];
    }

    protected function getDateExpression(string $granularity): string
    {
        switch ($granularity) {
            case 'week':
                return "DATE_FORMAT(DATE_SUB(h.changed_at, INTERVAL (DAYOFWEEK(h.changed_at) - 2) DAY), '%x-W%v')";
            case 'month':
                return "DATE_FORMAT(h.changed_at, '%Y-%m')";
            case 'day':
            default:
                return "DATE(h.changed_at)";
        }
    }

    protected function buildTrendResult(array $periods, array $statusData, string $granularity): array
    {
        $countMap = $statusData['counts'] ?? [];
        $currencyMap = $statusData['currencies'] ?? [];

        $result = [];
        foreach ($periods as $key => $period) {
            foreach ($this->statusMap as $status => $label) {
                $result[] = [
                    'period_key'   => $period['period_key'],
                    'period_label' => $period['period_label'],
                    'status'       => $status,
                    'status_label' => $label,
                    'order_count'  => $countMap[$key][$status] ?? 0,
                    'by_currency'  => $currencyMap[$key][$status] ?? [],
                ];
            }
        }
        return $result;
    }
}
