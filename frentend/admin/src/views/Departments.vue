<template>
  <div class="page">
    <el-card>
      <div class="toolbar">
        <el-button type="primary" @click="openDialog()">新增部门</el-button>
      </div>
      <el-table :data="list" stripe v-loading="loading">
        <el-table-column prop="name" label="名称" />
        <el-table-column prop="code" label="编码" />
        <el-table-column label="类型">
          <template #default="{ row }">{{ typeLabel(row.type) }}</template>
        </el-table-column>
        <el-table-column label="上级部门">
          <template #default="{ row }">{{ parentName(row.parent_id) || '-' }}</template>
        </el-table-column>
        <el-table-column label="排序" prop="sort_order" width="80" />
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="row.status ? 'success' : 'info'">{{ row.status ? '启用' : '停用' }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="200">
          <template #default="{ row }">
            <el-button size="small" @click="openDialog(row)">编辑</el-button>
            <el-popconfirm title="确定删除该部门？" @confirm="remove(row)">
              <template #reference>
                <el-button size="small" type="danger">删除</el-button>
              </template>
            </el-popconfirm>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog v-model="visible" :title="editingId ? '编辑部门' : '新增部门'" width="520px">
      <el-form :model="form" label-width="90px">
        <el-form-item label="名称">
          <el-input v-model="form.name" placeholder="请输入部门名称" />
        </el-form-item>
        <el-form-item label="编码">
          <el-input v-model="form.code" placeholder="可选，用于唯一标识" />
        </el-form-item>
        <el-form-item label="类型">
          <el-select v-model="form.type">
            <el-option v-for="item in typeOptions" :key="item.value" :label="item.label" :value="item.value" />
          </el-select>
        </el-form-item>
        <el-form-item label="上级部门">
          <el-select v-model="form.parent_id" clearable placeholder="请选择">
            <el-option
              v-for="item in parentOptions"
              :key="item.id"
              :label="item.name"
              :value="item.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="form.sort_order" :min="0" />
        </el-form-item>
        <el-form-item label="状态">
          <el-switch v-model="form.status" active-text="启用" inactive-text="停用" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="visible = false">取消</el-button>
        <el-button type="primary" @click="submit" :loading="submitting">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { computed, reactive, ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { api } from '../api'

const list = ref([])
const loading = ref(false)
const visible = ref(false)
const submitting = ref(false)
const editingId = ref(null)

const form = reactive({
  name: '',
  code: '',
  type: 'other',
  parent_id: null,
  sort_order: 0,
  status: true
})

const typeOptions = [
  { label: '销售', value: 'sales' },
  { label: '工厂', value: 'factory' },
  { label: '财务', value: 'finance' },
  { label: '管理员/运营', value: 'operation' },
  { label: '其他', value: 'other' }
]

const fetchList = async () => {
  loading.value = true
  try {
    const { data } = await api.adminDepartments()
    list.value = data.data.items || []
  } finally {
    loading.value = false
  }
}

const openDialog = (row = null) => {
  if (row) {
    editingId.value = row.id
    form.name = row.name
    form.code = row.code || ''
    form.type = row.type
    form.parent_id = row.parent_id || null
    form.sort_order = row.sort_order
    form.status = Boolean(row.status)
  } else {
    editingId.value = null
    form.name = ''
    form.code = ''
    form.type = 'other'
    form.parent_id = null
    form.sort_order = 0
    form.status = true
  }
  visible.value = true
}

const submit = async () => {
  if (!form.name) {
    return ElMessage.error('请填写名称')
  }
  submitting.value = true
  const payload = {
    name: form.name,
    code: form.code,
    type: form.type,
    parent_id: form.parent_id,
    sort_order: Number(form.sort_order) || 0,
    status: form.status ? 1 : 0
  }
  try {
    if (editingId.value) {
      await api.updateDepartment(editingId.value, payload)
      ElMessage.success('部门已更新')
    } else {
      await api.createDepartment(payload)
      ElMessage.success('部门已创建')
    }
    visible.value = false
    fetchList()
  } finally {
    submitting.value = false
  }
}

const remove = async (row) => {
  await api.deleteDepartment(row.id)
  ElMessage.success('已删除')
  fetchList()
}

const parentOptions = computed(() => list.value.filter((item) => item.id !== editingId.value))

const parentName = (parentId) => {
  if (!parentId) return ''
  const target = list.value.find((item) => item.id === parentId)
  return target ? target.name : ''
}

const typeLabel = (value) => {
  const target = typeOptions.find((item) => item.value === value)
  return target ? target.label : value
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
  justify-content: flex-end;
}
</style>
