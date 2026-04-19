<template>
  <scroll-view scroll-y class="page">
    <view class="card">
      <view class="section-title">验厂主题</view>
      <input class="subject-input" v-model="form.subject" placeholder="请输入验厂主题" />
    </view>
    <view class="card">
      <view class="section-title">验厂内容</view>
      <textarea v-model="form.content" placeholder="请输入验厂内容与要求" />
    </view>
    <view class="card">
      <view class="section-title">具体需求</view>
      <textarea v-model="form.requirement" placeholder="请输入具体需求" />
    </view>
    <view class="card">
      <view class="form-item">
        <text>看厂日期</text>
        <picker mode="date" :value="form.date" @change="onDateChange">
          <view class="picker" :class="{ placeholder: !form.date }">
            {{ form.date || '请选择日期' }}
          </view>
        </picker>
      </view>
      <view class="form-item">
        <text>看厂时间</text>
        <picker mode="time" :value="form.time" @change="onTimeChange">
          <view class="picker" :class="{ placeholder: !form.time }">
            {{ form.time || '请选择时间' }}
          </view>
        </picker>
      </view>
      <view class="form-item">
        <text>指定任务人</text>
        <view class="picker" :class="{ placeholder: !executorLabel }" @click="openExecutorSelector">
          {{ executorLabel || '请选择任务人' }}
        </view>
      </view>
      <view class="form-item">
        <text>附件上传</text>
        <view class="upload" @click="chooseAttachment">
          <text>点击上传</text>
          <text class="tip">支持JPG/PNG/PDF格式，单文件不超过200MB</text>
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
    </view>
    <view class="footer">
      <button class="outline" @click="saveDraft">保存草稿</button>
      <button class="primary" :loading="submitting" :disabled="submitting" @click="submit">提交任务</button>
    </view>
  </scroll-view>
  <view v-if="executorDialogVisible" class="assign-mask">
    <view class="assign-dialog">
      <view class="dialog-title">选择任务人</view>
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
  subject: '',
  content: '',
  requirement: '',
  date: '',
  time: '',
  executor_id: '',
  executor_name: ''
})
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

const ensureStaffLoaded = () => {
  if (!staffLoaded.value && !staffLoading.value) {
    fetchStaff()
  }
}

const fetchStaff = async () => {
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

const onDateChange = (event) => {
  form.date = event.detail.value
}

const onTimeChange = (event) => {
  form.time = event.detail.value
}

const pickFiles = () =>
  new Promise((resolve, reject) => {
    const pickFromAlbumOrCamera = () => {
      if (typeof uni.chooseMedia === 'function') {
        uni.chooseMedia({
          count: 6,
          mediaType: ['image'],
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
    }

    const pickFromFiles = () => {
      if (typeof uni.chooseMessageFile === 'function') {
        uni.chooseMessageFile({
          count: 6,
          type: 'all',
          extension: ['jpg', 'jpeg', 'png', 'pdf'],
          success: resolve,
          fail: reject
        })
        return
      }
      pickFromAlbumOrCamera()
    }

    uni.showActionSheet({
      itemList: ['拍摄或从相册选择', '从文件中选择'],
      success: (res) => {
        if (res.tapIndex === 1) {
          pickFromFiles()
        } else {
          pickFromAlbumOrCamera()
        }
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

const saveDraft = () => {
  uni.showToast({ title: '草稿已保存', icon: 'success' })
}

const submit = async () => {
  if (!form.subject) {
    uni.showToast({ title: '请输入验厂主题', icon: 'none' })
    return
  }
  if (!form.date) {
    uni.showToast({ title: '请选择看厂日期', icon: 'none' })
    return
  }
  submitting.value = true
  try {
    const visitTime = form.time || '00:00'
    const visitDateTime = form.date ? `${form.date} ${visitTime}:00` : null
    const descriptionParts = []
    if (form.content) {
      descriptionParts.push(form.content)
    }
    if (form.requirement) {
      descriptionParts.push(`具体需求：${form.requirement}`)
    }
    const attachmentIds = attachments.value.map((item) => item.media_id).filter(Boolean)
    await api.createTask({
      type: 'inspection',
      title: form.subject,
      description: descriptionParts.join('\n\n'),
      assigned_to: form.executor_id ? Number(form.executor_id) : null,
      start_at: visitDateTime,
      due_at: visitDateTime,
      payload: {
        visit_date: form.date,
        visit_time: visitTime,
        requirement: form.requirement,
        content: form.content
      },
      attachments: attachmentIds
    })
    uni.showToast({ title: '任务已提交', icon: 'success' })
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
.section-title {
  font-size: 30rpx;
  font-weight: 600;
  margin-bottom: 12rpx;
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
.upload,
input,
textarea {
  width: 100%;
  background: #f7f8fa;
  border-radius: 16rpx;
  padding: 16rpx;
  box-sizing: border-box;
}
.subject-input {
  padding-top: 28rpx;
  padding-bottom: 28rpx;
  min-height: 96rpx;
  line-height: 1.5;
}
.picker.placeholder {
  color: #999;
}
.upload {
  border: 1rpx dashed #1677ff;
  text-align: center;
  color: #1677ff;
}
.tip {
  display: block;
  color: #999;
  font-size: 22rpx;
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
.footer {
  display: flex;
  gap: 16rpx;
}
.outline,
.primary {
  flex: 1;
  border-radius: 32rpx;
}
.outline {
  border: 1rpx solid #1677ff;
  color: #1677ff;
  background: #fff;
}
.primary {
  background: #1677ff;
  color: #fff;
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
