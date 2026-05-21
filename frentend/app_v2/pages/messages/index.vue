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
      <view class="summary-item" v-for="card in summaryCards" :key="card.label" @click="switchBusinessFilter(card.business)">
        <view class="summary-value">{{ card.value }}</view>
        <view class="summary-label">{{ card.label }}</view>
      </view>
    </view>

    <view class="section">
      <view class="section-header">
        <view class="section-title">通知</view>
        <view class="tabs">
          <text
            v-for="item in businessTabs"
            :key="item.value"
            :class="{ active: activeBusiness === item.value }"
            @click="switchBusinessFilter(item.value)"
          >
            {{ item.label }}
          </text>
        </view>
      </view>
      <view class="filter-row">
        <text
          v-for="item in notifyFilters"
          :key="item.value"
          :class="['filter-btn', { active: activeNotify === item.value }]"
          @click="switchNotifyFilter(item.value)"
        >
          {{ item.label }}
        </text>
      </view>
      <view
        class="notify-card"
        v-for="item in personalNotifications"
        :key="item.id"
        @click="openNotification(item, 'notification')"
      >
        <view class="notify-head">
          <view class="notify-title">
            <view class="biz-tag" :class="'biz-' + getBusinessType(item)">{{ getBusinessLabel(item) }}</view>
            <text>{{ item.title }}</text>
            <view v-if="!item.is_read" class="dot"></view>
          </view>
          <view class="notify-time">{{ item.created_at }}</view>
        </view>
        <view class="notify-desc">{{ item.content }}</view>
      </view>
      <view v-if="!personalNotifications.length && !loading" class="empty">暂无{{ activeBusinessLabel }}通知</view>
      <view v-if="loading" class="loading-more">加载中...</view>
      <view
        v-else-if="hasMore && personalNotifications.length > 0"
        class="load-more-btn"
        @click="loadMoreNotifications"
      >
        点击加载更多
      </view>
      <view v-else-if="!hasMore && personalNotifications.length > 0" class="no-more">没有更多了</view>
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
import {
  navigateToDetail,
  canNavigateToDetail,
  getActionLabelByNotification
} from '../../utils/notification-router'

const BUSINESS_TYPES = {
  TASK: 'task',
  APPROVAL: 'approval',
  SYSTEM: 'system'
}

const TASK_TEMPLATE_CODES = ['task_assigned', 'task_urged', 'order_created']
const APPROVAL_TEMPLATE_CODES = ['leave_approved', 'leave_rejected', 'reimburse_approved', 'reimburse_rejected']

function getBusinessType(notification) {
  const templateCode = notification.template_code || ''
  const payloadType = notification.payload?.type || ''

  if (
    TASK_TEMPLATE_CODES.includes(templateCode) ||
    TASK_TEMPLATE_CODES.includes(payloadType) ||
    templateCode.includes('task') ||
    payloadType.includes('task') ||
    templateCode === 'order_created' ||
    payloadType === 'order_created'
  ) {
    return BUSINESS_TYPES.TASK
  }
  if (
    APPROVAL_TEMPLATE_CODES.includes(templateCode) ||
    APPROVAL_TEMPLATE_CODES.includes(payloadType) ||
    templateCode.includes('leave') ||
    payloadType.includes('leave') ||
    templateCode.includes('reimburse') ||
    payloadType.includes('reimburse')
  ) {
    return BUSINESS_TYPES.APPROVAL
  }
  return BUSINESS_TYPES.SYSTEM
}

function getBusinessLabel(notification) {
  const type = getBusinessType(notification)
  const labelMap = {
    [BUSINESS_TYPES.TASK]: '任务',
    [BUSINESS_TYPES.APPROVAL]: '审批',
    [BUSINESS_TYPES.SYSTEM]: '系统'
  }
  return labelMap[type] || '系统'
}

const keyword = ref('')
const personalNotifications = ref([])
const announcements = ref([])
const conversations = ref([])
const businessTabs = [
  { label: '全部', value: 'all' },
  { label: '任务', value: BUSINESS_TYPES.TASK },
  { label: '审批', value: BUSINESS_TYPES.APPROVAL },
  { label: '系统', value: BUSINESS_TYPES.SYSTEM }
]
const announcementTabs = [
  { label: '全部通知', value: 'all' },
  { label: '系统', value: 'system' },
  { label: '任务', value: 'task' }
]
const notifyFilters = [
  { label: '未读', value: 'unread' },
  { label: '全部', value: 'all' }
]
const PAGE_SIZE = 50
const activeBusiness = ref('all')
const activeAnnouncement = ref('all')
const activeNotify = ref('unread')
const listOffset = ref(0)
const hasMore = ref(true)
const loading = ref(false)
let requestId = 0

