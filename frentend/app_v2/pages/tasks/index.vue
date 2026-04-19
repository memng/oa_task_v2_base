<template>
  <scroll-view scroll-y class="page">
    <view class="hero">
      <view>
        <view class="hello">你好，{{ profileName }}</view>
        <view class="desc">按订单或类型查看与你相关的任务</view>
      </view>
      <button class="icon-btn" size="mini" @click="nav('/pages/tasks/create')">发起任务</button>
    </view>

    <view class="section">
      <view class="section-header">
        <view class="section-title">订单任务</view>
        <view class="section-sub">共 {{ orderGroups.length }} 单</view>
      </view>
      <view v-if="orderGroups.length" class="order-list">
        <view v-for="order in orderGroups" :key="order.orderId" class="order-item" @click="goOrderTasks(order)">
          <view class="order-info">
            <view class="order-pi">PI：{{ order.pi }}</view>
            <view class="order-customer">{{ order.customer || '客户待定' }}</view>
          </view>
          <view class="order-right">
            <view class="order-count">({{ order.count }})</view>
          </view>
        </view>
      </view>
      <view v-else class="empty">暂无分配到你的订单任务</view>
    </view>

    <view class="section">
      <view class="section-header">
        <view class="section-title">任务入口</view>
        <view class="section-sub">仅展示与你相关的类型</view>
      </view>
      <view v-if="entryList.length" class="entry-grid">
        <view v-for="entry in entryList" :key="entry.key" class="entry-card" @click="goCategoryTasks(entry.key)">
          <view class="entry-top">
            <view class="entry-title">{{ entry.title }}</view>
            <view class="entry-count">{{ entry.count }} 个</view>
          </view>
          <view class="entry-desc">{{ entry.desc }}</view>
        </view>
      </view>
      <view v-else class="empty">暂无其他类型的任务</view>
    </view>

    <view v-if="loading" class="loading">数据加载中...</view>
  </scroll-view>
</template>

<script setup>
import { computed, ref } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import store from '../../store'
import { api } from '../../utils/request'

const loading = ref(false)
const assignedTasks = ref([])
const reviewTasks = ref([])

const profile = computed(() => store.state.profile || {})
const profileName = computed(() => profile.value.name || '同事')
const isAdminDept = computed(() => {
  const type = profile.value?.dept?.type
  return type === 'operation' || type === 'finance'
})

const orderGroups = computed(() => {
  if (!assignedTasks.value.length) return []
  const groups = new Map()
  assignedTasks.value.forEach((task) => {
    if (!task.order_id) return
    const key = task.order_id
    if (!groups.has(key)) {
      groups.set(key, {
        orderId: key,
        pi: task.pi_number || task.pi_no || task.pi || '未填写PI',
        customer: task.customer_name || task.customer || '',
        count: 0
      })
    }
    const target = groups.get(key)
    target.count += 1
  })
  return Array.from(groups.values())
})

const factoryTasks = computed(() =>
  assignedTasks.value.filter((task) => task.type === 'factory_order' || task.type_label === '工厂订单')
)
const temporaryTasks = computed(() =>
  assignedTasks.value.filter((task) => task.type === 'temporary' || task.type_label === '临时任务')
)
const pendingReviewTasks = computed(() =>
  (reviewTasks.value || []).filter((task) => task.status === 'waiting_audit' || task.status === 'pending')
)

const entryList = computed(() => {
  const entries = []
  if (factoryTasks.value.length) {
    entries.push({
      key: 'factory',
      title: '工厂订单任务',
      count: factoryTasks.value.length,
      desc: '与你相关的工厂订单任务'
    })
  }
  if (temporaryTasks.value.length) {
    entries.push({
      key: 'temporary',
      title: '临时任务',
      count: temporaryTasks.value.length,
      desc: '分配给你的临时任务'
    })
  }
  if (isAdminDept.value && pendingReviewTasks.value.length) {
    entries.push({
      key: 'review',
      title: '审核任务',
      count: pendingReviewTasks.value.length,
      desc: '待审核的任务列表'
    })
  }
  return entries
})

