<template>
  <scroll-view scroll-y class="page">
    <view class="intro-card">
      <view class="intro-title">{{ pageTitle }}</view>
      <view class="intro-desc">{{ pageDesc }}</view>
    </view>

    <view class="search-bar">
      <input
        v-model="keyword"
        class="search-input"
        placeholder="请输入关键词搜索"
        confirm-type="search"
        @confirm="fetchTasks"
      />
      <button class="search-btn" size="mini" @click="fetchTasks">搜索</button>
    </view>

    <view class="filter-toolbar">
      <scroll-view scroll-x class="filter-scroll">
        <view class="filter-group">
          <text class="filter-label">优先级:</text>
          <view
            class="filter-chip"
            :class="{ active: selectedPriority === null }"
            @click="selectPriority(null)"
          >全部</view>
          <view
            v-for="p in priorityOptions"
            :key="p.value"
            class="filter-chip"
            :class="{ active: selectedPriority === p.value }"
            :style="selectedPriority === p.value ? { color: p.color, borderColor: p.color, background: p.color + '15' } : {}"
            @click="selectPriority(p.value)"
          >{{ p.label }}</view>
        </view>
        <view class="filter-group">
          <text class="filter-label">标签:</text>
          <view
            class="filter-chip"
            :class="{ active: selectedTag === null }"
            @click="selectTag(null)"
          >全部</view>
          <view
            v-for="tag in tagOptions"
            :key="tag.value"
            class="filter-chip"
            :class="{ active: selectedTag === tag.value }"
            :style="selectedTag === tag.value ? { color: tag.color, borderColor: tag.color, background: tag.color + '15' } : {}"
            @click="selectTag(tag.value)"
          >{{ tag.label }}</view>
        </view>
      </scroll-view>
    </view>

    <view v-if="!batchMode" class="normal-toolbar">
      <button class="batch-enter-btn" size="mini" @click="enterBatchMode">批量操作</button>
    </view>

    <view v-if="batchMode" class="batch-toolbar">
      <view class="batch-left" @tap="toggleSelectAll">
        <checkbox :checked="isAllSelected" />
        <text class="batch-label">全选</text>
      </view>
      <view class="batch-center">
        <text class="batch-count">已选 {{ selectedIds.length }} 项</text>
      </view>
      <view class="batch-right">
        <button class="batch-action-btn assign" size="mini" :disabled="!selectedIds.length" @click="openAssignSheet">指派</button>
        <button class="batch-action-btn urge" size="mini" :disabled="!selectedIds.length" @click="handleBatchUrge">催办</button>
        <button class="batch-cancel-btn" size="mini" @click="exitBatchMode">完成</button>
      </view>
    </view>

    <view class="task-list">
      <view v-for="task in formattedTasks" :key="task.id" class="task-card" :class="{ 'task-card-batch': batchMode }">
        <view v-if="batchMode" class="task-select-area" @tap="toggleSelect(task.id)">
          <checkbox :checked="selectedIds.includes(task.id)" />
          <text class="select-hint">选中</text>
        </view>
        <view class="task-content" @tap="openTask(task)">
          <view class="task-head">
            <view class="task-head-left">
              <view class="title-row">
                <text class="priority-tag" :style="{ color: task.priority_color, borderColor: task.priority_color }">{{ task.priority_label }}</text>
                <view class="task-title">{{ task.title }}</view>
              </view>
              <view class="task-type">{{ task.type }}</view>
            </view>
            <view class="task-status" :class="task.status">{{ task.statusLabel }}</view>
          </view>
          <view class="task-tags" v-if="task.tags && task.tags.length > 0">
            <view class="tag-item" v-for="tag in task.tags" :key="tag.key" :style="{ color: tag.color, borderColor: tag.color }">
              {{ tag.label }}
            </view>
          </view>
          <view class="task-body">
            <view class="task-desc">{{ task.desc }}</view>
            <view class="meta">
              <text>执行人：{{ task.owner || '待分配' }}</text>
              <text>截止：{{ task.deadline || '待定' }}</text>
            </view>
            <view class="meta secondary" v-if="task.orderPi">
              <text>订单：{{ task.orderPi }}</text>
              <text>{{ task.customer || '' }}</text>
            </view>
          </view>
          <view v-if="batchMode" class="task-detail-hint">
            <text class="detail-hint-text">点击查看详情 →</text>
          </view>
          <view v-else class="task-actions">
            <button class="outline" size="mini" @click.stop="openTask(task)">详情</button>
            <button class="outline" size="mini" @click.stop="openOrder(task)">订单详情</button>
            <button class="primary" size="mini" @click.stop="handlePrimary(task)">{{ primaryLabel }}</button>
          </view>
        </view>
      </view>
      <view v-if="!formattedTasks.length && !loading" class="empty">暂无任务</view>
      <view v-if="loading" class="loading">加载中...</view>
    </view>

    <view v-if="assignSheetVisible" class="sheet-mask" @tap="closeAssignSheet">
      <view class="sheet-body sheet-body-large" @tap.stop>
        <view class="sheet-header">
          <text class="sheet-title">批量指派</text>
          <text class="sheet-close" @tap="closeAssignSheet">✕</text>
        </view>
        <view class="sheet-selected-info">已选择 {{ selectedIds.length }} 个任务</view>
        
        <view class="staff-search-box">
          <input
            v-model="staffKeyword"
            class="staff-search-input"
            placeholder="搜索成员姓名或手机号"
            confirm-type="search"
            @confirm="onSearchStaff"
            @input="onStaffKeywordInput"
          />
        </view>

        <scroll-view scroll-x class="dept-tabs">
          <view
            class="dept-tab"
            :class="{ 'dept-tab-active': !selectedDeptId }"
            @tap="selectDept(null)"
          >全部</view>
          <view
            v-for="dept in deptList"
            :key="dept.id"
            class="dept-tab"
            :class="{ 'dept-tab-active': selectedDeptId === dept.id }"
            @tap="selectDept(dept.id)"
          >{{ dept.name }}</view>
        </scroll-view>
        
        <scroll-view scroll-y class="staff-list-scroll">
          <view v-if="staffLoading" class="staff-loading">加载中...</view>
          <view v-else-if="filteredStaffList.length === 0" class="staff-empty">未找到匹配的成员</view>
          <template v-else>
            <view v-for="group in groupedStaffList" :key="group.deptName" class="staff-group">
              <view v-if="group.deptName" class="staff-group-header">{{ group.deptName }}</view>
              <view
                v-for="staff in group.members"
                :key="staff.id"
                class="staff-item"
                :class="{ 'staff-item-selected': assignForm.assigned_to === staff.id }"
                @tap="selectStaff(staff)"
              >
                <view class="staff-avatar">{{ staff.name.charAt(0) }}</view>
                <view class="staff-info">
                  <view class="staff-name">{{ staff.name }}</view>
                  <view class="staff-dept">{{ staff.dept_name || '未分配部门' }}</view>
                </view>
                <view v-if="assignForm.assigned_to === staff.id" class="staff-check">✓</view>
              </view>
            </view>
          </template>
        </scroll-view>
        
        <view class="sheet-form">
          <view class="form-item">
            <text class="form-label">开始时间</text>
            <picker mode="date" @change="onStartDatePick">
              <view class="picker-value">{{ assignForm.start_at || '选择开始时间（可选）' }}</view>
            </picker>
          </view>
        </view>
        
        <button class="sheet-submit" :loading="batchLoading" :disabled="!assignForm.assigned_to" @tap="handleBatchAssign">
          {{ assignForm.assigned_to ? `指派给 ${assignForm.assigneeName}` : '请先选择负责人' }}
        </button>
      </view>
    </view>
  </scroll-view>
