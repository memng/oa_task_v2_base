<template>
  <div class="page">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>库存管理</span>
          <el-button type="primary" @click="openCreate">新增库存</el-button>
        </div>
      </template>
      <el-form :model="filters" inline class="filters">
        <el-form-item label="关键字">
          <el-input v-model="filters.keyword" placeholder="产品/型号/供应商" clearable @keyup.enter.native="fetchList" />
        </el-form-item>
        <el-form-item label="供应商">
          <el-select v-model="filters.supplier_id" placeholder="全部" clearable filterable>
            <el-option v-for="item in supplierOptions" :key="item.value" :label="item.label" :value="item.value" />
          </el-select>
        </el-form-item>
        <el-form-item label="仅显示有库存">
          <el-switch v-model="filters.available_only" :active-value="1" :inactive-value="0" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" :loading="loading" @click="fetchList">查询</el-button>
          <el-button @click="resetFilters">重置</el-button>
        </el-form-item>
      </el-form>

      <el-table :data="list" stripe :loading="loading">
        <el-table-column prop="product_name" label="产品" min-width="160" />
        <el-table-column prop="model" label="型号" width="140" />
        <el-table-column prop="voltage" label="电压" width="120" />
        <el-table-column prop="supplier_name" label="供应商" min-width="140">
          <template #default="{ row }">
            {{ row.supplier_name || '未设置' }}
          </template>
        </el-table-column>
        <el-table-column prop="quantity" label="数量" width="100">
          <template #default="{ row }">
            <el-tag :type="row.quantity > 0 ? 'success' : 'info'">{{ row.quantity }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="requirements" label="机器要求" min-width="200">
          <template #default="{ row }">
            <span v-if="row.requirements">{{ row.requirements }}</span>
            <span v-else class="muted">-</span>
          </template>
        </el-table-column>
        <el-table-column prop="source_type" label="来源" width="140">
          <template #default="{ row }">
            <el-tag v-if="row.source_type === 'factory_task'" type="warning">工厂订单</el-tag>
            <el-tag v-else-if="row.source_type === 'manual'" type="info">手动</el-tag>
            <span v-else class="muted">-</span>
          </template>
        </el-table-column>
        <el-table-column prop="updated_at" label="更新时间" width="180" />
        <el-table-column label="操作" width="120" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link @click="openEdit(row)">编辑</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog v-model="dialogVisible" :title="editing ? '编辑库存' : '新增库存'" width="520px">
      <el-form :model="form" label-width="90px">
        <el-form-item label="产品名称" required>
          <el-input v-model="form.product_name" placeholder="填写产品名称" />
        </el-form-item>
        <el-form-item label="型号">
          <el-input v-model="form.model" placeholder="型号" />
        </el-form-item>
        <el-form-item label="电压">
          <el-input v-model="form.voltage" placeholder="电压" />
        </el-form-item>
        <el-form-item label="供应商">
          <el-select v-model="form.supplier_id" placeholder="选择供应商" filterable clearable>
            <el-option v-for="item in supplierOptions" :key="item.value" :label="item.label" :value="item.value" />
          </el-select>
        </el-form-item>
        <el-form-item label="数量" required>
          <el-input-number v-model="form.quantity" :min="0" />
        </el-form-item>
        <el-form-item label="机器要求">
          <el-input type="textarea" v-model="form.requirements" placeholder="尺寸/包装/其他要求" :rows="3" />
        </el-form-item>
      </el-form>
      <template #footer>
        <div class="dialog-footer">
          <el-button @click="dialogVisible = false">取消</el-button>
          <el-button type="primary" :loading="saving" @click="submit">提交</el-button>
        </div>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { api } from '../api'

const filters = reactive({
  keyword: '',
  supplier_id: '',
  available_only: 1
})
const list = ref([])
const loading = ref(false)
const dialogVisible = ref(false)
const saving = ref(false)
const editing = ref(null)
const form = reactive({
  product_name: '',
  model: '',
  voltage: '',
  supplier_id: '',
  quantity: 1,
  requirements: ''
})
const supplierOptions = ref([])

const fetchSuppliers = async () => {
  try {
    const { data } = await api.adminSuppliers()
    supplierOptions.value = (data?.data?.items || data?.items || []).map((item) => ({
      label: item.name,
      value: item.id
    }))
  } catch (error) {
    console.error(error)
  }
}

const fetchList = async () => {
  loading.value = true
  try {
    const params = {
      keyword: filters.keyword || undefined,
      supplier_id: filters.supplier_id || undefined,
      available_only: filters.available_only
    }
    const { data } = await api.inventory(params)
    list.value = data?.data?.items || data?.items || []
  } finally {
    loading.value = false
  }
}

const resetFilters = () => {
  filters.keyword = ''
  filters.supplier_id = ''
  filters.available_only = 1
  fetchList()
}

const openCreate = () => {
  editing.value = null
  form.product_name = ''
  form.model = ''
  form.voltage = ''
  form.supplier_id = ''
  form.quantity = 1
  form.requirements = ''
  dialogVisible.value = true
}

const openEdit = (row) => {
  editing.value = row
  form.product_name = row.product_name
  form.model = row.model
  form.voltage = row.voltage
  form.supplier_id = row.supplier_id || ''
  form.quantity = row.quantity || 0
  form.requirements = row.requirements || ''
  dialogVisible.value = true
}

const submit = async () => {
  if (!form.product_name) {
    ElMessage.warning('请填写产品名称')
    return
  }
  saving.value = true
  try {
    if (editing.value) {
      await api.updateInventory(editing.value.id, {
        product_name: form.product_name,
        model: form.model,
        voltage: form.voltage,
        supplier_id: form.supplier_id || null,
        quantity: Number(form.quantity) || 0,
        requirements: form.requirements
      })
      ElMessage.success('库存已更新')
    } else {
      await api.createInventory({
        product_name: form.product_name,
        model: form.model,
        voltage: form.voltage,
        supplier_id: form.supplier_id || null,
        quantity: Number(form.quantity) || 0,
        requirements: form.requirements,
        source_type: 'manual'
      })
      ElMessage.success('库存已新增')
    }
    dialogVisible.value = false
    fetchList()
  } catch (error) {
    console.error(error)
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  fetchSuppliers()
  fetchList()
})
</script>

<style scoped>
.page {
  padding: 24px;
}
.filters {
  margin-bottom: 12px;
}
.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.muted {
  color: #909399;
}
.dialog-footer {
  text-align: right;
}
</style>
