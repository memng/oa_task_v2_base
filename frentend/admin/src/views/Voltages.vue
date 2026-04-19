<template>
  <div class="page">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>电压管理</span>
          <el-button type="primary" @click="openCreate">新增电压</el-button>
        </div>
      </template>
      <el-table :data="list" stripe :loading="loading">
        <el-table-column prop="label" label="名称" min-width="160" />
        <el-table-column prop="value" label="取值" width="140" />
        <el-table-column prop="description" label="描述" min-width="200">
          <template #default="{ row }">
            <span v-if="row.description">{{ row.description }}</span>
            <span v-else class="muted">-</span>
          </template>
        </el-table-column>
        <el-table-column prop="sort_order" label="排序" width="100" />
        <el-table-column prop="status" label="状态" width="120">
          <template #default="{ row }">
            <el-tag :type="row.status ? 'success' : 'info'">{{ row.status ? '启用' : '停用' }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="160" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link @click="openEdit(row)">编辑</el-button>
            <el-button type="danger" link @click="remove(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog v-model="dialogVisible" :title="isEdit ? '编辑电压' : '新增电压'" width="480px">
      <el-form :model="form" label-width="90px">
        <el-form-item label="名称">
          <el-input v-model="form.label" placeholder="如 220V/60Hz" />
        </el-form-item>
        <el-form-item label="取值">
          <el-input v-model="form.value" placeholder="用于提交的值" />
        </el-form-item>
        <el-form-item label="描述">
          <el-input v-model="form.description" placeholder="选填" />
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="form.sort_order" :min="0" />
        </el-form-item>
        <el-form-item label="状态">
          <el-switch v-model="form.status" :active-value="1" :inactive-value="0" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="submit">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { api } from '../api'

const list = ref([])
const loading = ref(false)
const dialogVisible = ref(false)
const saving = ref(false)
const form = reactive({
  id: null,
  label: '',
  value: '',
  description: '',
  sort_order: 0,
  status: 1
})
const isEdit = computed(() => !!form.id)

const resetForm = () => {
  form.id = null
  form.label = ''
  form.value = ''
  form.description = ''
  form.sort_order = 0
  form.status = 1
}

const fetch = async () => {
  loading.value = true
  try {
    const { data } = await api.adminVoltages()
    list.value = data.data.items || []
  } finally {
    loading.value = false
  }
}

const openCreate = () => {
  resetForm()
  dialogVisible.value = true
}

const openEdit = (row) => {
  form.id = row.id
  form.label = row.label
  form.value = row.value
  form.description = row.description
  form.sort_order = row.sort_order
  form.status = row.status
  dialogVisible.value = true
}

const submit = async () => {
  if (!form.label || !form.value) {
    ElMessage.warning('请填写名称和取值')
    return
  }
  saving.value = true
  try {
    if (isEdit.value) {
      await api.updateVoltage(form.id, {
        label: form.label,
        value: form.value,
        description: form.description,
        sort_order: form.sort_order,
        status: form.status
      })
      ElMessage.success('电压已更新')
    } else {
      await api.createVoltage({
        label: form.label,
        value: form.value,
        description: form.description,
        sort_order: form.sort_order,
        status: form.status
      })
      ElMessage.success('电压已创建')
    }
    dialogVisible.value = false
    fetch()
  } finally {
    saving.value = false
  }
}

const remove = (row) => {
  ElMessageBox.confirm(`确定删除电压 ${row.label} 吗？`, '提示', { type: 'warning' })
    .then(async () => {
      await api.deleteVoltage(row.id)
      ElMessage.success('已删除')
      fetch()
    })
    .catch(() => {})
}

onMounted(fetch)
</script>

<style scoped>
.page {
  padding: 24px;
}
.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.muted {
  color: #909399;
}
</style>
