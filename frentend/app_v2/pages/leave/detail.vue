<template>
  <scroll-view scroll-y class="page" v-if="detail">
    <view class="card">
      <view class="heading">
        <view>
          <view class="title">{{ detail.leave_type_label }}</view>
          <view class="subtitle">申请时间：{{ formatDateTime(detail.created_at) }}</view>
        </view>
        <view class="status-badge" :class="detail.status">{{ detail.status_label }}</view>
      </view>

      <view class="info-section">
        <view class="info-row">
          <text class="label">请假类型</text>
          <text class="value">{{ detail.leave_type_label }}</text>
        </view>
        <view class="info-row">
          <text class="label">审批状态</text>
          <text :class="['value', 'status', detail.status]">{{ detail.status_label }}</text>
        </view>
        <view class="info-row">
          <text class="label">开始时间</text>
          <text class="value">{{ formatDateTime(detail.start_at) }}</text>
        </view>
        <view class="info-row">
          <text class="label">结束时间</text>
          <text class="value">{{ formatDateTime(detail.end_at) }}</text>
        </view>
        <view class="info-row">
          <text class="label">请假时长</text>
          <text class="value">{{ detail.duration_hours }} 小时</text>
        </view>
        <view class="info-row" v-if="detail.reason">
          <text class="label">请假事由</text>
          <text class="value reason">{{ detail.reason }}</text>
        </view>
      </view>

      <view class="info-section" v-if="detail.approver_name || detail.approved_at">
        <view class="section-title">审批信息</view>
        <view class="info-row" v-if="detail.approver_name">
          <text class="label">审批人</text>
          <text class="value">{{ detail.approver_name }}</text>
        </view>
        <view class="info-row" v-if="detail.approved_at">
          <text class="label">审批时间</text>
          <text class="value">{{ formatDateTime(detail.approved_at) }}</text>
        </view>
      </view>

      <view class="info-section" v-if="detail.audits && detail.audits.length">
        <view class="section-title">审批记录</view>
        <view class="audit-list">
          <view class="audit-item" v-for="(audit, index) in detail.audits" :key="audit.id">
            <view class="audit-top">
              <text class="audit-action">{{ getAuditActionLabel(audit.action) }}</text>
              <text class="audit-time">{{ formatDateTime(audit.created_at) }}</text>
            </view>
            <view class="audit-row" v-if="audit.from_status">
              <text class="audit-label">状态变更</text>
              <text class="audit-value">{{ getAuditStatusLabel(audit.from_status) }} → {{ getAuditStatusLabel(audit.to_status) }}</text>
            </view>
            <view class="audit-row" v-if="audit.reason">
              <text class="audit-label">备注</text>
              <text class="audit-value">{{ audit.reason }}</text>
            </view>
          </view>
        </view>
      </view>
    </view>

    <view class="action-bar" v-if="detail.status === 'pending'">
      <button class="primary" @click="handleCancel">撤回申请</button>
    </view>
  </scroll-view>

  <view class="empty" v-else-if="!loading">
    <text class="empty-text">暂无请假详情</text>
  </view>

  <view class="loading" v-else>
    <text class="loading-text">加载中...</text>
  </view>
</template>

<script setup>
import { ref } from 'vue'
import { onLoad, onShow } from '@dcloudio/uni-app'
import { api } from '../../utils/request'

const detail = ref(null)
const loading = ref(true)
const leaveId = ref(null)
const cancelling = ref(false)

const formatDateTime = (dateStr) => {
  if (!dateStr) return '-'
  return dateStr.replace('T', ' ').substring(0, 16)
}

const getAuditActionLabel = (action) => {
  const map = {
    create: '创建申请',
    approve: '审批通过',
    reject: '审批拒绝',
    cancel: '撤回申请'
  }
  return map[action] || action
}

const getAuditStatusLabel = (status) => {
  const map = {
    pending: '审批中',
    approved: '已通过',
    rejected: '已拒绝',
    cancelled: '已撤销'
  }
  return map[status] || status
}

const loadDetail = async (id) => {
  if (!id) {
    loading.value = false
    return
  }
  loading.value = true
  try {
    const res = await api.leaveDetail(id)
    detail.value = res
  } catch (error) {
    console.error('获取请假详情失败:', error)
    detail.value = null
  } finally {
    loading.value = false
  }
}

