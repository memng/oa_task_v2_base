<template>
  <scroll-view scroll-y class="page">
    <view class="profile-card">
      <view class="profile">
        <image class="avatar" :src="avatar" mode="aspectFill"></image>
        <view>
          <view class="hello">你好，{{ greetName }}</view>
          <view class="company">{{ companyName }}</view>
        </view>
      </view>
      <view class="profile-actions">
        <button class="icon-btn" size="mini" @click="nav('/pages/messages/index')">
          消息
          <text v-if="unreadCount" class="badge">{{ unreadCount }}</text>
        </button>
        <button class="icon-btn primary" size="mini" @click="goCreateOrder">新建</button>
      </view>
    </view>

    <view class="search-bar">
      <view class="search-input">
        <input
          v-model="searchKeyword"
          placeholder="请输入关键词搜索"
          confirm-type="search"
          @confirm="handleSearch"
        />
      </view>
      <button class="search-btn" size="mini" @click="handleSearch">搜索</button>
    </view>

    <view class="summary-card">
      <view class="summary-item" v-for="item in summaryStats" :key="item.label">
        <view class="summary-label">{{ item.label }}</view>
        <view class="summary-value">{{ item.value }}</view>
      </view>
    </view>

    <view class="menu-card">
      <view class="menu-grid">
        <view class="menu-item" v-for="entry in quickEntries" :key="entry.title" @click="nav(entry.path)">
          <view class="menu-icon" :style="{ backgroundColor: entry.bg }">
            <image class="menu-icon-image" :src="entry.icon" mode="aspectFit" />
          </view>
          <view class="menu-title">{{ entry.title }}</view>
          <view class="menu-desc">{{ entry.desc }}</view>
        </view>
      </view>
    </view>

    <view class="section-card">
      <view class="section-header">
        <view class="section-title">待办事项 ({{ displayTasks.length }})</view>
        <text class="link" @click="goTaskCenter">查看全部</text>
      </view>
      <view v-if="displayTasks.length" class="task-list">
        <view class="task-item" v-for="task in displayTasks" :key="task.id">
          <view class="task-top">
            <view class="task-type">{{ task.type }}</view>
            <view class="task-status" :class="task.status">{{ task.statusLabel }}</view>
          </view>
          <view class="task-title">{{ task.title }}</view>
          <view class="task-desc">{{ task.desc }}</view>
          <view class="task-meta">
            <text>PI：{{ task.piNo || '未填写' }}</text>
            <text>截止：{{ task.deadline || '待定' }}</text>
          </view>
          <view class="task-actions">
            <button class="outline" size="mini" @click="openTask(task)">详情</button>
            <button
              v-if="task.orderId"
              class="outline"
              size="mini"
              @click="openOrder(task)"
            >
              订单详情
            </button>
            <button class="primary" size="mini" @click="openTask(task)">处理</button>
          </view>
        </view>
      </view>
      <view v-else class="empty">暂无待办事项</view>
    </view>

    <view class="section-card">
      <view class="section-header">
        <view class="section-title">意向订单 ({{ intentOrders.length }})</view>
        <text class="link" @click="nav('/pages/intent-order/list')">查看全部</text>
      </view>
      <view v-if="intentOrders.length" class="intent-list">
        <view class="intent-item" v-for="order in intentOrders" :key="order.id">
          <view class="intent-left">
            <view class="intent-name">{{ order.product_name || order.title }}</view>
            <view class="intent-meta">
              <text>{{ order.model || '型号待定' }}</text>
              <text>{{ order.quantity ? order.quantity + '台' : '数量待定' }}</text>
            </view>
          </view>
          <view class="intent-right">
            <view class="intent-status" :class="order.status || 'todo'">
              {{ statusLabel(order.status) }}
            </view>
            <view class="intent-action" @click="nav('/pages/intent-order/list')">查看</view>
          </view>
        </view>
      </view>
      <view v-else class="empty">暂无意向订单</view>
    </view>
  </scroll-view>
