<template>
  <scroll-view scroll-y class="page">
    <view class="search-bar">
      <view class="search-input">
        <input
          v-model="keyword"
          placeholder="搜索成员或群聊"
          confirm-type="search"
          @confirm="loadConversations"
        />
      </view>
      <button class="search-btn" size="mini" @click="loadConversations">搜索</button>
    </view>
    <view
      class="conversation-card"
      v-for="item in conversations"
      :key="item.room_id"
      :class="{ pinned: item.is_pinned }"
      @click="openConversation(item)"
      @longpress="showActionMenu(item)"
    >
      <view class="conversation-head">
        <view class="avatars">
          <image
            v-for="member in previewMembers(item.members)"
            :key="member.id"
            class="avatar"
            :src="member.avatar || defaultAvatar"
            mode="aspectFill"
          />
          <view v-if="item.is_pinned" class="pin-badge">📌</view>
        </view>
        <view class="conversation-info">
          <view class="name">
            <text>{{ item.name }}</text>
            <text v-if="item.is_muted" class="mute-icon">🔕</text>
          </view>
          <view class="meta">{{ formatMemberNames(item.members) }}</view>
        </view>
        <view class="time">{{ formatTime(item.last_message?.created_at) }}</view>
      </view>
      <view class="conversation-foot">
        <view class="last">{{ item.last_message?.content || '暂无消息' }}</view>
        <view v-if="item.unread" class="badge">{{ item.unread > 99 ? '99+' : item.unread }}</view>
      </view>
    </view>
    <view v-if="!conversations.length" class="empty">暂无会话，尝试发起一条新消息</view>

    <view v-if="actionMenu.visible" class="action-mask" @click="closeActionMenu">
      <view class="action-sheet" @click.stop>
        <view class="action-title">{{ actionMenu.item?.name }}</view>
        <view
          class="action-item"
          @click="handlePin"
        >
          {{ actionMenu.item?.is_pinned ? '取消置顶' : '置顶' }}
        </view>
        <view
          class="action-item"
          @click="handleMute"
        >
          {{ actionMenu.item?.is_muted ? '取消免打扰' : '消息免打扰' }}
        </view>
        <view
          class="action-item"
          @click="handleClearUnread"
        >
          未读清零
        </view>
        <view class="action-item cancel" @click="closeActionMenu">取消</view>
      </view>
    </view>
  </scroll-view>
</template>

<script setup>
import { ref } from 'vue'
import { onLoad, onShow } from '@dcloudio/uni-app'
import { api } from '../../utils/request'
import { refreshMessageSummary } from '../../utils/message-center'

const defaultAvatar = '/static/icons/avatar.png'
const keyword = ref('')
const conversations = ref([])
const currentType = ref('group')
const actionMenu = ref({
  visible: false,
  item: null
})

const loadConversations = async () => {
  const params = {
    type: currentType.value === 'all' ? undefined : currentType.value,
    keyword: keyword.value
  }
  const res = await api.chatConversations(params)
  conversations.value = res.items || []
}

const openConversation = (item) => {
  if (actionMenu.value.visible) return
  const title = encodeURIComponent(item.name || '聊天')
  uni.navigateTo({
    url: `/pages/messages/chat?id=${item.room_id}&title=${title}`
  })
}

const showActionMenu = (item) => {
  actionMenu.value = {
    visible: true,
    item: item
  }
}

const closeActionMenu = () => {
  actionMenu.value.visible = false
}

const updateConversationInList = (roomId, updates) => {
  const idx = conversations.value.findIndex(c => c.room_id === roomId)
  if (idx >= 0) {
    conversations.value[idx] = {
      ...conversations.value[idx],
      ...updates
    }
    sortConversations()
  }
}

const sortConversations = () => {
  conversations.value.sort((a, b) => {
    if (a.is_pinned !== b.is_pinned) {
      return a.is_pinned ? -1 : 1
    }
    if (a.is_pinned && b.is_pinned) {
      const pinnedA = a.pinned_at ? new Date(a.pinned_at).getTime() : 0
      const pinnedB = b.pinned_at ? new Date(b.pinned_at).getTime() : 0
      if (pinnedA !== pinnedB) {
        return pinnedB - pinnedA
      }
    }
    const timeA = a.last_at ? new Date(a.last_at).getTime() : 0
    const timeB = b.last_at ? new Date(b.last_at).getTime() : 0
    return timeB - timeA
  })
}

const handlePin = async () => {
  const item = actionMenu.value.item
  if (!item) return
  closeActionMenu()
  try {
    const res = await api.chatPin(item.room_id)
    updateConversationInList(item.room_id, {
      is_pinned: res.is_pinned,
      pinned_at: res.pinned_at,
      unread: res.unread
    })
    uni.showToast({ title: res.is_pinned ? '已置顶' : '已取消置顶', icon: 'success' })
  } catch (error) {
    console.error('置顶操作失败:', error)
  }
}

