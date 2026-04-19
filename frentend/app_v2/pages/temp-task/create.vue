<template>
  <scroll-view scroll-y class="page">
    <view class="card">
      <view class="title">任务信息</view>
      <view class="form-item">
        <text>任务标题</text>
        <input v-model="form.title" placeholder="请输入任务名称" />
      </view>
      <view class="form-item">
        <text>任务类型</text>
        <picker :range="types" range-key="label" @change="onTypeChange">
          <view class="picker">{{ currentType.label }}</view>
        </picker>
      </view>
      <view class="form-item">
        <text>指派对象</text>
        <view class="picker" :class="{ placeholder: !executorLabel }" @click="openExecutorSelector">
          {{ executorLabel || '请选择执行人' }}
        </view>
      </view>
    </view>

    <view class="card">
      <view class="title">描述</view>
      <textarea v-model="form.description" placeholder="请详细描述任务内容"></textarea>
    </view>

    <view class="card">
      <view class="title">附件上传</view>
      <view class="upload" @click="chooseAttachment">
        <text>点击上传</text>
        <text class="tip">支持图片/视频</text>
      </view>
      <view class="attachment-list" v-if="attachments.length">
        <view class="attachment-item" v-for="(item, index) in attachments" :key="item.media_id">
          <view>
            <view class="file-name">{{ item.name }}</view>
            <view class="file-size">{{ formatFileSize(item.size) }}</view>
          </view>
          <text class="remove" @click="removeAttachment(index)">删除</text>
        </view>
      </view>
    </view>

    <button class="submit" :loading="submitting" :disabled="submitting" @click="submit">提交任务</button>
  </scroll-view>
  <view v-if="executorDialogVisible" class="assign-mask">
    <view class="assign-dialog">
      <view class="dialog-title">选择执行人</view>
      <view class="dialog-section">
        <view class="section-label">成员列表</view>
        <scroll-view scroll-y class="staff-scroll">
          <view v-if="staffLoading" class="loading">员工列表加载中...</view>
          <view v-else>
            <view v-for="group in staffGroups" :key="group.name" class="staff-group">
              <view class="group-title">{{ group.name }}</view>
              <view class="staff-list">
                <view
                  v-for="user in group.users"
                  :key="user.id"
                  class="staff-item"
                  :class="{ active: isSelectedExecutor(user) }"
                  @click="selectExecutor(user)"
                >
                  {{ user.name }}
                </view>
              </view>
            </view>
            <view v-if="!staffGroups.length" class="empty">暂无员工</view>
          </view>
        </scroll-view>
      </view>
      <view class="dialog-actions">
        <button class="outline" @click="closeExecutorSelector">取消</button>
        <button class="primary" @click="confirmExecutor">确认</button>
      </view>
    </view>
  </view>
</template>

<script setup>
import { reactive, ref, computed } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { api, uploadFile } from '../../utils/request'

const form = reactive({
  title: '',
  description: '',
  type: 'temporary',
  executor_id: '',
  executor_name: ''
})
const types = [
  { label: '临时任务', value: 'temporary' },
  { label: '工厂任务', value: 'factory' }
]
const currentType = ref(types[0])

const attachments = ref([])
const uploading = ref(false)
const submitting = ref(false)

const staffList = ref([])
const staffLoading = ref(false)
const staffLoaded = ref(false)
const executorDialogVisible = ref(false)
const executorOriginal = reactive({ id: '', name: '' })
const selectedExecutorId = ref('')
const selectedExecutorName = ref('')

const staffGroups = computed(() => {
  if (!staffList.value.length) {
    return []
  }
  const groups = staffList.value.reduce((acc, user) => {
    const name = user.dept_name || '未分组'
    if (!acc[name]) {
      acc[name] = []
    }
    acc[name].push(user)
    return acc
  }, {})
  return Object.keys(groups).map((name) => ({
    name,
    users: groups[name]
  }))
})

