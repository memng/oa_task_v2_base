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
          <view v-else-if="msg.message_type === 'file'" class="file-content" @click="handleFileClick(msg)">
            <view class="file-icon">
              <text class="file-icon-text">{{ getFileExtension(msg.file_name) }}</text>
            </view>
            <view class="file-info">
              <text class="file-name">{{ msg.file_name }}</text>
              <text class="file-size">{{ formatFileSize(msg.file_size) }}</text>
            </view>
            <view class="file-download" @click.stop="handleFileClick(msg)">
              <text class="download-text">查看</text>
            </view>
          </view>
          <view v-else class="content">{{ msg.content || '[不支持的消息类型]' }}</view>
          <view class="meta">
            <text v-if="!isMine(msg)">{{ msg.sender_name || '' }} · </text>
            <text>{{ msg.created_at }}</text>
            <text v-if="isMine(msg)" class="read-status" @click.stop="handleReadStatusClick(msg)">
              <text v-if="roomInfo.type === 'direct'" :class="{ read: msg.read_status === 'read' }">
                {{ msg.read_status === 'read' ? '已读' : '未读' }}
              </text>
              <text v-else-if="roomInfo.type === 'group'" class="group-read-status">
                {{ msg.read_count }}人已读，{{ msg.unread_count }}人未读
              </text>
            </text>
          </view>
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
    
    <view v-if="readStatusPopup.visible" class="popup-mask" @click="closeReadStatusPopup">
      <view class="popup-content" @click.stop>
        <view class="popup-header">
          <text class="popup-title">{{ readStatusPopup.title }}</text>
          <text class="popup-close" @click="closeReadStatusPopup">×</text>
        </view>
        <scroll-view scroll-y class="popup-body">
          <view v-if="readStatusPopup.readers.length > 0" class="member-section">
            <view class="section-title">已读 ({{ readStatusPopup.readers.length }})</view>
            <view class="member-list">
              <view v-for="member in readStatusPopup.readers" :key="member.id" class="member-item">
                <image class="member-avatar" :src="resolveMemberAvatar(member)" mode="aspectFill" />
                <text class="member-name">{{ member.name }}</text>
              </view>
            </view>
          </view>
          <view v-if="readStatusPopup.unreaders.length > 0" class="member-section">
            <view class="section-title">未读 ({{ readStatusPopup.unreaders.length }})</view>
            <view class="member-list">
              <view v-for="member in readStatusPopup.unreaders" :key="member.id" class="member-item">
                <image class="member-avatar" :src="resolveMemberAvatar(member)" mode="aspectFill" />
                <text class="member-name">{{ member.name }}</text>
              </view>
            </view>
          </view>
        </scroll-view>
      </view>
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
const readStatusPopup = ref({
  visible: false,
  title: '',
  readers: [],
  unreaders: []
})
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
    handleFileClick(msg)
  }
}

const handleReadStatusClick = async (msg) => {
  if (!isMine(msg)) return
  
  try {
    const res = await api.chatMessageReaders(roomId.value, msg.id)
    readStatusPopup.value = {
      visible: true,
      title: roomInfo.value.type === 'direct' ? '消息状态' : '消息已读状态',
      readers: res.readers || [],
      unreaders: res.unreaders || []
    }
  } catch (error) {
    console.error('获取消息已读状态失败:', error)
    uni.showToast({ title: '获取消息状态失败', icon: 'none' })
  }
}

const closeReadStatusPopup = () => {
  readStatusPopup.value.visible = false
}

const resolveMemberAvatar = (member) => {
  return member.avatar || '/static/icons/avatar.png'
}

const handleFileClick = (msg) => {
  const fileName = msg.file_name || 'download'
  
  uni.showModal({
    title: '查看文件',
    content: `确定要查看文件 "${fileName}" 吗？`,
    success: (modalRes) => {
      if (modalRes.confirm) {
        downloadAndOpenFile(msg)
      }
    }
  })
}

const getDocumentFileType = (fileName) => {
  if (!fileName) return undefined
  
  const ext = fileName.split('.').pop().toLowerCase()
  
  const typeMap = {
    'doc': 'doc',
    'docx': 'docx',
    'xls': 'xls',
    'xlsx': 'xlsx',
    'ppt': 'ppt',
    'pptx': 'pptx',
    'pdf': 'pdf',
    'txt': 'txt',
    'html': 'html',
    'htm': 'html',
    'jpg': 'jpg',
    'jpeg': 'jpg',
    'png': 'png',
    'gif': 'gif',
    'bmp': 'bmp',
    'webp': 'webp'
  }
  
  return typeMap[ext] || undefined
}

const downloadAndOpenFile = (msg) => {
  const url = resolveMediaUrl(msg.storage_path)
  const fileName = msg.file_name || 'download'
  const token = store.state.token
  const fileType = getDocumentFileType(fileName)
  
  uni.showLoading({ title: '加载中...' })
  
  const downloadOptions = {
    url: url,
    success: (res) => {
      if (res.statusCode === 200) {
        const filePath = res.tempFilePath
        uni.hideLoading()
        
        const openOptions = {
          filePath: filePath,
          showMenu: true,
          success: () => {
            console.log('文件打开成功')
          },
          fail: (err) => {
            console.error('打开文件失败:', err)
            uni.showToast({
              title: '无法打开此文件类型',
              icon: 'none',
              duration: 3000
            })
          }
        }
        
        if (fileType) {
          openOptions.fileType = fileType
        }
        
        uni.openDocument(openOptions)
      } else {
        uni.hideLoading()
        uni.showToast({ 
          title: '加载失败: HTTP ' + res.statusCode, 
          icon: 'none',
          duration: 3000
        })
      }
    },
    fail: (err) => {
      uni.hideLoading()
      console.error('下载失败:', err)
      uni.showToast({ 
        title: '加载失败: ' + (err.errMsg || '网络错误'), 
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
.read-status {
  margin-left: 16rpx;
  padding: 4rpx 8rpx;
  border-radius: 4rpx;
}
.read-status .read {
  color: #52c41a;
}
.group-read-status {
  color: rgba(255, 255, 255, 0.8);
}
.popup-mask {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}
.popup-content {
  width: 80%;
  max-height: 70vh;
  background: #fff;
  border-radius: 16rpx;
  overflow: hidden;
}
.popup-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 24rpx 32rpx;
  border-bottom: 1rpx solid #f0f0f0;
}
.popup-title {
  font-size: 32rpx;
  font-weight: bold;
  color: #333;
}
.popup-close {
  font-size: 48rpx;
  color: #999;
  line-height: 1;
}
.popup-body {
  max-height: 50vh;
  padding: 16rpx 32rpx;
}
.member-section {
  margin-bottom: 24rpx;
}
.section-title {
  font-size: 28rpx;
  color: #666;
  margin-bottom: 16rpx;
  padding-left: 8rpx;
  border-left: 4rpx solid #1677ff;
}
.member-list {
  display: flex;
  flex-wrap: wrap;
  gap: 16rpx;
}
.member-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  width: 120rpx;
  padding: 16rpx 8rpx;
  border-radius: 8rpx;
  background: #fafafa;
}
.member-avatar {
  width: 72rpx;
  height: 72rpx;
  border-radius: 50%;
  margin-bottom: 8rpx;
}
.member-name {
  font-size: 22rpx;
  color: #666;
  text-align: center;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  width: 100%;
}
</style>
