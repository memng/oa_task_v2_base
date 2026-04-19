<template>
  <scroll-view scroll-y class="page">
    <view class="user-card">
      <image class="avatar" :src="avatar" mode="aspectFill" />
      <view>
        <view class="name">{{ userName }}</view>
        <view class="dept">{{ deptName }}</view>
      </view>
      <view class="tabs">
        <text v-for="item in tabs" :key="item.value" :class="{ active: activeTab === item.value }" @click="switchTab(item.value)">
          {{ item.label }}
        </text>
      </view>
    </view>

    <view class="calendar-card">
      <view class="month">{{ currentYear }} | {{ currentMonth }}</view>
      <view class="legend">
        <view v-for="info in legends" :key="info.label" class="legend-item">
          <view class="dot" :class="info.status"></view>
          <text>{{ info.label }}</text>
        </view>
      </view>
      <view class="calendar">
        <view
          v-for="day in days"
          :key="day.key"
          :class="['cell', { empty: day.empty, selected: selectedDate === day.date, today: day.isToday }]"
          @click="selectDay(day)"
        >
          <text v-if="!day.empty">{{ day.day }}</text>
          <view v-if="!day.empty" class="dot" :class="day.status"></view>
        </view>
      </view>
      <view class="summary">
        <view>{{ summaryInfo.title }}</view>
        <view>{{ summaryInfo.desc }}</view>
      </view>
      <view class="status-detail">{{ selectedDayInfo.description }}</view>
    </view>
  </scroll-view>
</template>

<script setup>
import { ref, computed } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import store from '../../store'
import { request } from '../../utils/request'

const tabs = [
  { label: '日', value: 'day' },
  { label: '周', value: 'week' },
  { label: '月', value: 'month' }
]
const STATUS_LABELS = {
  normal: '正常',
  late: '迟到',
  early: '早退',
  absent: '缺勤',
  leave: '请假',
  overtime: '加班',
  lack: '缺卡',
  none: '未打卡'
}

function safeDate(value) {
  if (!value) return null
  if (value instanceof Date) return new Date(value)
  if (typeof value === 'string') {
    return new Date(value.replace(/-/g, '/'))
  }
  return new Date(value)
}

