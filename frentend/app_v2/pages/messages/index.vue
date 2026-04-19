<template>
  <scroll-view scroll-y class="page">
    <view class="profile-card">
      <image class="avatar" :src="avatar" mode="aspectFill" />
      <view>
        <view class="name">{{ profileName }}</view>
        <view class="dept">{{ deptName }}</view>
      </view>
    </view>

    <view class="search-row">
      <view class="search">
        <input
          v-model="keyword"
          placeholder="请输入关键词搜索"
          confirm-type="search"
          @confirm="handleSearch"
        />
      </view>
      <button class="search-btn" size="mini" @click="handleSearch">搜索</button>
      <button class="add-btn" size="mini" @click="createChat">+</button>
    </view>

    <view class="summary-panel">
      <view class="summary-item" v-for="card in summaryCards" :key="card.label">
        <view class="summary-value">{{ card.value }}</view>
        <view class="summary-label">{{ card.label }}</view>
      </view>
    </view>

    <view class="section">
      <view class="section-header">
        <view class="section-title">通知</view>
        <view class="tabs compact">
          <text
            v-for="item in notifyFilters"
            :key="item.value"
            :class="{ active: activeNotify === item.value }"
            @click="switchNotifyFilter(item.value)"
          >
            {{ item.label }}
          </text>
        </view>
      </view>
      <view
        class="notify-card"
        v-for="item in personalNotifications"
        :key="item.id"
        @click="openNotification(item, 'notification')"
      >
        <view class="notify-head">
          <view class="notify-title">
            <text>{{ item.title }}</text>
            <view v-if="!item.is_read" class="dot"></view>
          </view>
          <view class="notify-time">{{ item.created_at }}</view>
        </view>
        <view class="notify-desc">{{ item.content }}</view>
      </view>
      <view v-if="!personalNotifications.length" class="empty">暂无个人通知</view>
    </view>

    <view class="section">
      <view class="section-header">
        <view class="section-title">公告</view>
        <view class="tabs">
          <text
            v-for="item in announcementTabs"
            :key="item.value"
            :class="{ active: activeAnnouncement === item.value }"
            @click="switchAnnouncement(item.value)"
          >
            {{ item.label }}
          </text>
        </view>
      </view>
      <view
        class="notify-card"
        v-for="item in announcements"
        :key="item.id"
        @click="openNotification(item, 'announcement')"
      >
        <view class="notify-head">
          <view class="notify-title">
            <text>{{ item.title }}</text>
            <view v-if="!item.is_read" class="dot"></view>
          </view>
          <view class="notify-time">{{ item.published_at || item.created_at }}</view>
        </view>
        <view class="notify-desc">{{ item.content }}</view>
      </view>
      <view v-if="!announcements.length" class="empty">暂无公告</view>
    </view>

    <view class="section quick-links">
      <view class="list-item" @click="openSelector('direct')">
        <text>单聊</text>
      </view>
      <view class="list-item" @click="openSelector('group')">
        <text>群聊</text>
      </view>
    </view>

    <view class="section chat-section">
      <view class="section-header">
        <view class="section-title">最近会话</view>
        <text class="link" @click="viewChatList">查看全部</text>
      </view>
      <view v-if="chatConversations.length" class="chat-list">
        <view class="chat-item" v-for="item in chatConversations" :key="item.room_id" @click="openConversation(item)">
          <view class="chat-info">
            <view class="chat-name">{{ item.name }}</view>
            <view class="chat-desc">{{ item.last_message?.content || '暂无消息' }}</view>
          </view>
          <view class="chat-meta">
            <text class="time">{{ item.last_message?.created_at || '' }}</text>
            <view v-if="item.unread" class="badge">{{ formatUnread(item.unread) }}</view>
          </view>
        </view>
      </view>
      <view v-else class="empty">暂无聊天记录</view>
    </view>
  </scroll-view>
</template>

