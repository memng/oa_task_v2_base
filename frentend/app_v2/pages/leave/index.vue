<template>
  <scroll-view scroll-y class="page">
    <view class="card">
      <view class="section-title">请假申请</view>
      <view class="form-item">
        <text>请假类型</text>
        <picker :range="leaveTypes" range-key="label" @change="onTypeChange">
          <view class="picker">{{ currentType.label }}</view>
        </picker>
      </view>
      <view class="form-item">
        <text>开始日期</text>
        <picker mode="date" :value="form.start_date" @change="(e) => onDateChange('start_date', e.detail.value)">
          <view class="picker">{{ form.start_date || '请选择日期' }}</view>
        </picker>
      </view>
      <view class="form-item">
        <text>开始时间</text>
        <picker mode="time" :value="form.start_time" @change="(e) => onDateChange('start_time', e.detail.value)">
          <view class="picker">{{ form.start_time || '请选择时间' }}</view>
        </picker>
      </view>
      <view class="form-item">
        <text>结束日期</text>
        <picker mode="date" :value="form.end_date" @change="(e) => onDateChange('end_date', e.detail.value)">
          <view class="picker">{{ form.end_date || '请选择日期' }}</view>
        </picker>
      </view>
      <view class="form-item">
        <text>结束时间</text>
        <picker mode="time" :value="form.end_time" @change="(e) => onDateChange('end_time', e.detail.value)">
          <view class="picker">{{ form.end_time || '请选择时间' }}</view>
        </picker>
      </view>
      <view class="form-item">
        <text>请假事由</text>
        <textarea v-model="form.reason" placeholder="请输入请假事由" />
      </view>
      <view class="duration">预计时长：{{ durationLabel }}</view>
      <button class="primary" :loading="submitting" @click="submit">提交申请</button>
    </view>

    <view class="card">
      <view class="section-title">我的申请记录</view>
      <view v-if="!requests.length" class="empty">暂无记录</view>
      <view class="record" v-for="item in requests" :key="item.id">
        <view class="record-row">
          <text>{{ formatType(item.leave_type) }}</text>
          <text :class="['status', item.status]">{{ statusLabel(item.status) }}</text>
        </view>
        <view class="record-row small">
          <text>{{ formatRange(item.start_at, item.end_at) }}</text>
          <text>{{ item.duration_hours }} 小时</text>
        </view>
        <view class="reason">{{ item.reason || '无备注' }}</view>
      </view>
    </view>
  </scroll-view>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { api } from '../../utils/request'

const leaveTypes = [
  { label: '年假', value: 'annual' },
  { label: '病假', value: 'sick' },
  { label: '事假', value: 'personal' },
  { label: '其他', value: 'other' }
]
const currentType = ref(leaveTypes[0])
const form = reactive({
  leave_type: currentType.value.value,
  start_date: '',
  start_time: '',
  end_date: '',
  end_time: '',
  reason: ''
})
const requests = ref([])
const submitting = ref(false)

const durationLabel = computed(() => {
  const hours = computeDuration()
  return hours > 0 ? `${hours} 小时` : '待计算'
})

const onTypeChange = (e) => {
  currentType.value = leaveTypes[e.detail.value]
  form.leave_type = currentType.value.value
}

const onDateChange = (key, value) => {
  form[key] = value
}

const buildDateTime = (date, time) => {
  if (!date || !time) return ''
  return `${date} ${time}:00`
}

const computeDuration = () => {
  const start = buildDateTime(form.start_date, form.start_time)
  const end = buildDateTime(form.end_date, form.end_time)
  if (!start || !end) return 0
  const startTime = new Date(start.replace(/-/g, '/')).getTime()
  const endTime = new Date(end.replace(/-/g, '/')).getTime()
  if (Number.isNaN(startTime) || Number.isNaN(endTime) || endTime <= startTime) return 0
  const diff = endTime - startTime
  return +(diff / (1000 * 60 * 60)).toFixed(1)
}

