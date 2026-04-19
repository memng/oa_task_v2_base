<template>
  <div class="page" v-loading="loading">
    <el-page-header @back="goBack" content="编辑订单" class="header">
      <template #title>
        <span>{{ order?.pi_number || '编辑订单' }}</span>
      </template>
      <template #extra>
        <el-space>
          <el-button @click="saveDraft" :loading="saving">保存草稿</el-button>
          <el-button type="primary" @click="submit('in_progress')" :loading="saving">提交保存</el-button>
        </el-space>
      </template>
    </el-page-header>

    <el-card>
      <template #header>
        <div class="card-header">
          <span>订单信息</span>
          <div class="muted">交货期/交货日期不可修改</div>
        </div>
      </template>
      <el-form label-width="120px" class="form">
        <el-form-item label="现有PI号">
          <el-space wrap>
            <el-tag v-for="pi in existingPis" :key="pi" type="success">{{ pi }}</el-tag>
          </el-space>
        </el-form-item>
        <el-form-item label="新增PI号">
          <div class="pi-box">
            <div class="pi-row" v-for="(pi, index) in piAdd" :key="index">
              <el-input v-model="piAdd[index]" placeholder="输入新PI号" />
              <el-button v-if="piAdd.length > 1" type="danger" link @click="removePi(index)">删除</el-button>
            </div>
            <el-button type="primary" link @click="addPi">+ 新增PI号</el-button>
          </div>
        </el-form-item>
        <el-form-item label="客户名称">
          <el-input v-model="form.customer_name" placeholder="请输入客户名称" />
        </el-form-item>
        <el-form-item label="交货期(天)">
          <span class="muted">{{ order?.delivery_period_days ?? '-' }}</span>
        </el-form-item>
        <el-form-item label="交货日期">
          <span class="muted">{{ order?.expected_delivery_at || '未设置' }}</span>
        </el-form-item>
        <el-form-item label="订单备注">
          <el-input type="textarea" v-model="form.remark" placeholder="填写订单备注/要求" />
        </el-form-item>
      </el-form>
    </el-card>

    <el-card class="products-card">
      <template #header>
        <div class="card-header">
          <span>产品信息</span>
          <el-button type="primary" @click="addProduct">添加产品</el-button>
        </div>
      </template>
      <el-table :data="products" border>
        <el-table-column label="产品名" min-width="150">
          <template #default="{ row }">
            <el-input v-model="row.product_name" placeholder="产品名称" />
          </template>
        </el-table-column>
        <el-table-column label="型号" min-width="120">
          <template #default="{ row }">
            <el-input v-model="row.model" placeholder="型号" />
          </template>
        </el-table-column>
        <el-table-column label="电压" width="150">
          <template #default="{ row }">
            <el-select v-model="row.voltage" placeholder="选择电压" filterable>
              <el-option v-for="item in voltageOptions" :key="item.value" :label="item.label" :value="item.value" />
            </el-select>
          </template>
        </el-table-column>
        <el-table-column label="数量" width="120">
          <template #default="{ row }">
            <el-input-number v-model="row.quantity" :min="1" @change="recalcTotals" />
          </template>
        </el-table-column>
        <el-table-column label="单价" width="140">
          <template #default="{ row }">
            <el-input-number v-model="row.unit_price" :min="0" :step="100" @change="recalcTotals" />
          </template>
        </el-table-column>
        <el-table-column label="总价" width="160">
          <template #default="{ row }">
            <el-input-number v-model="row.total_price" :min="0" :step="100" @change="recalcTotals" />
          </template>
        </el-table-column>
        <el-table-column label="币种" width="140">
          <template #default="{ row }">
            <el-select v-model="row.currency" placeholder="币种" filterable>
              <el-option v-for="item in currencyOptions" :key="item.value" :label="item.label" :value="item.value" />
            </el-select>
          </template>
        </el-table-column>
        <el-table-column label="功率" width="140">
          <template #default="{ row }">
            <el-input v-model="row.power" placeholder="功率" />
          </template>
        </el-table-column>
        <el-table-column label="加工长度" width="150">
          <template #default="{ row }">
            <el-input v-model="row.processing_length" placeholder="加工长度" />
          </template>
        </el-table-column>
        <el-table-column label="外形尺寸" min-width="160">
          <template #default="{ row }">
            <el-input v-model="row.dimensions" placeholder="长*宽*高" />
          </template>
        </el-table-column>
        <el-table-column label="采购负责人ID" width="160">
          <template #default="{ row }">
            <el-input v-model="row.assignee_id" placeholder="采购人ID（新增产品必填）" />
          </template>
        </el-table-column>
      </el-table>
      <div class="totals">
        <span>产品总计：{{ productTotalLabel }}</span>
        <span>订单总价：{{ grandTotalLabel }}</span>
      </div>
    </el-card>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { api } from '../api'

