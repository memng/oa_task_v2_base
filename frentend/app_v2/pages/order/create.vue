<template>
  <scroll-view scroll-y class="page">
    <view class="card">
      <view class="section-title">{{ pageTitle }}</view>
      <view class="form-item">
        <text>PI号码</text>
        <view class="pi-list">
          <view v-if="existingPis.length" class="pi-existing">
            <text class="label">已有PI：</text>
            <text class="value">{{ existingPis.join(' / ') }}</text>
          </view>
          <view class="pi-row" v-for="(pi, index) in form.pi_numbers" :key="index">
            <input v-model="form.pi_numbers[index]" placeholder="请输入 PI 号码" />
            <button v-if="form.pi_numbers.length > 1" class="ghost-btn" size="mini" @tap="removePi(index)">删除</button>
          </view>
        </view>
        <button class="outline mini" @tap="addPi">新增 PI 号</button>
      </view>
      <view class="form-item">
        <text>客户名称</text>
        <input v-model="form.customer_name" placeholder="请输入客户名称" />
      </view>
      <view class="form-row">
        <view class="half">
          <text>交货期(天)</text>
          <input type="number" v-model.number="form.delivery_period_days" :disabled="isEdit" placeholder="如 20" />
        </view>
        <view class="half">
          <text>交货日期</text>
          <picker mode="date" :value="form.expected_delivery_at" :disabled="isEdit" @change="onDateChange">
            <view class="picker" :class="{ placeholder: !form.expected_delivery_at }">
              {{ form.expected_delivery_at || '自动计算或手动选择' }}
            </view>
          </picker>
        </view>
      </view>
      <view class="form-item">
        <text>订单币种</text>
        <picker mode="selector" :range="currencyPickerRange" :value="orderCurrencyIndex" @change="onOrderCurrencyChange">
          <view class="picker" :class="{ placeholder: !form.currency }">
            {{ orderCurrencyLabel }}
          </view>
        </picker>
      </view>
    </view>

    <view class="card">
      <view class="section-title">产品列表</view>
      <view class="product" v-for="(product, index) in form.products" :key="index">
        <view class="row">
          <text>产品名称</text>
          <input v-model="product.product_name" placeholder="请输入名称" />
        </view>
        <view class="row">
          <text>型号</text>
          <input v-model="product.model" placeholder="请输入型号" />
        </view>
        <view class="row">
          <text>电压</text>
          <picker mode="selector" :range="voltagePickerRange" :value="voltageIndex(product.voltage)" @change="onVoltageChange($event, index)">
            <view class="picker" :class="{ placeholder: !product.voltage }">
              {{ voltageLabel(product.voltage) }}
            </view>
          </picker>
        </view>
        <view class="row">
          <text>机器功率</text>
          <input v-model="product.power" placeholder="如 5.5kW" />
        </view>
        <view class="row">
          <text>加工长度</text>
          <input v-model="product.processing_length" placeholder="输入加工长度" />
        </view>
        <view class="row">
          <text>外形尺寸</text>
          <input v-model="product.dimensions" placeholder="长*宽*高" />
        </view>
        <view class="row two-col">
          <view class="half">
            <text>数量</text>
            <input type="number" v-model.number="product.quantity" @input="recalcTotals" @blur="syncProductTotal(index)" />
          </view>
          <view class="half">
            <text>单价</text>
            <input type="number" v-model.number="product.unit_price" @input="recalcTotals" @blur="syncProductTotal(index)" />
          </view>
        </view>
        <view class="row">
          <text>总价</text>
          <input
            type="number"
            v-model.number="product.total_price"
            placeholder="默认=数量*单价"
            @input="onTotalPriceInput($event, index)"
          />
        </view>
        <view class="row">
          <text>币种</text>
          <picker mode="selector" :range="currencyPickerRange" :value="currencyIndex(product.currency || form.currency)" @change="onProductCurrencyChange($event, index)">
            <view class="picker" :class="{ placeholder: !product.currency }">
              {{ currencyLabel(product.currency) }}
            </view>
          </picker>
        </view>
        <view class="row">
          <text>采购人</text>
          <picker
            mode="selector"
            :range="procurementPickerRange"
            :value="assigneeIndex(product.assignee_id)"
            @change="handleAssigneeChange($event, index)"
          >
            <view class="picker" :class="{ placeholder: !product.assignee_id }">
              {{ assigneeLabel(product.assignee_id) }}
            </view>
          </picker>
        </view>
      </view>
      <button class="outline" size="mini" @tap="addProduct">添加产品</button>
    </view>

    <view class="card">
      <view class="section-title">价格信息</view>
      <view class="form-row">
        <view class="half">
          <text>海运费</text>
          <input type="number" v-model.number="form.sea_freight" placeholder="输入海运费" />
        </view>
        <view class="half">
          <text>折扣金额</text>
          <input type="number" v-model.number="form.discount_amount" placeholder="输入折扣" />
        </view>
      </view>
      <view class="summary">
        <text>产品总计：{{ productTotalLabel }}</text>
        <text>订单总价：{{ grandTotalLabel }}</text>
      </view>
    </view>

    <view class="card">
      <view class="section-title">订单备注 / 要求</view>
      <textarea v-model="form.remark" placeholder="填写客户对整个订单的要求"></textarea>
      <view class="upload" @tap="chooseAttachment">
        <text>上传图片 / 视频 / 文件</text>
      </view>
      <view class="attachment-list" v-if="attachments.length">
        <view class="attachment" v-for="(item, index) in attachments" :key="item.media_id || item.url || index">
          <image v-if="item.type === 'image'" :src="item.url" mode="aspectFill" />
          <video v-else-if="item.type === 'video'" :src="item.url" controls></video>
          <view v-else class="file-attachment">
            <text class="file-name">{{ item.name || item.url }}</text>
          </view>
          <text class="remove" @tap="removeAttachment(index)">删除</text>
        </view>
      </view>
    </view>

    <view class="footer">
      <button v-if="isEdit" class="outline" @tap="cancelEdit">取消</button>
      <button v-if="!isEdit || isDraftEdit" class="outline" @tap="saveDraft">保存草稿</button>
      <button class="primary" @tap="submit">{{ isEdit ? '提交保存' : '提交' }}</button>
    </view>
  </scroll-view>
