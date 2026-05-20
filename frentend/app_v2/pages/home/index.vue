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
        <view class="section-title">待办事项 ({{ todoCounts.total }})</view>
        <text class="link" @click="goTaskCenter">查看全部</text>
      </view>
      <view class="todo-tabs">
        <view
          v-for="tab in todoTabs"
          :key="tab.type"
          class="todo-tab"
          :class="{ active: activeTodoTab === tab.type }"
          @click="activeTodoTab = tab.type"
        >
          {{ tab.label }}
          <text v-if="todoCounts[tab.type] > 0" class="tab-badge">{{ todoCounts[tab.type] }}</text>
        </view>
      </view>
      <view v-if="filteredTodos.length" class="todo-list">
        <view
          class="todo-item"
          v-for="item in filteredTodos"
          :key="item.id"
          @click="handleTodoClick(item)"
        >
          <view class="todo-icon" :class="item.type">
            <text>{{ getTodoIcon(item.type) }}</text>
          </view>
          <view class="todo-content">
            <view class="todo-top">
              <view class="todo-type">{{ item.type_label }}</view>
              <view class="todo-status" :class="getStatusClass(item.type, item.status)">{{ item.status_label }}</view>
            </view>
            <view class="todo-title">{{ item.title }}</view>
            <view class="todo-desc">{{ item.desc }}</view>
            <view class="todo-meta">
              <text>{{ formatTime(item.created_at) }}</text>
            </view>
          </view>
          <view class="todo-arrow">
            <text>›</text>
          </view>
        </view>
      </view>
      <view v-else class="empty">暂无{{ currentTabLabel }}待办</view>
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

const todoList = ref([])
const todoCounts = ref({ total: 0, task: 0, leave: 0, reimburse: 0, announcement: 0 })
const activeTodoTab = ref('all')
const todoTabs = [
  { type: 'all', label: '全部' },
  { type: 'task', label: '任务' },
  { type: 'leave', label: '请假' },
  { type: 'reimburse', label: '报销' },
  { type: 'announcement', label: '公告' }
]

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
    const [summaryRes, todoRes, assignedRes, reviewRes, intentRes] = await Promise.all([
      api.summary(),
      api.todos({ limit: 10 }),
      api.taskList({ scope: 'assigned' }),
      isAdminDept.value ? api.taskList({ scope: 'review', status: 'waiting_audit' }) : Promise.resolve({ items: [] }),
      api.intentOrders({ limit: 3 })
    ])
    summary.value = summaryRes
    todoList.value = todoRes.items || []
    todoCounts.value = todoRes.counts || { total: 0, task: 0, leave: 0, reimburse: 0, announcement: 0 }
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

const filteredTodos = computed(() => {
  if (activeTodoTab.value === 'all') {
    return todoList.value
  }
  return todoList.value.filter(item => item.type === activeTodoTab.value)
})

const currentTabLabel = computed(() => {
  const tab = todoTabs.find(t => t.type === activeTodoTab.value)
  return tab ? tab.label : ''
})

const getTodoIcon = (type) => {
  const icons = {
    task: '📋',
    leave: '🏖️',
    reimburse: '💰',
    announcement: '📢'
  }
  return icons[type] || '📌'
}

const getStatusClass = (type, status) => {
  if (type === 'announcement') {
    return 'unread'
  }
  if (status === 'waiting_audit' || status === 'pending') {
    return 'warning'
  }
  if (status === 'in_progress') {
    return 'info'
  }
  return ''
}

const formatTime = (timeStr) => {
  if (!timeStr) return ''
  const date = new Date(timeStr)
  const now = new Date()
  const diff = now - date
  const minutes = Math.floor(diff / 60000)
  const hours = Math.floor(diff / 3600000)
  const days = Math.floor(diff / 86400000)
  
  if (minutes < 1) return '刚刚'
  if (minutes < 60) return `${minutes}分钟前`
  if (hours < 24) return `${hours}小时前`
  if (days < 7) return `${days}天前`
  return timeStr.substring(5, 10)
}

