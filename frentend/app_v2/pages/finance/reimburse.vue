<template>
  <scroll-view scroll-y class="page" @scrolltolower="loadMore">
    <view v-if="detailItem" class="card detail-card">
      <view class="detail-header">
        <view class="detail-title">报销详情</view>
        <text class="close-btn" @click="closeDetail">×</text>
      </view>
      <view class="detail-content">
        <view class="detail-item">
          <text class="detail-label">报销类型</text>
          <text class="detail-value">{{ getTypeLabel(detailItem.type) }}</text>
        </view>
        <view class="detail-item">
          <text class="detail-label">审批状态</text>
          <text :class="['detail-value', 'status', detailItem.status]">{{ getStatusLabel(detailItem.status) }}</text>
        </view>
        <view class="detail-item">
          <text class="detail-label">报销金额</text>
          <text class="detail-value amount">¥{{ detailItem.amount.toFixed(2) }}</text>
        </view>
        <view class="detail-item">
          <text class="detail-label">提交时间</text>
          <text class="detail-value">{{ formatDate(detailItem.created_at) }}</text>
        </view>
        <view class="detail-item" v-if="detailItem.approved_at">
          <text class="detail-label">审批时间</text>
          <text class="detail-value">{{ formatDate(detailItem.approved_at) }}</text>
        </view>
        <view class="detail-item" v-if="detailItem.remark">
          <text class="detail-label">备注</text>
          <text class="detail-value remark-text">{{ detailItem.remark }}</text>
        </view>
      </view>
    </view>

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
      <view class="upload-list">
        <view 
          v-for="(file, index) in uploadedFiles" 
          :key="index" 
          class="upload-item"
        >
          <view class="preview-wrapper">
            <image 
              v-if="isImageFile(file.name)" 
              class="file-preview" 
              :src="file.url" 
              mode="aspectFill" 
            />
            <view v-else class="file-icon">
              <uni-icons type="document" size="40" color="#666" />
            </view>
            <view class="delete-btn" @click.stop="removeFile(index)">
              <uni-icons type="close" size="24" color="#fff" />
            </view>
          </view>
          <text class="file-name-text">{{ file.name }}</text>
        </view>
        <view 
          class="upload-item upload-btn" 
          @click="upload" 
          :class="{ disabled: uploading }"
          v-if="uploadedFiles.length < MAX_UPLOAD_COUNT"
        >
          <uni-icons type="plus" size="48" color="#ccc" />
          <text class="upload-tip">添加票据</text>
        </view>
      </view>
      <view v-if="uploadedFiles.length === 0" class="empty-hint">
        <text class="hint-text">最多可上传 {{ MAX_UPLOAD_COUNT }} 个票据文件</text>
      </view>
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

    <view class="reimburse-card" v-for="item in list" :key="item.id" @click="viewDetail(item)">
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

      <view class="card-footer" v-if="item.receipts && item.receipts.length > 0">
        <view class="attachments-header">
          <text class="attachments-title">票据附件 ({{ item.receipts.length }})</text>
        </view>
        <view class="attachments-list">
          <view 
            v-for="(receipt, idx) in item.receipts" 
            :key="idx"
            class="attachment-item" 
            @click.stop="viewAttachment(receipt)"
          >
            <view class="attachment-icon">
              <image 
                v-if="isImageFile(receipt.file_name)" 
                class="attachment-preview" 
                :src="resolveAssetUrl(receipt.url)" 
                mode="aspectFill" 
              />
              <uni-icons v-else type="document" size="36" color="#1677ff" />
            </view>
            <view class="attachment-info">
              <text class="attachment-name">{{ receipt.file_name || '票据附件' }}</text>
              <text class="attachment-tip">点击查看</text>
            </view>
          </view>
        </view>
      </view>

      <view class="card-footer" v-else-if="item.receipt_url">
        <view class="attachment-section" @click.stop="viewAttachment({ url: item.receipt_url, file_name: item.receipt_name })">
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

const MAX_UPLOAD_COUNT = 9
const MAX_FILE_SIZE = 1024 * 1024
const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'pdf']
const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'bmp']

