<template>
  <scroll-view scroll-y class="page" v-if="profile">
    <view class="profile-card">
      <view class="left">
        <image
          class="avatar"
          :class="{ uploading: updatingAvatar }"
          :src="profile.avatar_url || defaultAvatar"
          mode="aspectFill"
          @click="changeAvatar"
        />
        <view>
          <view class="name">{{ profile.name }}</view>
          <view class="dept">{{ deptName }}</view>
          <view class="company">{{ profile.company || '安徽黑马重工机械科技有限公司' }}</view>
        </view>
      </view>
      <view class="icons">
        <view class="icon-btn" v-for="action in quickActions" :key="action.label" @click="nav(action.path)">
          <image class="icon-img" :src="action.icon" mode="aspectFit" />
          <text class="icon-label">{{ action.label }}</text>
        </view>
      </view>
    </view>

    <view class="attendance-card">
      <view class="status">
        <view class="label">考勤打卡</view>
        <view class="result">{{ attendanceSummary.todayStatus }}</view>
      </view>
      <view class="actions">
        <button class="primary" size="mini" @click="nav('/pages/attendance/index')">上班打卡</button>
        <button class="secondary" size="mini" @click="nav('/pages/attendance/index')">下班打卡</button>
      </view>
      <view class="check-times">
        <text>上班 {{ attendanceSummary.checkInTime || '未打卡' }}</text>
        <text>下班 {{ attendanceSummary.checkOutTime || '未打卡' }}</text>
      </view>
      <view class="meta">
        <text>{{ attendanceSummary.monthText }}</text>
        <text @click="nav('/pages/attendance/stats')">查看详情</text>
      </view>
    </view>

    <view class="grid-card">
      <view class="grid-item" v-for="item in shortcuts" :key="item.title" @click="nav(item.path)">
        <image class="grid-icon" :src="item.icon" mode="aspectFit" />
        <view class="grid-title">{{ item.title }}</view>
      </view>
    </view>

    <view class="settings-card">
      <view class="setting" v-for="setting in settings" :key="setting.label">
        <view>
          <view class="label">{{ setting.label }}</view>
          <view class="desc">{{ setting.desc }}</view>
        </view>
        <switch :checked="setting.enabled" color="#1677ff" />
      </view>
    </view>

    <button class="logout" @click="logout">退出登录</button>
  </scroll-view>
</template>

<script setup>
import { ref, computed } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import store from '../../store'
import { api, uploadFile, request } from '../../utils/request'
import { stopMessagePolling } from '../../utils/message-center'

const profile = ref(null)
const defaultAvatar = '/static/icons/avatar.png'
const iconsBase = '/static/icons'
const updatingAvatar = ref(false)
const attendanceSummary = ref({
  todayStatus: '加载中...',
  monthText: '本月考勤 --/--天',
  checkInTime: '',
  checkOutTime: ''
})

const quickActions = [
  { label: '考勤', path: '/pages/attendance/index', icon: `${iconsBase}/attendance.png` },
  { label: '资料', path: '/pages/mine/profile', icon: `${iconsBase}/settings.png` }
]

const shortcuts = [
  { title: '文件归档', path: '/pages/order/files/index', icon: `${iconsBase}/archive.png` },
  { title: '报销记录', path: '/pages/finance/reimburse-list', icon: `${iconsBase}/reimburse.png` },
  { title: '请假申请', path: '/pages/leave/index', icon: `${iconsBase}/attendance.png` },
  { title: '设置', path: '/pages/mine/profile', icon: `${iconsBase}/settings.png` }
]

const settings = [
  { label: 'WiFi打卡', desc: '进入指定WiFi自动打卡', enabled: true },
  { label: '定位打卡', desc: '开启定位打卡防止串岗', enabled: false },
  { label: '公众号推荐', desc: '关注公众号获取最新通知', enabled: true }
]

const deptName = computed(() => {
  if (!profile.value || !profile.value.dept) {
    return '未分配部门'
  }
  return profile.value.dept.name || '未分配部门'
})

const fetchProfile = async () => {
  const res = await api.profile()
  profile.value = res.profile
  store.setProfile(res.profile)
}