const handleCancel = async () => {
  if (!detail.value?.id || cancelling.value) return

  uni.showModal({
    title: '确认撤回',
    content: '确定要撤回该请假申请吗？',
    success: async (res) => {
      if (res.confirm) {
        cancelling.value = true
        try {
          await api.cancelLeave(detail.value.id)
          uni.showToast({ title: '已撤回', icon: 'success' })
          setTimeout(() => {
            loadDetail(detail.value.id)
          }, 1500)
        } catch (error) {
          console.error('撤回失败:', error)
        } finally {
          cancelling.value = false
        }
      }
    }
  })
}

onLoad((query) => {
  const id = query.id || query.leaveId
  if (id) {
    leaveId.value = id
    loadDetail(id)
  }
})

onShow(() => {
  if (leaveId.value) {
    loadDetail(leaveId.value)
  }
})
</script>

<style scoped lang="scss">
.page {
  padding: 24rpx;
  background: #f6f7fb;
  min-height: 100vh;
}

.card {
  background: #fff;
  border-radius: 24rpx;
  padding: 24rpx;
  margin-bottom: 24rpx;
  box-shadow: 0 12rpx 32rpx rgba(0, 0, 0, 0.04);
}

.heading {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 20rpx;
  padding-bottom: 20rpx;
  border-bottom: 1rpx solid #f0f0f0;
}

.title {
  font-size: 34rpx;
  font-weight: 600;
  color: #333;
}

.subtitle {
  font-size: 24rpx;
  color: #999;
  margin-top: 6rpx;
}

.status-badge {
  padding: 8rpx 24rpx;
  border-radius: 20rpx;
  font-size: 24rpx;
}

.status-badge.pending {
  background: #fff7e6;
  color: #fa8c16;
}

.status-badge.approved {
  background: #f6ffed;
  color: #52c41a;
}

.status-badge.rejected {
  background: #fff1f0;
  color: #ff4d4f;
}

.status-badge.cancelled {
  background: #f5f5f5;
  color: #999;
}

.info-section {
  margin-bottom: 20rpx;
}

.info-section:last-child {
  margin-bottom: 0;
}

.section-title {
  font-size: 28rpx;
  font-weight: 600;
  color: #333;
  margin-bottom: 16rpx;
}

.info-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 12rpx 0;
  border-bottom: 1rpx solid #f5f5f5;
}

.info-row:last-child {
  border-bottom: none;
}

.label {
  font-size: 26rpx;
  color: #666;
  flex-shrink: 0;
}

.value {
  font-size: 26rpx;
  color: #333;
  text-align: right;
  max-width: 60%;
  word-break: break-all;
}

.value.reason {
  line-height: 1.6;
}

.value.status.pending {
  color: #fa8c16;
}

.value.status.approved {
  color: #52c41a;
}

.value.status.rejected {
  color: #ff4d4f;
}

.value.status.cancelled {
  color: #999;
}

.audit-list {
  display: flex;
  flex-direction: column;
  gap: 16rpx;
}

.audit-item {
  background: #f7f8fa;
  border-radius: 16rpx;
  padding: 16rpx;
}

.audit-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8rpx;
}

.audit-action {
  font-size: 26rpx;
  font-weight: 600;
  color: #333;
}

.audit-time {
  font-size: 22rpx;
  color: #999;
}

.audit-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-top: 6rpx;
}

.audit-label {
  font-size: 24rpx;
  color: #666;
  flex-shrink: 0;
}

.audit-value {
  font-size: 24rpx;
  color: #333;
  text-align: right;
  max-width: 70%;
}

.action-bar {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  background: #fff;
  padding: 16rpx 24rpx;
  padding-bottom: calc(16rpx + env(safe-area-inset-bottom));
  box-shadow: 0 -2rpx 12rpx rgba(0, 0, 0, 0.08);
}

.primary {
  background: #1677ff;
  color: #fff;
  border-radius: 8rpx;
  height: 88rpx;
  line-height: 88rpx;
  font-size: 30rpx;
}

.loading,
.empty {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 60vh;
}

.loading-text,
.empty-text {
  font-size: 28rpx;
  color: #999;
}
</style>
