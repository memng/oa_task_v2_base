<template>
  <view class="card">
    <view class="card-header">
      <text class="title">{{ title }}</text>
      <slot name="action"></slot>
    </view>
    <view class="card-content">
      <slot></slot>
    </view>
  </view>
</template>

<script setup>
defineProps({
  title: {
    type: String,
    default: ''
  }
})
</script>

<style scoped lang="scss">
.card {
  background: #fff;
  border-radius: 16rpx;
  padding: 24rpx;
  margin-bottom: 24rpx;
  box-shadow: 0 8rpx 24rpx rgba(22, 119, 255, 0.08);
}
.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16rpx;
}
.title {
  font-weight: 600;
  font-size: 30rpx;
  color: #1f1f1f;
}
</style>
