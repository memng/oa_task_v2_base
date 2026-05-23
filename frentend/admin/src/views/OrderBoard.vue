<template>
  <div class="order-board">
    <el-card class="filter-card">
      <el-form inline :model="query" class="filter-form">
        <el-form-item label="创建时间">
          <el-date-picker
            v-model="dateRange"
            type="daterange"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            value-format="YYYY-MM-DD"
            @change="onDateChange"
          />
        </el-form-item>
        <el-form-item label="趋势粒度">
          <el-select v-model="query.granularity" @change="fetch">
            <el-option label="按天" value="day" />
            <el-option label="按周" value="week" />
            <el-option label="按月" value="month" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="fetch">查询</el-button>
          <el-button @click="reset">重置</el-button>
        </el-form-item>
      </el-form>
      <el-alert
        v-if="errorMsg"
        :title="errorMsg"
        type="error"
        show-icon
        :closable="false"
        class="error-alert"
      />
    </el-card>

    <div class="cards">
      <div class="card">
        <div class="card-label">订单总数</div>
        <div class="card-value">{{ summary.total?.orders || 0 }}</div>
      </div>
      <div class="card">
        <div class="card-label">订单总金额</div>
        <div class="card-value card-value-amount">
          {{ summary.total?.by_currency?.length ? formatCurrencyList(summary.total.by_currency) : '-' }}
        </div>
      </div>
      <div class="card">
        <div class="card-label">进行中订单</div>
        <div class="card-value">{{ getStatusCount('in_progress') }}</div>
      </div>
      <div class="card">
        <div class="card-label">已完成订单</div>
        <div class="card-value">{{ getStatusCount('completed') }}</div>
      </div>
    </div>

    <el-card>
      <template #header>
        <div class="card-header">
          <span>按阶段统计</span>
        </div>
      </template>
      <div class="stats-row">
        <div
          v-for="item in summary.by_status || []"
          :key="item.status"
          class="stats-item"
        >
          <div class="stats-bar">
            <div
              class="stats-bar-fill"
              :class="'status-' + item.status"
              :style="{ width: getBarWidth(item.order_count) + '%' }"
            />
          </div>
          <div class="stats-info">
            <div class="stats-label">
              <span class="status-dot" :class="'status-dot-' + item.status" />
              {{ item.status_label }}
            </div>
            <div class="stats-values">
              <span class="stats-count">{{ item.order_count }} 单</span>
              <span class="stats-amount">{{ formatCurrencyList(item.by_currency) }}</span>
            </div>
          </div>
        </div>
      </div>
    </el-card>

    <el-card>
      <template #header>
        <div class="card-header">
          <span>订单数量趋势</span>
          <span class="header-hint">按 {{ granularityLabel }} 统计各阶段订单数（基于状态变更时间）</span>
        </div>
      </template>
      <div class="chart-container">
        <div ref="countChartRef" class="chart" />
      </div>
    </el-card>

    <el-card>
      <template #header>
        <div class="card-header">
          <span>订单金额趋势</span>
          <span class="header-hint">
            主币种：{{ primaryCurrencyLabel }} · 按 {{ granularityLabel }} 统计各阶段金额（基于状态变更时间）
          </span>
        </div>
      </template>
      <div class="chart-container">
        <div ref="amountChartRef" class="chart" />
      </div>
    </el-card>

    <el-card>
      <template #header>
        <div class="card-header">
          <span>阶段明细</span>
        </div>
      </template>
      <el-table :data="summary.by_status || []" stripe>
        <el-table-column prop="status_label" label="阶段" width="120" />
        <el-table-column prop="order_count" label="订单数" width="120" align="right" />
        <el-table-column label="订单金额" min-width="200" align="right">
          <template #default="{ row }">
            {{ formatCurrencyList(row.by_currency) }}
          </template>
        </el-table-column>
        <el-table-column label="占比" width="120" align="right">
          <template #default="{ row }">
            {{ getPercent(row.order_count, summary.total?.orders) }}%
          </template>
        </el-table-column>
      </el-table>
    </el-card>
  </div>
</template>

<script setup>
import { reactive, ref, onMounted, nextTick, computed, onBeforeUnmount } from 'vue'
import { api } from '../api'
import * as echarts from 'echarts'

