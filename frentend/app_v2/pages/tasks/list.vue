<template>
  <scroll-view scroll-y class="page">
    <view class="intro-card">
      <view class="intro-title">{{ pageTitle }}</view>
      <view class="intro-desc">{{ pageDesc }}</view>
    </view>

    <view class="search-bar">
      <input
        v-model="keyword"
        class="search-input"
        placeholder="请输入关键词搜索"
        confirm-type="search"
        @confirm="fetchTasks"
      />
      <button class="search-btn" size="mini" @click="fetchTasks">搜索</button>
    </view>

    <view class="task-list">
      <view v-for="task in formattedTasks" :key="task.id" class="task-card">
        <view class="task-head">
          <view>
            <view class="task-title">{{ task.title }}</view>
            <view class="task-type">{{ task.type }}</view>
          </view>
          <view class="task-status" :class="task.status">{{ task.statusLabel }}</view>
        </view>
        <view class="task-body">
          <view class="task-desc">{{ task.desc }}</view>
          <view class="meta">
            <text>执行人：{{ task.owner || '待分配' }}</text>
            <text>截止：{{ task.deadline || '待定' }}</text>
          </view>
          <view class="meta secondary" v-if="task.orderPi">
            <text>订单：{{ task.orderPi }}</text>
            <text>{{ task.customer || '' }}</text>
          </view>
        </view>
        <view class="task-actions">
          <button class="outline" size="mini" @click="openTask(task)">详情</button>
          <button class="outline" size="mini" @click="openOrder(task)">订单详情</button>
          <button class="primary" size="mini" @click="handlePrimary(task)">{{ primaryLabel }}</button>
        </view>
      </view>
      <view v-if="!formattedTasks.length && !loading" class="empty">暂无任务</view>
      <view v-if="loading" class="loading">加载中...</view>
    </view>
  </scroll-view>
</template>

<script setup>
import { computed, ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { api } from '../../utils/request'

const mode = ref('order')
const orderId = ref('')
const orderPi = ref('')
const keyword = ref('')
const tasks = ref([])
const loading = ref(false)

const statusMap = {
  pending: '待处理',
  in_progress: '进行中',
  waiting_audit: '待审核',
  completed: '已完成',
  rejected: '已驳回'
}

const pageTitle = computed(() => {
  if (mode.value === 'factory') return '工厂订单任务'
  if (mode.value === 'temporary') return '临时任务'
  if (mode.value === 'review') return '审核任务'
  return orderPi.value ? `订单 ${orderPi.value}` : '订单任务'
})

const pageDesc = computed(() => {
  if (mode.value === 'factory') return '与你相关的工厂订单任务'
  if (mode.value === 'temporary') return '分配给你的临时任务'
  if (mode.value === 'review') return '待审核的任务列表'
  return orderPi.value ? `该订单下的任务（${orderPi.value}）` : '与你相关的订单任务'
})

const primaryLabel = computed(() => (mode.value === 'review' ? '审核' : '处理'))

const formattedTasks = computed(() =>
  tasks.value.map((item) => ({
    id: item.id,
    orderId: item.order_id,
    title: item.title || item.type_label || '任务',
    type: item.type_label || item.type || '任务类型',
    status: item.status || 'pending',
    statusLabel: item.status_label || statusMap[item.status] || '处理中',
    owner: item.assignee_name || item.creator_name || '待分配',
    deadline: item.due_at || item.deadline,
    desc: item.description || item.requirement || '请按要求执行',
    orderPi: item.pi_number || item.order_pi || '',
    customer: item.customer_name || ''
  }))
)

const buildParams = () => {
  const params = {}
  if (keyword.value) {
    params.keyword = keyword.value
  }
  if (mode.value === 'review') {
    params.scope = 'review'
    params.status = 'waiting_audit'
  } else {
    params.scope = 'assigned'
  }

  if (mode.value === 'order') {
    params.category = 'order'
    if (orderId.value) {
      params.order_id = orderId.value
    }
  }
  if (mode.value === 'factory') {
    params.type = 'factory_order'
  }
  if (mode.value === 'temporary') {
    params.type = 'temporary'
  }
  return params
}

const fetchTasks = async () => {
  loading.value = true
  try {
    const res = await api.taskList(buildParams())
    tasks.value = res.items || []
  } catch (error) {
    console.error(error)
  } finally {
    loading.value = false
  }
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

const handlePrimary = (task) => {
  openTask(task)
}

const setupPage = () => {
  uni.setNavigationBarTitle({ title: pageTitle.value })
}

onLoad((query) => {
  mode.value = query.mode || 'order'
  orderId.value = query.orderId || ''
  orderPi.value = query.pi ? decodeURIComponent(query.pi) : ''
  setupPage()
  fetchTasks()
})
</script>

<style scoped lang="scss">
.page {
  padding: 32rpx;
  box-sizing: border-box;
  background: #f5f7fb;
  min-height: 100vh;
}
.intro-card {
  background: #fff;
  border-radius: 20rpx;
  padding: 22rpx;
  box-shadow: 0 10rpx 24rpx rgba(0, 0, 0, 0.04);
}
.intro-title {
  font-size: 30rpx;
  font-weight: 700;
  color: #1f1f1f;
}
.intro-desc {
  margin-top: 6rpx;
  color: #7b7b7b;
  font-size: 24rpx;
}
.search-bar {
  margin-top: 20rpx;
  display: flex;
  gap: 12rpx;
}
.search-input {
  flex: 1;
  background: #fff;
  border-radius: 16rpx;
  padding: 18rpx 24rpx;
}
.search-btn {
  background: #1677ff;
  color: #fff;
  border-radius: 16rpx;
  border: none;
  padding: 0 30rpx;
}
.task-list {
  margin-top: 20rpx;
}
.task-card {
  background: #fff;
  border-radius: 24rpx;
  padding: 24rpx;
  margin-bottom: 18rpx;
  box-shadow: 0 10rpx 24rpx rgba(0, 0, 0, 0.04);
}
.task-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.task-title {
  font-size: 30rpx;
  font-weight: 600;
}
.task-type {
  font-size: 24rpx;
  color: #999;
  margin-top: 6rpx;
}
.task-status {
  padding: 6rpx 20rpx;
  border-radius: 20rpx;
  font-size: 22rpx;
  background: #f0f5ff;
  color: #1677ff;
}
.task-status.completed {
  background: #f6ffed;
  color: #52c41a;
}
.task-status.waiting_audit {
  background: #fff7e6;
  color: #fa8c16;
}
.task-body {
  margin-top: 12rpx;
}
.task-desc {
  font-size: 26rpx;
  color: #666;
}
.meta {
  margin-top: 12rpx;
  font-size: 24rpx;
  color: #8c8c8c;
  display: flex;
  justify-content: space-between;
}
.meta.secondary {
  font-size: 22rpx;
  color: #bfbfbf;
}
.task-actions {
  display: flex;
  justify-content: flex-end;
  gap: 16rpx;
  margin-top: 20rpx;
}
.outline {
  border: 1rpx solid #d6e4ff;
  color: #1677ff;
  background: #fff;
  border-radius: 28rpx;
  padding: 0 24rpx;
}
.primary {
  background: #1677ff;
  color: #fff;
  border: none;
  border-radius: 28rpx;
  padding: 0 32rpx;
}
.empty {
  text-align: center;
  color: #999;
  padding: 40rpx 0;
}
.loading {
  text-align: center;
  color: #999;
  padding: 24rpx 0;
}
</style>
