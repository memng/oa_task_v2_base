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
      <view class="section-header">
        <view class="section-title">票据上传</view>
        <view class="section-actions" v-if="uploadedFiles.length > 0">
          <text 
            class="action-btn" 
            @click="toggleSelectMode"
            :class="{ active: selectMode }"
          >
            {{ selectMode ? '取消' : '管理' }}
          </text>
        </view>
      </view>
      
      <view class="movable-area" v-if="selectMode">
        <movable-area class="drag-container" :scale-area="false">
          <view class="upload-list">
            <view 
              v-for="(file, index) in displayFiles" 
              :key="file.id" 
              class="upload-item"
              :class="{ 
                selected: selectedFileIds.includes(file.id), 
                'is-placeholder': isDragging && draggingFileId === file.id,
                'is-rearranging': isRearranging
              }"
            >
              <view class="item-content">
                <view 
                  class="select-indicator" 
                  @click.stop="toggleSelectById(file.id)"
                >
                  <uni-icons 
                    :type="selectedFileIds.includes(file.id) ? 'checkmark-filled' : 'circle'" 
                    size="32" 
                    :color="selectedFileIds.includes(file.id) ? '#1677ff' : '#ccc'" 
                  />
                </view>
                
                <view 
                  class="preview-wrapper"
                  @click.stop="previewSingleById(file.id)"
                >
                  <image 
                    v-if="isImageFile(file.name)" 
                    class="file-preview" 
                    :src="file.url" 
                    mode="aspectFill" 
                  />
                  <view v-else class="file-icon">
                    <uni-icons type="document" size="40" color="#666" />
                  </view>
                </view>

                <view 
                  class="drag-handle-zone"
                  @longpress.stop="startDrag(file, index, $event)"
                  @touchstart.stop="onDragTouchStart($event)"
                  @touchmove.stop.prevent="onDragTouchMove($event)"
                  @touchend.stop="onDragTouchEnd"
                  @touchcancel.stop="onDragTouchEnd"
                >
                  <view class="drag-handle-icon">
                    <uni-icons type="list" size="24" color="#999" />
                  </view>
                </view>
                
                <text class="file-name-text">{{ file.name }}</text>
                <view class="sort-badge">
                  <text class="sort-text">{{ index + 1 }}</text>
                </view>
              </view>
            </view>

            <movable-view
              v-if="isDragging"
              class="movable-item"
              :x="dragX"
              :y="dragY"
              direction="all"
              :inertia="false"
              :out-of-bounds="false"
              :damping="100"
              :friction="10"
            >
              <view class="upload-item dragging">
                <view class="preview-wrapper">
                  <image 
                    v-if="isImageFile(draggingFile?.name)" 
                    class="file-preview" 
                    :src="draggingFile?.url" 
                    mode="aspectFill" 
                  />
                  <view v-else class="file-icon">
                    <uni-icons type="document" size="40" color="#666" />
                  </view>
                </view>
                <text class="file-name-text">{{ draggingFile?.name }}</text>
              </view>
            </movable-view>

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
        </movable-area>
      </view>

      <view class="upload-list" v-else>
        <view 
          v-for="(file, index) in uploadedFiles" 
          :key="file.id" 
          class="upload-item"
          @click="previewSingleById(file.id)"
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
            <view class="delete-btn" @click.stop="removeFileById(file.id)">
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

      <view class="action-bar" v-if="selectMode">
        <view class="action-bar-left">
          <text class="select-all-btn" @click="toggleSelectAll">
            {{ isAllSelected ? '取消全选' : '全选' }}
          </text>
          <text class="selected-count">已选 {{ selectedFileIds.length }} 项</text>
        </view>
        <view class="action-bar-right">
          <button 
            class="action-bar-btn preview" 
            :disabled="selectedFileIds.length === 0"
            @click="batchPreview"
          >
            预览
          </button>
          <button 
            class="action-bar-btn delete" 
            :disabled="selectedFileIds.length === 0"
            @click="batchDelete"
          >
            删除
          </button>
        </view>
      </view>

      <view v-if="uploadedFiles.length === 0" class="empty-hint">
        <text class="hint-text">最多可上传 {{ MAX_UPLOAD_COUNT }} 个票据文件</text>
      </view>
      <view v-if="uploadedFiles.length > 0 && !selectMode" class="hint-text-bottom">
        <text class="hint-text">点击图片可预览，长按或点击「管理」可批量操作</text>
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
          <text class="attachments-hint" v-if="item.receipts.length > 1">点击图片可浏览</text>
        </view>
        <view class="attachments-list">
          <view 
            v-for="receipt in item.receipts" 
            :key="receipt.id"
            class="attachment-item" 
            @click.stop="viewListAttachment(item.receipts, receipt)"
          >
            <view class="attachment-icon" :class="getAttachmentDisplay(receipt.file_name).typeClass">
              <image 
                v-if="getAttachmentDisplay(receipt.file_name).isImage" 
                class="attachment-preview" 
                :src="resolveAssetUrl(receipt.url)" 
                mode="aspectFill" 
              />
              <text v-else-if="getAttachmentDisplay(receipt.file_name).icon" class="pdf-icon">{{ getAttachmentDisplay(receipt.file_name).icon }}</text>
              <uni-icons v-else type="document" size="36" color="#1677ff" />
            </view>
            <view class="attachment-info">
              <text class="attachment-name">{{ receipt.file_name || '票据附件' }}</text>
              <text class="attachment-tip">{{ getAttachmentDisplay(receipt.file_name).tipLabel }}</text>
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
import { reactive, ref, computed, watch } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { api, uploadReceipt, resolveAssetUrl } from '../../utils/request'
import { 
  getFileExtension, 
  isImageFile, 
  isPdfFile,
  getFileIcon,
  getFileTypeKey,
  previewAttachment,
  previewBatchAttachments 
} from '../../utils/attachment-preview'

