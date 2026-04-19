<template>
  <scroll-view scroll-y class="page">
    <view class="card">
      <view class="section-title">机器管理任务</view>
      <view class="form-item">
        <text>任务主题</text>
        <input v-model="form.title" placeholder="请输入任务主题" />
      </view>
      <view class="form-item">
        <text>机器名称</text>
        <input v-model="form.machine_name" placeholder="请输入机器名称" />
      </view>
      <view class="form-item">
        <text>采集要求</text>
        <textarea v-model="form.requirement" placeholder="请填写参数、照片要求" />
      </view>
      <view class="form-item">
        <text>执行人</text>
        <input v-model="form.executor" placeholder="请选择执行人" />
      </view>
      <view class="form-item">
        <text>开始时间</text>
        <input v-model="form.start_time" placeholder="2025-11-17 09:30" />
      </view>
    </view>
    <button class="primary" @click="submit">提交任务</button>
  </scroll-view>
</template>

<script setup>
import { reactive } from 'vue'

const form = reactive({
  title: '',
  machine_name: '',
  requirement: '',
  executor: '',
  start_time: ''
})

const submit = () => {
  uni.showToast({ title: '任务已提交', icon: 'success' })
}
</script>

<style scoped lang="scss">
.page {
  padding: 32rpx;
  background: #f6f7fb;
}
.card {
  background: #fff;
  border-radius: 24rpx;
  padding: 24rpx;
}
.section-title {
  font-size: 30rpx;
  font-weight: 600;
  margin-bottom: 16rpx;
}
.form-item {
  margin-bottom: 20rpx;
}
.form-item text {
  display: block;
  margin-bottom: 8rpx;
  color: #666;
}
.form-item input,
.form-item textarea {
  width: 100%;
  background: #f7f8fa;
  border-radius: 16rpx;
  padding: 16rpx;
}
.primary {
  margin-top: 24rpx;
  background: #1677ff;
  color: #fff;
  border-radius: 32rpx;
}
</style>
