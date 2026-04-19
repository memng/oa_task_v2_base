<template>
  <scroll-view scroll-y class="page">
    <view class="header">
      <view class="title">工厂订单看板</view>
      <button class="create-btn" size="mini" @click="openCreate">新建工厂订单</button>
    </view>
    <view class="tabs">
      <view
        v-for="tab in tabs"
        :key="tab.value"
        :class="['tab', { active: currentTab === tab.value }]"
        @click="currentTab = tab.value"
      >
        {{ tab.label }}
      </view>
    </view>
    <view class="summary">
      <view class="summary-item" v-for="item in summaryStats" :key="item.label">
        <view class="count">{{ item.count }}</view>
        <view class="label">{{ item.label }}</view>
      </view>
    </view>
    <picker :range="sortOptions" range-key="label" @change="onSortChange">
      <view class="picker">{{ currentSort.label }}</view>
    </picker>

    <view class="card" v-for="order in displayOrders" :key="order.id">
      <view class="pi">{{ order.pi_number }}</view>
      <view class="title">{{ order.product_name }}</view>
      <view class="meta">
        <text>型号：{{ order.model }}</text>
        <text>电压：{{ order.voltage }}</text>
        <text>{{ order.quantity }}台</text>
      </view>
      <view class="date">交期：{{ order.delivery }}</view>
      <view class="customer">客户：{{ order.customer }}</view>
      <view class="status" :class="order.status">{{ order.status_label }}</view>
    </view>
    <view v-if="!displayOrders.length && !loading" class="empty">暂无工厂订单</view>

    <view v-if="createVisible" class="mask">
      <view class="dialog">
        <view class="dialog-title">新建工厂订单</view>
        <view class="dialog-body">
          <view class="form-row">
            <text class="label">产品</text>
            <input v-model="createForm.product_name" placeholder="请输入产品名称" />
          </view>
          <view class="form-row">
            <text class="label">型号</text>
            <input v-model="createForm.model" placeholder="请输入型号" />
          </view>
          <view class="form-row">
            <text class="label">电压</text>
            <input v-model="createForm.voltage" placeholder="请输入电压" />
          </view>
          <view class="form-row">
            <text class="label">交期</text>
            <picker mode="date" :value="createForm.due_date" @change="onDueDateChange">
              <view class="picker-field">{{ createForm.due_date || '请选择交期' }}</view>
            </picker>
          </view>
          <view class="form-row">
            <text class="label">数量</text>
            <input type="number" v-model.number="createForm.quantity" placeholder="请输入数量" />
          </view>
          <view class="form-row">
            <text class="label">PI号</text>
            <input v-model="createForm.pi_number" placeholder="可选，便于关联" />
          </view>
          <view class="form-row">
            <text class="label">客户</text>
            <input v-model="createForm.customer_name" placeholder="可选，客户名称" />
          </view>
          <view class="form-row">
            <text class="label">负责人</text>
            <view class="assignee-field" @click="toggleStaffDialog">
              {{ assigneeLabel }}
            </view>
          </view>
        </view>
        <view class="dialog-actions">
          <button class="outline" size="mini" @click="closeCreate">取消</button>
          <button class="primary" size="mini" :loading="createLoading" @click="submitCreate">保存</button>
        </view>
      </view>
    </view>

    <view v-if="staffDialogVisible" class="mask">
      <view class="dialog large">
        <view class="dialog-title">选择负责人</view>
        <scroll-view scroll-y class="staff-scroll">
          <view v-if="staffLoading" class="loading">员工列表加载中...</view>
          <view v-else>
            <view v-for="group in staffGroups" :key="group.name" class="staff-group">
              <view class="group-title">{{ group.name }}</view>
              <view class="staff-list">
                <view
                  v-for="user in group.users"
                  :key="user.id"
                  class="staff-item"
                  :class="{ active: isStaffSelected(user) }"
                  @click="selectAssignee(user)"
                >
                  {{ user.name }}
                </view>
              </view>
            </view>
            <view v-if="!staffGroups.length" class="empty">暂无员工</view>
          </view>
        </scroll-view>
        <view class="dialog-actions">
          <button class="outline" size="mini" @click="staffDialogVisible = false">关闭</button>
          <button class="primary" size="mini" @click="staffDialogVisible = false">确定</button>
        </view>
      </view>
    </view>
  </scroll-view>
</template>

<script setup>
import { ref, computed } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { api } from '../../utils/request'

const tabs = [
  { label: '全部订单', value: 'all' },
  { label: '待生产', value: 'draft' },
  { label: '生产中', value: 'in_progress' },
  { label: '已完成', value: 'completed' }
]
const currentTab = ref('all')
const sortOptions = [
  { label: '最新', value: 'latest' },
  { label: '交期最早', value: 'early' }
]
const currentSort = ref(sortOptions[0])