</template>

<script setup>
import { ref, computed } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import store from '../../store'
import { api } from '../../utils/request'

const summary = ref({})
const pendingTasks = ref([])
const searchKeyword = ref('')
const intentSummary = ref({ pending: 0, done: 0, lost: 0 })
const intentHomeList = ref([])
const defaultAvatar = '/static/icons/avatar.png'

const iconsBase = '/static/icons'
const quickEntries = [
  { title: '订单任务', desc: '跟进执行', path: '/pages/order/list', icon: `${iconsBase}/order-task.png`, bg: '#e8f3ff' },
  { title: '客户验厂', desc: '现场反馈', path: '/pages/tasks/customer-inspection', icon: `${iconsBase}/customer-inspection.png`, bg: '#fff3e6' },
  { title: '临时任务', desc: '随手记录', path: '/pages/temp-task/create', icon: `${iconsBase}/temp-task.png`, bg: '#fef3f2' },
  { title: '工厂看板', desc: '实时生产', path: '/pages/workbench/factory', icon: `${iconsBase}/factory-board.png`, bg: '#f1faff' },
  { title: '意向订单', desc: '赢单跟进', path: '/pages/intent-order/list', icon: `${iconsBase}/intent-order.png`, bg: '#f3f5ff' },
  { title: '打卡勤助', desc: '考勤统计', path: '/pages/attendance/index', icon: `${iconsBase}/attendance.png`, bg: '#fffbee' }
]

const profile = computed(() => store.state.profile || {})
const isAdminDept = computed(() => {
  const type = profile.value?.dept?.type
  return type === 'operation' || type === 'finance'
})

const fetchData = async () => {
  try {
    summary.value = await api.summary()
    const [assignedRes, reviewRes, intentRes] = await Promise.all([
      api.taskList({ scope: 'assigned' }),
      isAdminDept.value ? api.taskList({ scope: 'review', status: 'waiting_audit' }) : Promise.resolve({ items: [] }),
      api.intentOrders({ limit: 3 })
    ])
    const isActive = (task) => !['completed', 'cancelled'].includes(task.status)
    const mergedTasks = new Map()
    ;(assignedRes.items || []).filter(isActive).forEach((task) => {
      mergedTasks.set(task.id, task)
    })
    ;(reviewRes.items || []).filter(isActive).forEach((task) => {
      if (!mergedTasks.has(task.id)) {
        mergedTasks.set(task.id, task)
      }
    })
    const mergedList = Array.from(mergedTasks.values())
    pendingTasks.value = mergedList.slice(0, 5)
    store.setPendingTasks(mergedList)
    intentHomeList.value = intentRes.items || []
    const remoteIntentSummary = intentRes.summary || {}
    intentSummary.value = {
      pending: remoteIntentSummary.pending || 0,
      done: remoteIntentSummary.done || 0,
      lost: remoteIntentSummary.lost || 0
    }
  } catch (e) {
    console.error(e)
  }
}

onShow(() => {
  fetchData()
})

const greetName = computed(() => profile.value.name || '欢迎')
const companyName = computed(() => {
  if (profile.value.company) return profile.value.company
  if (profile.value.dept && profile.value.dept.name) return profile.value.dept.name
  return '请完善公司信息'
})
const avatar = computed(() => profile.value.avatar_url || defaultAvatar)
const unreadCount = computed(() => store.state.notifications || 0)

const summaryBlock = computed(() => summary.value || {})
const summaryOrders = computed(() => summaryBlock.value.orders || {})
const summaryTasks = computed(() => summaryBlock.value.tasks || {})
const intentTotalCount = computed(
  () => (intentSummary.value.pending || 0) + (intentSummary.value.done || 0) + (intentSummary.value.lost || 0)
)

const summaryStats = computed(() => [
  { label: '订单进行中', value: summaryOrders.value.in_progress || 0 },
  { label: '待审核任务', value: summaryTasks.value.waiting_audit || 0 },
  { label: '意向订单', value: intentTotalCount.value || 0 }
])

