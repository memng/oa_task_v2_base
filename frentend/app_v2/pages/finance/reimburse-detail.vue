<template>
  <scroll-view scroll-y class="page" v-if="detail">
    <view class="card">
      <view class="heading">
        <view>
          <view class="title">{{ detail.type_label }}</view>
          <view class="subtitle">申请时间：{{ formatDateTime(detail.created_at) }}</view>
        </view>
        <view class="status-badge" :class="detail.status">{{ detail.status_label }}</view>
      </view>

      <view class="info-section">
        <view class="info-row">
          <text class="label">报销类型</text>
          <text class="value">{{ detail.type_label }}</text>
        </view>
        <view class="info-row">
          <text class="label">审批状态</text>
          <text :class="['value', 'status', detail.status]">{{ detail.status_label }}</text>
        </view>
        <view class="info-row">
          <text class="label">报销金额</text>
          <text class="value amount">¥{{ detail.amount.toFixed(2) }}</text>
        </view>
        <view class="info-row" v-if="detail.remark">
          <text class="label">备注</text>
          <text class="value remark">{{ detail.remark }}</text>
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

      <view class="info-section" v-if="detail.receipts && detail.receipts.length">
        <view class="section-title">票据附件 ({{ detail.receipts.length }})</view>
        <view class="attachments-grid">
          <view 
            class="attachment-item" 
            v-for="(receipt, index) in detail.receipts" 
            :key="index"
            @click="viewAttachment(receipt)"
          >
            <view class="attachment-preview">
              <image 
                v-if="isImageFile(receipt.file_name)" 
                :src="resolveAssetUrl(receipt.url)" 
                mode="aspectFill" 
                class="preview-image"
              />
              <view v-else class="preview-icon">
                <text class="icon-text">📄</text>
              </view>
            </view>
            <text class="attachment-name">{{ receipt.file_name || '票据附件' }}</text>
          </view>
        </view>
      </view>
    </view>
  </scroll-view>

  <view class="empty" v-else-if="!loading">
    <text class="empty-text">暂无报销详情</text>
  </view>

  <view class="loading" v-else>
    <text class="loading-text">加载中...</text>
  </view>
</template>

<script setup>
import { ref, computed } from 'vue'
import { onLoad, onShow } from '@dcloudio/uni-app'
import { api, resolveAssetUrl } from '../../utils/request'

const detail = ref(null)
const loading = ref(true)
const reimburseId = ref(null)

const formatDateTime = (dateStr) => {
  if (!dateStr) return '-'
  return dateStr.replace('T', ' ').substring(0, 16)
}

const isImageFile = (fileName) => {
  if (!fileName) return true
  const ext = fileName.split('.').pop()?.toLowerCase()
  return ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'].includes(ext)
}

const loadDetail = async (id) => {
  if (!id) {
    loading.value = false
    return
  }
  loading.value = true
  try {
    const res = await api.reimburseDetail(id)
    detail.value = res
  } catch (error) {
    console.error('获取报销详情失败:', error)
    detail.value = null
  } finally {
    loading.value = false
  }
}

const viewAttachment = (receipt) => {
  if (!receipt?.url) return

  if (isImageFile(receipt.file_name)) {
    const imageUrls = detail.value.receipts
      .filter((r) => isImageFile(r.file_name))
      .map((r) => resolveAssetUrl(r.url))
    
    if (imageUrls.length > 0) {
      const current = resolveAssetUrl(receipt.url)
      uni.previewImage({
        urls: imageUrls,
        current: current
      })
    }
  } else {
    uni.showModal({
      title: '查看附件',
      content: receipt.file_name || '票据附件',
      showCancel: false
    })
  }
}

onLoad((query) => {
  const id = query.id || query.reimburseId
  if (id) {
    reimburseId.value = id
    loadDetail(id)
  }
})

onShow(() => {
  if (reimburseId.value) {
    loadDetail(reimburseId.value)
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

.value.amount {
  font-size: 32rpx;
  font-weight: 600;
  color: #ff4d4f;
}

.value.remark {
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

.attachments-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16rpx;
}

.attachment-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  background: #f7f8fa;
  border-radius: 12rpx;
  padding: 16rpx;
}

.attachment-preview {
  width: 100%;
  aspect-ratio: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #fff;
  border-radius: 8rpx;
  overflow: hidden;
}

.preview-image {
  width: 100%;
  height: 100%;
}

.preview-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
}

.icon-text {
  font-size: 48rpx;
}

.attachment-name {
  font-size: 22rpx;
  color: #666;
  margin-top: 8rpx;
  text-align: center;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  max-width: 100%;
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
