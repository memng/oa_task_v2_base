<template>
  <scroll-view scroll-y class="page">
    <view class="banner">
      <view>
        <view class="pi">PI文件合规审核</view>
        <view class="product">任务人需上传文件</view>
      </view>
      <view class="time">截止时间：2025-11-17 14:00</view>
    </view>

    <view class="card">
      <view class="section-title">上传文件清单</view>
      <view class="file" v-for="item in files" :key="item.label">
        <text>{{ item.label }}</text>
        <text class="status" :class="{ done: item.done }">{{ item.done ? '已上传' : '待上传' }}</text>
      </view>
    </view>

    <view class="card">
      <view class="section-title">管理员审核操作</view>
      <textarea v-model="remark" placeholder="请输入审核意见" />
    </view>

    <view class="footer">
      <button class="danger" @click="reject">审核驳回</button>
      <button class="primary" @click="approve">审核通过</button>
    </view>
  </scroll-view>
</template>

<script setup>
import { ref } from 'vue'

const files = [
  { label: 'PI文件合同', done: true },
  { label: '商业发票', done: true },
  { label: '报关单', done: true },
  { label: '提单', done: true },
  { label: '海运费用发票', done: true },
  { label: '收款水单', done: false }
]
const remark = ref('')

const approve = () => {
  uni.showToast({ title: '审核通过', icon: 'success' })
  uni.navigateBack()
}

const reject = () => {
  uni.showToast({ title: '已驳回', icon: 'none' })
}
</script>

<style scoped lang="scss">
.page {
  padding: 32rpx;
  background: #f6f7fb;
}
.banner {
  background: #fff;
  border-radius: 24rpx;
  padding: 24rpx;
  margin-bottom: 24rpx;
}
.pi {
  font-size: 30rpx;
  font-weight: 600;
}
.product {
  color: #666;
  margin-top: 8rpx;
}
.time {
  margin-top: 12rpx;
  color: #999;
}
.card {
  background: #fff;
  border-radius: 24rpx;
  padding: 24rpx;
  margin-bottom: 24rpx;
}
.section-title {
  font-size: 30rpx;
  font-weight: 600;
  margin-bottom: 16rpx;
}
.file {
  display: flex;
  justify-content: space-between;
  padding: 12rpx 0;
  border-bottom: 1rpx solid #f0f0f0;
}
.file:last-child {
  border-bottom: none;
}
.status {
  color: #fa8c16;
}
.status.done {
  color: #52c41a;
}
textarea {
  background: #f7f8fa;
  border-radius: 16rpx;
  padding: 16rpx;
  height: 160rpx;
}
.footer {
  display: flex;
  gap: 16rpx;
}
.danger,
.primary {
  flex: 1;
  border-radius: 32rpx;
  color: #fff;
}
.danger {
  background: #ff4d4f;
}
.primary {
  background: #1677ff;
}
</style>