const types = [
  { label: '差旅费用', value: 'travel' },
  { label: '采购费用', value: 'purchase' }
]
const currentType = ref(types[0])
const form = reactive({
  type: 'travel',
  amount: '',
  remark: '',
  receipt_media_ids: []
})
const uploadedFiles = ref([])
const uploading = ref(false)
const submitting = ref(false)

const list = ref([])
const loadingList = ref(false)
const loadingMore = ref(false)
const hasMore = ref(true)
const page = ref(1)
const pageSize = 2
const detailItem = ref(null)

const filterTypeOptions = [
  { label: '全部类型', value: '' },
  { label: '差旅费用', value: 'travel' },
  { label: '采购费用', value: 'purchase' }
]
const currentFilterType = ref(filterTypeOptions[0])
const filterStartDate = ref('')
const filterEndDate = ref('')

const getFileExtension = (filePath) => {
  const ext = filePath.split('.').pop().toLowerCase()
  return ext
}

const isImageFile = (fileName) => {
  if (!fileName) return false
  const ext = getFileExtension(fileName)
  return IMAGE_EXTENSIONS.includes(ext)
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
        count: MAX_UPLOAD_COUNT - uploadedFiles.value.length,
        type: 'all',
        success: (res) => {
          const files = res.tempFiles || []
          if (files.length > 0) {
            const result = files.map(file => ({
              path: file.path || file.tempFilePath,
              name: file.name,
              size: file.size
            }))
            resolve(result)
          } else {
            resolve([])
          }
        },
        fail: reject
      })
      return
    }
    uni.chooseImage({
      count: MAX_UPLOAD_COUNT - uploadedFiles.value.length,
      sourceType: ['album', 'camera'],
      success: (res) => {
        const paths = res.tempFilePaths || []
        if (paths.length > 0) {
          const result = paths.map(path => ({
            path,
            name: `image.${getFileExtension(path)}`,
            size: null
          }))
          resolve(result)
        } else {
          resolve([])
        }
      },
      fail: reject
    })
  })

const onTypeChange = (e) => {
  currentType.value = types[e.detail.value]
  form.type = currentType.value.value
}

const removeFile = (index) => {
  uploadedFiles.value.splice(index, 1)
  form.receipt_media_ids.splice(index, 1)
}