const statusMeta = {
  draft: { label: '待生产', className: 'pending' },
  pending: { label: '待生产', className: 'pending' },
  in_progress: { label: '生产中', className: 'running' },
  completed: { label: '已完成', className: 'done' },
  cancelled: { label: '已取消', className: 'done' }
}
const orders = ref([])
const loading = ref(false)
const createVisible = ref(false)
const createLoading = ref(false)
const createForm = ref({
  product_name: '',
  model: '',
  voltage: '',
  due_date: '',
  quantity: 1,
  pi_number: '',
  customer_name: '',
  assigned_to: ''
})
const staffList = ref([])
const staffLoading = ref(false)
const staffDialogVisible = ref(false)
const staffLoaded = ref(false)

const parseDateValue = (value, fallback) => {
  if (!value) {
    return fallback
  }
  const ts = Date.parse(value.replace(/-/g, '/'))
  return Number.isNaN(ts) ? fallback : ts
}

const normalizeOrder = (item) => {
  const rawStatus = item.status === 'pending' ? 'draft' : item.status
  const meta = statusMeta[rawStatus] || statusMeta.in_progress
  return {
    id: item.task_id || item.product_id || `${item.order_id || 'task'}-${item.product_name || item.title || 'factory'}`,
    order_status: rawStatus || '',
    pi_number: item.pi_number || '-',
    product_name: item.product_name || item.title || '未命名产品',
    model: item.model || '-',
    voltage: item.voltage || '-',
    quantity: item.quantity || 0,
    delivery: item.expected_delivery_at || item.due_at || '待排期',
    customer: item.customer_name || '-',
    status: meta.className,
    status_label: meta.label,
    created_at: item.created_at || item.expected_delivery_at || ''
  }
}

const fetchOrders = async () => {
  if (loading.value) {
    return
  }
  loading.value = true
  try {
    const res = await api.factoryBoard()
    const items = Array.isArray(res?.items) ? res.items : []
    orders.value = items.map(normalizeOrder)
  } catch (error) {
    console.error(error)
    uni.showToast({ title: '工厂订单获取失败', icon: 'none' })
  } finally {
    loading.value = false
  }
}

onShow(() => {
  fetchOrders()
})

const openCreate = () => {
  createVisible.value = true
  ensureStaff()
}
const closeCreate = () => {
  createVisible.value = false
}
const onDueDateChange = (e) => {
  createForm.value.due_date = e.detail.value
}
const ensureStaff = async () => {
  if (staffLoaded.value || staffLoading.value) return
  staffLoading.value = true
  try {
    const res = await api.lookupStaff()
    staffList.value = res.items || []
    staffLoaded.value = true
  } catch (error) {
    console.error(error)
  } finally {
    staffLoading.value = false
  }
}

const staffGroups = computed(() => {
  if (!staffList.value.length) {
    return []
  }
  const groups = staffList.value.reduce((acc, user) => {
    const name = user.dept_name || '未分组'
    if (!acc[name]) {
      acc[name] = []
    }
    acc[name].push(user)
    return acc
  }, {})
  return Object.keys(groups).map((name) => ({
    name,
    users: groups[name]
  }))
})
const assigneeLabel = computed(() => {
  if (!createForm.value.assigned_to) return '请选择负责人'
  const target = staffList.value.find((u) => String(u.id) === String(createForm.value.assigned_to))
  return target ? target.name : '请选择负责人'
})
const toggleStaffDialog = () => {
  staffDialogVisible.value = true
  ensureStaff()
}
const selectAssignee = (user) => {
  createForm.value.assigned_to = user.id
}
const isStaffSelected = (user) => String(createForm.value.assigned_to) === String(user.id)

const submitCreate = async () => {
  if (!createForm.value.product_name) {
    uni.showToast({ title: '请输入产品名称', icon: 'none' })
    return
  }
  if (!createForm.value.assigned_to) {
    uni.showToast({ title: '请选择负责人', icon: 'none' })
    return
  }
  if (!createForm.value.quantity || createForm.value.quantity <= 0) {
    uni.showToast({ title: '请输入数量', icon: 'none' })
    return
  }
  createLoading.value = true
  try {
    await api.createFactoryOrder({
      title: createForm.value.product_name,
      product_name: createForm.value.product_name,
      model: createForm.value.model,
      voltage: createForm.value.voltage,
      quantity: Number(createForm.value.quantity),
      due_at: createForm.value.due_date ? `${createForm.value.due_date} 18:00:00` : null,
      pi_number: createForm.value.pi_number || null,
      customer_name: createForm.value.customer_name || null,
      assigned_to: createForm.value.assigned_to
    })
    uni.showToast({ title: '创建成功', icon: 'success' })
    closeCreate()
    fetchOrders()
    createForm.value = {
      product_name: '',
      model: '',
      voltage: '',
      due_date: '',
      quantity: 1,
      pi_number: '',
      customer_name: '',
      assigned_to: ''
    }
  } catch (error) {
    console.error(error)
  } finally {
    createLoading.value = false
  }
}

const summaryMap = computed(() => {
  const map = { draft: 0, in_progress: 0, completed: 0 }
  orders.value.forEach((order) => {
    if (map[order.order_status] !== undefined) {
      map[order.order_status] += 1
    }
  })
  return map
})

