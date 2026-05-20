<template>
  <scroll-view scroll-y class="page">
    <view v-if="detail" class="detail-card">
      <view class="category-tag">{{ detail.category_label }}</view>
      <view class="title">{{ detail.title }}</view>
      <view class="meta">
        <text>{{ detail.creator_name || '管理员' }}</text>
        <text>{{ formatTime(detail.published_at || detail.created_at) }}</text>
      </view>
      <view class="divider"></view>
      <rich-text class="content" :nodes="detail.content"></rich-text>
    </view>
    <view v-else class="loading">加载中...</view>
  </scroll-view>
</template>

<script setup>
import { ref, onLoad } from '@dcloudio/uni-app'
import { api } from '../../utils/request'

const detail = ref(null)

const fetchDetail = async (id) => {
  try {
    const res = await api.announcementDetail(id)
    detail.value = res
  } catch (e) {
    uni.showToast({ title: '加载失败', icon: 'none' })
  }
}

const formatTime = (timeStr) => {
  if (!timeStr) return ''
  return timeStr.substring(0, 16)
}

onLoad((options) => {
  if (options.id) {
    fetchDetail(options.id)
  }
})
</script>

<style scoped lang="scss">
.page {
  padding: 32rpx;
  background: #f6f7fb;
  min-height: 100vh;
}

.detail-card {
  background: #fff;
  border-radius: 24rpx;
  padding: 40rpx 32rpx;
}

.category-tag {
  display: inline-block;
  padding: 8rpx 20rpx;
  background: #f0f5ff;
  color: #1677ff;
  border-radius: 20rpx;
  font-size: 24rpx;
  margin-bottom: 24rpx;
}

.title {
  font-size: 36rpx;
  font-weight: 700;
  color: #262626;
  line-height: 1.4;
  margin-bottom: 24rpx;
}

.meta {
  display: flex;
  gap: 24rpx;
  font-size: 24rpx;
  color: #8c8c8c;
  margin-bottom: 32rpx;
}

.divider {
  height: 1rpx;
  background: #f0f0f0;
  margin-bottom: 32rpx;
}

.content {
  font-size: 28rpx;
  color: #262626;
  line-height: 1.8;
  word-break: break-word;
}



.loading {
  text-align: center;
  padding: 80rpx 0;
  color: #999;
}
</style>