const intentOrders = computed(() => intentHomeList.value)

const statusMap = {
  in_progress: '在办',
  waiting_audit: '待审核',
  pending: '待跟进',
  done: '已完成',
  completed: '已完成',
  closed: '已关闭'
}

const displayTasks = computed(() =>
  pendingTasks.value.map((task) => ({
    id: task.id,
    title: task.title || task.type_label || '任务',
    type: task.type_label || task.type || '任务类型',
    status: task.status || 'in_progress',
    statusLabel: task.status_label || statusMap[task.status] || '在办',
    desc: task.requirement || task.description || '请尽快处理该任务',
    piNo: task.pi_no || task.order_pi || task.pi_number,
    deadline: task.due_date || task.deadline || task.due_at,
    orderId: task.order_id || task.orderId
  }))
)

const statusLabel = (status) => {
  if (!status) return '待跟进'
  return statusMap[status] || status
}

const tabPages = ['/pages/home/index', '/pages/tasks/index', '/pages/messages/index', '/pages/mine/index']

const nav = (url) => {
  if (tabPages.includes(url)) {
    uni.switchTab({ url })
    return
  }
  uni.navigateTo({ url })
}

const goTaskCenter = () => {
  nav('/pages/tasks/index')
}

const openTask = (task) => {
  uni.navigateTo({ url: `/pages/tasks/detail?id=${task.id}` })
}

const openOrder = (task) => {
  if (!task.orderId) {
    uni.showToast({ title: '暂无订单信息', icon: 'none' })
    return
  }
  uni.navigateTo({ url: `/pages/order/detail?id=${task.orderId}` })
}

const goCreateOrder = () => {
  uni.navigateTo({ url: '/pages/order/create' })
}

const handleSearch = () => {
  if (!searchKeyword.value) {
    uni.showToast({ title: '请输入关键词', icon: 'none' })
    return
  }
  uni.navigateTo({ url: `/pages/order/list?keyword=${encodeURIComponent(searchKeyword.value)}` })
}
</script>