const MAX_UPLOAD_COUNT = 9
const MAX_FILE_SIZE = 1024 * 1024
const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'pdf']
const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'bmp']

const generateId = () => {
  return Date.now().toString(36) + Math.random().toString(36).substr(2, 9)
}

const isAllSelected = computed(() => {
  return uploadedFiles.value.length > 0 && selectedFileIds.value.length === uploadedFiles.value.length
})

const getFileById = (id) => {
  return uploadedFiles.value.find(f => f.id === id)
}

const getIndexById = (id) => {
  return uploadedFiles.value.findIndex(f => f.id === id)
}

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
const selectMode = ref(false)
const selectedFileIds = ref([])

const isDragging = ref(false)
const draggingFile = ref(null)
const draggingFileId = ref(null)
const dragStartIndex = ref(-1)
const dragOverIndex = ref(-1)
const dragX = ref(0)
const dragY = ref(0)
const dragStartX = ref(0)
const dragStartY = ref(0)
const dragContainerRect = reactive({ x: 0, y: 0, width: 0, height: 0 })
const dragItemRect = reactive({ width: 0, height: 0 })
const dragColCount = ref(3)
const isRearranging = ref(false)

const displayFiles = computed(() => {
  const files = [...uploadedFiles.value]
  if (!isDragging.value || dragStartIndex.value < 0 || dragOverIndex.value < 0) {
    return files
  }
  if (dragStartIndex.value === dragOverIndex.value) {
    return files
  }
  const [removed] = files.splice(dragStartIndex.value, 1)
  files.splice(dragOverIndex.value, 0, removed)
  return files
})

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

const removeFileById = (id) => {
  const index = getIndexById(id)
  if (index > -1) {
    removeFile(index)
  }
}

const toggleSelectMode = () => {
  selectMode.value = !selectMode.value
  selectedFileIds.value = []
  resetDragState()
  if (selectMode.value) {
    setTimeout(updateListOffset, 50)
  }
}

const toggleSelectById = (id) => {
  const idx = selectedFileIds.value.indexOf(id)
  if (idx > -1) {
    selectedFileIds.value.splice(idx, 1)
  } else {
    selectedFileIds.value.push(id)
  }
}

