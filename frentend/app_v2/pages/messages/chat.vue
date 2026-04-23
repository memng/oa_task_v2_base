<template>
  <view class="chat-page">
    <scroll-view
      scroll-y
      class="chat-body"
      :scroll-into-view="scrollTarget"
      scroll-with-animation
      @scrolltolower="loadMore"
    >
      <view
        v-for="msg in messages"
        :key="msg.id"
        :id="`msg-${msg.id}`"
        class="message-item"
        :class="{ mine: isMine(msg) }"
      >
        <image class="avatar" :src="resolveAvatar(msg)" mode="aspectFill" />
        <view class="bubble" @click="handleMessageClick(msg)">
          <view v-if="msg.message_type === 'text'" class="content">{{ msg.content }}</view>
          <view v-else-if="msg.message_type === 'image'" class="media-content">
            <image class="media-image" :src="resolveMediaUrl(msg.storage_path)" mode="aspectFit" @click.stop="previewImage(msg)" />
          </view>
          <view v-else-if="msg.message_type === 'video'" class="media-content">
            <video class="media-video" :src="resolveMediaUrl(msg.storage_path)" :poster="resolveMediaUrl(msg.storage_path)" controls></video>
          </view>
          <view v-else-if="msg.message_type === 'file'" class="file-content">
            <view class="file-icon">
              <text class="file-icon-text">{{ getFileExtension(msg.file_name) }}</text>
            </view>
            <view class="file-info">
              <text class="file-name">{{ msg.file_name }}</text>
              <text class="file-size">{{ formatFileSize(msg.file_size) }}</text>
            </view>
            <view class="file-download">
              <text class="download-text">下载</text>
            </view>
          </view>
          <view v-else class="content">{{ msg.content || '[不支持的消息类型]' }}</view>
          <view class="meta">{{ msg.sender_name || '' }} · {{ msg.created_at }}</view>
        </view>
      </view>
      <view v-if="!messages.length" class="empty">暂无聊天记录，快来打个招呼吧</view>
    </scroll-view>
    <view class="chat-input">
      <view class="input-actions">
        <button class="action-btn" size="mini" @click="chooseImage">
          <text class="action-icon">📷</text>
        </button>
        <button class="action-btn" size="mini" @click="chooseVideo">
          <text class="action-icon">🎥</text>
        </button>
        <button class="action-btn" size="mini" @click="chooseFile">
          <text class="action-icon">📁</text>
        </button>
      </view>
      <textarea
        v-model="inputValue"
        class="input"
        placeholder="输入消息内容"
        auto-height
        confirm-type="send"
        @confirm="sendMessage"
      ></textarea>
      <button class="send-btn" size="mini" :loading="sending" :disabled="sending" @click="sendMessage">发送</button>
    </view>
  </view>
</template>

<script setup>
import { ref, computed, nextTick } from 'vue'
import { onLoad, onShow, onHide, onUnload } from '@dcloudio/uni-app'
import store from '../../store'
import { api, uploadFile, resolveAssetUrl } from '../../utils/request'
import { refreshMessageSummary } from '../../utils/message-center'

const roomId = ref(null)
const roomInfo = ref({})
const messages = ref([])
const inputValue = ref('')
const sending = ref(false)
const uploading = ref(false)
const scrollTarget = ref('')
let timer = null

const currentUser = computed(() => store.state.profile || {})
const currentUserId = computed(() => currentUser.value.id)

const fetchMessages = async () => {
  if (!roomId.value) return
  const res = await api.chatMessages(roomId.value, { limit: 100 })
  messages.value = res.items || []
  roomInfo.value = res.room || {}
  applyRoomTitle()
  await markRead()
  await nextTick()
  scrollToBottom()
}

const markRead = async () => {
  if (!messages.value.length) return
  const last = messages.value[messages.value.length - 1]
  await api.chatMarkRead(roomId.value, { message_id: last.id })
  refreshMessageSummary()
}

const sendMessage = async () => {
  const content = inputValue.value.trim()
  if (!content || !roomId.value || sending.value) {
    return
  }
  sending.value = true
  try {
    const res = await api.chatSend(roomId.value, { content, message_type: 'text' })
    messages.value.push(res.message)
    inputValue.value = ''
    await nextTick()
    scrollToBottom()
    refreshMessageSummary()
  } catch (error) {
    uni.showToast({ title: '发送失败', icon: 'none' })
  } finally {
    sending.value = false
  }
}

