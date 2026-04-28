<template>
  <view class="page">
    <view class="filter-bar">
      <view class="filter-item">
        <text class="filter-label">报销类型</text>
        <picker :range="typeOptions" range-key="label" @change="onTypeChange">
          <view class="picker">{{ currentType.label }}</view>
        </picker>
      </view>
      <view class="filter-item">
        <text class="filter-label">开始日期</text>
        <picker mode="date" :value="startDate" @change="onStartDateChange">
          <view class="picker">{{ startDate || '选择日期' }}</view>
        </picker>
      </view>
      <view class="filter-item">
        <text class="filter-label">结束日期</text>
        <picker mode="date" :value="endDate" @change="onEndDateChange">
          <view class="picker">{{ endDate || '选择日期' }}</view>
        </picker>
      </view>
      <view class="filter-actions">
        <button class="filter-btn reset" @click="resetFilters">重置</button>
        <button class="filter-btn search" @click="search">查询</button>
      </view>
    </view>

    <scroll-view 
      scroll-y 
      class="list" 
      @scrolltolower="loadMore"
      :style="{ height: scrollViewHeight + 'px' }"
    >
      <view class="reimburse-card" v-for="item in list" :key="item.id">
        <view class="card-header">
          <view class="type-badge" :class="item.type">{{ getTypeLabel(item.type) }}</view>
          <view class="status-badge" :class="item.status">{{ getStatusLabel(item.status) }}</view>
        </view>
        
        <view class="card-body">
          <view class="info-row">
            <text class="label">报销金额</text>
            <text class="value amount">¥{{ item.amount.toFixed(2) }}</text>
          </view>
          <view class="info-row">
            <text class="label">提交时间</text>
            <text class="value">{{ formatDate(item.created_at) }}</text>
          </view>
          <view class="info-row" v-if="item.remark">
            <text class="label">备注</text>
            <text class="value remark">{{ item.remark }}</text>
          </view>
        </view>

        <view class="card-footer" v-if="item.receipt_url">
          <view class="attachment-section" @click="viewAttachment(item)">
            <view class="attachment-icon">
              <uni-icons type="document" size="36" color="#1677ff" />
            </view>
            <view class="attachment-info">
              <text class="attachment-name">{{ item.receipt_name || '票据附件' }}</text>
              <text class="attachment-tip">点击查看附件</text>
            </view>
            <uni-icons type="right" size="32" color="#999" />
          </view>
        </view>
      </view>

      <view v-if="loading && list.length === 0" class="loading">
        <text>加载中...</text>
      </view>

      <view v-if="!loading && list.length === 0" class="empty">
        <uni-icons type="info" size="80" color="#ccc" />
        <text class="empty-text">暂无报销记录</text>
        <text class="empty-tip">点击右上角按钮提交新的报销申请</text>
      </view>

      <view v-if="loadingMore" class="loading-more">
        <text>加载中...</text>
      </view>

      <view v-if="!hasMore && list.length > 0" class="no-more">
        <text>没有更多数据了</text>
      </view>
    </scroll-view>

    <view class="fab" @click="goToCreate">
      <uni-icons type="plusempty" size="48" color="#fff" />
    </view>
  </view>
</template>

<script setup>
import { ref, onMounted, onShow } from 'vue'
import { api, resolveAssetUrl } from '../../utils/request'

const list = ref([])
const loading = ref(false)
const loadingMore = ref(false)
const hasMore = ref(true)
const scrollViewHeight = ref(0)

const typeOptions = [
  { label: '全部类型', value: '' },
  { label: '差旅费用', value: 'travel' },
  { label: '采购费用', value: 'purchase' }
]
const currentType = ref(typeOptions[0])
const startDate = ref('')
const endDate = ref('')

const page = ref(1)
const pageSize = 2

const getTypeLabel = (type) => {
  const option = typeOptions.find(opt => opt.value === type)
  return option ? option.label : type
}