const executorLabel = computed(() => {
  if (form.executor_name) {
    return form.executor_name
  }
  if (!form.executor_id) {
    return ''
  }
  const target = staffList.value.find((user) => String(user.id) === String(form.executor_id))
  return target ? target.name : ''
})

const onTypeChange = (e) => {
  currentType.value = types[e.detail.value]
  form.type = currentType.value.value
}

const fetchStaff = async () => {
  if (staffLoading.value) {
    return
  }
  staffLoading.value = true
  try {
    const res = await api.lookupStaff()
    staffList.value = res.items || []
    staffLoaded.value = true
  } catch (error) {
    console.error(error)
  } finally {
    staffLoading.value = false
  }
}

const ensureStaffLoaded = () => {
  if (!staffLoaded.value && !staffLoading.value) {
    fetchStaff()
  }
}

const openExecutorSelector = () => {
  executorOriginal.id = form.executor_id ? String(form.executor_id) : ''
  executorOriginal.name = form.executor_name || ''
  selectedExecutorId.value = executorOriginal.id
  selectedExecutorName.value = executorOriginal.name
  executorDialogVisible.value = true
  ensureStaffLoaded()
}

const closeExecutorSelector = () => {
  selectedExecutorId.value = executorOriginal.id
  selectedExecutorName.value = executorOriginal.name
  executorDialogVisible.value = false
}

const selectExecutor = (staff) => {
  const selectedId = String(staff.id)
  selectedExecutorId.value = selectedId
  selectedExecutorName.value = staff.name
}

const isSelectedExecutor = (staff) => String(selectedExecutorId.value || '') === String(staff.id)

const confirmExecutor = () => {
  form.executor_id = selectedExecutorId.value ? String(selectedExecutorId.value) : ''
  form.executor_name = selectedExecutorId.value ? selectedExecutorName.value : ''
  executorDialogVisible.value = false
}

const pickFiles = () =>
  new Promise((resolve, reject) => {
    if (typeof uni.chooseMedia === 'function') {
      uni.chooseMedia({
        count: 6,
        mediaType: ['image', 'video'],
        sourceType: ['album', 'camera'],
        success: resolve,
        fail: reject
      })
      return
    }
    uni.chooseImage({
      count: 6,
      sourceType: ['album', 'camera'],
      success: (res) => {
        const paths = res.tempFilePaths || []
        const files =
          (res.tempFiles && res.tempFiles.length && res.tempFiles) ||
          paths.map((path, idx) => ({
            path,
            tempFilePath: path,
            size: res.tempFiles?.[idx]?.size || 0,
            name: extractFileName(path)
          }))
        resolve({ tempFiles: files })
      },
      fail: reject
    })
  })

const chooseAttachment = async () => {
  if (uploading.value) return
  try {
    const res = await pickFiles()
    const files = res.tempFiles || []
    if (!files.length) return
    uploading.value = true
    for (const file of files) {
      const filePath = file.path || file.tempFilePath
      if (!filePath) continue
      const result = await uploadFile(filePath)
      attachments.value.push({
        media_id: result.media_id,
        url: result.url,
        name: file.name || extractFileName(filePath),
        size: file.size || 0
      })
    }
  } catch (error) {
    console.error(error)
  } finally {
    uploading.value = false
  }
}

const extractFileName = (path) => {
  if (!path) return '附件'
  const segments = path.split('/')
  return segments[segments.length - 1] || '附件'
}

const removeAttachment = (index) => {
  attachments.value.splice(index, 1)
}

const formatFileSize = (size) => {
  if (!size) return ''
  if (size < 1024) {
    return `${size}B`
  }
  if (size < 1024 * 1024) {
    return `${(size / 1024).toFixed(1)}KB`
  }
  return `${(size / 1024 / 1024).toFixed(1)}MB`
}

