<template>
  <scroll-view scroll-y class="page" @scrolltolower="loadMore">
    <view class="card">
      <view class="section-title">报销信息</view>
      <view class="form-item">
        <text>报销类型</text>
        <picker :range="types" range-key="label" @change="onTypeChange">
          <view class="picker">{{ currentType.label }}</view>
        </picker>
      </view>
      <view class="form-item">
        <text>金额</text>
        <input v-model="form.amount" type="number" placeholder="请输入金额" />
      </view>
      <view class="form-item">
        <text>说明</text>
        <textarea v-model="form.remark" placeholder="请输入报销说明" />
      </view>
    </view>
    <view class="card">
      <view class="section-title">票据上传</view>
      <view class="upload" @click="upload" :class="{ disabled: uploading }">
        <template v-if="receiptUrl">
          <image class="receipt-preview" :src="receiptUrl" mode="aspectFit" />
          <text class="tip">点击可重新上传</text>
        </template>
        <view v-else class="placeholder">
          <text>点击上传票据</text>
        </view>
      </view>
      <view v-if="receiptName" class="file-name">{{ receiptName }}</view>
    </view>
    <button class="primary" :loading="submitting" @click="submit">提交报销</button>

    <view class="section-divider">
      <view class="divider-line"></view>
      <text class="divider-text">我的报销记录</text>
      <view class="divider-line"></view>
    </view>

    <view class="filter-card">
      <view class="filter-row">
        <view class="filter-item">
          <text class="filter-label">类型</text>
          <picker :range="filterTypeOptions" range-key="label" @change="onFilterTypeChange">
            <view class="filter-picker">{{ currentFilterType.label }}</view>
          </picker>
        </view>
      </view>
      <view class="filter-row">
        <view class="filter-item date-item">
          <text class="filter-label">开始日期</text>
          <picker mode="date" :value="filterStartDate" @change="onFilterStartDateChange">
            <view class="filter-picker">{{ filterStartDate || '不限' }}</view>
          </picker>
        </view>
        <view class="filter-item date-item">
          <text class="filter-label">结束日期</text>
          <picker mode="date" :value="filterEndDate" @change="onFilterEndDateChange">
            <view class="filter-picker">{{ filterEndDate || '不限' }}</view>
          </picker>
        </view>
      </view>
      <view class="filter-actions">
        <button class="filter-btn reset" @click="resetFilter">重置</button>
        <button class="filter-btn search" @click="searchList">查询</button>
      </view>
    </view>

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

    <view v-if="loadingList && list.length === 0" class="loading">
      <text>加载中...</text>
    </view>

    <view v-if="!loadingList && list.length === 0" class="empty">
      <uni-icons type="info" size="60" color="#ccc" />
      <text class="empty-text">暂无报销记录</text>
    </view>

    <view v-if="loadingMore" class="loading-more">
      <text>加载中...</text>
    </view>

    <view v-if="!hasMore && list.length > 0" class="no-more">
      <text>没有更多数据了</text>
    </view>
  </scroll-view>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { api, uploadReceipt, resolveAssetUrl } from '../../utils/request'

const types = [
  { label: '差旅费用', value: 'travel' },
  { label: '采购费用', value: 'purchase' }
]
const currentType = ref(types[0])
const form = reactive({
  type: 'travel',
  amount: '',
  remark: '',
  receipt_media_id: null
})
const receiptName = ref('')
const receiptUrl = ref('')
const uploading = ref(false)
const submitting = ref(false)

const list = ref([])
const loadingList = ref(false)
const loadingMore = ref(false)
const hasMore = ref(true)
const page = ref(1)
const pageSize = 2

const filterTypeOptions = [
  { label: '全部类型', value: '' },
  { label: '差旅费用', value: 'travel' },
  { label: '采购费用', value: 'purchase' }
]
const currentFilterType = ref(filterTypeOptions[0])
const filterStartDate = ref('')
const filterEndDate = ref('')