<script setup>
import { ref, computed } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import store from '../../store'
import { api } from '../../utils/request'
import { refreshMessageSummary } from '../../utils/message-center'

const keyword = ref('')
const personalNotifications = ref([])
const announcements = ref([])
const conversations = ref([])
const announcementTabs = [
  { label: '全部通知', value: 'all' },
  { label: '系统', value: 'system' },
  { label: '任务', value: 'task' }
]
const notifyFilters = [
  { label: '未读', value: 'unread' },
  { label: '全部', value: 'all' }
]
const activeAnnouncement = ref('all')
const activeNotify = ref('unread')

const fetchPersonalNotifications = async () => {
  const params = {
    keyword: keyword.value,
    status: activeNotify.value,
    limit: 20
  }
  const res = await api.notifications(params)
  personalNotifications.value = res.items || []
}

const fetchAnnouncements = async () => {
  const params = {
    keyword: keyword.value,
    limit: 20
  }
  if (activeAnnouncement.value !== 'all') {
    params.category = activeAnnouncement.value
  }
  const res = await api.announcements(params)
  announcements.value = res.items || []
}

const fetchConversations = async () => {
  const res = await api.chatConversations()
  conversations.value = (res.items || []).slice(0, 3)
}

const loadData = async () => {
  await Promise.all([fetchPersonalNotifications(), fetchAnnouncements(), fetchConversations(), refreshMessageSummary()])
}

const switchAnnouncement = (value) => {
  activeAnnouncement.value = value
  fetchAnnouncements()
}

const switchNotifyFilter = (value) => {
  activeNotify.value = value
  fetchPersonalNotifications()
}

const handleSearch = () => {
  loadData()
}

const openNotification = async (item, type) => {
  if (type === 'announcement') {
    if (!item.is_read) {
      await api.announcementMarkRead(item.id)
      item.is_read = true
      refreshMessageSummary()
    }
  } else if (!item.is_read) {
    await api.notificationMarkRead(item.id)
    item.is_read = true
    refreshMessageSummary()
  }
  uni.showModal({
    title: item.title,
    content: item.content || '暂无详情',
    showCancel: false
  })
}

const openSelector = (mode) => {
  const title = mode === 'group' ? '选择群成员' : '选择联系人'
  const encoded = encodeURIComponent(title)
  uni.navigateTo({ url: `/pages/messages/contact-select?mode=${mode}&title=${encoded}` })
}

const createChat = () => {
  uni.showActionSheet({
    itemList: ['发起单聊', '创建群聊'],
    success: (res) => {
      if (res.tapIndex === 0) {
        openSelector('direct')
      } else {
        openSelector('group')
      }
    }
  })
}

const openConversation = (item) => {
  if (!item) return
  const title = encodeURIComponent(item.name || '聊天')
  uni.navigateTo({ url: `/pages/messages/chat?id=${item.room_id}&title=${title}` })
}

const viewChatList = () => {
  uni.navigateTo({ url: '/pages/messages/group?type=all&title=' + encodeURIComponent('全部会话') })
}

const chatConversations = computed(() => conversations.value || [])
const formatUnread = (count) => {
  if (!count) return ''
  return count > 99 ? '99+' : count
}

const profile = computed(() => store.state.profile || {})
const profileName = computed(() => profile.value.name || '张三')
const deptName = computed(() => {
  if (profile.value.dept) return profile.value.dept.name
  return '销售部门'
})
const avatar = computed(() => profile.value.avatar_url || '/static/icons/avatar.png')
const summaryData = computed(() => store.state.messageSummary || {})
const summaryCards = computed(() => {
  const notifySummary = summaryData.value.notifications || {}
  const chatSummary = summaryData.value.chats || {}
  return [
    { label: '未读通知', value: notifySummary.personal || 0 },
    { label: '未读公告', value: notifySummary.announcements || 0 },
    { label: '未读消息', value: chatSummary.total || 0 }
  ]
})

onShow(loadData)
</script>

