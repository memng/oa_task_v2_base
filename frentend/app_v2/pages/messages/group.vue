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
    <view class="conversation-card" v-for="item in conversations" :key="item.room_id" @click="openConversation(item)">
      <view class="conversation-head">
        <view class="avatars">
          <image
            v-for="member in previewMembers(item.members)"
            :key="member.id"
            class="avatar"
            :src="member.avatar || defaultAvatar"
            mode="aspectFill"
          />
        </view>
        <view class="conversation-info">
          <view class="name">{{ item.name }}</view>
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
  </scroll-view>
</template>

<script setup>
import { ref } from 'vue'
import { onLoad, onShow } from '@dcloudio/uni-app'
import { api } from '../../utils/request'

const defaultAvatar = '/static/icons/avatar.png'
const keyword = ref('')
const conversations = ref([])
const currentType = ref('group')

const loadConversations = async () => {
  const params = {
    type: currentType.value === 'all' ? undefined : currentType.value,
    keyword: keyword.value
  }
  const res = await api.chatConversations(params)
  conversations.value = res.items || []
}

const openConversation = (item) => {
  const title = encodeURIComponent(item.name || '聊天')
  uni.navigateTo({
    url: `/pages/messages/chat?id=${item.room_id}&title=${title}`
  })
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
}
.conversation-head {
  display: flex;
  align-items: center;
  margin-bottom: 12rpx;
}
.avatars {
  display: flex;
  margin-right: 16rpx;
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
.conversation-info {
  flex: 1;
}
.name {
  font-size: 30rpx;
  font-weight: 600;
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
</style>
