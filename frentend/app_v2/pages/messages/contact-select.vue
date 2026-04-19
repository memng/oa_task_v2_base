<template>
  <view class="page">
    <view class="search-bar">
      <input
        v-model="keyword"
        placeholder="输入成员姓名/手机号"
        confirm-type="search"
        @confirm="fetchStaff"
      />
      <button class="search-btn" size="mini" @click="fetchStaff">搜索</button>
    </view>

    <view class="selected-bar">
      <text>已选 {{ selectedCount }} 人</text>
      <button class="primary" size="mini" :disabled="!canSubmit" @click="confirmSelection">
        {{ submitText }}
      </button>
    </view>

    <scroll-view scroll-y class="list">
      <view v-for="dept in groupedStaff" :key="dept.name" class="dept-block">
        <view class="dept-title">{{ dept.name }}</view>
        <view
          v-for="member in dept.members"
          :key="member.id"
          class="member-item"
          @click="toggleMember(member)"
        >
          <image class="avatar" :src="member.avatar_url || defaultAvatar" mode="aspectFill" />
          <view class="info">
            <view class="name">{{ member.name }}</view>
            <view class="desc">{{ member.mobile || '未填写手机号' }}</view>
          </view>
          <view class="check" :class="{ active: isSelected(member.id) }"></view>
        </view>
      </view>
      <view v-if="!groupedStaff.length" class="empty">暂无可选成员</view>
    </scroll-view>
  </view>
</template>

<script setup>
import { ref, computed } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { api } from '../../utils/request'

const mode = ref('group')
const keyword = ref('')
const staff = ref([])
const selectedIds = ref([])
const loading = ref(false)
const defaultAvatar = '/static/icons/avatar.png'

const isGroupMode = computed(() => mode.value === 'group')
const selectedCount = computed(() => selectedIds.value.length)
const submitText = computed(() => (isGroupMode.value ? '创建群聊' : '开始会话'))
const canSubmit = computed(() => {
  if (!selectedCount.value) return false
  if (isGroupMode.value) {
    return selectedCount.value >= 2
  }
  return true
})

const groupedStaff = computed(() => {
  if (!staff.value.length) return []
  const map = new Map()
  staff.value.forEach((item) => {
    const key = item.dept_name || '未分配部门'
    if (!map.has(key)) {
      map.set(key, [])
    }
    map.get(key).push(item)
  })
  return Array.from(map.entries()).map(([name, members]) => ({
    name,
    members
  }))
})

const isSelected = (id) => selectedIds.value.includes(id)

const toggleMember = (member) => {
  if (isGroupMode.value) {
    if (isSelected(member.id)) {
      selectedIds.value = selectedIds.value.filter((item) => item !== member.id)
    } else {
      selectedIds.value = [...selectedIds.value, member.id]
    }
  } else {
    if (isSelected(member.id)) {
      selectedIds.value = []
    } else {
      selectedIds.value = [member.id]
    }
  }
}

const confirmSelection = async () => {
  if (!canSubmit.value) {
    const tip = isGroupMode.value ? '请至少选择两位成员' : '请选择一位成员'
    uni.showToast({ title: tip, icon: 'none' })
    return
  }
  try {
    uni.showLoading({ title: '创建中', mask: true })
    const payload = {
      type: isGroupMode.value ? 'group' : 'direct',
      member_ids: selectedIds.value
    }
    const res = await api.chatCreate(payload)
    const roomId = res.room_id
    const title = isGroupMode.value
      ? '群聊'
      : (staff.value.find((member) => member.id === selectedIds.value[0])?.name || '聊天')
    const encoded = encodeURIComponent(title)
    uni.redirectTo({
      url: `/pages/messages/chat?id=${roomId}&title=${encoded}`
    })
  } catch (error) {
    uni.showToast({ title: '创建失败', icon: 'none' })
  } finally {
    uni.hideLoading()
  }
}

const fetchStaff = async () => {
  loading.value = true
  try {
    const res = await api.lookupStaff({ keyword: keyword.value })
    staff.value = res.items || []
  } catch (error) {
    console.error(error)
  } finally {
    loading.value = false
  }
}

onLoad((options) => {
  mode.value = options?.mode === 'direct' ? 'direct' : 'group'
  if (options?.title) {
    uni.setNavigationBarTitle({ title: decodeURIComponent(options.title) })
  } else {
    uni.setNavigationBarTitle({ title: mode.value === 'direct' ? '选择联系人' : '选择群成员' })
  }
  fetchStaff()
})
</script>

<style scoped lang="scss">
.page {
  display: flex;
  flex-direction: column;
  height: 100vh;
  padding: 32rpx;
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
.selected-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #fff;
  border-radius: 16rpx;
  padding: 16rpx 20rpx;
  margin-bottom: 16rpx;
  font-size: 26rpx;
}
.selected-bar .primary {
  background: #1677ff;
  color: #fff;
  border-radius: 24rpx;
  padding: 0 24rpx;
}
.selected-bar .primary:disabled {
  opacity: 0.5;
}
.list {
  flex: 1;
}
.dept-block {
  margin-bottom: 24rpx;
}
.dept-title {
  font-size: 26rpx;
  color: #999;
  margin-bottom: 12rpx;
}
.member-item {
  display: flex;
  align-items: center;
  padding: 16rpx;
  background: #fff;
  border-radius: 16rpx;
  margin-bottom: 12rpx;
}
.avatar {
  width: 72rpx;
  height: 72rpx;
  border-radius: 50%;
  margin-right: 16rpx;
}
.info {
  flex: 1;
}
.name {
  font-size: 28rpx;
  font-weight: 600;
}
.desc {
  font-size: 24rpx;
  color: #999;
  margin-top: 4rpx;
}
.check {
  width: 32rpx;
  height: 32rpx;
  border-radius: 16rpx;
  border: 2rpx solid #d9d9d9;
}
.check.active {
  border-color: #1677ff;
  background: #1677ff;
}
.empty {
  text-align: center;
  color: #999;
  margin-top: 60rpx;
}
</style>
