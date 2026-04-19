<template>
  <scroll-view scroll-y class="page">
    <view class="search-bar">
      <input v-model="keyword" placeholder="请输入关键词搜索" confirm-type="search" @confirm="fetchList" />
      <button class="search-btn" size="mini" @click="fetchList">搜索</button>
    </view>
    <picker :range="filters" range-key="label" @change="onFilterChange">
      <view class="filter">{{ currentFilter.label }}</view>
    </picker>

    <view class="notice-card" v-for="item in list" :key="item.id">
      <view class="notice-top">
        <view>
          <view class="title">{{ item.title }}</view>
          <view class="desc">{{ item.content }}</view>
        </view>
        <view class="time">{{ item.published_at }}</view>
      </view>
      <view class="actions">
        <text class="link">查看任务详情</text>
        <text class="status">{{ item.status_label || '未读' }}</text>
      </view>
    </view>
    <view v-if="!list.length" class="empty">暂无通知</view>
  </scroll-view>
</template>

<script setup>
import { ref } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { api } from '../../utils/request'

const keyword = ref('')
const list = ref([])
const filters = [
  { label: '全部通知', value: '' },
  { label: '系统通知', value: 'system' },
  { label: '任务通知', value: 'task' },
  { label: '通用通知', value: 'general' }
]
const currentFilter = ref(filters[0])

const fetchList = async () => {
  const params = { keyword: keyword.value }
  if (currentFilter.value.value) {
    params.category = currentFilter.value.value
  }
  const res = await api.announcements(params)
  list.value = res.items || []
}

const onFilterChange = (e) => {
  currentFilter.value = filters[e.detail.value]
  fetchList()
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