const upload = async () => {
  if (uploading.value) return
  if (uploadedFiles.value.length >= MAX_UPLOAD_COUNT) {
    uni.showToast({ title: `最多只能上传 ${MAX_UPLOAD_COUNT} 个文件`, icon: 'none' })
    return
  }
  
  try {
    uploading.value = true
    const files = await chooseFile()
    if (!files || files.length === 0) return

    for (const file of files) {
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
        continue
      }

      const result = await uploadReceipt(file.path)
      if (result && result.media_id) {
        uploadedFiles.value.push({
          media_id: result.media_id,
          name: file.name || result.file_name || '票据附件',
          url: result.url || ''
        })
        form.receipt_media_ids.push(result.media_id)
      }
    }
    
    if (uploadedFiles.value.length > 0) {
      uni.showToast({ title: '上传成功', icon: 'success' })
    }
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
    const payload = {
      type: form.type,
      amount: Number(form.amount),
      remark: form.remark
    }
    
    if (form.receipt_media_ids.length > 0) {
      payload.receipt_media_ids = form.receipt_media_ids
    }
    
    await api.createReimburse(payload)
    uni.showToast({ title: '报销已提交', icon: 'success' })
    
    form.amount = ''
    form.remark = ''
    form.receipt_media_ids = []
    uploadedFiles.value = []
    
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

const viewAttachment = (receipt) => {
  if (!receipt || !receipt.url) {
    uni.showToast({ title: '附件不存在', icon: 'none' })
    return
  }

  const fullUrl = resolveAssetUrl(receipt.url)
  const fileName = receipt.file_name || 'attachment'
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

const viewDetail = (item) => {
  detailItem.value = item
}

const closeDetail = () => {
  detailItem.value = null
}

const getUrlParams = () => {
  const pages = getCurrentPages()
  const currentPage = pages[pages.length - 1]
  const options = currentPage.options || {}
  return options
}

const findAndShowDetailById = async (id) => {
  if (!id) return
  const intId = parseInt(id, 10)
  if (Number.isNaN(intId)) return

  let found = list.value.find((item) => item.id === intId)
  if (found) {
    detailItem.value = found
    return
  }

  try {
    const params = {
      page: 1,
      pageSize: 100
    }
    const res = await api.reimburseList(params)
    const items = res.items || []
    found = items.find((item) => item.id === intId)
    if (found) {
      detailItem.value = found
    }
  } catch (error) {
    console.error('获取报销详情失败:', error)
  }
}

onShow(() => {
  detailItem.value = null
  fetchList(true)

  const params = getUrlParams()
  if (params.id) {
    setTimeout(() => {
      findAndShowDetailById(params.id)
    }, 500)
  }
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

.upload-list {
  display: flex;
  flex-wrap: wrap;
  gap: 16rpx;
}

.upload-item {
  width: 160rpx;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8rpx;
}

.preview-wrapper {
  position: relative;
  width: 160rpx;
  height: 160rpx;
  border-radius: 12rpx;
  overflow: hidden;
  background: #f7f8fa;
}

.file-preview {
  width: 100%;
  height: 100%;
}

.file-icon {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f0f5ff;
}

.delete-btn {
  position: absolute;
  top: 4rpx;
  right: 4rpx;
  width: 40rpx;
  height: 40rpx;
  background: rgba(0, 0, 0, 0.5);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.file-name-text {
  font-size: 22rpx;
  color: #666;
  text-align: center;
  width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.upload-btn {
  width: 160rpx;
  height: 160rpx;
  border: 2rpx dashed #d9d9d9;
  border-radius: 12rpx;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8rpx;
}

.upload-btn.disabled {
  opacity: 0.5;
}

.upload-tip {
  font-size: 24rpx;
  color: #999;
}

.empty-hint {
  margin-top: 16rpx;
  text-align: center;
}

.hint-text {
  font-size: 24rpx;
  color: #999;
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

.attachments-header {
  margin-bottom: 12rpx;
}

.attachments-title {
  font-size: 24rpx;
  color: #999;
}

.attachments-list {
  display: flex;
  flex-direction: column;
  gap: 12rpx;
}

.attachment-item {
  display: flex;
  align-items: center;
  gap: 16rpx;
  padding: 12rpx;
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
  overflow: hidden;
}

.attachment-preview {
  width: 100%;
  height: 100%;
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

.attachment-section {
  display: flex;
  align-items: center;
  gap: 16rpx;
  padding: 16rpx;
  background: #fafafa;
  border-radius: 12rpx;
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

.detail-card {
  border: 2rpx solid #1677ff;
  background: #f0f7ff;
}

.detail-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16rpx;
  padding-bottom: 16rpx;
  border-bottom: 1rpx solid #d6e4ff;
}

.detail-title {
  font-size: 30rpx;
  font-weight: 600;
  color: #1677ff;
}

.close-btn {
  width: 48rpx;
  height: 48rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 36rpx;
  color: #999;
  background: #fff;
  border-radius: 50%;
}

.detail-content {
  display: flex;
  flex-direction: column;
  gap: 16rpx;
}

.detail-item {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}

.detail-label {
  font-size: 26rpx;
  color: #666;
  flex-shrink: 0;
}

.detail-value {
  font-size: 26rpx;
  color: #333;
  text-align: right;
  max-width: 60%;
  word-break: break-all;
}

.detail-value.amount {
  font-size: 32rpx;
  font-weight: 600;
  color: #ff4d4f;
}

.detail-value.remark-text {
  line-height: 1.6;
}

.detail-value.status {
  font-size: 24rpx;
}

.detail-value.status.pending {
  color: #fa8c16;
}

.detail-value.status.approved {
  color: #52c41a;
}

.detail-value.status.rejected {
  color: #ff4d4f;
}
</style>
