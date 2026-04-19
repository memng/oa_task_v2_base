<template>
  <scroll-view scroll-y class="page">
    <view class="top-card">
      <view class="user">
        <image class="avatar" :src="avatar" mode="aspectFill" />
        <view>
          <view class="name">{{ userName }}</view>
          <view class="dept">{{ deptName }}</view>
        </view>
      </view>
      <button class="small-btn" @click="navStats">统计</button>
    </view>

    <view class="status-row">
      <view class="status-card">
        <view>上班</view>
        <view class="status-text">{{ lastCheckIn || '未打卡' }}</view>
      </view>
      <view class="status-card">
        <view>下班</view>
        <view class="status-text">{{ lastCheckOut || '未打卡' }}</view>
      </view>
    </view>

    <view class="clock-card">
      <view class="clock" @click="checkIn">
        <text>{{ isWorking ? '下班打卡' : '上班打卡' }}</text>
        <text class="time">{{ currentTime }}</text>
      </view>
      <view class="location">
        <view class="city">{{ location }}</view>
        <view class="company">{{ company }}</view>
      </view>
      <view class="worktime">
        <view>上班时间：{{ workTime.start }}</view>
        <view>下班时间：{{ workTime.end }}</view>
      </view>
    </view>

    <view class="records-card">
      <view class="title">考勤规则</view>
      <view v-for="rule in rules" :key="rule.id" class="rule">
        <text>{{ rule.name }}</text>
        <text>{{ rule.start_time }} - {{ rule.end_time }}</text>
      </view>
    </view>
    <view class="records-card">
      <view class="title">最近记录</view>
      <view v-for="item in records" :key="item.id" class="record-item">
        <view class="record-row">
          <text class="record-time">{{ item.checked_at }}</text>
          <text class="record-status">{{ item.status_label }}</text>
        </view>
        <view v-if="item.location_display" class="record-location">{{ item.location_display }}</view>
      </view>
    </view>
  </scroll-view>
</template>

<script setup>
import { ref, computed } from 'vue'
import { onShow, onHide, onUnload } from '@dcloudio/uni-app'
import store from '../../store'
import { request } from '../../utils/request'

const rules = ref([])
const records = ref([])
const lastCheckIn = ref('')
const lastCheckOut = ref('')
const isWorking = ref(false)
const currentTime = ref('09:00:00')
const location = ref('定位中...')
const coords = ref(null)
const STATUS_LABELS = {
  normal: '正常',
  late: '迟到',
  early: '早退',
  absent: '缺勤',
  leave: '请假',
  overtime: '加班',
  lack: '缺卡'
}

const company = computed(() => {
  if (store.state.profile && store.state.profile.company_name) {
    return store.state.profile.company_name
  }
  if (store.state.profile && store.state.profile.company) {
    return store.state.profile.company.name || store.state.profile.company
  }
  return '安徽黑马重工机械科技有限公司'
})
const workTime = ref({ start: '09:00', end: '19:00' })

