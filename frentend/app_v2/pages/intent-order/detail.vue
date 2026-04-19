<template>
  <scroll-view scroll-y class="page" v-if="detail">
    <view class="card">
      <view class="title">{{ detail.customer_name }}</view>
      <view class="subtitle">{{ detail.product_name }} · {{ detail.model || '型号待定' }}</view>
      <view class="meta-row">
        <text>数量：{{ detail.quantity }}</text>
        <text>电压：{{ detail.voltage || '-' }}</text>
      </view>
      <view class="meta-row">
        <text>状态：{{ detail.status }}</text>
        <text>预计成交：{{ detail.expected_close_date || '待定' }}</text>
      </view>
    </view>

    <view class="card">
      <view class="section-title">客户需求</view>
      <view class="content">{{ detail.customer_requirements || '暂无描述' }}</view>
    </view>
  </scroll-view>
</template>

<script setup>
import { ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { api } from '../../utils/request'

const detail = ref(null)

onLoad(async (query) => {
  const res = await api.intentOrderDetail(query.id)
  detail.value = res.item
})
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
  margin-bottom: 24rpx;
  box-shadow: 0 12rpx 32rpx rgba(0, 0, 0, 0.04);
}
.title {
  font-size: 34rpx;
  font-weight: 600;
}
.subtitle {
  margin-top: 8rpx;
  color: #8c8c8c;
}
.meta-row {
  display: flex;
  justify-content: space-between;
  margin-top: 12rpx;
  color: #666;
}
.section-title {
  font-size: 30rpx;
  font-weight: 600;
  margin-bottom: 12rpx;
}
.content {
  color: #666;
  line-height: 1.6;
}
</style>
