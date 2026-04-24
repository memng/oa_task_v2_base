<template>
  <view class="page">
    <view class="filter">
      <input v-model="keyword" placeholder="搜索PI或客户" />
      <picker :range="statusOptions" range-key="label" @change="onStatusChange">
        <view class="status">{{ currentStatus.label }}</view>
      </picker>
    </view>
    <scroll-view scroll-y class="list">
      <order-card 
        v-for="item in orders" 
        :key="item.id" 
        :order="item" 
        :show-cancel-btn="canCancelOrder(item)"
        @click="openDetail(item)"
        @cancel="handleCancelOrder(item)"
      />
      <view v-if="!orders.length" class="empty">暂无订单</view>
    </scroll-view>
  </view>
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import { onLoad, onShow } from '@dcloudio/uni-app'
import { api } from '../../utils/request'
import OrderCard from '../../components/OrderCard.vue'
import store from '../../store'

const orders = ref([])
const keyword = ref('')
const statusOptions = [
  { label: '全部', value: '' },
  { label: '进行中', value: 'in_progress' },
  { label: '已完成', value: 'completed' },
  { label: '草稿', value: 'draft' },
  { label: '已取消', value: 'cancelled' }
]
const currentStatus = ref(statusOptions[0])

const currentUser = computed(() => store.state.profile || {})
const currentUserId = computed(() => currentUser.value?.id)
const isAdminDept = computed(() => {
  const deptType = currentUser.value?.dept?.type
  return deptType === 'operation' || deptType === 'finance'
})

const canCancelOrder = (order) => {
  if (!order) return false
  const isCreator = String(order.initiator_id) === String(currentUserId.value)
  if (order.status === 'draft') {
    return isCreator
  }
  if (order.status === 'in_progress') {
    return isCreator || isAdminDept.value
  }
  return false
}

const canEditDraft = (order) => {
  if (!order || order.status !== 'draft') return false
  return String(order.initiator_id) === String(currentUserId.value)
}

const fetchOrders = async () => {
  const res = await api.orderList({ keyword: keyword.value, status: currentStatus.value.value })
  const items = res.items || []
  // keep ids as string to avoid precision loss in large bigint ids
  orders.value = items.map((item) => ({
    ...item,
    id: item.id != null ? String(item.id) : ''
  }))
}

watch(keyword, fetchOrders)

const onStatusChange = (e) => {
  currentStatus.value = statusOptions[e.detail.value]
  fetchOrders()
}

const openDetail = (order) => {
  if (!order?.id) {
    uni.showToast({ title: '订单ID缺失', icon: 'none' })
    return
  }
  const orderId = encodeURIComponent(String(order.id))
  if (order.status === 'draft') {
    if (canEditDraft(order)) {
      uni.navigateTo({ url: `/pages/order/create?orderId=${orderId}&mode=edit` })
    } else {
      uni.showToast({ title: '只有订单创建人可以编辑草稿', icon: 'none' })
    }
  } else {
    uni.navigateTo({ url: `/pages/order/detail?id=${orderId}` })
  }
}

const handleCancelOrder = (order) => {
  uni.showModal({
    title: '确认取消',
    content: '确定要取消该订单吗？取消后该订单下的所有未完成任务将被取消。',
    confirmColor: '#ff4d4f',
    success: async (res) => {
      if (res.confirm) {
        try {
          await api.cancelOrder(order.id)
          uni.showToast({ title: '订单已取消', icon: 'success' })
          fetchOrders()
        } catch (error) {
          console.error('取消订单失败:', error)
        }
      }
    }
  })
}

onLoad((options) => {
  if (options && options.keyword) {
    keyword.value = decodeURIComponent(options.keyword)
  }
  fetchOrders()
})

onShow(() => {
  const needRefresh = uni.getStorageSync('ORDER_NEED_REFRESH')
  if (needRefresh) {
    uni.removeStorageSync('ORDER_NEED_REFRESH')
    fetchOrders()
  }
})
</script>

<style scoped lang="scss">
.page {
  height: 100vh;
  display: flex;
  flex-direction: column;
}
.filter {
  padding: 20rpx;
  display: flex;
  gap: 16rpx;
  background: #fff;
}
input {
  flex: 1;
  background: #f5f5f5;
  border-radius: 12rpx;
  padding: 16rpx;
}
.status {
  width: 160rpx;
  text-align: center;
  background: #f5f5f5;
  border-radius: 12rpx;
  padding: 16rpx 0;
}
.list {
  flex: 1;
  padding: 24rpx;
}
.empty {
  text-align: center;
  color: #999;
  padding-top: 60rpx;
}
</style>