const summaryStats = computed(() => [
  { label: '待生产', count: summaryMap.value.draft || 0 },
  { label: '生产中', count: summaryMap.value.in_progress || 0 },
  { label: '已完成', count: summaryMap.value.completed || 0 }
])

const displayOrders = computed(() => {
  let list = [...orders.value]
  if (currentTab.value !== 'all') {
    list = list.filter((order) => order.order_status === currentTab.value)
  }
  if (currentSort.value.value === 'early') {
    list.sort((a, b) => parseDateValue(a.delivery, Infinity) - parseDateValue(b.delivery, Infinity))
  } else {
    list.sort((a, b) => parseDateValue(b.created_at, 0) - parseDateValue(a.created_at, 0))
  }
  return list
})

const onSortChange = (e) => {
  currentSort.value = sortOptions[e.detail.value]
  uni.showToast({ title: '排序已更新', icon: 'none' })
}
</script>

<style scoped lang="scss">
.page {
  padding: 32rpx;
  background: #f6f7fb;
}
.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12rpx;
}
.header .title {
  font-size: 32rpx;
  font-weight: 700;
}
.create-btn {
  background: #1677ff;
  color: #fff;
  border: none;
  border-radius: 24rpx;
  padding: 0 24rpx;
}
.tabs {
  display: flex;
  background: #fff;
  border-radius: 24rpx;
}
.tab {
  flex: 1;
  text-align: center;
  padding: 20rpx 0;
  color: #666;
}
.tab.active {
  color: #1677ff;
  font-weight: 600;
}
.summary {
  display: flex;
  justify-content: space-between;
  margin: 24rpx 0;
}
.summary-item {
  flex: 1;
  background: #fff;
  border-radius: 16rpx;
  padding: 16rpx;
  text-align: center;
  margin-right: 12rpx;
}
.summary-item:last-child {
  margin-right: 0;
}
.count {
  font-size: 36rpx;
  font-weight: 600;
}
.label {
  color: #999;
}
.picker {
  background: #fff;
  border-radius: 16rpx;
  padding: 16rpx;
}
.card {
  background: #fff;
  border-radius: 24rpx;
  padding: 24rpx;
  margin-top: 24rpx;
  position: relative;
}
.pi {
  color: #1677ff;
  font-size: 24rpx;
}
.title {
  font-size: 30rpx;
  font-weight: 600;
  margin: 8rpx 0;
}
.meta {
  display: flex;
  justify-content: space-between;
  color: #666;
  font-size: 24rpx;
}
.date,
.customer {
  margin-top: 8rpx;
  color: #666;
}
.status {
  position: absolute;
  right: 24rpx;
  top: 24rpx;
  padding: 4rpx 16rpx;
  border-radius: 16rpx;
  font-size: 22rpx;
}
.status.pending {
  background: #fff7e6;
  color: #fa8c16;
}
.status.running {
  background: #e6fffb;
  color: #13c2c2;
}
.status.done {
  background: #f6ffed;
  color: #52c41a;
}
.empty {
  text-align: center;
  color: #999;
  margin-top: 80rpx;
}
.mask {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 32rpx;
  z-index: 1000;
}
.dialog {
  width: 100%;
  background: #fff;
  border-radius: 20rpx;
  padding: 24rpx;
  display: flex;
  flex-direction: column;
  gap: 12rpx;
  max-height: 90vh;
}
.dialog.large {
  max-height: 80vh;
}
.dialog-title {
  font-size: 30rpx;
  font-weight: 700;
}
.dialog-body {
  display: flex;
  flex-direction: column;
  gap: 12rpx;
}
.form-row {
  background: #f6f7fb;
  border-radius: 12rpx;
  padding: 14rpx 16rpx;
  display: flex;
  align-items: center;
  gap: 12rpx;
}
.label {
  width: 140rpx;
  color: #666;
  font-size: 26rpx;
}
.picker-field {
  flex: 1;
  background: #fff;
  border-radius: 10rpx;
  padding: 12rpx;
}
.assignee-field {
  flex: 1;
  background: #fff;
  border-radius: 10rpx;
  padding: 12rpx;
  color: #333;
}
.dialog-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12rpx;
}
.outline {
  border: 1rpx solid #d6e4ff;
  color: #1677ff;
  background: #fff;
  border-radius: 20rpx;
  padding: 0 24rpx;
}
.primary {
  background: #1677ff;
  color: #fff;
  border: none;
  border-radius: 20rpx;
  padding: 0 28rpx;
}
.staff-scroll {
  max-height: 50vh;
}
.staff-group {
  margin-bottom: 16rpx;
}
.group-title {
  font-weight: 600;
  margin-bottom: 8rpx;
}
.staff-list {
  display: flex;
  flex-wrap: wrap;
  gap: 10rpx;
}
.staff-item {
  padding: 10rpx 16rpx;
  background: #f6f7fb;
  border-radius: 12rpx;
  font-size: 26rpx;
  color: #333;
}
.staff-item.active {
  background: #1677ff;
  color: #fff;
}
.loading {
  text-align: center;
  color: #999;
  padding: 20rpx 0;
}
</style>