</template>

<script setup>
import { computed, ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { api } from '../../utils/request'

const mode = ref('order')
const orderId = ref('')
const orderPi = ref('')
const keyword = ref('')
const tasks = ref([])
const loading = ref(false)
const selectedPriority = ref(null)
const selectedTag = ref(null)
const priorityOptions = [
  { value: 0, label: 'P0', name: '最高', color: '#ff4d4f' },
  { value: 1, label: 'P1', name: '高', color: '#fa8c16' },
  { value: 2, label: 'P2', name: '中', color: '#faad14' },
  { value: 3, label: 'P3', name: '低', color: '#52c41a' }
]
const tagOptions = ref([
  { value: 'urgent', label: '紧急', color: '#ff4d4f' },
  { value: 'customer', label: '客户', color: '#1677ff' },
  { value: 'internal', label: '内部', color: '#722ed1' }
])
const fetchTagOptions = async () => {
  try {
    const res = await api.tagOptions()
    if (res && res.items && res.items.length > 0) {
      tagOptions.value = res.items
    }
  } catch (error) {
    console.error('Failed to fetch tag options:', error)
  }
}

const batchMode = ref(false)
const selectedIds = ref([])
const batchLoading = ref(false)
const assignSheetVisible = ref(false)
const staffList = ref([])
const staffLoading = ref(false)
const staffKeyword = ref('')
const selectedDeptId = ref(null)
const deptList = ref([])
let searchTimer = null
const assignForm = ref({
  assigned_to: null,
  assigneeName: '',
  start_at: ''
})

const statusMap = {
  pending: '待处理',
  in_progress: '进行中',
  waiting_audit: '待审核',
  completed: '已完成',
  rejected: '已驳回'
}

const filteredStaffList = computed(() => {
  let list = staffList.value
  if (selectedDeptId.value) {
    list = list.filter(s => s.dept_id === selectedDeptId.value)
  }
  if (staffKeyword.value) {
    const kw = staffKeyword.value.toLowerCase()
    list = list.filter(s =>
      s.name.toLowerCase().includes(kw) ||
      (s.mobile && s.mobile.includes(kw)) ||
      (s.dept_name && s.dept_name.toLowerCase().includes(kw))
    )
  }
  return list
})

const groupedStaffList = computed(() => {
  const list = filteredStaffList.value
  const groups = {}
  list.forEach(s => {
    const key = s.dept_name || '未分配部门'
    if (!groups[key]) {
      groups[key] = { deptName: key, members: [] }
    }
    groups[key].members.push(s)
  })
  return Object.values(groups)
})

const pageTitle = computed(() => {
  if (mode.value === 'factory') return '工厂订单任务'
  if (mode.value === 'temporary') return '临时任务'
  if (mode.value === 'initiated') return '我发起的任务'
  if (mode.value === 'review') return '审核任务'
  return orderPi.value ? `订单 ${orderPi.value}` : '订单任务'
})

const pageDesc = computed(() => {
  if (mode.value === 'factory') return '与你相关的工厂订单任务'
  if (mode.value === 'temporary') return '分配给你的临时任务'
  if (mode.value === 'initiated') return '你创建的全部任务'
  if (mode.value === 'review') return '待审核的任务列表'
  return orderPi.value ? `该订单下的任务（${orderPi.value}）` : '与你相关的订单任务'
})

const primaryLabel = computed(() => (mode.value === 'review' ? '审核' : '处理'))

const formattedTasks = computed(() =>
  tasks.value.map((item) => ({
    id: item.id,
    orderId: item.order_id,
    title: item.title || item.type_label || '任务',
    type: item.type_label || item.type || '任务类型',
    status: item.status || 'pending',
    statusLabel: item.status_label || statusMap[item.status] || '处理中',
    owner: item.assignee_name || item.creator_name || '待分配',
    deadline: item.due_at || item.deadline,
    desc: item.description || item.requirement || '请按要求执行',
    orderPi: item.pi_number || item.order_pi || '',
    customer: item.customer_name || '',
    priority_label: item.priority_label,
    priority_color: item.priority_color,
    tags: item.tags
  }))
)

const isAllSelected = computed(() =>
  formattedTasks.value.length > 0 && selectedIds.value.length === formattedTasks.value.length
)

const buildParams = () => {
  const params = {}
  if (keyword.value) {
    params.keyword = keyword.value
  }
  if (mode.value === 'review') {
    params.scope = 'review'
    params.status = 'waiting_audit'
  } else if (mode.value === 'initiated') {
    params.scope = 'initiated'
  } else {
    params.scope = 'assigned'
  }

  if (mode.value === 'order') {
    params.category = 'order'
    if (orderId.value) {
      params.order_id = orderId.value
    }
  }
  if (mode.value === 'factory') {
    params.type = 'factory_order'
  }
  if (mode.value === 'temporary') {
    params.type = 'temporary'
  }
  if (selectedPriority.value !== null) {
    params.priority = selectedPriority.value
  }
  if (selectedTag.value !== null) {
    params.tag = selectedTag.value
  }
  return params
}

const fetchTasks = async () => {
  loading.value = true
  try {
    const res = await api.taskList(buildParams())
    tasks.value = res.items || []
  } catch (error) {
    console.error(error)
  } finally {
    loading.value = false
  }
}

const enterBatchMode = () => {
  batchMode.value = true
  selectedIds.value = []
}

const exitBatchMode = () => {
  batchMode.value = false
  selectedIds.value = []
}

const toggleSelect = (id) => {
  const idx = selectedIds.value.indexOf(id)
  if (idx >= 0) {
    selectedIds.value.splice(idx, 1)
  } else {
    selectedIds.value.push(id)
  }
}

const toggleSelectAll = () => {
  if (isAllSelected.value) {
    selectedIds.value = []
  } else {
    selectedIds.value = formattedTasks.value.map(t => t.id)
  }
}

const loadStaff = async (keyword = '') => {
  staffLoading.value = true
  try {
    const params = {}
    if (keyword) params.keyword = keyword
    if (selectedDeptId.value) params.dept_id = selectedDeptId.value
    const res = await api.lookupStaff(params)
    staffList.value = res.items || []
  } catch (e) {
    console.error(e)
  } finally {
    staffLoading.value = false
  }
}

const loadDepartments = async () => {
  try {
    const res = await api.departments()
    deptList.value = res || []
  } catch (e) {
    console.error(e)
  }
}

const selectDept = (deptId) => {
  selectedDeptId.value = deptId
  loadStaff(staffKeyword.value.trim())
}

const onStaffKeywordInput = () => {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    onSearchStaff()
  }, 300)
}