const MAX_FILE_SIZE = 1024 * 1024
const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'pdf']

const getFileExtension = (filePath) => {
  const ext = filePath.split('.').pop().toLowerCase()
  return ext
}

const validateFile = (filePath, size = null) => {
  const extension = getFileExtension(filePath)
  if (!ALLOWED_EXTENSIONS.includes(extension)) {
    return {
      valid: false,
      message: '仅支持 jpg、png、pdf 格式的文件'
    }
  }
  if (size && size > MAX_FILE_SIZE) {
    return {
      valid: false,
      message: '文件大小不能超过1MB'
    }
  }
  return { valid: true }
}

const getFileInfo = (filePath) =>
  new Promise((resolve, reject) => {
    uni.getFileInfo({
      filePath,
      success: (res) => {
        resolve({
          size: res.size,
          digest: res.digest
        })
      },
      fail: reject
    })
  })

const chooseFile = () =>
  new Promise((resolve, reject) => {
    if (typeof uni.chooseMessageFile === 'function') {
      uni.chooseMessageFile({
        count: 1,
        type: 'all',
        success: (res) => {
          const file = res.tempFiles?.[0]
          if (file) {
            resolve({
              path: file.path || file.tempFilePath,
              name: file.name,
              size: file.size
            })
          } else {
            resolve(null)
          }
        },
        fail: reject
      })
      return
    }
    uni.chooseImage({
      count: 1,
      sourceType: ['album', 'camera'],
      success: (res) => {
        const path = res.tempFilePaths?.[0]
        if (path) {
          resolve({
            path,
            name: `image.${getFileExtension(path)}`,
            size: null
          })
        } else {
          resolve(null)
        }
      },
      fail: reject
    })
  })

const onTypeChange = (e) => {
  currentType.value = types[e.detail.value]
  form.type = currentType.value.value
}

const upload = async () => {
  if (uploading.value) return
  try {
    uploading.value = true
    const file = await chooseFile()
    if (!file) return

    let fileSize = file.size
    if (!fileSize) {
      try {
        const fileInfo = await getFileInfo(file.path)
        fileSize = fileInfo.size
      } catch (e) {
        console.error('获取文件信息失败', e)
      }
    }

    const validation = validateFile(file.path, fileSize)
    if (!validation.valid) {
      uni.showToast({ title: validation.message, icon: 'none' })
      return
    }

    const result = await uploadReceipt(file.path)
    form.receipt_media_id = result.media_id
    receiptName.value = file.name || result.file_name || '票据附件'
    receiptUrl.value = result.url || ''
    uni.showToast({ title: '上传成功', icon: 'success' })
  } catch (error) {
    if (error?.errMsg && error.errMsg.includes('cancel')) {
      return
    }
    uni.showToast({ title: '上传失败', icon: 'none' })
  } finally {
    uploading.value = false
  }
}

const submit = async () => {
  if (submitting.value) return
  if (!form.amount || Number(form.amount) <= 0) {
    uni.showToast({ title: '请输入正确金额', icon: 'none' })
    return
  }
  submitting.value = true
  try {
    await api.createReimburse({
      type: form.type,
      amount: Number(form.amount),
      remark: form.remark,
      receipt_media_id: form.receipt_media_id
    })
    uni.showToast({ title: '报销已提交', icon: 'success' })
    form.amount = ''
    form.remark = ''
    form.receipt_media_id = null
    receiptName.value = ''
    receiptUrl.value = ''
    
    setTimeout(() => {
      fetchList(true)
    }, 1000)
  } catch (error) {
    uni.showToast({ title: '提交失败', icon: 'none' })
  } finally {
    submitting.value = false
  }
}

