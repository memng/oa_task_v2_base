<template>
  <div class="page">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>币种管理</span>
          <el-button type="primary" @click="openCreate">新增币种</el-button>
        </div>
      </template>
      <el-table :data="list" stripe :loading="loading">
        <el-table-column prop="code" label="代码" width="120" />
        <el-table-column prop="name" label="名称" min-width="160" />
        <el-table-column prop="symbol" label="符号" width="100">
          <template #default="{ row }">
            <span v-if="row.symbol">{{ row.symbol }}</span>
            <span v-else class="muted">-</span>
          </template>
        </el-table-column>
        <el-table-column label="默认" width="120">
          <template #default="{ row }">
            <el-tag :type="row.is_default ? 'success' : 'info'">
              {{ row.is_default ? '默认' : '否' }}
            </el-tag>
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

    <el-dialog v-model="dialogVisible" :title="isEdit ? '编辑币种' : '新增币种'" width="480px">
      <el-form :model="form" label-width="90px">
        <el-form-item label="代码">
          <el-input v-model="form.code" placeholder="如 USD" />
        </el-form-item>
        <el-form-item label="名称">
          <el-input v-model="form.name" placeholder="如 美元" />
        </el-form-item>
        <el-form-item label="符号">
          <el-input v-model="form.symbol" placeholder="如 $" />
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="form.sort_order" :min="0" />
        </el-form-item>
        <el-form-item label="默认">
          <el-switch v-model="form.is_default" :active-value="1" :inactive-value="0" />
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
  code: '',
  name: '',
  symbol: '',
  sort_order: 0,
  is_default: 0,
  status: 1
})
const isEdit = computed(() => !!form.id)

const resetForm = () => {
  form.id = null
  form.code = ''
  form.name = ''
  form.symbol = ''
  form.sort_order = 0
  form.is_default = 0
  form.status = 1
}

const fetch = async () => {
  loading.value = true
  try {
    const { data } = await api.adminCurrencies()
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
  form.code = row.code
  form.name = row.name
  form.symbol = row.symbol
  form.sort_order = row.sort_order
  form.is_default = row.is_default
  form.status = row.status
  dialogVisible.value = true
}

const submit = async () => {
  if (!form.code || !form.name) {
    ElMessage.warning('请完善代码和名称')
    return
  }
  saving.value = true
  try {
    if (isEdit.value) {
      await api.updateCurrency(form.id, {
        code: form.code,
        name: form.name,
        symbol: form.symbol,
        sort_order: form.sort_order,
        is_default: form.is_default,
        status: form.status
      })
      ElMessage.success('币种已更新')
    } else {
      await api.createCurrency({
        code: form.code,
        name: form.name,
        symbol: form.symbol,
        sort_order: form.sort_order,
        is_default: form.is_default,
        status: form.status
      })
      ElMessage.success('币种已创建')
    }
    dialogVisible.value = false
    fetch()
  } finally {
    saving.value = false
  }
}

const remove = (row) => {
  ElMessageBox.confirm(`确定删除币种 ${row.name || row.code} 吗？`, '提示', { type: 'warning' })
    .then(async () => {
      await api.deleteCurrency(row.id)
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