const handleTodoClick = (item) => {
  switch (item.type) {
    case 'task':
      if (item.extra?.task_id) {
        uni.navigateTo({ url: `/pages/tasks/detail?id=${item.extra.task_id}` })
      }
      break
    case 'leave':
      if (item.extra?.leave_id) {
        uni.navigateTo({ url: `/pages/leave/detail?id=${item.extra.leave_id}` })
      } else {
        uni.navigateTo({ url: '/pages/leave/index' })
      }
      break
    case 'reimburse':
      if (item.extra?.reimburse_id) {
        uni.navigateTo({ url: `/pages/finance/reimburse-detail?id=${item.extra.reimburse_id}` })
      } else {
        uni.navigateTo({ url: '/pages/finance/reimburse' })
      }
      break
    case 'announcement':
      if (item.extra?.announcement_id) {
        uni.navigateTo({ url: `/pages/notice/detail?id=${item.extra.announcement_id}` })
      } else {
        uni.navigateTo({ url: '/pages/notice/list' })
      }
      break
    default:
      uni.showToast({ title: '暂不支持', icon: 'none' })
  }
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

.todo-tabs {
  display: flex;
  gap: 16rpx;
  margin-bottom: 24rpx;
  overflow-x: auto;
  padding-bottom: 8rpx;
}

.todo-tab {
  flex-shrink: 0;
  padding: 12rpx 28rpx;
  background: #f5f7fa;
  border-radius: 32rpx;
  font-size: 26rpx;
  color: #666;
  display: flex;
  align-items: center;
  gap: 8rpx;
  position: relative;
}

.todo-tab.active {
  background: #e6f0ff;
  color: #1677ff;
}

.tab-badge {
  background: #ff4d4f;
  color: #fff;
  font-size: 20rpx;
  padding: 2rpx 10rpx;
  border-radius: 20rpx;
  min-width: 32rpx;
  text-align: center;
}

.todo-list {
  display: flex;
  flex-direction: column;
  gap: 16rpx;
}

.todo-item {
  display: flex;
  align-items: flex-start;
  gap: 20rpx;
  padding: 24rpx;
  background: #fafbfc;
  border-radius: 20rpx;
  border: 1rpx solid #f0f0f0;
}

.todo-icon {
  width: 80rpx;
  height: 80rpx;
  border-radius: 20rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 36rpx;
  flex-shrink: 0;
}

.todo-icon.task {
  background: #e6f0ff;
}

.todo-icon.leave {
  background: #e6fffb;
}

.todo-icon.reimburse {
  background: #fff7e6;
}

.todo-icon.announcement {
  background: #f9f0ff;
}

.todo-content {
  flex: 1;
  min-width: 0;
}

.todo-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8rpx;
}

.todo-type {
  font-size: 24rpx;
  color: #8c8c8c;
}

.todo-status {
  padding: 4rpx 16rpx;
  border-radius: 24rpx;
  font-size: 22rpx;
  background: #eaf2ff;
  color: #1677ff;
}

.todo-status.warning {
  background: #fff7e6;
  color: #fa8c16;
}

.todo-status.info {
  background: #e6f0ff;
  color: #1677ff;
}

.todo-status.unread {
  background: #f9f0ff;
  color: #722ed1;
}

.todo-title {
  font-size: 28rpx;
  font-weight: 600;
  color: #262626;
  margin-bottom: 8rpx;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.todo-desc {
  font-size: 24rpx;
  color: #8c8c8c;
  margin-bottom: 8rpx;
  line-height: 1.4;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.todo-meta {
  font-size: 22rpx;
  color: #bfbfbf;
}

.todo-arrow {
  color: #bfbfbf;
  font-size: 32rpx;
  align-self: center;
}
</style>