const activeBusinessLabel = computed(() => {
  const tab = businessTabs.find(t => t.value === activeBusiness.value)
  return tab ? tab.label : ''
})

const resetPagination = () => {
  listOffset.value = 0
  hasMore.value = true
  personalNotifications.value = []
}

const fetchPersonalNotifications = async (reset = true) => {
  if (reset) {
    requestId++
  } else {
    if (loading.value || !hasMore.value) return
  }

  const currentRequestId = requestId

  if (reset) {
    resetPagination()
  }

  if (!hasMore.value) return

  loading.value = true
  try {
    const params = {
      keyword: keyword.value,
      status: activeNotify.value,
      business_type: activeBusiness.value,
      limit: PAGE_SIZE,
      offset: listOffset.value
    }
    const res = await api.notifications(params)

    if (currentRequestId !== requestId) {
      return
    }

    const newItems = res.items || []
    const total = res.total || 0

    if (reset) {
      personalNotifications.value = newItems
    } else {
      personalNotifications.value = [...personalNotifications.value, ...newItems]
    }

    listOffset.value = personalNotifications.value.length
    hasMore.value = personalNotifications.value.length < total
  } catch (error) {
    if (currentRequestId === requestId) {
      console.warn('fetch notifications failed', error)
    }
  } finally {
    if (currentRequestId === requestId) {
      loading.value = false
    }
  }
}

const loadMoreNotifications = () => {
  if (!hasMore.value || loading.value) return
  fetchPersonalNotifications(false)
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

const switchBusinessFilter = (value) => {
  if (value && value !== 'all' && !businessTabs.find(t => t.value === value)) {
    return
  }
  activeBusiness.value = value || 'all'
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
    uni.showModal({
      title: item.title,
      content: item.content || '暂无详情',
      showCancel: false
    })
  } else {
    const wasUnread = !item.is_read
    if (wasUnread) {
      await api.notificationMarkRead(item.id)
      item.is_read = true
      refreshMessageSummary()
      if (activeNotify.value === 'unread') {
        const idx = personalNotifications.value.findIndex(n => n.id === item.id)
        if (idx >= 0) {
          personalNotifications.value.splice(idx, 1)
          listOffset.value = Math.max(0, listOffset.value - 1)
        }
      }
    }
    if (canNavigateToDetail(item)) {
      navigateToDetail(item)
    } else {
      uni.showModal({
        title: item.title,
        content: item.content || '暂无详情',
        showCancel: false
      })
    }
  }
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
const businessSummary = computed(() => summaryData.value.notifications?.by_business || {})
const summaryCards = computed(() => {
  return [
    { label: '任务', value: businessSummary.value.task || 0, business: BUSINESS_TYPES.TASK },
    { label: '审批', value: businessSummary.value.approval || 0, business: BUSINESS_TYPES.APPROVAL },
    { label: '系统', value: businessSummary.value.system || 0, business: BUSINESS_TYPES.SYSTEM }
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
.filter-row {
  display: flex;
  gap: 12rpx;
  margin-bottom: 16rpx;
}
.filter-btn {
  font-size: 24rpx;
  color: #666;
  padding: 8rpx 20rpx;
  border-radius: 20rpx;
  background: #f6f7fb;
}
.filter-btn.active {
  color: #1677ff;
  background: #e6f4ff;
}
.biz-tag {
  font-size: 20rpx;
  padding: 4rpx 12rpx;
  border-radius: 8rpx;
  margin-right: 8rpx;
  font-weight: 500;
}
.biz-tag.biz-task {
  background: #fff7e6;
  color: #fa8c16;
}
.biz-tag.biz-approval {
  background: #f6ffed;
  color: #52c41a;
}
.biz-tag.biz-system {
  background: #e6f4ff;
  color: #1677ff;
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
.loading-more,
.no-more {
  text-align: center;
  color: #999;
  padding: 24rpx 0;
  font-size: 24rpx;
}
.load-more-btn {
  text-align: center;
  color: #1677ff;
  padding: 24rpx 0;
  font-size: 26rpx;
  background: #f6f7fb;
  border-radius: 16rpx;
  margin-top: 8rpx;
}
</style>