const toggleSelectAll = () => {
  if (isAllSelected.value) {
    selectedFileIds.value = []
  } else {
    selectedFileIds.value = uploadedFiles.value.map(f => f.id)
  }
}

const previewSingleById = (id) => {
  const file = getFileById(id)
  if (file) {
    previewAttachment({ ...file, file_name: file.name }, uploadedFiles.value.map(f => ({ ...f, file_name: f.name })), (url) => url).catch(() => {})
  }
}

const batchPreview = () => {
  const selectedFiles = selectedFileIds.value.map(id => {
    const file = getFileById(id)
    return file ? { ...file, file_name: file.name } : null
  }).filter(Boolean)
  
  previewBatchAttachments(selectedFiles, (url) => url)
}

const batchDelete = () => {
  if (selectedFileIds.value.length === 0) return

  uni.showModal({
    title: '确认删除',
    content: `确定要删除选中的 ${selectedFileIds.value.length} 个附件吗？`,
    success: (res) => {
      if (res.confirm) {
        selectedFileIds.value.forEach(id => {
          const index = getIndexById(id)
          if (index > -1) {
            uploadedFiles.value.splice(index, 1)
            form.receipt_media_ids.splice(index, 1)
          }
        })
        selectedFileIds.value = []
        
        if (uploadedFiles.value.length === 0) {
          selectMode.value = false
        }
        uni.showToast({ title: '删除成功', icon: 'success' })
      }
    }
  })
}

const resetDragState = () => {
  isDragging.value = false
  draggingFile.value = null
  draggingFileId.value = null
  dragStartIndex.value = -1
  dragOverIndex.value = -1
  dragX.value = 0
  dragY.value = 0
  dragStartX.value = 0
  dragStartY.value = 0
  isRearranging.value = false
}

const measureDragArea = () => {
  const query = uni.createSelectorQuery()
  query.select('.drag-container .upload-list').boundingClientRect((containerRect) => {
    if (containerRect) {
      dragContainerRect.x = containerRect.left
      dragContainerRect.y = containerRect.top
      dragContainerRect.width = containerRect.width
      dragContainerRect.height = containerRect.height
    }
  }).exec()
  
  query.select('.upload-item:not(.upload-btn) .preview-wrapper').boundingClientRect((itemRect) => {
    if (itemRect) {
      dragItemRect.width = itemRect.width
      dragItemRect.height = itemRect.height + 56
    }
  }).exec()
}

const calculateDropIndex = (pageX, pageY) => {
  if (dragContainerRect.width === 0 || dragItemRect.width === 0) {
    const fallbackItemWidth = 176
    const fallbackItemHeight = 216
    const relativeX = pageX - dragContainerRect.x
    const relativeY = pageY - dragContainerRect.y
    const col = Math.floor(relativeX / fallbackItemWidth)
    const row = Math.floor(relativeY / fallbackItemHeight)
    const idx = col + row * 3
    return Math.max(0, Math.min(idx, uploadedFiles.value.length - 1))
  }
  
  const itemWidth = dragItemRect.width + 16
  const itemHeight = dragItemRect.height + 16
  const cols = Math.max(1, Math.floor(dragContainerRect.width / itemWidth))
  
  const relativeX = pageX - dragContainerRect.x
  const relativeY = pageY - dragContainerRect.y
  const col = Math.floor(relativeX / itemWidth)
  const row = Math.floor(relativeY / itemHeight)
  const idx = col + row * cols
  
  return Math.max(0, Math.min(idx, uploadedFiles.value.length - 1))
}

const getDragItemPosition = (index) => {
  if (dragItemRect.width === 0) {
    return {
      x: index % 3 * 176,
      y: Math.floor(index / 3) * 216
    }
  }
  const itemWidth = dragItemRect.width + 16
  const itemHeight = dragItemRect.height + 16
  const cols = Math.max(1, Math.floor(dragContainerRect.width / itemWidth))
  
  return {
    x: (index % cols) * itemWidth,
    y: Math.floor(index / cols) * itemHeight
  }
}