const sendMediaMessage = async (mediaId, messageType, content = '') => {
  if (!roomId.value || sending.value) {
    return
  }
  sending.value = true
  try {
    const res = await api.chatSend(roomId.value, {
      content,
      message_type: messageType,
      media_id: mediaId
    })
    messages.value.push(res.message)
    await nextTick()
    scrollToBottom()
    refreshMessageSummary()
  } catch (error) {
    uni.showToast({ title: '发送失败', icon: 'none' })
  } finally {
    sending.value = false
  }
}

const chooseImage = () => {
  uni.chooseImage({
    count: 9,
    sizeType: ['original', 'compressed'],
    sourceType: ['album', 'camera'],
    success: async (res) => {
      uploading.value = true
      try {
        for (const tempFilePath of res.tempFilePaths) {
          const uploadRes = await uploadFile(tempFilePath)
          if (uploadRes.media_id) {
            await sendMediaMessage(uploadRes.media_id, 'image')
          }
        }
      } catch (error) {
        console.error('上传图片失败:', error)
      } finally {
        uploading.value = false
      }
    }
  })
}

const chooseVideo = () => {
  uni.chooseVideo({
    sourceType: ['album', 'camera'],
    maxDuration: 60,
    camera: 'back',
    success: async (res) => {
      uploading.value = true
      try {
        const uploadRes = await uploadFile(res.tempFilePath)
        if (uploadRes.media_id) {
          await sendMediaMessage(uploadRes.media_id, 'video')
        }
      } catch (error) {
        console.error('上传视频失败:', error)
      } finally {
        uploading.value = false
      }
    }
  })
}

const chooseFile = () => {
  uni.chooseMessageFile({
    count: 1,
    type: 'all',
    success: async (res) => {
      uploading.value = true
      try {
        const file = res.tempFiles[0]
        const uploadRes = await uploadFile(file.path)
        if (uploadRes.media_id) {
          await sendMediaMessage(uploadRes.media_id, 'file', file.name)
        }
      } catch (error) {
        console.error('上传文件失败:', error)
      } finally {
        uploading.value = false
      }
    }
  })
}

const scrollToBottom = () => {
  if (!messages.value.length) return
  const last = messages.value[messages.value.length - 1]
  scrollTarget.value = `msg-${last.id}`
}

const isMine = (msg) => {
  return msg.sender_id === currentUserId.value
}

const resolveAvatar = (msg) => {
  if (isMine(msg)) {
    return currentUser.value.avatar_url || '/static/icons/avatar.png'
  }
  return msg.sender_avatar || '/static/icons/avatar.png'
}

const resolveMediaUrl = (storagePath) => {
  if (!storagePath) return ''
  return resolveAssetUrl('/storage/' + storagePath)
}

const previewImage = (msg) => {
  const url = resolveMediaUrl(msg.storage_path)
  uni.previewImage({
    current: url,
    urls: [url]
  })
}

const handleMessageClick = (msg) => {
  if (msg.message_type === 'file') {
    downloadFile(msg)
  }
}

const downloadFile = (msg) => {
  const url = resolveMediaUrl(msg.storage_path)
  const fileName = msg.file_name || 'download'
  const token = store.state.token
  
  uni.showModal({
    title: '下载文件',
    content: `确定要下载文件 "${fileName}" 吗？`,
    success: (modalRes) => {
      if (modalRes.confirm) {
        uni.showLoading({ title: '下载中...' })
        
        const downloadOptions = {
          url: url,
          success: (res) => {
            if (res.statusCode === 200) {
              const filePath = res.tempFilePath
              uni.hideLoading()
              uni.openDocument({
                filePath: filePath,
                showMenu: true,
                success: () => {
                  console.log('文件打开成功')
                },
                fail: (err) => {
                  console.error('打开文件失败:', err)
                  uni.showModal({
                    title: '下载完成',
                    content: '文件已下载完成，您可以在文件管理器中查看。\n临时路径: ' + filePath,
                    showCancel: false
                  })
                }
              })
            } else {
              uni.hideLoading()
              uni.showToast({ 
                title: '下载失败: HTTP ' + res.statusCode, 
                icon: 'none',
                duration: 3000
              })
            }
          },
          fail: (err) => {
            uni.hideLoading()
            console.error('下载失败:', err)
            uni.showToast({ 
              title: '下载失败: ' + (err.errMsg || '网络错误'), 
              icon: 'none',
              duration: 3000
            })
          }
        }
        
        if (token) {
          downloadOptions.header = {
            Authorization: `Bearer ${token}`
          }
        }
        
        uni.downloadFile(downloadOptions)
      }
    }
  })
}

const getFileExtension = (fileName) => {
  if (!fileName) return 'FILE'
  const ext = fileName.split('.').pop().toUpperCase()
  return ext.length > 4 ? 'FILE' : ext
}

