<template>
  <scroll-view scroll-y class="page">
    <view class="header">
      <view class="tabs">
        <view
          v-for="item in tabs"
          :key="item.value"
          :class="['tab', { active: currentTab === item.value }]"
          @click="switchTab(item.value)"
        >
          <view class="count">{{ summary[item.value] || 0 }}</view>
          <view>{{ item.label }}</view>
        </view>
      </view>
      <picker :range="statusFilters" range-key="label" @change="onStatusChange">
        <view class="filter">{{ currentStatus.label }}</view>
      </picker>
    </view>

    <view class="card" v-for="item in list" :key="item.id">
      <view class="pi">{{ item.pi_number }}</view>
      <view class="title">{{ item.product_name }}</view>
      <view class="meta">
        <text>型号：{{ item.model }}</text>
        <text>电压：{{ item.voltage }}</text>
        <text>数量：{{ item.quantity }}台</text>
      </view>
      <view class="customer">{{ item.customer_name }}</view>
      <view class="requirement">
        <view>客户要求</view>
        <view class="detail">{{ item.requirement || '待补充' }}</view>
      </view>
      <view class="actions">
        <button size="mini" @click="edit(item)">编辑</button>
        <button class="primary" size="mini" @click="changeStatus(item)">变更状态</button>
        <button size="mini" @click="openDetail(item)">详情</button>
      </view>
    </view>
    <view v-if="!list.length" class="empty">暂无意向订单</view>

    <button class="fab" @click="showForm = !showForm">+</button>
    <view v-if="showForm" class="form">
      <input v-model="form.customer_name" placeholder="客户名称" />
      <input v-model="form.product_name" placeholder="产品名称" />
      <input v-model="form.model" placeholder="型号" />
      <button class="primary" size="mini" @click="create">保存</button>
    </view>
  </scroll-view>
</template>

<script setup>
import { ref } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { api, request } from '../../utils/request'

const list = ref([])
const tabs = [
  { label: '待成交订单', value: 'pending' },
  { label: '已完成订单', value: 'done' },
  { label: '未成交订单', value: 'lost' }
]
const currentTab = ref('pending')
const statusFilters = [
  { label: '全部', value: '' },
  { label: '待交付', value: 'pending' },
  { label: '已成交', value: 'done' },
  { label: '未成交', value: 'lost' }
]
const currentStatus = ref(statusFilters[0])
const summary = ref({ pending: 0, done: 0, lost: 0 })
const showForm = ref(false)
const form = ref({ customer_name: '', product_name: '', model: '', quantity: 1 })

const fetchList = async () => {
  const params = {}
  const tabStatus = currentStatus.value.value || currentTab.value
  if (tabStatus) {
    params.status = tabStatus
  }
  const res = await api.intentOrders(params)
  list.value = res.items || []
  const remoteSummary = res.summary || {}
  summary.value = {
    pending: remoteSummary.pending || 0,
    done: remoteSummary.done || 0,
    lost: remoteSummary.lost || 0
  }
}

const switchTab = (value) => {
  currentTab.value = value
  fetchList()
}

const onStatusChange = (e) => {
  currentStatus.value = statusFilters[e.detail.value]
  fetchList()
}

const create = async () => {
  if (!form.value.customer_name || !form.value.product_name) {
    uni.showToast({ title: '请填写完整', icon: 'none' })
    return
  }
  await request({ url: '/intent-orders', method: 'POST', data: form.value })
  showForm.value = false
  form.value = { customer_name: '', product_name: '', model: '', quantity: 1 }
  fetchList()
}

const edit = () => {
  uni.showToast({ title: '编辑功能待接入', icon: 'none' })
}

const changeStatus = () => {
  uni.showToast({ title: '变更状态待接入', icon: 'none' })
}

const openDetail = (item) => {
  uni.navigateTo({ url: `/pages/intent-order/detail?id=${item.id}` })
}

onShow(fetchList)
</script>

<style scoped lang="scss">
.page {
  padding: 32rpx;
  background: #f6f7fb;
  position: relative;
}
.header {
  background: #fff;
  border-radius: 24rpx;
  padding: 24rpx;
  margin-bottom: 24rpx;
}
.tabs {
  display: flex;
  justify-content: space-between;
}
.tab {
  flex: 1;
  text-align: center;
  border-right: 1rpx solid #f0f0f0;
  color: #666;
}
.tab:last-child {
  border-right: none;
}
.tab.active {
  color: #1677ff;
}
.count {
  font-size: 36rpx;
  font-weight: 600;
}
.filter {
  margin-top: 16rpx;
  background: #f7f8fa;
  padding: 16rpx;
  border-radius: 16rpx;
}
.card {
  background: #fff;
  border-radius: 24rpx;
  padding: 24rpx;
  margin-bottom: 24rpx;
}
.pi {
  color: #1677ff;
  font-size: 24rpx;
}
.title {
  font-size: 30rpx;
  font-weight: 600;
  margin: 8rpx 0;
}
.meta {
  display: flex;
  justify-content: space-between;
  color: #666;
  font-size: 24rpx;
}
.customer {
  margin: 12rpx 0;
  color: #fa541c;
}
.requirement {
  font-size: 24rpx;
  color: #666;
}
.detail {
  margin-top: 4rpx;
  color: #333;
}
.actions {
  display: flex;
  justify-content: flex-end;
  gap: 16rpx;
  margin-top: 16rpx;
}
.primary {
  background: #1677ff;
  color: #fff;
}
.empty {
  text-align: center;
  color: #999;
  padding: 60rpx 0;
}
.fab {
  position: fixed;
  right: 48rpx;
  bottom: 200rpx;
  width: 88rpx;
  height: 88rpx;
  border-radius: 44rpx;
  background: #1677ff;
  color: #fff;
  border: none;
}
.form {
  position: fixed;
  left: 32rpx;
  right: 32rpx;
  bottom: 40rpx;
  background: #fff;
  padding: 24rpx;
  border-radius: 24rpx;
  box-shadow: 0 12rpx 32rpx rgba(0, 0, 0, 0.1);
  display: flex;
  flex-direction: column;
  gap: 12rpx;
}
.form input {
  background: #f7f8fa;
  border-radius: 16rpx;
  padding: 16rpx;
}
</style>