const onSearchStaff = () => {
  loadStaff(staffKeyword.value.trim())
}

const selectStaff = (staff) => {
  assignForm.value.assigned_to = staff.id
  assignForm.value.assigneeName = staff.name
}

const openAssignSheet = () => {
  assignForm.value = { assigned_to: null, assigneeName: '', start_at: '' }
  staffKeyword.value = ''
  selectedDeptId.value = null
  staffList.value = []
  loadDepartments()
  loadStaff()
  assignSheetVisible.value = true
}

const closeAssignSheet = () => {
  assignSheetVisible.value = false
}

const onStartDatePick = (e) => {
  assignForm.value.start_at = e.detail.value
}

const showBatchResult = (title, result) => {
  const { success_count, failed_count, failed_tasks } = result
  const total = success_count + failed_count

  if (failed_count === 0) {
    uni.showModal({
      title,
      content: `操作完成\n总数：${total}   成功：${success_count}   失败：0`,
      showCancel: false,
      confirmText: '知道了',
      confirmColor: '#1677ff'
    })
    return
  }

  const reasonGroups = {}
  failed_tasks.forEach(item => {
    const key = item.reason || '未知原因'
    if (!reasonGroups[key]) reasonGroups[key] = []
    reasonGroups[key].push(item)
  })

  let content = `总数：${total}  成功：${success_count}  失败：${failed_count}\n\n`
  const entries = Object.entries(reasonGroups)
  entries.forEach(([reason, items], idx) => {
    content += `【${reason}】${items.length}项\n`
    items.slice(0, 3).forEach(item => {
      content += `  · 任务#${item.id}\n`
    })
    if (items.length > 3) {
      content += `  ...等${items.length}项\n`
    }
    if (idx < entries.length - 1) content += '\n'
  })

  uni.showModal({
    title,
    content,
    showCancel: false,
    confirmText: '知道了',
    confirmColor: '#1677ff'
  })
}