const formatDate = (val) => {
  if (!val) return ''
  const date = new Date(val)
  if (Number.isNaN(date.getTime())) return val
  const pad = (num) => String(num).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

const formatDateTime = (val) => {
  if (!val) return ''
  const date = new Date(val)
  if (Number.isNaN(date.getTime())) return val
  const pad = (num) => String(num).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(
    date.getMinutes()
  )}:${pad(date.getSeconds())}`
}

const isCheckInRecord = (record) => {
  const indicator = (record.check_type || record.type || '').toLowerCase()
  return indicator === 'check_in' || indicator === 'checkin'
}

const isCheckOutRecord = (record) => {
  const indicator = (record.check_type || record.type || '').toLowerCase()
  return indicator === 'check_out' || indicator === 'checkout'
}

const updateTodayStatus = () => {
  const today = formatDate(new Date())
  const todaysRecords = records.value.filter((item) => (item.checked_at || '').startsWith(today))
  const checkInRecord = todaysRecords.find((item) => isCheckInRecord(item))
  const checkOutRecord = todaysRecords.find((item) => isCheckOutRecord(item))
  lastCheckIn.value = checkInRecord ? formatDateTime(checkInRecord.checked_at) : ''
  lastCheckOut.value = checkOutRecord ? formatDateTime(checkOutRecord.checked_at) : ''
  isWorking.value = !!checkInRecord && !checkOutRecord
}

const fetchData = async () => {
  try {
    const resRules = await request({ url: '/attendance/rules' })
    rules.value = resRules.items || []
    const resRecords = await request({ url: '/attendance/records' })
    records.value = mapRecordStatus(resRecords.items || [])
  } catch (error) {
    console.error('fetch attendance data failed', error)
  } finally {
    updateTodayStatus()
  }
}

const formatLocationText = (payload) => {
  if (!payload) return '未知位置'
  const address = payload.address
  if (typeof address === 'string' && address.trim()) {
    return address
  }
  if (address && typeof address === 'object') {
    const pieces = [address.province, address.city, address.district, address.street, address.streetNum || address.street_number]
    const text = pieces.filter(Boolean).join('')
    if (text) return text
  }
  if (payload.name) {
    return `${payload.name}${payload.address ? ` ${payload.address}` : ''}`.trim()
  }
  const { latitude, longitude } = payload
  if (typeof latitude === 'number' && typeof longitude === 'number') {
    return `纬度${latitude.toFixed(4)} 经度${longitude.toFixed(4)}`
  }
  return '无法获取定位'
}

const resolveRecordLocation = (record) => {
  if (!record) return ''
  if (record.location_display) return record.location_display
  if (record.location_text) return record.location_text
  if (record.location) return record.location
  if (record.address) {
    return formatLocationText({ address: record.address })
  }
  const lat = Number(record.lat)
  const lng = Number(record.lng)
  if (!Number.isNaN(lat) && !Number.isNaN(lng)) {
    return formatLocationText({ latitude: lat, longitude: lng })
  }
  return ''
}

const mapRecordStatus = (items = []) =>
  items.map((item) => ({
    ...item,
    status_label: item.status_label || STATUS_LABELS[item.status] || '异常',
    location_display: resolveRecordLocation(item)
  }))

const enhanceLocationDetail = async (lat, lng) => {
  try {
    const result = await request({ url: '/location/reverse', data: { lat, lng } })
    const betterText = (result && result.text) || ''
    if (betterText && coords.value && coords.value.lat === lat && coords.value.lng === lng) {
      coords.value.location_text = betterText
      location.value = betterText
    }
  } catch (error) {
    console.warn('reverse geocoder failed', error)
  }
}

const fetchLocation = () =>
  new Promise((resolve) => {
    location.value = '定位中...'
    const handleSuccess = (res) => {
      const lat = Number(res.latitude)
      const lng = Number(res.longitude)
      const payload = { ...res, latitude: lat, longitude: lng }
      const formatted = formatLocationText(payload)
      coords.value = { lat, lng, location_text: formatted }
      location.value = formatted
      resolve(coords.value)
      enhanceLocationDetail(lat, lng)
    }
    const handleFail = (error) => {
      console.warn('get location fail', error)
      location.value = '定位失败，请检查权限'
      coords.value = null
      resolve(null)
    }
    const fuzzyOptions = { success: handleSuccess, fail: handleFail }

    // Prefer fuzzy location API for compliance; fall back to precise location on other platforms
    if (typeof wx !== 'undefined' && typeof wx.getFuzzyLocation === 'function') {
      wx.getFuzzyLocation(fuzzyOptions)
      return
    }
    if (typeof uni !== 'undefined' && typeof uni.getFuzzyLocation === 'function') {
      uni.getFuzzyLocation(fuzzyOptions)
      return
    }
    uni.getLocation({ type: 'gcj02', success: handleSuccess, fail: handleFail })
  })

const checkIn = async () => {
  if (!coords.value) {
    await fetchLocation()
  }
  if (!coords.value) {
    uni.showToast({ title: '无法获取定位，请检查权限', icon: 'none' })
    return
  }
  await request({
    url: '/attendance/checkin',
    method: 'POST',
    data: {
      status: isWorking.value ? 'checkout' : 'checkin',
      lat: coords.value && coords.value.lat,
      lng: coords.value && coords.value.lng,
      location_text: coords.value && coords.value.location_text ? coords.value.location_text : location.value
    }
  })
  const nowDisplay = formatDateTime(new Date())
  if (isWorking.value) {
    lastCheckOut.value = nowDisplay
  } else {
    lastCheckIn.value = nowDisplay
  }
  uni.showToast({ title: isWorking.value ? '下班打卡成功' : '上班打卡成功', icon: 'success' })
  isWorking.value = !isWorking.value
  fetchData()
}

const navStats = () => {
  uni.navigateTo({ url: '/pages/attendance/stats' })
}

const userName = computed(() => (store.state.profile && store.state.profile.name) || '张三')
const deptName = computed(() => {
  if (store.state.profile && store.state.profile.dept) return store.state.profile.dept.name
  return '销售部'
})
const avatar = computed(() => (store.state.profile && store.state.profile.avatar_url) || '/static/icons/avatar.png')

let timer = null
const startClock = () => {
  clearInterval(timer)
  timer = setInterval(() => {
    const now = new Date()
    const hh = String(now.getHours()).padStart(2, '0')
    const mm = String(now.getMinutes()).padStart(2, '0')
    const ss = String(now.getSeconds()).padStart(2, '0')
    currentTime.value = `${hh}:${mm}:${ss}`
  }, 1000)
}

onShow(() => {
  fetchData()
  fetchLocation()
  startClock()
})

onHide(() => {
  clearInterval(timer)
})

onUnload(() => {
  clearInterval(timer)
})
</script>

<style scoped lang="scss">
.page {
  padding: 32rpx;
  background: #f6f7fb;
}
.top-card {
  background: #fff;
  border-radius: 24rpx;
  padding: 24rpx;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.user {
  display: flex;
  gap: 16rpx;
  align-items: center;
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
.small-btn {
  border: 1rpx solid #1677ff;
  background: #fff;
  color: #1677ff;
  border-radius: 24rpx;
  padding: 0 24rpx;
}
.status-row {
  display: flex;
  gap: 16rpx;
  margin: 24rpx 0;
}
.status-card {
  flex: 1;
  background: #fff;
  border-radius: 20rpx;
  padding: 20rpx;
  font-size: 26rpx;
}
.status-text {
  font-size: 30rpx;
  color: #52c41a;
  margin-top: 8rpx;
}
.clock-card {
  background: #fff;
  border-radius: 24rpx;
  padding: 24rpx;
  text-align: center;
  margin-bottom: 24rpx;
}
.clock {
  width: 360rpx;
  height: 360rpx;
  border-radius: 180rpx;
  margin: 0 auto;
  background: #1677ff;
  color: #fff;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  gap: 12rpx;
}
.clock .time {
  font-size: 40rpx;
  font-weight: 600;
}
.location {
  margin-top: 24rpx;
  color: #666;
}
.company {
  font-size: 24rpx;
  color: #999;
  margin-top: 4rpx;
}
.worktime {
  margin-top: 16rpx;
  display: flex;
  justify-content: space-between;
  color: #666;
}
.records-card {
  background: #fff;
  border-radius: 24rpx;
  padding: 24rpx;
  margin-bottom: 24rpx;
}
.title {
  font-size: 30rpx;
  font-weight: 600;
  margin-bottom: 16rpx;
}
.rule {
  display: flex;
  justify-content: space-between;
  padding: 12rpx 0;
  font-size: 26rpx;
  border-bottom: 1rpx solid #f5f5f5;
}
.rule:last-child {
  border-bottom: none;
}
.record-item {
  padding: 16rpx 0;
  border-bottom: 1rpx solid #f5f5f5;
}
.record-item:last-child {
  border-bottom: none;
}
.record-row {
  display: flex;
  justify-content: space-between;
  font-size: 26rpx;
  color: #333;
}
.record-status {
  color: #1677ff;
}
.record-location {
  margin-top: 8rpx;
  font-size: 24rpx;
  color: #999;
  text-align: left;
  word-break: break-all;
}
</style>