<style scoped lang="scss">
.page {
  padding: 32rpx;
  background: #f6f7fb;
}
.profile-card {
  display: flex;
  gap: 20rpx;
  align-items: center;
  background: #fff;
  padding: 24rpx;
  border-radius: 24rpx;
}
.avatar {
  width: 96rpx;
  height: 96rpx;
  border-radius: 48rpx;
}
.name {
  font-size: 32rpx;
  font-weight: 600;
}
.dept {
  font-size: 24rpx;
  color: #999;
  margin-top: 4rpx;
}
.search-row {
  display: flex;
  gap: 12rpx;
  align-items: center;
  margin: 24rpx 0;
}
.search {
  flex: 1;
  background: #fff;
  border-radius: 16rpx;
  padding: 12rpx 20rpx;
}
.search input {
  width: 100%;
}
.search-btn {
  background: #1677ff;
  color: #fff;
  border-radius: 16rpx;
}
.add-btn {
  width: 60rpx;
  height: 60rpx;
  border-radius: 16rpx;
  background: #fff;
  color: #1677ff;
  border: 1rpx solid #d6e4ff;
}
.summary-panel {
  display: flex;
  gap: 20rpx;
  margin-bottom: 24rpx;
}
.summary-item {
  flex: 1;
  background: #fff;
  border-radius: 24rpx;
  padding: 20rpx;
  text-align: center;
}
.summary-value {
  font-size: 36rpx;
  font-weight: 600;
  color: #1677ff;
}
.summary-label {
  font-size: 24rpx;
  color: #999;
  margin-top: 6rpx;
}
.section {
  background: #fff;
  border-radius: 24rpx;
  padding: 24rpx;
  margin-bottom: 24rpx;
}
.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12rpx;
}
.section-title {
  font-size: 30rpx;
  font-weight: 600;
}
.tabs {
  display: flex;
  gap: 24rpx;
  font-size: 26rpx;
  color: #999;
}
.tabs .active {
  color: #1677ff;
  font-weight: 600;
}
.tabs.compact {
  gap: 12rpx;
}
.notify-card {
  background: #f6f7fb;
  border-radius: 20rpx;
  padding: 20rpx;
  margin-bottom: 20rpx;
}
.notify-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8rpx;
}
.notify-title {
  display: flex;
  align-items: center;
  gap: 8rpx;
  font-size: 28rpx;
  font-weight: 600;
}
.notify-time {
  font-size: 22rpx;
  color: #999;
}
.notify-desc {
  font-size: 24rpx;
  color: #666;
}
.dot {
  width: 12rpx;
  height: 12rpx;
  border-radius: 50%;
  background: #ff4d4f;
}
.quick-links .list-item {
  display: flex;
  justify-content: flex-start;
  align-items: center;
  padding: 20rpx 0;
  border-bottom: 1rpx solid #f0f0f0;
  font-size: 28rpx;
}
.quick-links .list-item:last-child {
  border-bottom: none;
}
.chat-section .chat-list {
  display: flex;
  flex-direction: column;
  gap: 16rpx;
}
.chat-item {
  display: flex;
  justify-content: space-between;
  gap: 16rpx;
  padding: 16rpx 0;
  border-bottom: 1rpx solid #f0f0f0;
}
.chat-item:last-child {
  border-bottom: none;
}
.chat-info {
  flex: 1;
}
.chat-name {
  font-size: 28rpx;
  font-weight: 600;
}
.chat-desc {
  font-size: 24rpx;
  color: #999;
  margin-top: 6rpx;
}
.chat-meta {
  text-align: right;
  min-width: 140rpx;
}
.chat-meta .time {
  font-size: 22rpx;
  color: #999;
}
.badge {
  margin-top: 8rpx;
  background: #ff4d4f;
  color: #fff;
  font-size: 22rpx;
  padding: 4rpx 12rpx;
  border-radius: 20rpx;
  display: inline-block;
}
.empty {
  text-align: center;
  color: #999;
  padding: 32rpx 0;
}
</style>