const handleBatchAssign = async () => {
  if (!assignForm.value.assigned_to) {
    uni.showToast({ title: '请选择负责人', icon: 'none' })
    return
  }
  batchLoading.value = true
  try {
    const res = await api.batchAssignTasks({
      task_ids: selectedIds.value,
      assigned_to: assignForm.value.assigned_to,
      start_at: assignForm.value.start_at
    })
    closeAssignSheet()
    exitBatchMode()
    await fetchTasks()
    showBatchResult('批量指派结果', res)
  } catch (err) {
    uni.showToast({ title: '批量指派失败', icon: 'none' })
  } finally {
    batchLoading.value = false
  }
}

const handleBatchUrge = async () => {
  uni.showModal({
    title: '批量催办',
    content: `确定催办选中的 ${selectedIds.value.length} 个任务吗？`,
    success: async (res) => {
      if (!res.confirm) return
      batchLoading.value = true
      try {
        const result = await api.batchUrgeTasks({ task_ids: selectedIds.value })
        exitBatchMode()
        await fetchTasks()
        showBatchResult('批量催办结果', result)
      } catch (err) {
        uni.showToast({ title: '批量催办失败', icon: 'none' })
      } finally {
        batchLoading.value = false
      }
    }
  })
}

const openTask = (task) => {
  uni.navigateTo({ url: `/pages/tasks/detail?id=${task.id}` })
}