const getStatusLabel = (status) => {
  const statusMap = {
    pending: '待审核',
    approved: '已通过',
    rejected: '已驳回'
  }
  return statusMap[status] || status
}

const formatDate = (dateStr) => {
  if (!dateStr) return ''
  return dateStr.replace('T', ' ').substring(0, 16)
}

const fetchList = async (reset = true) => {
  if (reset) {
    loading.value = true
    page.value = 1
    hasMore.value = true
  } else {
    loadingMore.value = true
  }

  try {
    const params = {
      page: page.value,
      pageSize: pageSize
    }

    if (currentType.value.value) {
      params.type = currentType.value.value
    }
    if (startDate.value) {
      params.startDate = startDate.value
    }
    if (endDate.value) {
      params.endDate = endDate.value
    }

    const res = await api.reimburseList(params)
    
    if (reset) {
      list.value = res.items || []
    } else {
      list.value = [...list.value, ...(res.items || [])]
    }

    hasMore.value = page.value < res.totalPages
    page.value++
  } catch (error) {
    console.error('获取报销列表失败:', error)
  } finally {
    loading.value = false
    loadingMore.value = false
  }
}

const loadMore = () => {
  if (loadingMore.value || !hasMore.value) return
  fetchList(false)
}

const onTypeChange = (e) => {
  currentType.value = typeOptions[e.detail.value]
}

const onStartDateChange = (e) => {
  startDate.value = e.detail.value
}

const onEndDateChange = (e) => {
  endDate.value = e.detail.value
}

const resetFilters = () => {
  currentType.value = typeOptions[0]
  startDate.value = ''
  endDate.value = ''
  fetchList(true)
}

const search = () => {
  fetchList(true)
}

const viewAttachment = (item) => {
  if (!item.receipt_url) {
    uni.showToast({ title: '附件不存在', icon: 'none' })
    return
  }

  const fullUrl = resolveAssetUrl(item.receipt_url)
  const fileName = item.receipt_name || 'attachment'
  const ext = fileName.split('.').pop().toLowerCase()

  if (['jpg', 'jpeg', 'png', 'gif', 'bmp'].includes(ext)) {
    uni.previewImage({
      urls: [fullUrl],
      current: 0
    })
  } else if (ext === 'pdf') {
    uni.showLoading({ title: '加载中...' })
    uni.downloadFile({
      url: fullUrl,
      success: (res) => {
        uni.hideLoading()
        if (res.statusCode === 200) {
          uni.openDocument({
            filePath: res.tempFilePath,
            fileType: 'pdf',
            success: () => console.log('打开文档成功'),
            fail: (err) => {
              console.error('打开文档失败:', err)
              uni.showToast({ title: '无法打开此文件', icon: 'none' })
            }
          })
        }
      },
      fail: () => {
        uni.hideLoading()
        uni.showToast({ title: '下载失败', icon: 'none' })
      }
    })
  } else {
    uni.showModal({
      title: '提示',
      content: '当前不支持预览此类型的文件，是否复制链接？',
      success: (res) => {
        if (res.confirm) {
          uni.setClipboardData({
            data: fullUrl,
            success: () => {
              uni.showToast({ title: '链接已复制', icon: 'success' })
            }
          })
        }
      }
    })
  }
}

const calculateScrollViewHeight = () => {
  const systemInfo = uni.getSystemInfoSync()
  const windowHeight = systemInfo.windowHeight
  const query = uni.createSelectorQuery()
  query.select('.filter-bar').boundingClientRect((rect) => {
    if (rect) {
      scrollViewHeight.value = windowHeight - rect.height - 10
    } else {
      scrollViewHeight.value = windowHeight - 200
    }
  }).exec()
}

const goToCreate = () => {
  uni.navigateTo({ url: '/pages/finance/reimburse' })
}

onShow(() => {
  fetchList(true)
})

onMounted(() => {
  setTimeout(calculateScrollViewHeight, 100)
})
</script>