const handleMute = async () => {
  const item = actionMenu.value.item
  if (!item) return
  closeActionMenu()
  const newMuted = !item.is_muted
  try {
    const res = await api.chatMute(item.room_id, { is_muted: newMuted ? 1 : 0 })
    updateConversationInList(item.room_id, {
      is_muted: res.is_muted,
      unread: res.unread
    })
    uni.showToast({ title: res.is_muted ? '已开启免打扰' : '已关闭免打扰', icon: 'success' })
    refreshMessageSummary()
  } catch (error) {
    console.error('免打扰操作失败:', error)
  }
}

const handleClearUnread = async () => {
  const item = actionMenu.value.item
  if (!item) return
  closeActionMenu()
  try {
    const res = await api.chatClearUnread(item.room_id)
    updateConversationInList(item.room_id, { unread: res.unread || 0 })
    uni.showToast({ title: '未读已清零', icon: 'success' })
    refreshMessageSummary()
  } catch (error) {
    console.error('未读清零失败:', error)
  }
}

const previewMembers = (members = []) => {
  return (members || []).slice(0, 4)
}

const formatMemberNames = (members = []) => {
  if (!members || !members.length) return ''
  const names = members.map((m) => m.name || '成员')
  return names.slice(0, 3).join('、')
}

const formatTime = (time) => {
  return time || ''
}

onLoad((options) => {
  if (options?.type === 'direct') {
    currentType.value = 'direct'
  } else if (options?.type === 'all') {
    currentType.value = 'all'
  } else {
    currentType.value = 'group'
  }
  if (options?.title) {
    uni.setNavigationBarTitle({ title: decodeURIComponent(options.title) })
  }
  loadConversations()
})

onShow(() => {
  loadConversations()
})
</script>

<style scoped lang="scss">
.page {
  padding: 32rpx;
}
.search-bar {
  display: flex;
  gap: 16rpx;
  margin-bottom: 24rpx;
}
.search-input {
  flex: 1;
  background: #fff;
  border-radius: 16rpx;
  padding: 16rpx 20rpx;
}
.search-btn {
  background: #1677ff;
  color: #fff;
  border-radius: 16rpx;
}
.conversation-card {
  background: #fff;
  border-radius: 24rpx;
  padding: 24rpx;
  margin-bottom: 24rpx;
  position: relative;
}
.conversation-card.pinned {
  background: #fffbe6;
  border: 2rpx solid #ffe58f;
}
.conversation-head {
  display: flex;
  align-items: center;
  margin-bottom: 12rpx;
}
.avatars {
  display: flex;
  margin-right: 16rpx;
  position: relative;
}
.avatar {
  width: 60rpx;
  height: 60rpx;
  border-radius: 50%;
  border: 2rpx solid #fff;
  margin-left: -14rpx;
}
.avatars .avatar:first-child {
  margin-left: 0;
}
.pin-badge {
  position: absolute;
  top: -8rpx;
  right: -8rpx;
  font-size: 24rpx;
}
.conversation-info {
  flex: 1;
}
.name {
  font-size: 30rpx;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 8rpx;
}
.mute-icon {
  font-size: 24rpx;
}
.meta {
  font-size: 24rpx;
  color: #999;
  margin-top: 6rpx;
}
.time {
  font-size: 22rpx;
  color: #999;
}
.conversation-foot {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.last {
  color: #666;
  font-size: 26rpx;
  flex: 1;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.badge {
  background: #ff4d4f;
  color: #fff;
  font-size: 24rpx;
  padding: 2rpx 14rpx;
  border-radius: 20rpx;
  margin-left: 12rpx;
}
.empty {
  text-align: center;
  color: #999;
  margin-top: 80rpx;
}

.action-mask {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  z-index: 1000;
  display: flex;
  align-items: flex-end;
}
.action-sheet {
  width: 100%;
  background: #fff;
  border-radius: 24rpx 24rpx 0 0;
  padding: 24rpx;
  padding-bottom: calc(24rpx + env(safe-area-inset-bottom));
}
.action-title {
  text-align: center;
  font-size: 28rpx;
  color: #999;
  padding: 16rpx 0;
  border-bottom: 1rpx solid #f0f0f0;
  margin-bottom: 8rpx;
}
.action-item {
  text-align: center;
  padding: 32rpx 0;
  font-size: 32rpx;
  color: #333;
  border-bottom: 1rpx solid #f5f5f5;
}
.action-item.cancel {
  color: #999;
  border-bottom: none;
  margin-top: 16rpx;
  background: #fafafa;
  border-radius: 16rpx;
}
</style>
