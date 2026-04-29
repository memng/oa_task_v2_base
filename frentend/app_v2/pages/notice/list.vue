<template>
  <scroll-view scroll-y class="page">
    <view class="search-bar">
      <input v-model="keyword" placeholder="请输入关键词搜索" confirm-type="search" @confirm="fetchList" />
      <button class="search-btn" size="mini" @click="fetchList">搜索</button>
    </view>
    <picker :range="filters" range-key="label" @change="onFilterChange">
      <view class="filter">{{ currentFilter.label }}</view>
    </picker>

    <view class="notice-card" v-for="item in list" :key="item.id" @click="openNotification(item)">
      <view class="notice-top">
        <view>
          <view class="title">
            {{ item.title }}
            <view v-if="!item.is_read" class="dot"></view>
          </view>
          <view class="desc">{{ item.content }}</view>
        </view>
        <view class="time">{{ item.created_at }}</view>
      </view>
      <view class="actions" v-if="canNavigate(item)">
        <text class="link">{{ getActionLabel(item) }}</text>
        <text class="status">{{ item.is_read ? '已读' : '未读' }}</text>
      </view>
      <view class="actions" v-else>
        <text class="status">{{ item.is_read ? '已读' : '未读' }}</text>
      </view>
    </view>
    <view v-if="!list.length" class="empty">暂无通知</view>
  </scroll-view>
</template>

<script setup>
import { ref, computed } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { api } from '../../utils/request'
import {
  navigateToDetail,
  canNavigateToDetail,
  getActionLabelByNotification
} from '../../utils/notification-router'
import { refreshMessageSummary } from '../../utils/message-center'

const keyword = ref('')
const list = ref([])
const filters = [
  { label: '全部通知', value: 'all' },
  { label: '未读通知', value: 'unread' }
]
const currentFilter = ref(filters[0])

const fetchList = async () => {
  const params = { keyword: keyword.value }
  if (currentFilter.value.value === 'unread') {
    params.status = 'unread'
  }
  const res = await api.notifications(params)
  list.value = res.items || []
}

const onFilterChange = (e) => {
  currentFilter.value = filters[e.detail.value]
  fetchList()
}

const canNavigate = (item) => {
  return canNavigateToDetail(item)
}

const getActionLabel = (item) => {
  return getActionLabelByNotification(item)
}

const openNotification = async (item) => {
  if (!item.is_read) {
    await api.notificationMarkRead(item.id)
    item.is_read = true
    refreshMessageSummary()
  }
  if (canNavigateToDetail(item)) {
    navigateToDetail(item)
  } else {
    uni.showModal({
      title: item.title,
      content: item.content || '暂无详情',
      showCancel: false
    })
  }
}

onShow(fetchList)
</script>

<style scoped lang="scss">
.page {
  padding: 32rpx;
  background: #f6f7fb;
}
.search-bar {
  display: flex;
  gap: 12rpx;
  margin-bottom: 16rpx;
}
.search-bar input {
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
.filter {
  background: #fff;
  border-radius: 16rpx;
  padding: 16rpx;
  margin-bottom: 24rpx;
}
.notice-card {
  background: #fff;
  border-radius: 24rpx;
  padding: 24rpx;
  margin-bottom: 20rpx;
}
.notice-top {
  display: flex;
  justify-content: space-between;
  gap: 20rpx;
}
.title {
  font-size: 30rpx;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 8rpx;
}
.dot {
  width: 12rpx;
  height: 12rpx;
  border-radius: 50%;
  background: #ff4d4f;
}
.desc {
  font-size: 24rpx;
  color: #666;
  margin-top: 8rpx;
}
.time {
  font-size: 22rpx;
  color: #999;
}
.actions {
  margin-top: 16rpx;
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 24rpx;
}
.link {
  color: #1677ff;
}
.status {
  color: #999;
}
.empty {
  text-align: center;
  color: #999;
  padding-top: 60rpx;
}
</style>