const startDrag = (file, index, event) => {
  if (!event.touches || event.touches.length === 0) return
  
  measureDragArea()
  
  isDragging.value = true
  draggingFile.value = file
  draggingFileId.value = file.id
  dragStartIndex.value = index
  
  const pos = getDragItemPosition(index)
  dragX.value = pos.x
  dragY.value = pos.y
  
  dragStartX.value = event.touches[0].pageX - pos.x
  dragStartY.value = event.touches[0].pageY - pos.y
  
  uni.vibrateShort()
}

const onDragTouchStart = (event) => {
  if (!isDragging.value) return
  dragStartX.value = event.touches[0].pageX - dragX.value
  dragStartY.value = event.touches[0].pageY - dragY.value
}

const onDragTouchMove = (event) => {
  if (!isDragging.value || !event.touches || event.touches.length === 0) return
  
  const touch = event.touches[0]
  const newX = touch.pageX - dragStartX.value
  const newY = touch.pageY - dragStartY.value
  
  dragX.value = newX
  dragY.value = newY
  
  const targetIndex = calculateDropIndex(touch.pageX, touch.pageY)
  if (targetIndex > -1 && targetIndex !== dragOverIndex.value && targetIndex !== dragStartIndex.value) {
    isRearranging.value = true
    dragOverIndex.value = targetIndex
    uni.vibrateShort({ type: 'light' })
    setTimeout(() => {
      isRearranging.value = false
    }, 150)
  }
}

const onDragTouchEnd = () => {
  if (!isDragging.value) return
  
  if (dragOverIndex.value > -1 && dragOverIndex.value !== dragStartIndex.value) {
    const fromIndex = dragStartIndex.value
    const toIndex = dragOverIndex.value
    
    if (fromIndex > -1 && toIndex > -1 && fromIndex !== toIndex) {
      const dragItem = uploadedFiles.value[fromIndex]
      const dragMediaId = form.receipt_media_ids[fromIndex]
      
      uploadedFiles.value.splice(fromIndex, 1)
      form.receipt_media_ids.splice(fromIndex, 1)
      
      uploadedFiles.value.splice(toIndex, 0, dragItem)
      form.receipt_media_ids.splice(toIndex, 0, dragMediaId)
      
      uni.vibrateShort()
    }
  }
  
  resetDragState()
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
          id: generateId(),
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

const viewListAttachment = (receipts, currentReceipt) => {
  if (!currentReceipt || !currentReceipt.url) {
    uni.showToast({ title: '附件不存在', icon: 'none' })
    return
  }
  previewAttachment(currentReceipt, receipts, resolveAssetUrl).catch(() => {})
}

const viewAttachment = (receipt) => {
  if (!receipt || !receipt.url) {
    uni.showToast({ title: '附件不存在', icon: 'none' })
    return
  }
  previewAttachment(receipt, [receipt], resolveAssetUrl).catch(() => {})
}

const getAttachmentDisplay = (fileName) => {
  return {
    isImage: isImageFile(fileName),
    isPdf: isPdfFile(fileName),
    typeClass: `type-${getFileTypeKey(fileName)}`,
    icon: getFileIcon(fileName),
    tipLabel: isImageFile(fileName) ? '点击查看' : `${getFileExtension(fileName).toUpperCase()} · 点击查看`
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
.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16rpx;
}

.section-title {
  font-size: 30rpx;
  font-weight: 600;
}

.section-actions {
  display: flex;
  gap: 16rpx;
}

.action-btn {
  font-size: 26rpx;
  color: #1677ff;
  padding: 8rpx 16rpx;
  border-radius: 8rpx;
  
  &.active {
    background: #e6f4ff;
  }
}

.movable-area {
  width: 100%;
  position: relative;
}

.movable-item {
  width: 160rpx;
  height: auto;
  z-index: 999;
}

.item-content {
  width: 100%;
  height: 100%;
}

.quick-view {
  position: absolute;
  top: 8rpx;
  right: 8rpx;
  width: 48rpx;
  height: 48rpx;
  background: rgba(255, 255, 255, 0.95);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2rpx 8rpx rgba(0, 0, 0, 0.1);
  z-index: 15;
}

.upload-item.drag-placeholder {
  opacity: 0.3;
  transform: scale(0.95);
}

.upload-item.drag-over {
  transform: scale(1.05);
  transition: transform 0.2s ease;
}

.upload-item.drag-over .preview-wrapper {
  border: 3rpx dashed #1677ff;
  background: #e6f4ff;
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
  position: relative;
  transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1), 
              opacity 0.15s ease;
  
  &.selected {
    .preview-wrapper {
      border: 3rpx solid #1677ff;
      box-shadow: 0 0 0 2rpx rgba(22, 119, 255, 0.2);
    }
  }
  
  &.is-placeholder {
    opacity: 0.2;
    transform: scale(0.92);
    pointer-events: none;
  }
  
  &.is-rearranging {
    transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1), 
                opacity 0.2s ease;
  }
}

