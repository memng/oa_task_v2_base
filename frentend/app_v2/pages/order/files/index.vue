<template>
  <scroll-view scroll-y class="page">
    <view class="search-row">
      <input
        v-model="keyword"
        placeholder="搜索PI号码/产品名称"
        confirm-type="search"
        @confirm="fetchList"
      />
      <button class="search-btn" size="mini" @click="fetchList">搜索</button>
    </view>
    <picker :range="roles" range-key="label" @change="onRoleChange">
      <view class="picker">{{ currentRole.label }}</view>
    </picker>

    <view v-if="loading" class="empty">加载中...</view>
    <view v-else-if="!list.length" class="empty">暂无上传任务</view>

    <view class="task-card" v-for="item in list" :key="item.id">
      <view class="header">
        <view class="pi">PI号码：{{ item.pi_number || '-' }}</view>
        <view class="status" :class="item.docStatusClass">{{ item.docStatusLabel }}</view>
      </view>
      <view class="product">产品名称：{{ item.product_name || '-' }}</view>
      <view class="deadline">截止日期：{{ item.deadlineText }}</view>
      <view class="meta">
        <text>客户：{{ item.customer_name }}</text>
        <text v-if="item.sales_owner_name">负责人：{{ item.sales_owner_name }}</text>
      </view>
      <button class="action" :class="item.docStatusClass" @click="handleAction(item)">
        {{ actionLabel(item.docStatusClass) }}
      </button>
    </view>
  </scroll-view>
</template>

<script setup>
import { ref } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import store from '../../../store'
import { api } from '../../../utils/request'

const keyword = ref('')
const list = ref([])
const loading = ref(true)
const roles = [
  { label: '管理员', value: 'all' },
  { label: '任务人', value: 'mine' }
]
const currentRole = ref(roles[0])

const formatDeadline = (val) => {
  if (!val) return '待定'
  return val.length > 16 ? val : `${val}`
}

const mapItem = (item = {}) => {
  const summary = item.document_summary || {}
  const status = summary.status || 'upload'
  const statusLabel = summary.status_label || '待上传文件'
  return {
    ...item,
    deadlineText: formatDeadline(item.expected_delivery_at),
    docStatusClass: status,
    docStatusLabel: statusLabel
  }
}

const fetchList = async () => {
  loading.value = true
  try {
    const params = {
      page: 1,
      page_size: 20
    }
    const kw = keyword.value.trim()
    if (kw) params.keyword = kw
    if (currentRole.value.value === 'mine' && store.state.profile?.id) {
      params.initiator_id = store.state.profile.id
    }
    const res = await api.orderList(params)
    list.value = (res.items || []).map((item) => mapItem(item))
  } catch (error) {
    console.error('fetch order list failed', error)
    list.value = []
    uni.showToast({ title: '加载失败', icon: 'none' })
  } finally {
    loading.value = false
    uni.stopPullDownRefresh()
  }
}

const onRoleChange = (e) => {
  const next = roles[e.detail.value]
  currentRole.value = next
  fetchList()
}

const actionLabel = (status) => {
  if (status === 'audit') return '查看状态'
  if (status === 'reupload') return '重新上传'
  return '上传文件'
}

const handleAction = (item) => {
  const status = item.docStatusClass
  if (status === 'audit') {
    uni.navigateTo({ url: `/pages/order/files/audit?id=${item.id}` })
    return
  }
  uni.navigateTo({ url: `/pages/order/files/upload?id=${item.id}` })
}

onShow(() => {
  fetchList()
})
</script>

<style scoped lang="scss">
.page {
  padding: 32rpx;
  background: #f6f7fb;
  min-height: 100vh;
}
.search-row {
  display: flex;
  gap: 16rpx;
}
.search-row input {
  flex: 1;
  background: #fff;
  border-radius: 16rpx;
  padding: 16rpx;
}
.search-btn {
  background: #1677ff;
  color: #fff;
  border-radius: 16rpx;
}
.picker {
  margin-top: 16rpx;
  background: #fff;
  border-radius: 16rpx;
  padding: 16rpx;
}
.task-card {
  background: #fff;
  border-radius: 24rpx;
  padding: 24rpx;
  margin-top: 24rpx;
}
.header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}
.pi {
  color: #1677ff;
  font-size: 26rpx;
}
.product {
  margin: 12rpx 0;
  font-size: 28rpx;
  font-weight: 600;
}
.deadline {
  color: #999;
  font-size: 24rpx;
}
.meta {
  margin-top: 8rpx;
  display: flex;
  justify-content: space-between;
  color: #666;
  font-size: 24rpx;
}
.status {
  padding: 4rpx 12rpx;
  border-radius: 16rpx;
  font-size: 22rpx;
}
.status.upload {
  background: #fff7e6;
  color: #fa8c16;
}
.status.audit {
  background: #e6f7ff;
  color: #1677ff;
}
.status.reupload {
  background: #fff1f0;
  color: #ff4d4f;
}
.action {
  width: 100%;
  margin-top: 20rpx;
  border-radius: 32rpx;
  padding: 20rpx 0;
  border: none;
  color: #fff;
  font-size: 30rpx;
}
.action.upload {
  background: #1677ff;
}
.action.audit {
  background: #bfbfbf;
}
.action.reupload {
  background: #ff4d4f;
}
.empty {
  text-align: center;
  color: #999;
  padding: 60rpx 0;
}
</style>
