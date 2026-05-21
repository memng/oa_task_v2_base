<template>
  <scroll-view scroll-y class="page">
    <view class="search-bar">
      <input v-model="keyword" placeholder="请输入关键词搜索" confirm-type="search" @confirm="fetchList" />
      <button class="search-btn" size="mini" @click="fetchList">搜索</button>
    </view>

    <view class="group-tabs">
      <view
        v-for="tab in tabs"
        :key="tab.value"
        class="tab-item"
        :class="{ active: currentTab === tab.value }"
        @click="switchTab(tab.value)"
      >
        <text>{{ tab.label }}</text>
        <text v-if="tab.count > 0" class="badge">{{ tab.count > 99 ? '99+' : tab.count }}</text>
      </view>
    </view>

    <view class="toolbar">
      <picker :range="statusFilters" range-key="label" @change="onStatusFilterChange">
        <view class="filter-chip">{{ currentStatusFilter.label }}</view>
      </picker>
      <view
        v-if="currentGroupUnread > 0"
        class="read-group-btn"
        @click="handleReadGroup"
      >
        当前分组已读
      </view>
      <view
        v-if="summary.total > 0 && currentStatusFilter.value.value !== 'read'"
        class="read-all-btn"
        @click="handleReadAll"
      >
        全部已读
      </view>
    </view>

    <view class="notice-card" v-for="item in list" :key="item.id" @click="openNotification(item)">
      <view class="notice-top">
        <view>
          <view class="title">
            {{ item.title }}
            <view v-if="!item.is_read" class="dot"></view>
          </view>
          <view class="desc">{{ item.content }}</view>
        </view>
        <view class="time">{{ item.created_at }}</view>
      </view>
      <view class="actions" v-if="canNavigate(item)">
        <text class="link">{{ getActionLabel(item) }}</text>
        <text class="status">{{ item.is_read ? '已读' : '未读' }}</text>
      </view>
      <view class="actions" v-else>
        <text class="status">{{ item.is_read ? '已读' : '未读' }}</text>
      </view>
    </view>
    <view v-if="!list.length && !loading" class="empty">暂无通知</view>
  </scroll-view>
</template>

<script setup>
import { ref, computed } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { api } from '../../utils/request'
import {
  navigateToDetail,
  canNavigateToDetail,
  getActionLabelByNotification
} from '../../utils/notification-router'
import { refreshMessageSummary } from '../../utils/message-center'

const keyword = ref('')
const list = ref([])
const loading = ref(false)

const statusFilters = [
  { label: '全部', value: 'all' },
  { label: '未读', value: 'unread' },
  { label: '已读', value: 'read' }
]
const currentStatusFilter = ref(statusFilters[0])

const currentTab = ref('all')
const summary = ref({ total: 0, task: 0, approval: 0, system: 0 })

const tabs = computed(() => ([
  { label: '全部', value: 'all', count: summary.value.total },
  { label: '任务', value: 'task', count: summary.value.task },
  { label: '审批', value: 'approval', count: summary.value.approval },
  { label: '系统', value: 'system', count: summary.value.system }
]))

const currentGroupUnread = computed(() => {
  if (currentStatusFilter.value.value === 'read') return 0
  const v = currentTab.value
  if (v === 'all') return summary.value.total
  return summary.value[v] || 0
})

const fetchList = async () => {
  loading.value = true
  try {
    const params = { keyword: keyword.value }
    const status = currentStatusFilter.value.value
    if (status === 'unread') {
      params.status = 'unread'
    } else if (status === 'read') {
      params.status = 'read'
    }
    if (currentTab.value !== 'all') {
      params.business_type = currentTab.value
    }
    const res = await api.notifications(params)
    list.value = res.items || []
  } finally {
    loading.value = false
  }
}

const fetchSummary = async () => {
  try {
    const data = await api.notificationReadSummary()
    if (data) {
      summary.value = {
        total: data.total || 0,
        task: data.task || 0,
        approval: data.approval || 0,
        system: data.system || 0
      }
    }
  } catch (e) {
    // ignore
  }
}

const switchTab = (value) => {
  currentTab.value = value
  fetchList()
}

const onStatusFilterChange = (e) => {
  currentStatusFilter.value = statusFilters[e.detail.value]
  fetchList()
}

const canNavigate = (item) => {
  return canNavigateToDetail(item)
}

const getActionLabel = (item) => {
  return getActionLabelByNotification(item)
}