const route = useRoute()
const router = useRouter()
const loading = ref(false)
const saving = ref(false)
const order = ref(null)
const existingPis = ref([])
const piAdd = ref([''])
const form = reactive({
  customer_name: '',
  remark: '',
  status: ''
})
const products = ref([])
const currencyOptions = ref([])
const voltageOptions = ref([])
const productTotal = ref(0)

const productTotalLabel = computed(() => formatAmount(productTotal.value))
const grandTotalLabel = computed(() => {
  const freight = Number(order.value?.sea_freight || 0)
  const discount = Number(order.value?.discount_amount || 0)
  const total = Number(productTotal.value || 0) + freight - discount
  return formatAmount(total)
})

const formatAmount = (value) => {
  if (value === undefined || value === null || value === '') return '-'
  const num = Number(value)
  return Number.isNaN(num) ? value : num.toFixed(2)
}

const calcProductTotal = (item) => {
  const qty = Number(item.quantity) || 0
  const unit = Number(item.unit_price) || 0
  const manual = item.total_price
  if (manual !== null && manual !== undefined && manual !== '') {
    const val = Number(manual)
    if (!Number.isNaN(val)) {
      return val
    }
  }
  return Number((qty * unit).toFixed(2))
}

const recalcTotals = () => {
  productTotal.value = products.value.reduce((sum, item) => sum + calcProductTotal(item), 0)
}

const loadDetail = async () => {
  loading.value = true
  try {
    const { data } = await api.orderDetail(route.params.id)
    order.value = data.data.order
    form.customer_name = order.value?.customer_name || ''
    form.remark = order.value?.remark || ''
    form.status = order.value?.status || ''
    existingPis.value = order.value?.pi_numbers || (order.value?.pi_number ? [order.value.pi_number] : [])
    products.value = (data.data.products || []).map((item) => ({
      id: item.id,
      product_name: item.product_name,
      model: item.model,
      voltage: item.voltage,
      quantity: item.quantity,
      unit_price: Number(item.unit_price || 0),
      total_price: item.total_price ?? item.unit_price * item.quantity,
      currency: item.currency || order.value?.currency,
      power: item.power || '',
      processing_length: item.processing_length || '',
      dimensions: item.dimensions || '',
      assignee_id: item.assignee_id || ''
    }))
    recalcTotals()
  } finally {
    loading.value = false
  }
}

const loadOptions = async () => {
  try {
    const [curRes, voltRes] = await Promise.all([api.currencies(), api.voltages()])
    currencyOptions.value = (curRes.data?.data?.items || curRes.data?.items || []).map((item) => ({
      label: `${item.code}${item.name ? ` (${item.name})` : ''}`,
      value: item.code
    }))
    voltageOptions.value = (voltRes.data?.data?.items || voltRes.data?.items || []).map((item) => ({
      label: item.label || item.value,
      value: item.value
    }))
  } catch (error) {
    console.error(error)
  }
}

const addPi = () => {
  piAdd.value.push('')
}

const removePi = (index) => {
  if (piAdd.value.length <= 1) return
  piAdd.value.splice(index, 1)
}

const addProduct = () => {
  products.value.push({
    id: null,
    product_name: '',
    model: '',
    voltage: '',
    quantity: 1,
    unit_price: 0,
    total_price: null,
    currency: order.value?.currency || '',
    power: '',
    processing_length: '',
    dimensions: '',
    assignee_id: ''
  })
}

const buildPayload = (status) => {
  const productsPayload = products.value.map((item) => ({
    id: item.id,
    product_name: item.product_name,
    model: item.model,
    voltage: item.voltage,
    quantity: Number(item.quantity) || 0,
    unit_price: Number(item.unit_price) || 0,
    total_price: item.total_price !== null && item.total_price !== undefined && item.total_price !== '' ? Number(item.total_price) : null,
    currency: item.currency || order.value?.currency,
    power: item.power,
    processing_length: item.processing_length,
    dimensions: item.dimensions,
    assignee_id: item.assignee_id ? Number(item.assignee_id) : null
  }))
  return {
    customer_name: form.customer_name,
    remark: form.remark,
    status: status || form.status,
    pi_numbers_add: piAdd.value.map((pi) => String(pi || '').trim()).filter((pi) => pi),
    products: productsPayload
  }
}

const submit = async (status) => {
  saving.value = true
  try {
    await api.updateOrder(route.params.id, buildPayload(status))
    ElMessage.success('订单已保存')
    router.push(`/orders/${route.params.id}`)
  } finally {
    saving.value = false
  }
}

const saveDraft = () => submit('draft')

const goBack = () => {
  router.back()
}

onMounted(() => {
  loadOptions()
  loadDetail()
})
</script>

<style scoped>
.page {
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.header {
  margin-bottom: 4px;
}
.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.muted {
  color: #909399;
}
.pi-box {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.pi-row {
  display: flex;
  gap: 8px;
}
.products-card {
  margin-top: 8px;
}
.totals {
  margin-top: 12px;
  display: flex;
  justify-content: flex-end;
  gap: 16px;
  font-weight: 600;
}
</style>
