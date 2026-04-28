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
</style>