const submit = async () => {
  if (submitting.value) return
  const startAt = buildDateTime(form.start_date, form.start_time)
  const endAt = buildDateTime(form.end_date, form.end_time)
  if (!startAt || !endAt) {
    uni.showToast({ title: '请选择请假时间', icon: 'none' })
    return
  }
  const duration = computeDuration()
  if (duration <= 0) {
    uni.showToast({ title: '请确认时间范围', icon: 'none' })
    return
  }
  submitting.value = true
  try {
    await api.createLeave({
      leave_type: form.leave_type,
      start_at: startAt,
      end_at: endAt,
      duration_hours: duration,
      reason: form.reason
    })
    uni.showToast({ title: '已提交', icon: 'success' })
    form.start_date = ''
    form.start_time = ''
    form.end_date = ''
    form.end_time = ''
    form.reason = ''
    fetchRequests()
  } catch (error) {
    if (error && error.data && error.data.conflicts && error.data.conflicts.length > 0) {
      const conflicts = error.data.conflicts
      const conflictDetails = conflicts.map((item) => {
        const typeLabel = formatType(item.leave_type)
        const statusLabelText = statusLabel(item.status)
        return `${typeLabel}(${statusLabelText})：${item.conflict_range}`
      }).join('\n')
      uni.showModal({
        title: '时间冲突',
        content: `您已有 ${conflicts.length} 条请假记录与当前申请时间冲突：\n${conflictDetails}`,
        showCancel: false,
        confirmText: '知道了'
      })
    } else {
      uni.showToast({ title: error?.message || '提交失败', icon: 'none' })
    }
  } finally {
    submitting.value = false
  }
}

const fetchRequests = async () => {
  try {
    const res = await api.leaveList()
    requests.value = res.items || []
  } catch (error) {
    requests.value = []
  }
}

const statusLabel = (status) => {
  if (status === 'approved') return '已通过'
  if (status === 'rejected') return '已驳回'
  if (status === 'cancelled') return '已取消'
  return '审批中'
}

const formatType = (type) => {
  const target = leaveTypes.find((item) => item.value === type)
  return target ? target.label : '其他'
}

const formatRange = (start, end) => {
  if (!start || !end) return '-'
  return `${start.slice(5, 16)} ~ ${end.slice(5, 16)}`
}

onShow(() => {
  fetchRequests()
})
</script>

<style scoped lang="scss">
.page {
  padding: 32rpx;
  background: #f6f7fb;
}
.card {
  background: #fff;
  border-radius: 24rpx;
  padding: 24rpx;
  margin-bottom: 24rpx;
}
.section-title {
  font-size: 30rpx;
  font-weight: 600;
  margin-bottom: 16rpx;
}
.form-item {
  margin-bottom: 16rpx;
}
.form-item text {
  display: block;
  margin-bottom: 8rpx;
  color: #666;
}
.picker,
textarea {
  width: 100%;
  background: #f7f8fa;
  border-radius: 16rpx;
  padding: 16rpx;
}
.duration {
  margin-top: 12rpx;
  color: #999;
  font-size: 24rpx;
}
.primary {
  margin-top: 16rpx;
  background: #1677ff;
  color: #fff;
  border-radius: 32rpx;
}
.record {
  padding: 16rpx 0;
  border-bottom: 1rpx solid #f0f0f0;
}
.record:last-child {
  border-bottom: none;
}
.record-row {
  display: flex;
  justify-content: space-between;
  margin-bottom: 8rpx;
}
.record-row.small {
  font-size: 24rpx;
  color: #666;
}
.status {
  font-size: 24rpx;
}
.status.pending {
  color: #faad14;
}
.status.approved {
  color: #52c41a;
}
.status.rejected {
  color: #ff4d4f;
}
.reason {
  font-size: 24rpx;
  color: #666;
}
.empty {
  text-align: center;
  color: #999;
  padding: 40rpx 0;
}
</style>