function formatDate(value) {
  const date = safeDate(value)
  if (!date || Number.isNaN(date.getTime())) return ''
  const pad = (num) => String(num).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

function formatTime(value) {
  const date = safeDate(value)
  if (!date || Number.isNaN(date.getTime())) return ''
  const pad = (num) => String(num).padStart(2, '0')
  return `${pad(date.getHours())}:${pad(date.getMinutes())}`
}

const activeTab = ref('day')
const legends = [
  { label: '正常', status: 'normal' },
  { label: '异常', status: 'abnormal' },
  { label: '缺卡/未打卡', status: 'lack' }
]
const records = ref([])
const days = ref([])
const todayStr = formatDate(new Date())
const selectedDate = ref(todayStr)

const defaultDayInfo = {
  status: 'none',
  statusLabel: STATUS_LABELS.none,
  summary: '没有打卡记录',
  description: '当天没有打卡记录'
}

const buildDayMap = (items = []) => {
  const map = {}
  items.forEach((record) => {
    const dateKey = formatDate(record.checked_at)
    if (!dateKey) return
    if (!map[dateKey]) {
      map[dateKey] = { records: [] }
    }
    map[dateKey].records.push(record)
  })
  Object.entries(map).forEach(([dateKey, info]) => {
    const checkIn = info.records.find((item) => isCheckType(item, 'in'))
    const checkOut = info.records.find((item) => isCheckType(item, 'out'))
    const abnormalRecord = info.records.find((item) => item.status && item.status !== 'normal')
    let status = 'normal'
    let statusLabel = STATUS_LABELS.normal
    if (!info.records.length) {
      status = 'none'
      statusLabel = STATUS_LABELS.none
    } else if (abnormalRecord) {
      status = 'abnormal'
      statusLabel = STATUS_LABELS[abnormalRecord.status] || '异常'
    } else if (!checkIn || !checkOut) {
      status = 'lack'
      statusLabel = STATUS_LABELS.lack
    }
    const descriptionParts = []
    if (checkIn) {
      descriptionParts.push(`上班：${formatTime(checkIn.checked_at)}`)
    } else {
      descriptionParts.push('缺少上班打卡')
    }
    if (checkOut) {
      descriptionParts.push(`下班：${formatTime(checkOut.checked_at)}`)
    } else {
      descriptionParts.push('缺少下班打卡')
    }
    descriptionParts.push(`状态：${statusLabel}`)
    info.status = status
    info.statusLabel = statusLabel
    info.summary = `打卡${info.records.length}次`
    info.description = descriptionParts.join('；')
  })
  return map
}

const dayStatusMap = computed(() => buildDayMap(records.value))

const selectedDateInfo = computed(() => dayStatusMap.value[selectedDate.value] || defaultDayInfo)

const selectedDateObj = computed(() => {
  const date = safeDate(selectedDate.value)
  return date && !Number.isNaN(date.getTime()) ? date : safeDate(todayStr)
})

const currentYear = computed(() => selectedDateObj.value.getFullYear())
const currentMonth = computed(() => {
  const pad = (num) => String(num).padStart(2, '0')
  return `${pad(selectedDateObj.value.getMonth() + 1)}.${pad(selectedDateObj.value.getDate())}`
})

const summaryInfo = computed(() => {
  if (activeTab.value === 'day') {
    return {
      title: `当日：${selectedDate.value}`,
      desc: `${selectedDateInfo.value.summary} | ${selectedDateInfo.value.statusLabel}`
    }
  }
  if (activeTab.value === 'week') {
    const { start, end } = getWeekRange(selectedDateObj.value)
    const stats = collectRangeStats(start, end)
    return {
      title: `本周（${start} ~ ${end}）`,
      desc: `正常${stats.normal}天，异常${stats.abnormal}天，缺卡${stats.lack + stats.none}天`
    }
  }
  const { start, end } = getMonthRange(selectedDateObj.value)
  const stats = collectRangeStats(start, end)
  return {
    title: `本月（${start} ~ ${end}）`,
    desc: `正常${stats.normal}天，异常${stats.abnormal}天，缺卡${stats.lack + stats.none}天`
  }
})

function isCheckType(record, type) {
  const key = (record.check_type || record.type || '').toLowerCase()
  if (type === 'in') {
    return key === 'check_in' || key === 'checkin'
  }
  return key === 'check_out' || key === 'checkout'
}

function getWeekRange(dateObj) {
  const start = new Date(dateObj)
  const day = start.getDay() || 7
  start.setDate(start.getDate() - day + 1)
  const end = new Date(start)
  end.setDate(start.getDate() + 6)
  return { start: formatDate(start), end: formatDate(end) }
}

function getMonthRange(dateObj) {
  const start = new Date(dateObj.getFullYear(), dateObj.getMonth(), 1)
  const end = new Date(dateObj.getFullYear(), dateObj.getMonth() + 1, 0)
  return { start: formatDate(start), end: formatDate(end) }
}

function collectRangeStats(startStr, endStr) {
  const stats = { normal: 0, abnormal: 0, lack: 0, none: 0 }
  const start = safeDate(startStr)
  const end = safeDate(endStr)
  if (!start || !end) return stats
  const cursor = new Date(start)
  while (cursor <= end) {
    const key = formatDate(cursor)
    const info = dayStatusMap.value[key] || defaultDayInfo
    stats[info.status] = (stats[info.status] || 0) + 1
    cursor.setDate(cursor.getDate() + 1)
  }
  return stats
}

const selectedDayInfo = computed(() => selectedDateInfo.value)

const selectDay = (day) => {
  if (day.empty || !day.date) return
  selectedDate.value = day.date
}

const switchTab = (value) => {
  activeTab.value = value
}

const buildCalendar = () => {
  const base = selectedDateObj.value
  const year = base.getFullYear()
  const month = base.getMonth()
  const firstDay = new Date(year, month, 1)
  const totalDays = new Date(year, month + 1, 0).getDate()
  const cells = []
  for (let i = 0; i < firstDay.getDay(); i++) {
    cells.push({ key: `pre-${i}`, empty: true })
  }
  for (let day = 1; day <= totalDays; day++) {
    const dateObj = new Date(year, month, day)
    const dateStr = formatDate(dateObj)
    const info = dayStatusMap.value[dateStr] || defaultDayInfo
    cells.push({
      key: dateStr,
      day,
      date: dateStr,
      empty: false,
      isToday: dateStr === todayStr,
      status: info.status === 'none' ? 'lack' : info.status,
      statusLabel: info.statusLabel,
      summary: info.summary,
      description: info.description
    })
  }
  while (cells.length % 7 !== 0) {
    cells.push({ key: `post-${cells.length}`, empty: true })
  }
  days.value = cells
  if (!selectedDate.value) {
    const todayCell = cells.find((cell) => cell.date === todayStr)
    selectedDate.value = (todayCell && todayCell.date) || cells.find((cell) => !cell.empty)?.date || ''
  }
}

const fetchRecords = async () => {
  try {
    const resRecords = await request({ url: '/attendance/records' })
    records.value = resRecords.items || []
  } catch (error) {
    console.error('fetch attendance stats failed', error)
  } finally {
    buildCalendar()
  }
}

onShow(() => {
  fetchRecords()
})

const userName = computed(() => (store.state.profile && store.state.profile.name) || '张三')
const deptName = computed(() => {
  if (store.state.profile && store.state.profile.dept) return store.state.profile.dept.name
  return '加入考勤组'
})
const avatar = computed(() => (store.state.profile && store.state.profile.avatar_url) || '/static/icons/avatar.png')
</script>

<style scoped lang="scss">
.page {
  padding: 32rpx;
  background: #f6f7fb;
}
.user-card {
  background: #fff;
  border-radius: 24rpx;
  padding: 24rpx;
  display: flex;
  align-items: center;
  gap: 16rpx;
}
.avatar {
  width: 80rpx;
  height: 80rpx;
  border-radius: 40rpx;
}
.name {
  font-size: 30rpx;
  font-weight: 600;
}
.dept {
  font-size: 24rpx;
  color: #999;
}
.tabs {
  margin-left: auto;
  display: flex;
  gap: 20rpx;
}
.tabs text {
  color: #999;
}
.tabs .active {
  color: #1677ff;
  font-weight: 600;
}
.calendar-card {
  background: #fff;
  border-radius: 24rpx;
  padding: 24rpx;
  margin-top: 24rpx;
}
.month {
  font-size: 28rpx;
  font-weight: 600;
}
.legend {
  font-size: 24rpx;
  color: #666;
  margin: 12rpx 0 8rpx;
  display: flex;
  flex-wrap: wrap;
  gap: 12rpx 24rpx;
}
.legend-item {
  display: flex;
  align-items: center;
  gap: 8rpx;
}
.legend-item .dot {
  width: 16rpx;
  height: 16rpx;
  border-radius: 50%;
}
.calendar {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 16rpx;
  margin-top: 12rpx;
}
.cell {
  width: 80rpx;
  height: 80rpx;
  border-radius: 16rpx;
  background: #f7f8fa;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  font-size: 26rpx;
  color: #666;
}
.cell.empty {
  background: transparent;
}
.cell.selected {
  background: rgba(22, 119, 255, 0.15);
  border: 2rpx solid #1677ff;
  color: #1677ff;
}
.cell.today {
  border: 2rpx solid rgba(22, 119, 255, 0.4);
}
.cell .dot {
  width: 12rpx;
  height: 12rpx;
  border-radius: 6rpx;
  margin-top: 6rpx;
}
.dot.normal {
  background: #52c41a;
}
.dot.abnormal {
  background: #f5222d;
}
.dot.lack {
  background: #d9d9d9;
}
.summary {
  margin-top: 24rpx;
  color: #333;
  display: flex;
  flex-direction: column;
  gap: 8rpx;
  line-height: 1.4;
}
.status-detail {
  margin-top: 16rpx;
  padding: 16rpx;
  background: #f7f8fa;
  border-radius: 16rpx;
  font-size: 26rpx;
  color: #333;
  line-height: 1.5;
}
</style>
