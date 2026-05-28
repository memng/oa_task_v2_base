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
        <view class="section-header">
          <view class="section-title">票据附件 ({{ detail.receipts.length }})</view>
          <text class="hint-text" v-if="detail.receipts.length > 1">点击图片可滑动浏览</text>
        </view>
        <view class="attachments-grid">
          <view 
            class="attachment-item" 
            v-for="receipt in detail.receipts" 
            :key="receipt.id"
            @click="viewAttachment(receipt)"
          >
            <view class="attachment-preview" :class="getAttachmentDisplay(receipt.file_name).typeClass">
              <image 
                v-if="getAttachmentDisplay(receipt.file_name).isImage" 
                :src="resolveAssetUrl(receipt.url)" 
                mode="aspectFill" 
                class="preview-image"
              />
              <view v-else class="preview-icon">
                <text class="icon-text">{{ getAttachmentDisplay(receipt.file_name).icon }}</text>
              </view>
              <view class="file-type-badge" v-if="!getAttachmentDisplay(receipt.file_name).isImage">
                <text class="badge-text">{{ getAttachmentDisplay(receipt.file_name).badgeText }}</text>
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
import { 
  getFileExtension, 
  isImageFile, 
  isPdfFile,
  getFileIcon,
  getFileTypeKey,
  previewAttachment
} from '../../utils/attachment-preview'

const getAttachmentDisplay = (fileName) => {
  return {
    isImage: isImageFile(fileName),
    isPdf: isPdfFile(fileName),
    typeClass: `type-${getFileTypeKey(fileName)}`,
    icon: getFileIcon(fileName),
    badgeText: getFileExtension(fileName).toUpperCase()
  }
}

const detail = ref(null)
const loading = ref(true)
const reimburseId = ref(null)

const formatDateTime = (dateStr) => {
  if (!dateStr) return '-'
  return dateStr.replace('T', ' ').substring(0, 16)
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
  const siblings = detail.value?.receipts || [receipt]
  previewAttachment(receipt, siblings, resolveAssetUrl).catch(() => {})
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

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16rpx;
}

.section-title {
  font-size: 28rpx;
  font-weight: 600;
  color: #333;
  margin-bottom: 0;
}

.hint-text {
  font-size: 22rpx;
  color: #999;
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
  position: relative;
  
  &.type-pdf {
    background: #fff7e6;
  }
  
  &.type-image {
    background: #f0f5ff;
  }
  
  &.type-other {
    background: #f6ffed;
  }
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

.file-type-badge {
  position: absolute;
  bottom: 8rpx;
  right: 8rpx;
  background: rgba(0, 0, 0, 0.6);
  padding: 4rpx 12rpx;
  border-radius: 8rpx;
}

.badge-text {
  font-size: 20rpx;
  color: #fff;
  font-weight: 600;
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
