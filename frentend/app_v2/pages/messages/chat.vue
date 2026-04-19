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
        <view class="bubble">
          <view class="content">{{ msg.content }}</view>
          <view class="meta">{{ msg.sender_name || '' }} · {{ msg.created_at }}</view>
        </view>
      </view>
      <view v-if="!messages.length" class="empty">暂无聊天记录，快来打个招呼吧</view>
    </scroll-view>
    <view class="chat-input">
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
import { api } from '../../utils/request'
import { refreshMessageSummary } from '../../utils/message-center'

const roomId = ref(null)
const roomInfo = ref({})
const messages = ref([])
const inputValue = ref('')
const sending = ref(false)
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