</template>

<script setup>
import { reactive, ref, computed, watch } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { api, uploadFile, resolveAssetUrl } from '../../utils/request'

const createProduct = () => ({
  product_name: '',
  model: '',
  quantity: 1,
  unit_price: 0,
  total_price: null,
  total_price_manual: false,
  currency: '',
  voltage: '',
  power: '',
  processing_length: '',
  dimensions: '',
  assignee_id: ''
})

const form = reactive({
  pi_numbers: [''],
  customer_name: '',
  currency: '',
  delivery_period_days: '',
  expected_delivery_at: '',
  sea_freight: 0,
  discount_amount: 0,
  remark: '',
  products: [createProduct()],
  attachments: []
})

const attachments = ref([])
const uploading = ref(false)
const isEdit = ref(false)
const isDraftEdit = ref(false)
const editOrderId = ref('')
const existingPis = ref([])
const procurementUsers = ref([])
const procurementLabels = computed(() => procurementUsers.value.map((item) => item.name || String(item.id || '') || '未命名'))
const currencyOptions = ref([])
const voltageOptions = ref([])
const currencyLabels = computed(() => currencyOptions.value.map((item) => item.label))
const voltageLabels = computed(() => voltageOptions.value.map((item) => item.label))
const currencyPickerRange = computed(() => currencyLabels.value.length ? currencyLabels.value : ['请选择'])
const voltagePickerRange = computed(() => voltageLabels.value.length ? voltageLabels.value : ['请选择'])
const procurementPickerRange = computed(() => procurementLabels.value.length ? procurementLabels.value : ['请选择'])
const orderCurrencyIndex = computed(() => currencyIndex(form.currency))

const orderCurrencyLabel = computed(() => currencyLabel(form.currency) || '请选择币种')
const productTotal = ref(0)
const productTotalLabel = computed(() => formatWithCurrency(productTotal.value))
const grandTotal = computed(() => {
  const freight = Number(form.sea_freight) || 0
  const discount = Number(form.discount_amount) || 0
  return Math.max(0, Number((productTotal.value + freight - discount).toFixed(2)))
})
const grandTotalLabel = computed(() => formatWithCurrency(grandTotal.value))
const pageTitle = computed(() => {
  if (!isEdit.value) return '新建订单'
  return isDraftEdit.value ? '编辑订单（草稿）' : '编辑订单'
})

