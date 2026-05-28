<template>
  <el-card>
    <div class="toolbar">
      <el-select v-model="status" placeholder="全部状态" clearable @change="fetchList">
        <el-option label="全部" value=""></el-option>
        <el-option label="审批中" value="pending"></el-option>
        <el-option label="已通过" value="approved"></el-option>
        <el-option label="已驳回" value="rejected"></el-option>
        <el-option label="已撤回" value="cancelled"></el-option>
      </el-select>
      <el-button type="primary" @click="fetchList">刷新</el-button>
    </div>
    <el-table :data="list" border stripe>
      <el-table-column prop="id" label="ID" width="70" />
      <el-table-column prop="user_name" label="申请人" />
      <el-table-column prop="leave_type" label="类型">
        <template #default="{ row }">{{ typeLabel(row.leave_type) }}</template>
      </el-table-column>
      <el-table-column label="时间范围">
        <template #default="{ row }">{{ row.start_at }} ~ {{ row.end_at }}</template>
      </el-table-column>
      <el-table-column prop="duration_hours" label="时长(小时)" width="120" />
      <el-table-column prop="reason" label="事由" />
      <el-table-column prop="status" label="状态" width="120">
        <template #default="{ row }">
          <el-tag :type="statusType(row.status)">{{ statusText(row.status) }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="审批流程" width="280">
        <template #default="{ row }">
          <div v-if="row.approval_flows && row.approval_flows.length">
              <div v-for="flow in row.approval_flows" :key="flow.step_order" class="flow-step">
                <span class="flow-step-name">{{ flow.step_name }}</span>
                <span class="flow-step-approver">
                  <template v-if="flow.approver_name">
                    {{ flow.approver_type_label || '审批人' }}：{{ flow.approver_name }}
                  </template>
                  <template v-else-if="flow.skip_reason_label">
                    {{ flow.skip_reason_label }}
                  </template>
                  <template v-else>
                    {{ flow.approver_type_label || '审批人' }}：待分配
                  </template>
                </span>
                <el-tag size="small" :type="flowStatusType(flow.status)">{{ flow.status_label || flowStatusText(flow.status) }}</el-tag>
              </div>
            </div>
          <span v-else>—</span>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="220">
        <template #default="{ row }">
          <el-button v-if="row.status === 'pending'" type="success" size="small" @click="updateStatus(row.id, 'approved')">通过</el-button>
          <el-button v-if="row.status === 'pending'" type="danger" size="small" @click="updateStatus(row.id, 'rejected')">驳回</el-button>
          <span v-else>—</span>
        </template>
      </el-table-column>
    </el-table>
  </el-card>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../api'

const list = ref([])
const status = ref('')

const fetchList = async () => {
  const { data } = await api.leaveRequests({ status: status.value || undefined })
  list.value = data.data.items || []
}

const updateStatus = async (id, nextStatus) => {
  await api.updateLeaveStatus(id, { status: nextStatus })
  fetchList()
}

const statusText = (value) => {
  if (value === 'approved') return '已通过'
  if (value === 'rejected') return '已驳回'
  if (value === 'cancelled') return '已撤回'
  return '审批中'
}

const statusType = (value) => {
  if (value === 'approved') return 'success'
  if (value === 'rejected') return 'danger'
  if (value === 'cancelled') return 'info'
  return 'warning'
}

const flowStatusText = (value) => {
  if (value === 'approved') return '已通过'
  if (value === 'rejected') return '已拒绝'
  if (value === 'auto_skipped') return '自动通过'
  return '待审批'
}

const flowStatusType = (value) => {
  if (value === 'approved') return 'success'
  if (value === 'rejected') return 'danger'
  if (value === 'auto_skipped') return 'success'
  return 'warning'
}

const typeLabel = (type) => {
  const map = { annual: '年假', sick: '病假', personal: '事假', other: '其他' }
  return map[type] || '其他'
}

onMounted(fetchList)
</script>

<style scoped>
.toolbar {
  display: flex;
  justify-content: space-between;
  margin-bottom: 16px;
}
.toolbar .el-select {
  width: 200px;
}
.flow-step {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 4px;
  font-size: 12px;
}
.flow-step:last-child {
  margin-bottom: 0;
}
.flow-step-name {
  color: #666;
  white-space: nowrap;
}
.flow-step-approver {
  color: #333;
  font-weight: 500;
}
</style>
