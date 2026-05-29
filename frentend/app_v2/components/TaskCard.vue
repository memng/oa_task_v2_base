<template>
  <view class="task-card" @click="emit('click')">
    <view class="task-header">
      <view class="title-row">
        <text class="priority-tag" :style="{ color: task.priority_color, borderColor: task.priority_color }">{{ task.priority_label }}</text>
        <text class="task-title">{{ task.title }}</text>
      </view>
      <text class="status" :class="task.status">{{ statusText }}</text>
    </view>
    <view class="tags-row" v-if="task.tags && task.tags.length > 0">
      <view class="tag-item" v-for="tag in task.tags" :key="tag.key" :style="{ color: tag.color, borderColor: tag.color }">
        {{ tag.label }}
      </view>
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
  align-items: flex-start;
  margin-bottom: 12rpx;
}
.title-row {
  display: flex;
  align-items: center;
  gap: 8rpx;
  flex: 1;
  margin-right: 12rpx;
}
.priority-tag {
  font-size: 20rpx;
  padding: 2rpx 8rpx;
  border: 1rpx solid;
  border-radius: 6rpx;
  font-weight: 600;
  flex-shrink: 0;
}
.task-title {
  font-size: 28rpx;
  font-weight: 600;
  flex: 1;
}
.tags-row {
  display: flex;
  flex-wrap: wrap;
  gap: 8rpx;
  margin-bottom: 12rpx;
}
.tag-item {
  font-size: 20rpx;
  padding: 2rpx 8rpx;
  border: 1rpx solid;
  border-radius: 6rpx;
  flex-shrink: 0;
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
