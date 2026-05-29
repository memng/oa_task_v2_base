<template>
  <div class="page">
    <el-card>
      <div class="toolbar">
        <el-button type="primary" @click="openDialog()">新增标签</el-button>
      </div>
      <el-table :data="list" stripe v-loading="loading">
        <el-table-column label="标签预览" width="150">
          <template #default="{ row }">
            <el-tag :style="{ color: row.color, borderColor: row.color, backgroundColor: row.color + '15' }">
              {{ row.label }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="key" label="键名" />
        <el-table-column prop="label" label="显示名称" />
        <el-table-column label="颜色" width="120">
          <template #default="{ row }">
            <div class="color-display">
              <span class="color-block" :style="{ backgroundColor: row.color }"></span>
              <span>{{ row.color }}</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="sort" label="排序" width="80" />
        <el-table-column label="系统默认" width="100">
          <template #default="{ row }">
            <el-tag :type="row.is_default ? 'success' : 'info'" size="small">
              {{ row.is_default ? '是' : '否' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="200">
          <template #default="{ row }">
            <el-button size="small" @click="openDialog(row)">编辑</el-button>
            <el-popconfirm
              v-if="!row.is_default"
              title="删除标签后，已使用该标签的任务会自动移除该标签，确定删除？"
              @confirm="remove(row)"
            >
              <template #reference>
                <el-button size="small" type="danger">删除</el-button>
              </template>
            </el-popconfirm>
            <el-tooltip v-else content="系统默认标签不能删除">
              <el-button size="small" type="danger" disabled>删除</el-button>
            </el-tooltip>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog v-model="visible" :title="editingId ? '编辑标签' : '新增标签'" width="520px">
      <el-form :model="form" label-width="100px">
        <el-form-item label="键名" required>
          <el-input
            v-model="form.key"
            placeholder="英文标识，如 urgent"
            :disabled="isDefaultTag"
          />
          <div class="form-tip">
            英文标识，用于系统内部识别，只能包含小写字母、数字和下划线
          </div>
        </el-form-item>
        <el-form-item label="显示名称" required>
          <el-input v-model="form.label" placeholder="如 紧急" />
        </el-form-item>
        <el-form-item label="颜色" required>
          <el-color-picker v-model="form.color" show-alpha="false" />
          <div class="form-tip">选择标签显示的颜色</div>
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="form.sort" :min="0" />
          <div class="form-tip">数字越小越靠前</div>
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
  key: '',
  label: '',
  color: '#1677ff',
  sort: 0
})

const isDefaultTag = computed(() => {
  if (!editingId.value) return false
  const tag = list.value.find(item => item.id === editingId.value)
  return tag?.is_default || false
})

const fetch = async () => {
  loading.value = true
  try {
    const { data } = await api.adminTags()
    list.value = data?.data?.items || []
  } catch (err) {
    ElMessage.error(err.response?.data?.message || '加载失败')
  } finally {
    loading.value = false
  }
}

const openDialog = (row = null) => {
  editingId.value = row?.id || null
  if (row) {
    form.key = row.key
    form.label = row.label
    form.color = row.color
    form.sort = row.sort || 0
  } else {
    form.key = ''
    form.label = ''
    form.color = '#1677ff'
    form.sort = 0
  }
  visible.value = true
}

const submit = async () => {
  if (!form.key.trim()) {
    ElMessage.warning('请输入键名')
    return
  }
  if (!/^[a-z_][a-z0-9_]*$/.test(form.key)) {
    ElMessage.warning('键名只能包含小写字母、数字和下划线，且必须以字母或下划线开头')
    return
  }
  if (!form.label.trim()) {
    ElMessage.warning('请输入显示名称')
    return
  }
  if (!form.color) {
    ElMessage.warning('请选择颜色')
    return
  }

  submitting.value = true
  try {
    const payload = {
      key: form.key.trim(),
      label: form.label.trim(),
      color: form.color,
      sort: form.sort
    }

    if (editingId.value) {
      await api.updateTag(editingId.value, payload)
      ElMessage.success('更新成功')
    } else {
      await api.createTag(payload)
      ElMessage.success('创建成功')
    }
    visible.value = false
    await fetch()
  } catch (err) {
    ElMessage.error(err.response?.data?.message || '保存失败')
  } finally {
    submitting.value = false
  }
}

const remove = async (row) => {
  try {
    await api.deleteTag(row.id)
    ElMessage.success('删除成功')
    await fetch()
  } catch (err) {
    ElMessage.error(err.response?.data?.message || '删除失败')
  }
}

onMounted(fetch)
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
.color-display {
  display: flex;
  align-items: center;
  gap: 8px;
}
.color-block {
  display: inline-block;
  width: 20px;
  height: 20px;
  border-radius: 4px;
  border: 1px solid #eee;
}
.form-tip {
  margin-top: 4px;
  font-size: 12px;
  color: #999;
}
</style>