<style scoped lang="scss">
.page {
  height: 100vh;
  display: flex;
  flex-direction: column;
  background: #f6f7fb;
}

.filter-bar {
  background: #fff;
  padding: 24rpx;
  border-radius: 0 0 24rpx 24rpx;
  box-shadow: 0 2rpx 12rpx rgba(0, 0, 0, 0.05);
}

.filter-item {
  display: flex;
  align-items: center;
  margin-bottom: 16rpx;
}

.filter-label {
  width: 160rpx;
  font-size: 28rpx;
  color: #333;
}

.picker {
  flex: 1;
  background: #f5f5f5;
  border-radius: 12rpx;
  padding: 16rpx 20rpx;
  font-size: 28rpx;
  color: #333;
}

.filter-actions {
  display: flex;
  gap: 24rpx;
  margin-top: 8rpx;
}

.filter-btn {
  flex: 1;
  height: 72rpx;
  line-height: 72rpx;
  border-radius: 36rpx;
  font-size: 28rpx;
  border: none;
  
  &.reset {
    background: #f0f5ff;
    color: #1677ff;
  }
  
  &.search {
    background: #1677ff;
    color: #fff;
  }
}

.list {
  flex: 1;
  padding: 24rpx;
}

.reimburse-card {
  background: #fff;
  border-radius: 24rpx;
  padding: 24rpx;
  margin-bottom: 24rpx;
  box-shadow: 0 2rpx 12rpx rgba(0, 0, 0, 0.03);
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20rpx;
  padding-bottom: 16rpx;
  border-bottom: 1rpx solid #f0f0f0;
}

.type-badge {
  padding: 8rpx 16rpx;
  border-radius: 8rpx;
  font-size: 24rpx;
  
  &.travel {
    background: #e6f7ff;
    color: #1890ff;
  }
  
  &.purchase {
    background: #f6ffed;
    color: #52c41a;
  }
}

.status-badge {
  padding: 8rpx 16rpx;
  border-radius: 8rpx;
  font-size: 24rpx;
  
  &.pending {
    background: #fff7e6;
    color: #fa8c16;
  }
  
  &.approved {
    background: #f6ffed;
    color: #52c41a;
  }
  
  &.rejected {
    background: #fff2f0;
    color: #ff4d4f;
  }
}

.card-body {
  margin-bottom: 16rpx;
}

.info-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 12rpx;
}

.label {
  font-size: 26rpx;
  color: #999;
}

.value {
  font-size: 26rpx;
  color: #333;
  text-align: right;
  max-width: 60%;
  
  &.amount {
    font-size: 32rpx;
    font-weight: 600;
    color: #ff4d4f;
  }
  
  &.remark {
    color: #666;
    line-height: 1.5;
  }
}

.card-footer {
  padding-top: 16rpx;
  border-top: 1rpx solid #f0f0f0;
}

.attachment-section {
  display: flex;
  align-items: center;
  gap: 16rpx;
  padding: 16rpx;
  background: #fafafa;
  border-radius: 12rpx;
}

.attachment-icon {
  width: 80rpx;
  height: 80rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #e6f7ff;
  border-radius: 12rpx;
}

.attachment-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 4rpx;
}

.attachment-name {
  font-size: 26rpx;
  color: #333;
}

.attachment-tip {
  font-size: 22rpx;
  color: #999;
}

.loading,
.loading-more,
.no-more {
  text-align: center;
  padding: 40rpx;
  color: #999;
  font-size: 26rpx;
}

.empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 80rpx 40rpx;
}

.empty-text {
  margin-top: 24rpx;
  font-size: 28rpx;
  color: #666;
}

.empty-tip {
  margin-top: 12rpx;
  font-size: 24rpx;
  color: #999;
}

.fab {
  position: fixed;
  right: 40rpx;
  bottom: 80rpx;
  width: 112rpx;
  height: 112rpx;
  background: #1677ff;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 8rpx 24rpx rgba(22, 119, 255, 0.4);
  z-index: 100;
}
</style>