const formatFileSize = (bytes) => {
  if (!bytes || bytes === 0) return '0 B'
  const k = 1024
  const sizes = ['B', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

const loadMore = () => {
  // reserved for pagination
}

const startPolling = () => {
  if (timer) return
  timer = setInterval(() => {
    fetchMessages()
  }, 5000)
}

const stopPolling = () => {
  if (timer) {
    clearInterval(timer)
    timer = null
  }
}

const applyRoomTitle = () => {
  if (roomInfo.value && roomInfo.value.name) {
    uni.setNavigationBarTitle({ title: roomInfo.value.name })
  }
}

onLoad((options) => {
  if (options?.id) {
    roomId.value = Number(options.id)
  }
  if (options?.title) {
    uni.setNavigationBarTitle({ title: decodeURIComponent(options.title) })
  }
  fetchMessages()
})

onShow(() => {
  fetchMessages()
  startPolling()
})

onHide(() => {
  stopPolling()
})

onUnload(() => {
  stopPolling()
})
</script>

<style scoped lang="scss">
.chat-page {
  display: flex;
  flex-direction: column;
  height: 100vh;
  background: #f6f7fb;
}
.chat-body {
  flex: 1;
  padding: 32rpx;
  box-sizing: border-box;
}
.message-item {
  display: flex;
  align-items: flex-end;
  margin-bottom: 24rpx;
}
.message-item.mine {
  flex-direction: row-reverse;
}
.message-item .avatar {
  width: 64rpx;
  height: 64rpx;
  border-radius: 50%;
  margin: 0 12rpx;
}
.bubble {
  max-width: 520rpx;
  background: #fff;
  border-radius: 16rpx;
  padding: 16rpx;
  box-shadow: 0 8rpx 16rpx rgba(0, 0, 0, 0.05);
}
.message-item.mine .bubble {
  background: #1677ff;
  color: #fff;
}
.content {
  font-size: 28rpx;
  word-break: break-all;
}
.media-content {
  display: flex;
  justify-content: center;
  align-items: center;
}
.media-image {
  max-width: 400rpx;
  max-height: 400rpx;
  border-radius: 8rpx;
}
.media-video {
  width: 400rpx;
  height: 300rpx;
  border-radius: 8rpx;
}
.file-content {
  display: flex;
  align-items: center;
  gap: 16rpx;
  padding: 8rpx;
}
.file-icon {
  width: 80rpx;
  height: 80rpx;
  background: #e6f7ff;
  border-radius: 12rpx;
  display: flex;
  align-items: center;
  justify-content: center;
}
.file-icon-text {
  font-size: 20rpx;
  font-weight: bold;
  color: #1890ff;
}
.file-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 8rpx;
}
.file-name {
  font-size: 26rpx;
  color: #333;
  word-break: break-all;
  overflow: hidden;
  text-overflow: ellipsis;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}
.message-item.mine .file-name {
  color: #fff;
}
.file-size {
  font-size: 22rpx;
  color: #999;
}
.message-item.mine .file-size {
  color: rgba(255, 255, 255, 0.7);
}
.file-download {
  padding: 8rpx 16rpx;
  background: #1890ff;
  border-radius: 8rpx;
}
.message-item.mine .file-download {
  background: rgba(255, 255, 255, 0.2);
}
.download-text {
  font-size: 24rpx;
  color: #fff;
}
.meta {
  font-size: 22rpx;
  color: rgba(0, 0, 0, 0.45);
  margin-top: 8rpx;
}
.message-item.mine .meta {
  color: rgba(255, 255, 255, 0.7);
}
.chat-input {
  display: flex;
  align-items: flex-end;
  padding: 20rpx 24rpx;
  background: #fff;
  box-shadow: 0 -4rpx 20rpx rgba(0, 0, 0, 0.04);
  gap: 16rpx;
}
.input-actions {
  display: flex;
  gap: 8rpx;
}
.action-btn {
  width: 72rpx;
  height: 72rpx;
  background: #f6f7fb;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  margin: 0;
  border: none;
}
.action-btn::after {
  border: none;
}
.action-icon {
  font-size: 32rpx;
}
.input {
  flex: 1;
  background: #f6f7fb;
  border-radius: 20rpx;
  padding: 16rpx 20rpx;
  min-height: 80rpx;
  max-height: 200rpx;
}
.send-btn {
  background: #1677ff;
  color: #fff;
  border-radius: 24rpx;
}
.empty {
  text-align: center;
  color: #999;
  margin-top: 160rpx;
}
</style>