const openOrder = (task) => {
  if (!task.orderId) {
    uni.showToast({ title: '暂无订单信息', icon: 'none' })
    return
  }
  uni.navigateTo({ url: `/pages/order/detail?id=${task.orderId}` })
}

const selectPriority = (value) => {
  selectedPriority.value = value
  fetchTasks()
}

const selectTag = (value) => {
  selectedTag.value = value
  fetchTasks()
}

const handlePrimary = (task) => {
  openTask(task)
}

const setupPage = () => {
  uni.setNavigationBarTitle({ title: pageTitle.value })
}

onLoad((query) => {
  mode.value = query.mode || 'order'
  orderId.value = query.orderId || ''
  orderPi.value = query.pi ? decodeURIComponent(query.pi) : ''
  setupPage()
  fetchTagOptions()
  fetchTasks()
})
</script>

<style scoped lang="scss">
.page {
  padding: 32rpx;
  box-sizing: border-box;
  background: #f5f7fb;
  min-height: 100vh;
}
.intro-card {
  background: #fff;
  border-radius: 20rpx;
  padding: 22rpx;
  box-shadow: 0 10rpx 24rpx rgba(0, 0, 0, 0.04);
}
.intro-title {
  font-size: 30rpx;
  font-weight: 700;
  color: #1f1f1f;
}
.intro-desc {
  margin-top: 6rpx;
  color: #7b7b7b;
  font-size: 24rpx;
}
.search-bar {
  margin-top: 20rpx;
  display: flex;
  gap: 12rpx;
}
.search-input {
  flex: 1;
  background: #fff;
  border-radius: 16rpx;
  padding: 18rpx 24rpx;
}
.search-btn {
  background: #1677ff;
  color: #fff;
  border-radius: 16rpx;
  border: none;
  padding: 0 30rpx;
}
.filter-toolbar {
  margin-top: 20rpx;
}
.filter-scroll {
  white-space: nowrap;
}
.filter-group {
  display: inline-flex;
  align-items: center;
  margin-right: 32rpx;
}
.filter-label {
  font-size: 24rpx;
  color: #666;
  margin-right: 12rpx;
  flex-shrink: 0;
}
.filter-chip {
  display: inline-block;
  padding: 10rpx 24rpx;
  font-size: 24rpx;
  color: #666;
  background: #fff;
  border: 1rpx solid #e8e8e8;
  border-radius: 28rpx;
  margin-right: 12rpx;
}
.filter-chip.active {
  border: 1rpx solid #1677ff;
  color: #1677ff;
  background: #e6f4ff;
}
.title-row {
  display: flex;
  align-items: center;
  gap: 12rpx;
}
.priority-tag {
  font-size: 20rpx;
  padding: 4rpx 12rpx;
  border: 1rpx solid;
  border-radius: 8rpx;
  font-weight: 600;
  flex-shrink: 0;
}
.task-tags {
  margin-top: 12rpx;
  display: flex;
  flex-wrap: wrap;
  gap: 12rpx;
}
.tag-item {
  font-size: 22rpx;
  padding: 6rpx 16rpx;
  border: 1rpx solid;
  border-radius: 20rpx;
  background: #fff;
}
.normal-toolbar {
  margin-top: 20rpx;
  display: flex;
  justify-content: flex-end;
}
.batch-enter-btn {
  background: #fff;
  color: #1677ff;
  border: 1rpx solid #d6e4ff;
  border-radius: 28rpx;
  padding: 0 28rpx;
  font-size: 24rpx;
}
.batch-toolbar {
  margin-top: 20rpx;
  background: #fff;
  border-radius: 20rpx;
  padding: 16rpx 24rpx;
  display: flex;
  justify-content: space-between;
  align-items: center;
  box-shadow: 0 8rpx 20rpx rgba(0, 0, 0, 0.04);
}
.batch-left {
  display: flex;
  align-items: center;
  gap: 12rpx;
}
.batch-label {
  font-size: 26rpx;
  color: #333;
}
.batch-center {
  flex: 1;
  text-align: center;
}
.batch-count {
  font-size: 24rpx;
  color: #1677ff;
  font-weight: 600;
}
.batch-right {
  display: flex;
  gap: 12rpx;
}
.batch-action-btn {
  border-radius: 28rpx;
  border: none;
  padding: 0 24rpx;
  font-size: 24rpx;
}
.batch-action-btn.assign {
  background: #1677ff;
  color: #fff;
}
.batch-action-btn.urge {
  background: #fa8c16;
  color: #fff;
}
.batch-action-btn[disabled] {
  opacity: 0.5;
}
.batch-cancel-btn {
  background: #f5f5f5;
  color: #666;
  border: none;
  border-radius: 28rpx;
  padding: 0 24rpx;
  font-size: 24rpx;
}
.task-list {
  margin-top: 20rpx;
}
.task-card {
  background: #fff;
  border-radius: 24rpx;
  padding: 24rpx;
  margin-bottom: 18rpx;
  box-shadow: 0 10rpx 24rpx rgba(0, 0, 0, 0.04);
  display: flex;
  gap: 16rpx;
}
.task-card-batch {
  padding: 0;
  overflow: hidden;
}
.task-select-area {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  width: 100rpx;
  flex-shrink: 0;
  gap: 8rpx;
  background: #fafbfc;
  border-right: 1rpx solid #f0f0f0;
  padding: 24rpx 12rpx;
}
.select-hint {
  font-size: 20rpx;
  color: #999;
}
.task-content {
  flex: 1;
  min-width: 0;
  padding: 24rpx;
}
.task-card-batch .task-content {
  padding: 24rpx;
}
.task-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}
.task-head-left {
  flex: 1;
  min-width: 0;
}
.task-title {
  font-size: 30rpx;
  font-weight: 600;
}
.task-type {
  font-size: 24rpx;
  color: #999;
  margin-top: 6rpx;
}
.task-status {
  padding: 6rpx 20rpx;
  border-radius: 20rpx;
  font-size: 22rpx;
  background: #f0f5ff;
  color: #1677ff;
  flex-shrink: 0;
}
.task-status.completed {
  background: #f6ffed;
  color: #52c41a;
}
.task-status.waiting_audit {
  background: #fff7e6;
  color: #fa8c16;
}
.task-detail-hint {
  margin-top: 16rpx;
  padding-top: 12rpx;
  border-top: 1rpx dashed #f0f0f0;
}
.detail-hint-text {
  font-size: 24rpx;
  color: #1677ff;
}
.task-body {
  margin-top: 12rpx;
}
.task-desc {
  font-size: 26rpx;
  color: #666;
}
.meta {
  margin-top: 12rpx;
  font-size: 24rpx;
  color: #8c8c8c;
  display: flex;
  justify-content: space-between;
}
.meta.secondary {
  font-size: 22rpx;
  color: #bfbfbf;
}
.task-actions {
  display: flex;
  justify-content: flex-end;
  gap: 16rpx;
  margin-top: 20rpx;
}
.outline {
  border: 1rpx solid #d6e4ff;
  color: #1677ff;
  background: #fff;
  border-radius: 28rpx;
  padding: 0 24rpx;
}
.primary {
  background: #1677ff;
  color: #fff;
  border: none;
  border-radius: 28rpx;
  padding: 0 32rpx;
}
.empty {
  text-align: center;
  color: #999;
  padding: 40rpx 0;
}
.loading {
  text-align: center;
  color: #999;
  padding: 24rpx 0;
}
.sheet-mask {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.4);
  z-index: 999;
  display: flex;
  align-items: flex-end;
}
.sheet-body {
  width: 100%;
  background: #fff;
  border-radius: 32rpx 32rpx 0 0;
  padding: 32rpx;
  padding-bottom: calc(32rpx + env(safe-area-inset-bottom));
}
.sheet-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20rpx;
}
.sheet-title {
  font-size: 32rpx;
  font-weight: 700;
  color: #1f1f1f;
}
.sheet-close {
  font-size: 36rpx;
  color: #999;
  padding: 8rpx;
}
.sheet-selected-info {
  font-size: 24rpx;
  color: #1677ff;
  margin-bottom: 24rpx;
}
.sheet-form {
  margin-bottom: 32rpx;
}
.form-item {
  margin-bottom: 24rpx;
}
.form-label {
  font-size: 28rpx;
  color: #333;
  margin-bottom: 12rpx;
  display: block;
}
.picker-value {
  background: #f5f7fb;
  border-radius: 16rpx;
  padding: 20rpx 24rpx;
  font-size: 28rpx;
  color: #333;
}
.sheet-submit {
  background: #1677ff;
  color: #fff;
  border: none;
  border-radius: 44rpx;
  font-size: 30rpx;
  padding: 20rpx 0;
}
.sheet-submit[disabled] {
  opacity: 0.5;
}
.sheet-body-large {
  max-height: 80vh;
  display: flex;
  flex-direction: column;
}
.staff-search-box {
  margin-bottom: 12rpx;
}
.staff-search-input {
  background: #f5f7fb;
  border-radius: 16rpx;
  padding: 20rpx 24rpx;
  font-size: 28rpx;
  width: 100%;
  box-sizing: border-box;
}
.dept-tabs {
  white-space: nowrap;
  margin-bottom: 12rpx;
  padding-bottom: 4rpx;
}
.dept-tab {
  display: inline-block;
  padding: 10rpx 24rpx;
  font-size: 24rpx;
  color: #666;
  background: #f5f7fb;
  border-radius: 28rpx;
  margin-right: 12rpx;
}
.dept-tab-active {
  background: #1677ff;
  color: #fff;
}
.staff-list-scroll {
  max-height: 36vh;
  margin-bottom: 16rpx;
  border-radius: 16rpx;
  background: #fafbfc;
}
.staff-group {
  margin-bottom: 4rpx;
}
.staff-group-header {
  padding: 16rpx 24rpx 8rpx;
  font-size: 22rpx;
  color: #999;
  font-weight: 600;
  background: #f5f7fb;
  position: sticky;
  top: 0;
  z-index: 1;
}
.staff-loading,
.staff-empty {
  text-align: center;
  color: #999;
  padding: 40rpx 0;
  font-size: 26rpx;
}
.staff-item {
  display: flex;
  align-items: center;
  padding: 20rpx 24rpx;
  border-bottom: 1rpx solid #f0f0f0;
}
.staff-item:last-child {
  border-bottom: none;
}
.staff-item-selected {
  background: #e6f4ff;
}
.staff-avatar {
  width: 64rpx;
  height: 64rpx;
  border-radius: 50%;
  background: #1677ff;
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 28rpx;
  font-weight: 600;
  margin-right: 20rpx;
  flex-shrink: 0;
}
.staff-info {
  flex: 1;
  min-width: 0;
}
.staff-name {
  font-size: 28rpx;
  color: #333;
  font-weight: 500;
}
.staff-dept {
  font-size: 24rpx;
  color: #999;
  margin-top: 4rpx;
}
.staff-check {
  color: #1677ff;
  font-size: 32rpx;
  font-weight: bold;
  margin-left: 12rpx;
}
</style>