const openNotification = async (item) => {
  if (!item.is_read) {
    try {
      await api.notificationMarkRead(item.id)
      item.is_read = true
      refreshMessageSummary()
      await fetchSummary()
    } catch (e) {
      uni.showToast({ title: '标记已读失败，请重试', icon: 'none' })
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

const handleReadAll = () => {
  uni.showModal({
    title: '提示',
    content: '确定将所有通知标记为已读？',
    success: async (res) => {
      if (!res.confirm) return
      try {
        const data = await api.notificationReadAll()
        uni.showToast({
          title: `已将 ${data.affected || 0} 条通知标记为已读`,
          icon: 'none'
        })
        summary.value = { total: 0, task: 0, approval: 0, system: 0 }
        list.value = list.value.map(i => ({ ...i, is_read: true }))
        refreshMessageSummary()
      } catch (e) {
        uni.showToast({ title: '操作失败，请重试', icon: 'none' })
      }
    }
  })
}

const handleReadGroup = () => {
  const tab = currentTab.value
  const payload = {}
  if (tab !== 'all') {
    payload.business_type = tab
  } else {
    payload.ids = list.value.filter(i => !i.is_read).map(i => i.id)
    if (!payload.ids.length) {
      uni.showToast({ title: '没有未读通知', icon: 'none' })
      return
    }
  }
  uni.showModal({
    title: '提示',
    content: tab === 'all' ? '确定将当前列表的未读标记为已读？' : '确定将该分组的未读标记为已读？',
    success: async (res) => {
      if (!res.confirm) return
      try {
        const data = await api.notificationReadGroup(payload)
        uni.showToast({
          title: `已将 ${data.affected || 0} 条通知标记为已读`,
          icon: 'none'
        })
        await fetchSummary()
        fetchList()
        refreshMessageSummary()
      } catch (e) {
        uni.showToast({ title: '操作失败，请重试', icon: 'none' })
      }
    }
  })
}

onShow(() => {
  fetchSummary()
  fetchList()
})
</script>

<style scoped lang="scss">
.page {
  padding: 32rpx;
  background: #f6f7fb;
  height: 100vh;
  box-sizing: border-box;
}
.search-bar {
  display: flex;
  gap: 12rpx;
  margin-bottom: 16rpx;
}
.search-bar input {
  flex: 1;
  background: #fff;
  border-radius: 16rpx;
  padding: 16rpx;
}
.search-btn {
  background: #1677ff;
  color: #fff;
  border-radius: 16rpx;
}
.group-tabs {
  display: flex;
  background: #fff;
  border-radius: 16rpx;
  padding: 8rpx;
  margin-bottom: 16rpx;
  gap: 8rpx;
}
.tab-item {
  flex: 1;
  text-align: center;
  padding: 16rpx 0;
  border-radius: 12rpx;
  font-size: 26rpx;
  color: #333;
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6rpx;
}
.tab-item.active {
  background: #1677ff;
  color: #fff;
}
.tab-item.active .badge {
  background: #fff;
  color: #1677ff;
}
.badge {
  min-width: 28rpx;
  padding: 0 8rpx;
  height: 28rpx;
  line-height: 28rpx;
  border-radius: 14rpx;
  font-size: 20rpx;
  background: #ff4d4f;
  color: #fff;
  text-align: center;
}
.toolbar {
  display: flex;
  align-items: center;
  gap: 16rpx;
  margin-bottom: 24rpx;
}
.filter-chip {
  background: #fff;
  border-radius: 32rpx;
  padding: 12rpx 24rpx;
  font-size: 24rpx;
  color: #333;
}
.read-group-btn,
.read-all-btn {
  margin-left: auto;
  font-size: 24rpx;
  color: #1677ff;
  padding: 12rpx 20rpx;
  background: #e6f0ff;
  border-radius: 32rpx;
}
.read-all-btn {
  margin-left: 0;
  background: #1677ff;
  color: #fff;
}
.notice-card {
  background: #fff;
  border-radius: 24rpx;
  padding: 24rpx;
  margin-bottom: 20rpx;
}
.notice-top {
  display: flex;
  justify-content: space-between;
  gap: 20rpx;
}
.title {
  font-size: 30rpx;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 8rpx;
}
.dot {
  width: 12rpx;
  height: 12rpx;
  border-radius: 50%;
  background: #ff4d4f;
}
.desc {
  font-size: 24rpx;
  color: #666;
  margin-top: 8rpx;
}
.time {
  font-size: 22rpx;
  color: #999;
}
.actions {
  margin-top: 16rpx;
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 24rpx;
}
.link {
  color: #1677ff;
}
.status {
  color: #999;
}
.empty {
  text-align: center;
  color: #999;
  padding-top: 60rpx;
}
</style>