const fetchData = async () => {
  loading.value = true
  try {
    const [assignedRes, reviewRes] = await Promise.all([
      api.taskList({ scope: 'assigned' }),
      isAdminDept.value ? api.taskList({ scope: 'review', status: 'waiting_audit' }) : Promise.resolve({ items: [] })
    ])
    assignedTasks.value = assignedRes.items || []
    reviewTasks.value = reviewRes.items || []
  } catch (error) {
    console.error(error)
  } finally {
    loading.value = false
  }
}

const goOrderTasks = (order) => {
  uni.navigateTo({
    url: `/pages/tasks/list?mode=order&orderId=${order.orderId}&pi=${encodeURIComponent(order.pi)}`
  })
}

const goCategoryTasks = (mode) => {
  uni.navigateTo({
    url: `/pages/tasks/list?mode=${mode}`
  })
}

const nav = (url) => {
  uni.navigateTo({ url })
}

onShow(() => {
  fetchData()
})
</script>

<style scoped lang="scss">
.page {
  padding: 32rpx;
  background: #f5f7fb;
  min-height: 100vh;
  box-sizing: border-box;
}
.hero {
  background: linear-gradient(135deg, #1677ff, #65a6ff);
  color: #fff;
  border-radius: 24rpx;
  padding: 28rpx;
  display: flex;
  justify-content: space-between;
  align-items: center;
  box-shadow: 0 16rpx 36rpx rgba(22, 119, 255, 0.15);
}
.hello {
  font-size: 32rpx;
  font-weight: 700;
}
.desc {
  font-size: 24rpx;
  color: rgba(255, 255, 255, 0.85);
  margin-top: 6rpx;
}
.icon-btn {
  border: none;
  background: rgba(255, 255, 255, 0.18);
  color: #fff;
  border-radius: 32rpx;
  padding: 0 28rpx;
}
.section {
  margin-top: 24rpx;
  background: #fff;
  border-radius: 24rpx;
  padding: 24rpx;
  box-shadow: 0 10rpx 28rpx rgba(0, 0, 0, 0.04);
}
.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12rpx;
}
.section-title {
  font-size: 30rpx;
  font-weight: 700;
  color: #222;
}
.section-sub {
  font-size: 24rpx;
  color: #9a9a9a;
}
.order-list {
  display: flex;
  flex-direction: column;
  gap: 12rpx;
}
.order-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 18rpx 16rpx;
  border: 1rpx solid #f0f0f0;
  border-radius: 18rpx;
  background: #fafbff;
}
.order-item:active {
  background: #f3f7ff;
}
.order-info {
  display: flex;
  flex-direction: column;
  gap: 6rpx;
}
.order-pi {
  font-size: 28rpx;
  font-weight: 600;
  color: #1f1f1f;
}
.order-customer {
  font-size: 24rpx;
  color: #9a9a9a;
}
.order-right {
  display: flex;
  align-items: center;
  gap: 12rpx;
  color: #1677ff;
  font-size: 26rpx;
}
.order-count {
  font-weight: 700;
}
.entry-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 16rpx;
  margin-top: 8rpx;
}
.entry-card {
  padding: 20rpx;
  border-radius: 18rpx;
  background: linear-gradient(135deg, #f6faff, #f0f2ff);
  border: 1rpx solid #edf1ff;
}
.entry-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.entry-title {
  font-size: 28rpx;
  font-weight: 700;
  color: #1f1f1f;
}
.entry-count {
  font-size: 24rpx;
  color: #1677ff;
}
.entry-desc {
  margin-top: 8rpx;
  font-size: 24rpx;
  color: #6b6b6b;
}
.empty {
  text-align: center;
  color: #a6a6a6;
  padding: 40rpx 0 12rpx;
  font-size: 24rpx;
}
.loading {
  text-align: center;
  color: #999;
  padding: 24rpx 0 12rpx;
  font-size: 24rpx;
}
</style>