const summary = ref({
  total: { orders: 0, by_currency: [] },
  by_status: [],
  recent_trend: [],
  currency_symbols: {},
})

const query = reactive({
  start_date: '',
  end_date: '',
  granularity: 'day',
})
const dateRange = ref([])
const errorMsg = ref('')

const countChartRef = ref(null)
const amountChartRef = ref(null)
let countChart = null
let amountChart = null

const statusColors = {
  draft: '#909399',
  in_progress: '#409EFF',
  completed: '#67C23A',
  cancelled: '#E6A23C',
}

const granularityLabels = {
  day: '天',
  week: '周',
  month: '月',
}

const granularityLabel = computed(() => granularityLabels[query.granularity] || '天')

const primaryCurrency = computed(() => {
  const backendPrimary = summary.value.total?.primary_currency
  if (backendPrimary) return backendPrimary
  const list = summary.value.total?.by_currency || []
  if (!list.length) return null
  let max = list[0]
  for (const item of list) {
    if ((item.amount || 0) > (max.amount || 0)) {
      max = item
    }
  }
  return max
})

const primaryCurrencyLabel = computed(() => {
  const c = primaryCurrency.value
  return c ? `${c.symbol || ''}${c.currency || ''}` : '-'
})

const fetch = async () => {
  errorMsg.value = ''
  const params = {}
  if (query.start_date) params.start_date = query.start_date
  if (query.end_date) params.end_date = query.end_date
  if (query.granularity) params.granularity = query.granularity

  try {
    const { data } = await api.orderBoardSummary(params)
    summary.value = data.data || summary.value
    await nextTick()
    renderCharts()
  } catch (e) {
    if (e.response?.data?.message) {
      errorMsg.value = e.response.data.message
    } else {
      errorMsg.value = '获取数据失败'
    }
    console.error('Failed to fetch order board summary', e)
  }
}

const onDateChange = (val) => {
  if (val && val.length === 2) {
    query.start_date = val[0]
    query.end_date = val[1]
  } else {
    query.start_date = ''
    query.end_date = ''
  }
}

const reset = () => {
  query.start_date = ''
  query.end_date = ''
  query.granularity = 'day'
  dateRange.value = []
  errorMsg.value = ''
  fetch()
}

const getStatusCount = (status) => {
  const item = (summary.value.by_status || []).find((s) => s.status === status)
  return item ? item.order_count : 0
}

const getMaxCount = () => {
  const list = summary.value.by_status || []
  if (!list.length) return 1
  return Math.max(...list.map((s) => s.order_count), 1)
}

const getBarWidth = (count) => {
  return Math.min(100, (count / getMaxCount()) * 100)
}

const formatCurrencyList = (list) => {
  if (!list || !list.length) return '-'
  return list.map((c) => {
    const symbol = c.symbol || c.currency || ''
    const amount = formatAmount(c.amount)
    return `${symbol}${amount}`
  }).join(' / ')
}

const formatAmount = (val) => {
  if (val == null) return '0'
  const num = Number(val)
  if (num >= 10000) {
    return (num / 10000).toFixed(2) + ' 万'
  }
  return num.toFixed(2)
}

const getPercent = (part, total) => {
  if (!total) return '0.0'
  return ((part / total) * 100).toFixed(1)
}

const buildTrendChartData = () => {
  const trend = summary.value.recent_trend || []
  const periods = [...new Set(trend.map((t) => t.period_key))].sort()
  const statuses = [...new Set(trend.map((t) => t.status))]

  const countSeries = statuses.map((status) => ({
    name: statusColors[status] ? statusLabel(status) : status,
    type: 'line',
    smooth: true,
    data: periods.map((p) => {
      const item = trend.find((t) => t.period_key === p && t.status === status)
      return item ? item.order_count : 0
    }),
    itemStyle: { color: statusColors[status] || '#999' },
  }))

  const primaryCur = primaryCurrency.value
  const amountSeries = statuses.map((status) => ({
    name: statusColors[status] ? statusLabel(status) : status,
    type: 'line',
    smooth: true,
    data: periods.map((p) => {
      const item = trend.find((t) => t.period_key === p && t.status === status)
      if (!item || !item.by_currency || !primaryCur) return 0
      const curItem = item.by_currency.find((c) => c.currency === primaryCur.currency)
      return curItem ? curItem.amount || 0 : 0
    }),
    itemStyle: { color: statusColors[status] || '#999' },
  }))

  return { periods, countSeries, amountSeries }
}