.select-indicator {
  position: absolute;
  top: -8rpx;
  left: -8rpx;
  z-index: 10;
  background: #fff;
  border-radius: 50%;
  width: 40rpx;
  height: 40rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2rpx 8rpx rgba(0, 0, 0, 0.1);
}

.drag-container {
  width: 100%;
  min-height: 400rpx;
  position: relative;
}

.movable-item {
  width: 160rpx;
  height: auto;
  z-index: 999;
  pointer-events: none;
}

.drag-handle-zone {
  width: 48rpx;
  height: 48rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f5f5f5;
  border-radius: 12rpx;
  margin-top: 8rpx;
  
  &:active {
    background: #e0e0e0;
  }
}

.drag-handle-icon {
  display: flex;
  align-items: center;
  justify-content: center;
}

.upload-item.dragging {
  box-shadow: 0 12rpx 40rpx rgba(0, 0, 0, 0.3);
  opacity: 0.9;
  transform: scale(1.05);
  pointer-events: none;
}

.sort-badge {
  position: absolute;
  bottom: 8rpx;
  left: -8rpx;
  width: 36rpx;
  height: 36rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(0, 0, 0, 0.6);
  border-radius: 50%;
  box-shadow: 0 2rpx 8rpx rgba(0, 0, 0, 0.15);
  z-index: 10;
}

.sort-text {
  font-size: 20rpx;
  color: #fff;
  font-weight: 600;
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

.hint-text-bottom {
  margin-top: 16rpx;
  text-align: center;
}

.action-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 20rpx;
  padding-top: 20rpx;
  border-top: 1rpx solid #f0f0f0;
}

.action-bar-left {
  display: flex;
  align-items: center;
  gap: 20rpx;
}

.select-all-btn {
  font-size: 26rpx;
  color: #1677ff;
}

.selected-count {
  font-size: 24rpx;
  color: #999;
}

.action-bar-right {
  display: flex;
  gap: 16rpx;
}

.action-bar-btn {
  padding: 12rpx 28rpx;
  border-radius: 32rpx;
  font-size: 26rpx;
  border: none;
  line-height: 1;
  
  &.preview {
    background: #e6f4ff;
    color: #1677ff;
    
    &[disabled] {
      background: #f5f5f5;
      color: #ccc;
    }
  }
  
  &.delete {
    background: #fff2f0;
    color: #ff4d4f;
    
    &[disabled] {
      background: #f5f5f5;
      color: #ccc;
    }
  }
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
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.attachments-title {
  font-size: 24rpx;
  color: #999;
}

.attachments-hint {
  font-size: 20rpx;
  color: #1677ff;
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
  transition: background 0.2s ease;
  
  &:active {
    background: #f0f0f0;
  }
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
  position: relative;
  
  &.type-image {
    background: #f0f5ff;
  }
  
  &.type-pdf {
    background: #fff7e6;
  }
  
  &.type-other {
    background: #f6ffed;
  }
}

.pdf-icon {
  font-size: 36rpx;
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
