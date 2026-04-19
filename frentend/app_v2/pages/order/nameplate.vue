<template>
  <scroll-view scroll-y class="page">
    <view class="tabs">
      <view
        v-for="item in tabs"
        :key="item.value"
        :class="['tab', { active: currentTab === item.value }]"
        @click="currentTab = item.value"
      >
        {{ item.label }}
      </view>
    </view>

    <view class="card">
      <view class="section-title">制作铭牌</view>
      <view class="form-item">
        <text>产品名称</text>
        <input v-model="form.product_name" placeholder="请输入产品名称" />
      </view>
      <view class="form-item">
        <text>产品型号</text>
        <input v-model="form.model" placeholder="请输入产品型号" />
      </view>
      <view class="form-item">
        <text>机箱电压</text>
        <picker :range="voltages" range-key="label" @change="onVoltageChange">
          <view class="picker">{{ currentVoltage.label }}</view>
        </picker>
      </view>
      <view class="form-item">
        <text>客户特殊要求</text>
        <textarea v-model="form.requirement" placeholder="请输入特殊要求" />
      </view>
      <view class="form-item">
        <text>分配任务人</text>
        <input v-model="form.executor" placeholder="请选择任务负责人" />
      </view>
      <view class="preview">
        <view class="preview-title">铭牌预览</view>
        <view class="preview-box">
          <view>产品名称：{{ form.product_name || 'XXXXXXX' }}</view>
          <view>型号：{{ form.model || 'XXXXXXX' }}</view>
          <view>电压：{{ currentVoltage.label }}</view>
        </view>
      </view>
      <view class="footer">
        <button class="outline">取消</button>
        <button class="primary" @click="save">保存并分配任务</button>
      </view>
    </view>
  </scroll-view>
</template>

<script setup>
import { ref, reactive } from 'vue'

const tabs = [
  { label: '制作铭牌', value: 'create' },
  { label: '任务分配', value: 'assign' },
  { label: '图片上传', value: 'upload' },
  { label: '审核管理', value: 'audit' }
]
const currentTab = ref('create')

const voltages = [
  { label: '220V', value: '220V' },
  { label: '380V', value: '380V' }
]
const currentVoltage = ref(voltages[0])

const form = reactive({
  product_name: '',
  model: '',
  requirement: '',
  executor: ''
})

const onVoltageChange = (e) => {
  currentVoltage.value = voltages[e.detail.value]
}

const save = () => {
  uni.showToast({ title: '已保存', icon: 'success' })
}
</script>

<style scoped lang="scss">
.page {
  padding: 32rpx;
  background: #f6f7fb;
}
.tabs {
  display: flex;
  background: #fff;
  border-radius: 24rpx;
  margin-bottom: 24rpx;
}
.tab {
  flex: 1;
  text-align: center;
  padding: 20rpx 0;
  color: #666;
}
.tab.active {
  color: #1677ff;
  font-weight: 600;
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
.picker {
  background: #f7f8fa;
  border-radius: 16rpx;
  padding: 16rpx;
}
.preview {
  margin: 24rpx 0;
}
.preview-title {
  font-size: 26rpx;
  margin-bottom: 8rpx;
}
.preview-box {
  border: 1rpx dashed #d9d9d9;
  border-radius: 16rpx;
  padding: 24rpx;
  color: #666;
  line-height: 1.6;
}
.footer {
  display: flex;
  gap: 16rpx;
}
.outline {
  flex: 1;
  border: 1rpx solid #1677ff;
  color: #1677ff;
  border-radius: 32rpx;
}
.primary {
  flex: 1;
  background: #1677ff;
  color: #fff;
  border-radius: 32rpx;
}
</style>
