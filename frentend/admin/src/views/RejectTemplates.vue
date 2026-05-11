<template>
  <div class="page">
    <el-card>
      <div class="toolbar">
        <div>
          <el-radio-group v-model="activeFilter" @change="fetchList">
            <el-radio-button value="">全部</el-radio-button>
            <el-radio-button value="1">启用中</el-radio-button>
            <el-radio-button value="0">已停用</el-radio-button>
          </el-radio-group>
        </div>
        <el-button type="primary" @click="handleAdd">
          <el-icon><Plus /></el-icon>
          新增模板
        </el-button>
      </div>

      <el-table :data="list" stripe v-loading="loading">
        <el-table-column prop="content" label="模板内容" min-width="300" show-overflow-tooltip />
        <el-table-column prop="sort_order" label="排序" width="100" />
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="row.is_active ? 'success' : 'info'">
              {{ row.is_active ? '启用中' : '已停用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="200">
          <template #default="{ row }">
            <el-button size="small" @click="handleEdit(row)">
              <el-icon><Edit /></el-icon>
              编辑
            </el-button>
            <el-popconfirm title="确定删除该模板？" @confirm="handleDelete(row)">
                <template #reference>
                  <el-button size="small" type="danger">
                    <el-icon><Delete /></el-icon>
                    删除
                  </el-button>
                </template>
            </el-popconfirm>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog
      v-model="dialogVisible"
      :title="editingItem ? '编辑模板' : '新增模板'"
      width="500px"
    >
      <el-form :model="form" label-width="80px">
        <el-form-item label="模板内容">
          <el-input
            v-model="form.content"
            type="textarea"
            :rows="3"
            maxlength="500"
            show-word-limit
            placeholder="请输入驳回原因模板内容"
          />
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="form.sort_order" :min="0" />
        </el-form-item>
        <el-form-item label="启用">
          <el-switch v-model="form.is_active" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSave">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Plus, Edit, Delete } from '@element-plus/icons-vue'
import { api } from '../api'

const list = ref([])
const loading = ref(false)
const activeFilter = ref('')
const dialogVisible = ref(false)
const editingItem = ref(null)
const form = ref({
  content: '',
  sort_order: 0,
  is_active: true
})

const fetchList = async () => {
  loading.value = true
  try {
    const params = activeFilter.value !== '' ? { is_active: activeFilter.value } : {}
    const { data } = await api.rejectTemplates(params)
    list.value = data.data.items || []
  } finally {
    loading.value = false
  }
}

const handleAdd = () => {
  editingItem.value = null
  form.value = {
    content: '',
    sort_order: 0,
    is_active: true
  }
  dialogVisible.value = true
}

const handleEdit = (row) => {
  editingItem.value = row
  form.value = { ...row }
  dialogVisible.value = true
}

const handleSave = async () => {
  const trimmed = form.value.content?.trim()
  if (!trimmed) {
    ElMessage.warning('模板内容不能为空')
    return
  }
  form.value.content = trimmed

  if (editingItem.value) {
    await api.updateRejectTemplate(editingItem.value.id, form.value)
    ElMessage.success('更新成功')
  } else {
    await api.createRejectTemplate(form.value)
    ElMessage.success('创建成功')
  }
  dialogVisible.value = false
  fetchList()
}

const handleDelete = async (row) => {
  await api.deleteRejectTemplate(row.id)
  ElMessage.success('删除成功')
  fetchList()
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
  align-items: center;
}
</style>