watch(
  () => form.delivery_period_days,
  (val) => {
    const days = Number(val)
    if (!Number.isNaN(days) && days > 0) {
      const target = new Date()
      target.setDate(target.getDate() + days)
      form.expected_delivery_at = formatDate(target)
    }
  }
)

const formatDate = (date) => {
  const pad = (num) => String(num).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

const calcProductTotal = (product) => {
  const qty = Number(product.quantity) || 0
  const unit = Number(product.unit_price) || 0
  const manual = product.total_price
  if (product.total_price_manual && manual !== '' && manual !== null && manual !== undefined) {
    const manualNum = Number(manual)
    if (!Number.isNaN(manualNum)) {
      return Number(manualNum.toFixed(2))
    }
  }
  return Number((qty * unit).toFixed(2))
}

const syncProductTotal = (index) => {
  const product = form.products[index]
  if (!product) return
  const auto = calcProductTotal(product)
  if (!product.total_price_manual && (product.total_price === null || product.total_price === '' || Number(product.total_price) === 0)) {
    product.total_price = auto
  }
  recalcTotals()
}

const onTotalPriceInput = (event, index) => {
  const product = form.products[index]
  if (!product) return
  const value = event?.detail?.value ?? ''
  product.total_price_manual = value !== ''
  if (value === '') {
    product.total_price = null
  }
  recalcTotals()
}

const recalcTotals = () => {
  productTotal.value = form.products.reduce((sum, item) => sum + calcProductTotal(item), 0)
}

watch(
  () => form.products,
  () => {
    recalcTotals()
  },
  { deep: true, immediate: true }
)

const currencyLabel = (code) => {
  if (!code) return '选择币种'
  const target = currencyOptions.value.find((item) => item.value === code)
  if (!target) return code
  return target.label
}

const currencyIndex = (code) => {
  const idx = currencyOptions.value.findIndex((item) => item.value === code)
  return idx >= 0 ? idx : 0
}

const voltageLabel = (value) => {
  if (!value) return '选择电压'
  const target = voltageOptions.value.find((item) => item.value === value)
  return target ? target.label : value
}

const voltageIndex = (value) => {
  const idx = voltageOptions.value.findIndex((item) => item.value === value)
  return idx >= 0 ? idx : 0
}

const formatWithCurrency = (amount) => {
  if (amount === null || amount === undefined || amount === '') return ''
  const num = Number(amount)
  const formatted = Number.isNaN(num) ? amount : num.toFixed(2)
  return `${formatted}${form.currency ? ` ${form.currency}` : ''}`
}

const fetchOptions = async () => {
  try {
    const [currencyRes, voltageRes] = await Promise.all([api.currencies(), api.voltages()])
    let currencies = (currencyRes?.items || []).map((item) => ({
      label: `${item.code}${item.name ? ` (${item.name})` : ''}`,
      value: item.code,
      is_default: item.is_default
    }))
    if (!currencies.length) {
      currencies = [{ label: 'CNY', value: 'CNY', is_default: 1 }]
    }
    currencyOptions.value = currencies.filter((item) => item && item.value && item.label)
    const defaultCurrency = currencies.find((item) => item.is_default) || currencies[0]
    if (defaultCurrency && !form.currency) {
      form.currency = defaultCurrency.value
      form.products.forEach((product) => {
        if (!product.currency) {
          product.currency = defaultCurrency.value
        }
      })
    }
    voltageOptions.value = (voltageRes?.items || [])
      .map((item) => ({
      label: item.label || item.value,
      value: item.value
      }))
      .filter((item) => item && item.value && item.label)
  } catch (error) {
    console.error(error)
  }
}

const addPi = () => {
  form.pi_numbers.push('')
}

const removePi = (index) => {
  if (form.pi_numbers.length <= 1) return
  form.pi_numbers.splice(index, 1)
}

const addProduct = () => {
  const next = createProduct()
  next.currency = form.currency
  form.products.push(next)
  recalcTotals()
}

const fetchProcurementUsers = async () => {
  try {
    const res = await api.lookupStaff({ group: 'procurement' })
    procurementUsers.value = res.items || []
  } catch (error) {
    console.error(error)
  }
}

const assigneeLabel = (id) => {
  if (!id) {
    return '请选择采购人'
  }
  const numericId = Number(id)
  const target = procurementUsers.value.find((user) => Number(user.id) === numericId)
  return target ? target.name : '请选择采购人'
}

const assigneeIndex = (id) => {
  if (!id) return 0
  const numericId = Number(id)
  const idx = procurementUsers.value.findIndex((user) => Number(user.id) === numericId)
  return idx >= 0 ? idx : 0
}

const handleAssigneeChange = (event, index) => {
  if (!procurementUsers.value.length) return
  const selectedIndex = Number(event.detail.value)
  const target = procurementUsers.value[selectedIndex]
  if (target) {
    form.products[index].assignee_id = target.id
  }
}

const onOrderCurrencyChange = (event) => {
  if (!currencyOptions.value.length) return
  const selectedIndex = Number(event.detail.value)
  const selected = currencyOptions.value[selectedIndex]
  if (selected) {
    form.currency = selected.value
    form.products.forEach((product) => {
      if (!product.currency) {
        product.currency = selected.value
      }
    })
  }
}

const onProductCurrencyChange = (event, index) => {
  if (!currencyOptions.value.length) return
  const selectedIndex = Number(event.detail.value)
  const selected = currencyOptions.value[selectedIndex]
  if (selected) {
    form.products[index].currency = selected.value
  }
}

const onVoltageChange = (event, index) => {
  if (!voltageOptions.value.length) return
  const selectedIndex = Number(event.detail.value)
  const selected = voltageOptions.value[selectedIndex]
  if (selected) {
    form.products[index].voltage = selected.value
  }
}

const onDateChange = (event) => {
  form.expected_delivery_at = event.detail.value
}

const chooseAttachment = () => {
  if (uploading.value) return
  uni.showActionSheet({
    itemList: ['图片/视频', '文件'],
    success: ({ tapIndex }) => {
      if (tapIndex === 1) {
        chooseFile()
      } else {
        chooseMedia()
      }
    }
  })
}

const addAttachment = async (filePath, type, name = '') => {
  const result = await uploadFile(filePath)
  attachments.value.push({
    media_id: result.media_id,
    url: result.url,
    type,
    name
  })
  form.attachments = attachments.value.map((item) => ({
    media_id: item.media_id,
    doc_type: 'requirement'
  }))
}

const chooseMedia = () => {
  uni.chooseMedia({
    count: 6,
    mediaType: ['image', 'video'],
    success: async (res) => {
      if (!res.tempFiles?.length) return
      uploading.value = true
      try {
        for (const file of res.tempFiles) {
          const type = file.fileType === 'video' ? 'video' : 'image'
          await addAttachment(file.tempFilePath, type)
        }
      } catch (error) {
        console.error(error)
      } finally {
        uploading.value = false
      }
    }
  })
}

const chooseFile = () => {
  uni.chooseMessageFile({
    count: 3,
    type: 'all',
    success: async (res) => {
      if (!res.tempFiles?.length) return
      uploading.value = true
      try {
        for (const file of res.tempFiles) {
          await addAttachment(file.path, 'file', file.name)
        }
      } catch (error) {
        console.error(error)
      } finally {
        uploading.value = false
      }
    }
  })
}

const removeAttachment = (index) => {
  attachments.value.splice(index, 1)
  form.attachments = attachments.value.map((item) => ({
    media_id: item.media_id,
    doc_type: 'requirement'
  }))
}

const saveDraft = () => {
  saveOrder('draft')
}

const submit = async () => {
  saveOrder('in_progress')
}

const markRefreshAndBack = () => {
  uni.setStorageSync('ORDER_NEED_REFRESH', '1')
  uni.navigateBack()
}

const cancelEdit = () => {
  markRefreshAndBack()
}

const saveOrder = async (status = 'draft') => {
  const piNumbers = form.pi_numbers.map((item) => String(item || '').trim()).filter((item) => item)
  if (!piNumbers.length && !isEdit.value) {
    uni.showToast({ title: '请填写PI号', icon: 'none' })
    return
  }
  if (isEdit.value && !piNumbers.length && !existingPis.value.length) {
    uni.showToast({ title: '请填写PI号', icon: 'none' })
    return
  }
  if (!form.customer_name) {
    uni.showToast({ title: '请填写客户名称', icon: 'none' })
    return
  }
  const products = form.products.map((item, idx) => {
    if (!item.product_name) {
      throw new Error(`第 ${idx + 1} 个产品名称不能为空`)
    }
    return {
      id: item.id || null,
      product_name: item.product_name,
      model: item.model,
      quantity: Number(item.quantity) || 0,
      unit_price: Number(item.unit_price) || 0,
      total_price: calcProductTotal(item),
      currency: item.currency || form.currency,
      voltage: item.voltage,
      power: item.power,
      processing_length: item.processing_length,
      dimensions: item.dimensions,
      assignee_id: item.assignee_id ? Number(item.assignee_id) : null
    }
  })
  try {
    const payload = {
      status,
      pi_numbers: isEdit.value ? undefined : piNumbers,
      pi_numbers_add: isEdit.value ? piNumbers : undefined,
      customer_name: form.customer_name,
      currency: form.currency,
      delivery_period_days: form.delivery_period_days || null,
      expected_delivery_at: form.expected_delivery_at,
      sea_freight: form.sea_freight || 0,
      discount_amount: form.discount_amount || 0,
      grand_total: grandTotal.value,
      products,
      remark: form.remark,
      attachments: form.attachments || []
    }
    if (isEdit.value) {
      await api.updateOrder(editOrderId.value, payload)
    } else {
      await api.createOrder(payload)
    }
    uni.showToast({ title: status === 'draft' ? '草稿已保存' : '提交成功', icon: 'success' })
    setTimeout(() => {
      markRefreshAndBack()
    }, 400)
  } catch (error) {
    uni.showToast({ title: (error && error.message) || '提交失败', icon: 'none' })
  }
}

onLoad((query) => {
  fetchOptions()
  fetchProcurementUsers()
  if (query && query.orderId) {
    isEdit.value = true
    isDraftEdit.value = query.mode === 'edit' && query.status === 'draft'
    editOrderId.value = String(query.orderId)
    loadOrderDetail(editOrderId.value)
  }
})

const loadOrderDetail = async (id) => {
  try {
    const res = await api.orderDetail(id)
    if (!res || !res.order) {
      throw new Error('订单不存在')
    }
    const ord = res.order
    isDraftEdit.value = ord.status === 'draft'
    const procurementMap = {}
    const tasks = res.tasks || res.order_tasks || []
    if (tasks.length) {
      tasks.forEach((task) => {
        if (task.type !== 'procurement' || !task.order_product_id) return
        const key = String(task.order_product_id)
        if (task.assigned_to !== undefined && task.assigned_to !== null) {
          procurementMap[key] = task.assigned_to
        }
      })
    }
    existingPis.value = ord.pi_numbers && ord.pi_numbers.length ? ord.pi_numbers : (ord.pi_number ? [ord.pi_number] : [])
    form.pi_numbers = ['']
    form.customer_name = ord.customer_name || ''
    form.currency = ord.currency || ''
    form.delivery_period_days = ord.delivery_period_days || ''
    form.expected_delivery_at = ord.expected_delivery_at || ''
    form.sea_freight = ord.sea_freight || 0
    form.discount_amount = ord.discount_amount || 0
    form.remark = ord.remark || ''
    const docs = (res.documents || [])
      .filter((doc) => (doc.doc_type || '') === 'requirement')
      .map((doc) => {
        const url = resolveAssetUrl(doc.url || doc.storage_path || '')
        const ft = doc.file_type || ''
        let type = 'file'
        if (ft.startsWith('image')) type = 'image'
        else if (ft.startsWith('video')) type = 'video'
        return {
          media_id: doc.media_id || doc.id,
          url,
          type,
          name: doc.file_name || doc.doc_type || ''
        }
      })
    attachments.value = docs
    form.attachments = attachments.value.map((item) => ({
      media_id: item.media_id,
      doc_type: 'requirement'
    }))
    form.products = (res.products || []).map((item) => ({
      id: item.id,
      product_name: item.product_name,
      model: item.model,
      quantity: item.quantity,
      unit_price: item.unit_price,
      total_price: item.total_price,
      total_price_manual: !!item.total_price,
      currency: item.currency || ord.currency,
      voltage: item.voltage,
      power: item.power,
      processing_length: item.processing_length,
      dimensions: item.dimensions,
      assignee_id: procurementMap[String(item.id)] ?? item.assignee_id ?? ''
    }))
    recalcTotals()
  } catch (error) {
    uni.showToast({ title: (error && error.message) || '加载失败', icon: 'none' })
  }
}
</script>

<style scoped lang="scss">
.page {
  padding: 32rpx;
  background: #f6f7fb;
}
.card {
  background: #fff;
  border-radius: 24rpx;
  padding: 24rpx;
  margin-bottom: 24rpx;
}
.section-title {
  font-size: 30rpx;
  font-weight: 600;
  margin-bottom: 16rpx;
}
.form-item {
  margin-bottom: 20rpx;
}
.form-item text {
  display: block;
  margin-bottom: 8rpx;
  color: #666;
}
.form-item input,
textarea {
  width: 100%;
  background: #f7f8fa;
  border-radius: 16rpx;
  padding: 16rpx;
}
.form-row {
  display: flex;
  gap: 16rpx;
  margin-bottom: 16rpx;
}
.half {
  flex: 1;
}
.picker {
  width: 100%;
  background: #f7f8fa;
  border-radius: 16rpx;
  padding: 16rpx;
}
.picker.placeholder {
  color: #999;
}
.pi-list {
  display: flex;
  flex-direction: column;
  gap: 12rpx;
  margin-bottom: 12rpx;
}
.pi-existing {
  display: flex;
  gap: 8rpx;
  align-items: center;
  color: #666;
}
.pi-existing .label {
  font-weight: 600;
}
.pi-row {
  display: flex;
  gap: 12rpx;
}
.ghost-btn {
  min-width: 120rpx;
  background: #fff;
  color: #1677ff;
  border: 1rpx solid #1677ff;
  border-radius: 12rpx;
  padding: 8rpx 16rpx;
}
.mini {
  margin-top: 12rpx;
}
.product {
  border: 1rpx solid #f0f0f0;
  border-radius: 16rpx;
  padding: 16rpx;
  margin-bottom: 16rpx;
}
.row {
  margin-bottom: 12rpx;
}
.row text {
  display: block;
  color: #999;
  margin-bottom: 6rpx;
}
.two-col {
  display: flex;
  gap: 12rpx;
}
.outline {
  border: 1rpx solid #1677ff;
  background: #fff;
  color: #1677ff;
  border-radius: 24rpx;
}
.upload {
  border: 1rpx dashed #1677ff;
  border-radius: 16rpx;
  text-align: center;
  padding: 40rpx 0;
  color: #1677ff;
}
.attachment-list {
  margin-top: 16rpx;
  display: flex;
  flex-wrap: wrap;
  gap: 16rpx;
}
.attachment {
  width: 200rpx;
  position: relative;
}
.attachment image,
.attachment video {
  width: 100%;
  height: 200rpx;
  border-radius: 16rpx;
}
.file-attachment {
  width: 100%;
  height: 200rpx;
  border-radius: 16rpx;
  background: #f7f8fa;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 12rpx;
  box-sizing: border-box;
}
.file-name {
  color: #555;
  font-size: 24rpx;
  text-align: center;
}
.remove {
  position: absolute;
  right: 10rpx;
  top: 10rpx;
  background: rgba(0, 0, 0, 0.4);
  color: #fff;
  padding: 4rpx 12rpx;
  border-radius: 12rpx;
  font-size: 22rpx;
}
.summary {
  display: flex;
  flex-direction: column;
  gap: 8rpx;
  color: #333;
  margin-top: 8rpx;
}
.footer {
  display: flex;
  gap: 16rpx;
  margin-bottom: 40rpx;
}
.primary {
  flex: 1;
  background: #1677ff;
  color: #fff;
  border-radius: 32rpx;
}
.footer .outline {
  flex: 1;
}
</style>
