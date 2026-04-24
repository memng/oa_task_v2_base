<template>
  <view class="order-card">
    <view class="content" @click="emit('click')">
      <view class="head">
        <text class="pi">PI: {{ piLabel }}</text>
        <text class="status" :class="order.status">{{ statusLabel }}</text>
      </view>
      <view class="body">
        <text class="customer">客户：{{ order.customer_name }}</text>
        <text class="time">交期：{{ order.expected_delivery_at || '待定' }}</text>
        <text v-if="order.grand_total" class="total">总价：{{ order.grand_total }} {{ order.currency || '' }}</text>
      </view>
    </view>
    <view v-if="showCancelButton" class="actions" @click.stop>
      <text class="cancel-btn" @click="handleCancel">取消订单</text>
    </view>
  </view>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  order: {
    type: Object,
    required: true
  },
  showCancelBtn: {
    type: Boolean,
    default: null
  }
})
const emit = defineEmits(['click', 'cancel'])

const map = {
  draft: '草稿',
  in_progress: '进行中',
  completed: '已完成',
  cancelled: '已取消'
}
const statusLabel = computed(() => map[props.order.status] || '未知')
const piLabel = computed(() => {
  if (props.order.pi_numbers && props.order.pi_numbers.length) {
    return props.order.pi_numbers.join(' / ')
  }
  return props.order.pi_number
})
const showCancelButton = computed(() => {
  if (props.showCancelBtn !== null) {
    return props.showCancelBtn
  }
  const status = props.order.status
  return status === 'draft' || status === 'in_progress'
})
const handleCancel = () => {
  emit('cancel', props.order)
}
</script>

<style scoped lang="scss">
.order-card {
  background: #fff;
  border-radius: 16rpx;
  padding: 24rpx;
  margin-bottom: 20rpx;
}
.content {
  cursor: pointer;
}
.head {
  display: flex;
  justify-content: space-between;
  margin-bottom: 12rpx;
}
.pi {
  font-weight: 600;
}
.status {
  font-size: 24rpx;
}
.status.draft { color: #faad14; }
.status.in_progress { color: #1677ff; }
.status.completed { color: #52c41a; }
.status.cancelled { color: #999; }
.body {
  font-size: 26rpx;
  color: #555;
  display: flex;
  flex-direction: column;
  gap: 6rpx;
}
.total {
  color: #1677ff;
}
.actions {
  margin-top: 16rpx;
  padding-top: 16rpx;
  border-top: 1rpx solid #f0f0f0;
  display: flex;
  justify-content: flex-end;
}
.cancel-btn {
  font-size: 26rpx;
  color: #ff4d4f;
  padding: 8rpx 24rpx;
}
</style>
