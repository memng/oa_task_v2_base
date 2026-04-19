<template>
  <view class="task-card" @click="emit('click')">
    <view class="task-header">
      <text class="task-title">{{ task.title }}</text>
      <text class="status" :class="task.status">{{ statusText }}</text>
    </view>
    <view class="meta">
      <text>{{ task.type_label }}</text>
      <text v-if="task.due_at">截止 {{ task.due_at }}</text>
    </view>
  </view>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  task: {
    type: Object,
    required: true
  }
})
const emit = defineEmits(['click'])

const statusMap = {
  pending: '待开始',
  in_progress: '进行中',
  waiting_audit: '待审核',
  rejected: '已驳回',
  completed: '已完成'
}
const statusText = computed(() => statusMap[props.task.status] || '未知')
</script>

<style scoped lang="scss">
.task-card {
  background: #fff;
  border-radius: 12rpx;
  padding: 20rpx;
  margin-bottom: 16rpx;
}
.task-header {
  display: flex;
  justify-content: space-between;
  margin-bottom: 12rpx;
}
.task-title {
  font-size: 28rpx;
  font-weight: 600;
}
.status {
  font-size: 24rpx;
  color: #999;
}
.status.pending { color: #faad14; }
.status.in_progress { color: #1677ff; }
.status.waiting_audit { color: #722ed1; }
.status.completed { color: #52c41a; }
.status.rejected { color: #ff4d4f; }
.meta {
  font-size: 24rpx;
  color: #666;
  display: flex;
  justify-content: space-between;
}
</style>
