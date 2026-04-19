<template>
  <view class="page">
    <view class="filter">
      <input v-model="keyword" placeholder="搜索PI或客户" />
      <picker :range="statusOptions" range-key="label" @change="onStatusChange">
        <view class="status">{{ currentStatus.label }}</view>
      </picker>
    </view>
    <scroll-view scroll-y class="list">
      <order-card v-for="item in orders" :key="item.id" :order="item" @click="openDetail(item)" />
      <view v-if="!orders.length" class="empty">暂无订单</view>
    </scroll-view>
  </view>
</template>

<script setup>
import { ref, watch } from 'vue'
import { onLoad, onShow } from '@dcloudio/uni-app'
import { api } from '../../utils/request'
import OrderCard from '../../components/OrderCard.vue'

const orders = ref([])
const keyword = ref('')
const statusOptions = [
  { label: '全部', value: '' },
  { label: '进行中', value: 'in_progress' },
  { label: '已完成', value: 'completed' },
  { label: '草稿', value: 'draft' }
]
const currentStatus = ref(statusOptions[0])

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
    uni.navigateTo({ url: `/pages/order/create?orderId=${orderId}&mode=edit` })
  } else {
    uni.navigateTo({ url: `/pages/order/detail?id=${orderId}` })
  }
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