const pad = (num) => String(num).padStart(2, '0')
const todayKey = () => {
  const now = new Date()
  return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`
}
const monthKey = () => {
  const now = new Date()
  return `${now.getFullYear()}-${pad(now.getMonth() + 1)}`
}

const isCheckInRecord = (record) => {
  const indicator = (record.check_type || record.type || '').toLowerCase()
  return indicator === 'check_in' || indicator === 'checkin'
}

const isCheckOutRecord = (record) => {
  const indicator = (record.check_type || record.type || '').toLowerCase()
  return indicator === 'check_out' || indicator === 'checkout'
}

const formatCheckTime = (val) => {
  if (!val || typeof val !== 'string') return ''
  const parts = val.split(' ')
  if (parts.length < 2) return ''
  return parts[1].slice(0, 5)
}

const updateAttendanceSummary = (records = []) => {
  const today = todayKey()
  const month = monthKey()
  const monthDays = new Set()
  let checkInRecord = null
  let checkOutRecord = null
  records.forEach((record) => {
    const checkedAt = record.checked_at || ''
    if (!checkedAt) return
    const dayKey = checkedAt.slice(0, 10)
    const monthPart = checkedAt.slice(0, 7)
    if (dayKey === today) {
      if (!checkInRecord && isCheckInRecord(record)) {
        checkInRecord = record
      } else if (!checkOutRecord && isCheckOutRecord(record)) {
        checkOutRecord = record
      }
    }
    if (monthPart === month) {
      monthDays.add(dayKey)
    }
  })
  let todayStatus = '今日未打卡'
  if (checkInRecord && checkOutRecord) {
    todayStatus = '今日已打卡'
  } else if (checkInRecord) {
    todayStatus = '已上班，待下班'
  }
  const now = new Date()
  const totalDays = new Date(now.getFullYear(), now.getMonth() + 1, 0).getDate()
  attendanceSummary.value = {
    todayStatus,
    monthText: `本月考勤 ${monthDays.size}/${totalDays}天`,
    checkInTime: checkInRecord ? formatCheckTime(checkInRecord.checked_at) : '',
    checkOutTime: checkOutRecord ? formatCheckTime(checkOutRecord.checked_at) : ''
  }
}

const fetchAttendanceSummary = async () => {
  try {
    const res = await request({ url: '/attendance/records' })
    updateAttendanceSummary(res.items || [])
  } catch (error) {
    console.error('fetch attendance summary failed', error)
    attendanceSummary.value = {
      todayStatus: '数据获取失败',
      monthText: '本月考勤 --/--天',
      checkInTime: '',
      checkOutTime: ''
    }
  }
}

const changeAvatar = () => {
  if (updatingAvatar.value) return
  uni.chooseImage({
    count: 1,
    sizeType: ['compressed'],
    sourceType: ['album', 'camera'],
    async success(res) {
      const [filePath] = res.tempFilePaths || []
      if (!filePath) return
      try {
        updatingAvatar.value = true
        uni.showLoading({ title: '上传中', mask: true })
        const uploadRes = await uploadFile(filePath)
        if (!uploadRes || !uploadRes.url) {
          throw new Error('upload failed')
        }
        const result = await api.updateProfile({ avatar_url: uploadRes.url })
        if (result && result.profile) {
          profile.value = result.profile
          store.setProfile(result.profile)
        }
        uni.showToast({ title: '头像已更新', icon: 'success' })
      } catch (error) {
        console.warn('change avatar failed', error)
        uni.showToast({ title: '头像更新失败', icon: 'none' })
      } finally {
        updatingAvatar.value = false
        uni.hideLoading()
      }
    },
    fail: (error) => {
      console.warn('choose avatar cancelled', error)
    }
  })
}

const nav = (url) => {
  uni.navigateTo({ url })
}

const logout = async () => {
  try {
    await api.logout()
  } catch (error) {
    console.warn('logout failed', error)
  } finally {
    store.clearToken()
    store.setProfile(null)
    profile.value = null
    stopMessagePolling()
    uni.reLaunch({ url: '/pages/auth/login' })
  }
}

onShow(() => {
  if (store.state.token) {
    fetchProfile()
    fetchAttendanceSummary()
  }
})
</script>

<style scoped lang="scss">
.page {
  padding: 32rpx;
  background: #f6f7fb;
}
.profile-card {
  background: #fff;
  border-radius: 24rpx;
  padding: 24rpx;
  display: flex;
  justify-content: space-between;
  gap: 16rpx;
}
.left {
  display: flex;
  gap: 16rpx;
}
.avatar {
  width: 96rpx;
  height: 96rpx;
  border-radius: 48rpx;
}
.avatar.uploading {
  opacity: 0.7;
}
.name {
  font-size: 32rpx;
  font-weight: 600;
}
.dept {
  color: #999;
  margin-top: 4rpx;
}
.company {
  font-size: 24rpx;
  color: #666;
  margin-top: 4rpx;
}
.icons {
  display: flex;
  gap: 16rpx;
  align-items: flex-start;
}
.icon-btn {
  width: 110rpx;
  height: 110rpx;
  border-radius: 24rpx;
  background: #f0f5ff;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: #1677ff;
  font-size: 24rpx;
}
.icon-img {
  width: 44rpx;
  height: 44rpx;
  margin-bottom: 8rpx;
}
.icon-label {
  line-height: 1;
}
.attendance-card,
.module-card,
.grid-card,
.settings-card {
  background: #fff;
  border-radius: 24rpx;
  padding: 24rpx;
  margin-top: 24rpx;
}
.status {
  display: flex;
  justify-content: space-between;
  color: #333;
}
.result {
  color: #52c41a;
}
.actions {
  margin: 16rpx 0;
  display: flex;
  gap: 16rpx;
}
.check-times {
  display: flex;
  justify-content: space-between;
  color: #666;
  font-size: 24rpx;
  margin-bottom: 8rpx;
}
.primary {
  flex: 1;
  background: #1677ff;
  color: #fff;
  border-radius: 32rpx;
}
.secondary {
  flex: 1;
  background: #f0f2ff;
  color: #666;
  border-radius: 32rpx;
}
.meta {
  display: flex;
  justify-content: space-between;
  color: #999;
  font-size: 24rpx;
}
.module-title {
  font-size: 30rpx;
  font-weight: 600;
}
.module-desc {
  color: #999;
  margin-top: 8rpx;
}
.grid-card {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 24rpx;
}
.grid-item {
  background: #f6f7fb;
  border-radius: 16rpx;
  padding: 20rpx;
  text-align: center;
}
.grid-icon {
  width: 56rpx;
  height: 56rpx;
  margin-bottom: 12rpx;
}
.grid-title {
  font-size: 26rpx;
}
.setting {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20rpx 0;
  border-bottom: 1rpx solid #f0f0f0;
}
.setting:last-child {
  border-bottom: none;
}
.desc {
  font-size: 24rpx;
  color: #999;
}
.logout {
  margin-top: 32rpx;
  background: #fff1f0;
  color: #ff4d4f;
  border: none;
  border-radius: 32rpx;
}
</style>