<style scoped lang="scss">
.page {
  padding: 32rpx 32rpx 80rpx;
  background: #f5f6fb;
}
.profile-card {
  background: #fff;
  border-radius: 24rpx;
  padding: 24rpx;
  display: flex;
  justify-content: space-between;
  align-items: center;
  box-shadow: 0 12rpx 32rpx rgba(0, 0, 0, 0.04);
}
.profile {
  display: flex;
  align-items: center;
  gap: 20rpx;
}
.avatar {
  width: 96rpx;
  height: 96rpx;
  border-radius: 48rpx;
  background: #f0f0f0;
}
.hello {
  font-size: 36rpx;
  font-weight: 600;
}
.company {
  color: #999;
  font-size: 24rpx;
}
.profile-actions {
  display: flex;
  gap: 16rpx;
}
.icon-btn {
  border: none;
  background: #f5f7ff;
  color: #1677ff;
  padding: 0 28rpx;
  border-radius: 44rpx;
  font-size: 26rpx;
  position: relative;
}
.icon-btn.primary {
  background: #1677ff;
  color: #fff;
}
.badge {
  position: absolute;
  top: -12rpx;
  right: -8rpx;
  background: #ff4d4f;
  color: #fff;
  font-size: 20rpx;
  border-radius: 20rpx;
  padding: 4rpx 10rpx;
}
.search-bar {
  margin-top: 32rpx;
  display: flex;
  gap: 16rpx;
}
.search-input {
  flex: 1;
  background: #fff;
  border-radius: 16rpx;
  padding: 12rpx 24rpx;
}
.search-input input {
  width: 100%;
  font-size: 28rpx;
}
.search-btn {
  background: #1677ff;
  color: #fff;
  border: none;
  padding: 0 32rpx;
  border-radius: 16rpx;
}
.summary-card {
  margin-top: 24rpx;
  background: linear-gradient(135deg, #eff4ff, #f6fbff);
  border-radius: 24rpx;
  padding: 32rpx;
  display: flex;
  justify-content: space-between;
}
.summary-item {
  flex: 1;
  text-align: center;
}
.summary-label {
  color: #8c8c8c;
  font-size: 24rpx;
}
.summary-value {
  font-size: 40rpx;
  font-weight: 700;
  margin-top: 12rpx;
}
.menu-card {
  background: #fff;
  border-radius: 24rpx;
  padding: 24rpx;
  margin-top: 24rpx;
}
.menu-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 20rpx;
}
.menu-item {
  padding: 24rpx;
  border-radius: 20rpx;
  background: #f7f8fa;
}
.menu-icon {
  width: 96rpx;
  height: 96rpx;
  border-radius: 24rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 16rpx;
}
.menu-icon-image {
  width: 60rpx;
  height: 60rpx;
  display: block;
}
.menu-title {
  font-size: 30rpx;
  font-weight: 600;
}
.menu-desc {
  font-size: 24rpx;
  color: #8c8c8c;
  margin-top: 4rpx;
}
.section-card {
  background: #fff;
  border-radius: 24rpx;
  padding: 24rpx;
  margin-top: 32rpx;
}
.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16rpx;
}
.section-title {
  font-size: 32rpx;
  font-weight: 600;
}
.link {
  color: #1677ff;
  font-size: 24rpx;
}
.task-item {
  border: 1rpx solid #f0f0f0;
  border-radius: 24rpx;
  padding: 24rpx;
  margin-bottom: 24rpx;
  background: #f9fbff;
}
.task-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12rpx;
}
.task-type {
  font-size: 26rpx;
  color: #666;
}
.task-status {
  padding: 4rpx 16rpx;
  border-radius: 24rpx;
  font-size: 22rpx;
  color: #1677ff;
  background: #eaf2ff;
}
.task-status.completed {
  color: #52c41a;
  background: #f6ffed;
}
.task-status.waiting_audit {
  color: #fa8c16;
  background: #fff7e6;
}
.task-title {
  font-size: 30rpx;
  font-weight: 600;
}
.task-desc {
  font-size: 24rpx;
  color: #8c8c8c;
  margin: 12rpx 0;
}
.task-meta {
  font-size: 24rpx;
  color: #8c8c8c;
  display: flex;
  justify-content: space-between;
}
.task-actions {
  margin-top: 16rpx;
  display: flex;
  justify-content: flex-end;
  gap: 16rpx;
}
.outline {
  border: 1rpx solid #d6e4ff;
  background: #fff;
  color: #1677ff;
  border-radius: 32rpx;
  padding: 0 24rpx;
}
.primary {
  background: #1677ff;
  color: #fff;
  border: none;
  border-radius: 32rpx;
  padding: 0 32rpx;
}
.intent-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 24rpx 0;
  border-bottom: 1rpx solid #f0f0f0;
}
.intent-item:last-child {
  border-bottom: none;
}
.intent-name {
  font-size: 30rpx;
  font-weight: 600;
}
.intent-meta {
  font-size: 24rpx;
  color: #8c8c8c;
  display: flex;
  gap: 20rpx;
  margin-top: 6rpx;
}
.intent-status {
  padding: 6rpx 20rpx;
  border-radius: 24rpx;
  font-size: 22rpx;
  text-align: center;
}
.intent-status.todo {
  background: #fff7e6;
  color: #fa8c16;
}
.intent-status.done,
.intent-status.completed {
  background: #f6ffed;
  color: #52c41a;
}
.intent-status.closed {
  background: #fff1f0;
  color: #ff4d4f;
}
.intent-action {
  color: #1677ff;
  font-size: 24rpx;
  margin-top: 8rpx;
}
.empty {
  text-align: center;
  color: #999;
  padding: 32rpx 0;
}
</style>