const getTypeLabel = (type) => {
  const option = types.find(opt => opt.value === type)
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
    loadingList.value = true
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

    if (currentFilterType.value.value) {
      params.type = currentFilterType.value.value
    }
    if (filterStartDate.value) {
      params.startDate = filterStartDate.value
    }
    if (filterEndDate.value) {
      params.endDate = filterEndDate.value
    }

    const res = await api.reimburseList(params)
    const items = res.items || []
    
    if (reset) {
      list.value = items
    } else {
      list.value = [...list.value, ...items]
    }

    hasMore.value = items.length >= pageSize
    page.value++
  } catch (error) {
    console.error('获取报销列表失败:', error)
  } finally {
    loadingList.value = false
    loadingMore.value = false
  }
}

const loadMore = () => {
  console.log('scrolltolower triggered', { loadingMore: loadingMore.value, hasMore: hasMore.value, page: page.value })
  if (loadingMore.value || !hasMore.value) {
    console.log('loadMore skipped', { loadingMore: loadingMore.value, hasMore: hasMore.value })
    return
  }
  console.log('loading more, page:', page.value)
  fetchList(false)
}

const onFilterTypeChange = (e) => {
  currentFilterType.value = filterTypeOptions[e.detail.value]
}

const onFilterStartDateChange = (e) => {
  filterStartDate.value = e.detail.value
}

const onFilterEndDateChange = (e) => {
  filterEndDate.value = e.detail.value
}

const resetFilter = () => {
  currentFilterType.value = filterTypeOptions[0]
  filterStartDate.value = ''
  filterEndDate.value = ''
  fetchList(true)
}

const searchList = () => {
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

onShow(() => {
  fetchList(true)
})
</script>

<style scoped lang="scss">
.page {
  height: 100vh;
  padding: 32rpx;
  background: #f6f7fb;
  box-sizing: border-box;
}
.card {
  background: #fff;
  border-radius: 24rpx;
  padding: 24rpx;
  margin-bottom: 24rpx;
}
.section-title {
  font-size: 30rpx;
  font-weight: 600;
  margin-bottom: 16rpx;
}
.form-item {
  margin-bottom: 16rpx;
}
.form-item text {
  display: block;
  margin-bottom: 8rpx;
  color: #666;
}
.picker,
input,
textarea {
  width: 100%;
  background: #f7f8fa;
  border-radius: 16rpx;
  padding: 16rpx;
}
.upload {
  border: 1rpx dashed #1677ff;
  border-radius: 16rpx;
  text-align: center;
  padding: 20rpx;
  color: #1677ff;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}
.upload.disabled {
  opacity: 0.6;
}
.placeholder {
  padding: 20rpx 0;
}
.receipt-preview {
  width: 200rpx;
  height: 200rpx;
  border-radius: 8rpx;
  background: #f7f8fa;
  margin-bottom: 8rpx;
}
.tip {
  font-size: 22rpx;
  color: #999;
}
.file-name {
  margin-top: 8rpx;
  font-size: 24rpx;
  color: #333;
}
.primary {
  background: #1677ff;
  color: #fff;
  border-radius: 32rpx;
}

.section-divider {
  display: flex;
  align-items: center;
  margin: 40rpx 0 24rpx 0;
}
.divider-line {
  flex: 1;
  height: 1rpx;
  background: #e0e0e0;
}
.divider-text {
  padding: 0 24rpx;
  font-size: 28rpx;
  color: #999;
}

.filter-card {
  background: #fff;
  border-radius: 24rpx;
  padding: 24rpx;
  margin-bottom: 24rpx;
}
.filter-row {
  display: flex;
  gap: 16rpx;
  margin-bottom: 16rpx;
}
.filter-item {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 8rpx;
  
  &.date-item {
    flex: 1;
  }
}
.filter-label {
  font-size: 24rpx;
  color: #999;
}
.filter-picker {
  background: #f5f5f5;
  border-radius: 12rpx;
  padding: 14rpx 16rpx;
  font-size: 26rpx;
  color: #333;
  text-align: center;
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
  padding: 60rpx 40rpx;
}

.empty-text {
  margin-top: 20rpx;
  font-size: 26rpx;
  color: #999;
}
</style>
