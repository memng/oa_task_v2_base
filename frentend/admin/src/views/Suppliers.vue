<template>
  <div class="page">
    <el-card>
      <div class="toolbar">
        <el-input
          v-model="keyword"
          placeholder="搜索供应商"
          clearable
          class="keyword"
          @keyup.enter.native="fetchList"
          @clear="fetchList"
        />
        <el-button type="primary" @click="openDialog()">新增供应商</el-button>
      </div>
      <el-table :data="list" stripe v-loading="loading">
        <el-table-column prop="name" label="名称" min-width="160" />
        <el-table-column prop="contact_name" label="联系人" width="140" />
        <el-table-column prop="contact_phone" label="联系电话" width="160" />
        <el-table-column prop="payment_terms" label="付款条款" min-width="160" />
        <el-table-column label="自有工厂" width="120">
          <template #default="{ row }">
            <el-tag :type="row.is_internal ? 'success' : 'info'">{{ row.is_internal ? '是' : '否' }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="factory_owner_name" label="负责人" width="160" />
        <el-table-column label="评分" width="120">
          <template #default="{ row }">
            <el-rate :model-value="row.rating || 0" disabled />
          </template>
        </el-table-column>
        <el-table-column label="状态" width="120">
          <template #default="{ row }">
            <el-tag :type="row.status ? 'success' : 'info'">{{ row.status ? '启用' : '停用' }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="200">
          <template #default="{ row }">
            <el-button size="small" @click="openDialog(row)">编辑</el-button>
            <el-popconfirm title="确定删除该供应商？" @confirm="remove(row)">
              <template #reference>
                <el-button size="small" type="danger">删除</el-button>
              </template>
            </el-popconfirm>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog v-model="visible" :title="editingId ? '编辑供应商' : '新增供应商'" width="560px">
      <el-form :model="form" label-width="100px">
        <el-form-item label="名称">
          <el-input v-model="form.name" placeholder="请输入供应商名称" />
        </el-form-item>
        <el-form-item label="联系人">
          <el-input v-model="form.contact_name" placeholder="联系人姓名" />
        </el-form-item>
        <el-form-item label="联系电话">
          <el-input v-model="form.contact_phone" placeholder="联系电话" />
        </el-form-item>
        <el-form-item label="邮箱">
          <el-input v-model="form.contact_email" placeholder="邮箱" />
        </el-form-item>
        <el-form-item label="地址">
          <el-input v-model="form.address" placeholder="公司地址" />
        </el-form-item>
        <el-form-item label="付款条款">
          <el-input v-model="form.payment_terms" placeholder="例如 30% 预付款" />
        </el-form-item>
        <el-form-item label="评级">
          <el-rate v-model="form.rating" />
        </el-form-item>
        <el-form-item label="自有工厂">
          <el-switch v-model="form.is_internal" active-text="是" inactive-text="否" />
        </el-form-item>
        <el-form-item v-if="form.is_internal" label="负责人">
          <el-select v-model="form.factory_owner_id" placeholder="请选择负责人" filterable>
            <el-option-group v-for="group in staffGroups" :key="group.name" :label="group.name">
              <el-option v-for="user in group.users" :key="user.id" :label="user.name" :value="user.id" />
            </el-option-group>
          </el-select>
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
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { api } from '../api'

const list = ref([])
const loading = ref(false)
const keyword = ref('')
const visible = ref(false)
const submitting = ref(false)
const editingId = ref(null)
const staffList = ref([])

const form = reactive({
  name: '',
  contact_name: '',
  contact_phone: '',
  contact_email: '',
  address: '',
  payment_terms: '',
  rating: 0,
  status: true,
  is_internal: false,
  factory_owner_id: ''
})

const staffGroups = computed(() => {
  const groups = {}
  staffList.value.forEach((item) => {
    const key = item.dept_name || '未分组'
    if (!groups[key]) {
      groups[key] = []
    }
    groups[key].push(item)
  })
  return Object.keys(groups).map((name) => ({
    name,
    users: groups[name]
  }))
})

const fetchList = async () => {
  loading.value = true
  try {
    const { data } = await api.adminSuppliers({ keyword: keyword.value || undefined })
    list.value = data.data.items || []
  } finally {
    loading.value = false
  }
}

const fetchStaff = async () => {
  try {
    const { data } = await api.lookupStaff()
    staffList.value = data.data.items || []
  } catch (error) {
    console.error(error)
  }
}

const openDialog = (row = null) => {
  if (row) {
    editingId.value = row.id
    form.name = row.name
    form.contact_name = row.contact_name || ''
    form.contact_phone = row.contact_phone || ''
    form.contact_email = row.contact_email || ''
    form.address = row.address || ''
    form.payment_terms = row.payment_terms || ''
    form.rating = row.rating || 0
    form.status = Boolean(row.status)
    form.is_internal = Boolean(row.is_internal)
    form.factory_owner_id = row.factory_owner_id ? Number(row.factory_owner_id) : ''
  } else {
    editingId.value = null
    form.name = ''
    form.contact_name = ''
    form.contact_phone = ''
    form.contact_email = ''
    form.address = ''
    form.payment_terms = ''
    form.rating = 0
    form.status = true
    form.is_internal = false
    form.factory_owner_id = ''
  }
  visible.value = true
}

const submit = async () => {
  if (!form.name) {
    ElMessage.error('请填写供应商名称')
    return
  }
  submitting.value = true
  const payload = {
    name: form.name,
    contact_name: form.contact_name,
    contact_phone: form.contact_phone,
    contact_email: form.contact_email,
    address: form.address,
    payment_terms: form.payment_terms,
    rating: form.rating,
    status: form.status ? 1 : 0,
    is_internal: form.is_internal ? 1 : 0,
    factory_owner_id: form.is_internal && form.factory_owner_id ? Number(form.factory_owner_id) : null
  }
  try {
    if (editingId.value) {
      await api.updateSupplier(editingId.value, payload)
      ElMessage.success('供应商已更新')
    } else {
      await api.createSupplier(payload)
      ElMessage.success('供应商已创建')
    }
    visible.value = false
    fetchList()
  } finally {
    submitting.value = false
  }
}

const remove = async (row) => {
  await api.deleteSupplier(row.id)
  ElMessage.success('已删除')
  fetchList()
}

watch(
  () => form.is_internal,
  (val) => {
    if (!val) {
      form.factory_owner_id = ''
    }
  }
)

onMounted(() => {
  fetchList()
  fetchStaff()
})
</script>

<style scoped>
.page {
  padding: 24px;
}
.toolbar {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-bottom: 16px;
}
.keyword {
  width: 260px;
}
</style>