const statusLabel = (status) => {
  const map = {
    draft: '草稿',
    in_progress: '进行中',
    completed: '已完成',
    cancelled: '已取消',
  }
  return map[status] || status
}

const renderCharts = () => {
  const { periods, countSeries, amountSeries } = buildTrendChartData()

  if (countChartRef.value && countSeries.length) {
    if (!countChart) {
      countChart = echarts.init(countChartRef.value)
    }
    countChart.setOption({
      tooltip: { trigger: 'axis', axisPointer: { type: 'cross' } },
      legend: { data: countSeries.map((s) => s.name) },
      grid: { left: 50, right: 30, top: 40, bottom: 30 },
      xAxis: {
        type: 'category',
        data: periods,
        boundaryGap: false,
      },
      yAxis: {
        type: 'value',
        name: '订单数',
        minInterval: 1,
      },
      series: countSeries,
    }, true)
  }

  if (amountChartRef.value && amountSeries.length) {
    if (!amountChart) {
      amountChart = echarts.init(amountChartRef.value)
    }
    amountChart.setOption({
      tooltip: {
        trigger: 'axis', axisPointer: { type: 'cross' } },
      legend: { data: amountSeries.map((s) => s.name) },
      grid: { left: 60, right: 30, top: 40, bottom: 30 },
      xAxis: {
        type: 'category',
        data: periods,
        boundaryGap: false,
      },
      yAxis: {
        type: 'value',
        name: `金额(${primaryCurrency.value?.currency || ''})`,
        axisLabel: {
          formatter: (val) => {
            if (val >= 10000) return (val / 10000).toFixed(1) + '万'
            return val
          },
        },
      },
      series: amountSeries,
    }, true)
  }
}

const handleResize = () => {
  countChart?.resize()
  amountChart?.resize()
}

onMounted(() => {
  fetch()
  window.addEventListener('resize', handleResize)
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', handleResize)
  countChart?.dispose()
  amountChart?.dispose()
})
</script>

<style scoped>
.order-board {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.filter-card {
  margin-bottom: 4px;
}

.filter-form {
  margin-bottom: 0;
}

.error-alert {
  margin-top: 12px;
}

.cards {
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
}

.card {
  flex: 1;
  min-width: 200px;
  background: #fff;
  border-radius: 8px;
  padding: 20px 24px;
  border: 1px solid #ebeef5;
  transition: box-shadow 0.2s;
}

.card:hover {
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
}

.card-label {
  color: #909399;
  font-size: 14px;
  margin-bottom: 8px;
}

.card-value {
  font-size: 28px;
  font-weight: 600;
  color: #303133;
}

.card-value-amount {
  font-size: 22px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.header-hint {
  font-size: 12px;
  color: #909399;
  font-weight: normal;
}

.stats-row {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.stats-item {
  display: flex;
  align-items: center;
  gap: 16px;
}

.stats-bar {
  width: 200px;
  height: 8px;
  background: #f0f2f5;
  border-radius: 4px;
  overflow: hidden;
}

.stats-bar-fill {
  height: 100%;
  border-radius: 4px;
  transition: width 0.3s;
}

.status-draft { background-color: #909399; }
.status-in_progress { background-color: #409EFF; }
.status-completed { background-color: #67C23A; }
.status-cancelled { background-color: #E6A23C; }

.stats-info {
  flex: 1;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.stats-label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  color: #303133;
}

.status-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  display: inline-block;
}

.status-dot-draft { background-color: #909399; }
.status-dot-in_progress { background-color: #409EFF; }
.status-dot-completed { background-color: #67C23A; }
.status-dot-cancelled { background-color: #E6A23C; }

.stats-values {
  display: flex;
  gap: 16px;
  font-size: 13px;
}

.stats-count {
  color: #303133;
}

.stats-amount {
  color: #909399;
}

.chart-container {
  width: 100%;
}

.chart {
  width: 100%;
  height: 320px;
}
</style>
