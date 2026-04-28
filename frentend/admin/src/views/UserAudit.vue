<template>
  <div class="page">
    <el-card>
      <div class="toolbar">
        <el-radio-group v-model="status" @change="fetchList">
          <el-radio-button v-for="item in statusOptions" :key="item.value" :label="item.value">{{ item.label }}</el-radio-button>
        </el-radio-group>
      </div>
      <el-table :data="list" stripe v-loading="loading">
        <el-table-column prop="name" label="姓名" />
        <el-table-column prop="mobile" label="手机号" />
        <el-table-column prop="dept_name" label="部门" />
        <el-table-column prop="created_at" label="注册时间" width="180" />
        <el-table-column label="状态" width="110">
          <template #default="{ row }">
            <el-tag :type="statusType(row.status)">{{ statusLabel(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="220">
          <template #default="{ row }">
            <el-button v-if="row.status === 'pending'" size="small" type="success" @click="approve(row)">通过</el-button>
            <el-button
              v-if="row.status === 'pending'"
              size="small"
              type="danger"
              @click="reject(row)"
            >
              拒绝
            </el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { api } from '../api'

const status = ref('pending')
const list = ref([])
const loading = ref(false)

const statusOptions = [
  { label: '待审核', value: 'pending' },
  { label: '已通过', value: 'active' },
  { label: '已拒绝', value: 'disabled' }
]

const fetchList = async () => {
  loading.value = true
  try {
    const { data } = await api.adminUsers({ status: status.value })
    list.value = data.data.items || []
  } finally {
    loading.value = false
  }
}

const approve = async (row) => {
  await api.approveUser(row.id)
  ElMessage.success('已通过审核')
  fetchList()
}

const reject = async (row) => {
  const { value } = await ElMessageBox.prompt('请输入拒绝原因', '拒绝注册', {
    confirmButtonText: '确定',
    cancelButtonText: '取消',
    inputPattern: /\S+/,
    inputErrorMessage: '拒绝原因不能为空'
  })
  await api.rejectUser(row.id, { reject_reason: value })
  ElMessage.success('已拒绝该注册')
  fetchList()
}

const statusLabel = (value) => {
  const map = {
    pending: '待审核',
    active: '已通过',
    disabled: '已拒绝'
  }
  return map[value] || value
}

const statusType = (value) => {
  const map = {
    pending: 'warning',
    active: 'success',
    disabled: 'info'
  }
  return map[value] || 'info'
}

onMounted(fetchList)
</script>

<style scoped>
.page {
  padding: 24px;
}
.toolbar {
  margin-bottom: 16px;
  display: flex;
  justify-content: space-between;
}
</style>