const submit = async () => {
  if (!form.title) {
    uni.showToast({ title: '请输入标题', icon: 'none' })
    return
  }
  submitting.value = true
  try {
    const attachmentIds = attachments.value.map((item) => item.media_id).filter(Boolean)
    await api.createTask({
      title: form.title,
      description: form.description,
      type: form.type || 'temporary',
      assigned_to: form.executor_id ? Number(form.executor_id) : null,
      attachments: attachmentIds
    })
    uni.showToast({ title: '已发布', icon: 'success' })
    setTimeout(() => {
      uni.navigateBack()
    }, 600)
  } catch (error) {
    console.error(error)
  } finally {
    submitting.value = false
  }
}

onShow(() => {
  ensureStaffLoaded()
})
</script>

<style scoped lang="scss">
.page {
  padding: 32rpx;
  background: #f6f7fb;
}
.card {
  background: #fff;
  border-radius: 24rpx;
  padding: 24rpx;
  margin-bottom: 24rpx;
}
.title {
  font-size: 30rpx;
  font-weight: 600;
  margin-bottom: 12rpx;
}
.form-item {
  margin-bottom: 20rpx;
}
.form-item text {
  display: block;
  margin-bottom: 8rpx;
  color: #666;
}
.form-item input,
textarea {
  width: 100%;
  background: #f7f8fa;
  border-radius: 16rpx;
  padding: 16rpx;
}
.picker {
  background: #f7f8fa;
  border-radius: 16rpx;
  padding: 16rpx;
}
.picker.placeholder {
  color: #999;
}
.upload {
  border: 1rpx dashed #1677ff;
  border-radius: 16rpx;
  text-align: center;
  padding: 40rpx 0;
  color: #1677ff;
}
.tip {
  display: block;
  font-size: 22rpx;
  color: #999;
}
.attachment-list {
  margin-top: 16rpx;
  border: 1rpx solid #f0f0f0;
  border-radius: 16rpx;
}
.attachment-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16rpx;
  border-bottom: 1rpx solid #f0f0f0;
}
.attachment-item:last-child {
  border-bottom: none;
}
.file-name {
  font-size: 26rpx;
}
.file-size {
  font-size: 22rpx;
  color: #999;
}
.remove {
  color: #ff4d4f;
}
.submit {
  background: #1677ff;
  color: #fff;
  border-radius: 32rpx;
}
.assign-mask {
  position: fixed;
  left: 0;
  right: 0;
  top: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: flex-end;
  justify-content: center;
  padding: 32rpx;
  box-sizing: border-box;
}
.assign-dialog {
  width: 100%;
  max-height: 80vh;
  background: #fff;
  border-radius: 24rpx;
  padding: 24rpx;
  display: flex;
  flex-direction: column;
}
.dialog-title {
  font-size: 30rpx;
  font-weight: 600;
  margin-bottom: 16rpx;
}
.dialog-section {
  display: flex;
  flex-direction: column;
  gap: 12rpx;
}
.section-label {
  font-size: 26rpx;
  color: #555;
}
.staff-scroll {
  max-height: 360rpx;
}
.staff-group {
  margin-bottom: 24rpx;
}
.group-title {
  font-size: 26rpx;
  font-weight: 600;
  margin-bottom: 12rpx;
}
.staff-list {
  display: flex;
  flex-wrap: wrap;
  gap: 12rpx;
}
.staff-item {
  position: relative;
  padding: 12rpx 24rpx;
  border-radius: 24rpx;
  background: #f5f5f5;
  display: inline-flex;
  align-items: center;
  gap: 8rpx;
  font-size: 26rpx;
}
.staff-item.active {
  background: #1677ff;
  color: #fff;
}
.dialog-actions {
  display: flex;
  gap: 16rpx;
  margin-top: 16rpx;
}
.loading {
  text-align: center;
  padding: 40rpx 0;
  color: #999;
}
.empty {
  text-align: center;
  color: #999;
}
</style>
