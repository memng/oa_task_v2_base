<template>
  <scroll-view scroll-y class="page">
    <view class="head">
      <picker :range="orders" range-key="label" @change="onOrderChange">
        <view class="picker">{{ currentOrder.label }}</view>
      </picker>
      <picker :range="roles" range-key="label" @change="onRoleChange">
        <view class="picker">{{ currentRole.label }}</view>
      </picker>
    </view>

    <view class="fee-card" v-for="section in feeSections" :key="section.title">
      <view class="section-title">{{ section.title }}</view>
      <view class="fee-row" v-for="field in section.fields" :key="field.key">
        <text>{{ field.label }}</text>
        <input v-model.number="fees[field.key]" type="number" placeholder="0.00" />
      </view>
      <view class="total">费用小计：¥{{ sectionTotal(section.fields).toFixed(2) }}</view>
    </view>

    <view class="card">
      <view class="section-title">备注</view>
      <textarea v-model="fees.remark" placeholder="请输入备注信息" />
    </view>

    <view class="footer">
      <button class="outline" @click="reset">重置</button>
      <button class="primary" @click="save">保存费用信息</button>
    </view>

    <view class="history">
      <view class="history-item" v-for="item in history" :key="item.id">
        <view>
          <view class="pi">{{ item.pi }}</view>
          <view class="meta">费用：¥{{ item.amount }}</view>
        </view>
        <view class="time">{{ item.time }}</view>
      </view>
    </view>
  </scroll-view>
</template>

<script setup>
import { reactive, ref } from 'vue'

const orders = [
  { label: '订单号 OD20250617001', value: 'OD20250617001' },
  { label: '订单号 OD20250617002', value: 'OD20250617002' }
]
const roles = [
  { label: '采购任务人', value: 'procurement' },
  { label: '财务', value: 'finance' }
]
const currentOrder = ref(orders[0])
const currentRole = ref(roles[0])

const feeSections = [
  {
    title: '国内费用（任务人填写）',
    fields: [
      { label: '运费/拖车', key: 'domestic_shipping' },
      { label: '木箱', key: 'domestic_box' },
      { label: '进仓', key: 'domestic_storage' },
      { label: '其他费用', key: 'domestic_other' }
    ]
  },
  {
    title: '国外费用（任务人填写）',
    fields: [
      { label: '海运', key: 'oversea_shipping' },
      { label: '国际快递', key: 'oversea_express' },
      { label: '证书', key: 'oversea_certificate' },
      { label: '其他费用', key: 'oversea_other' }
    ]
  },
  {
    title: '财务费用（财务填写）',
    fields: [
      { label: '收款手续费', key: 'finance_service' },
      { label: '美元费用', key: 'finance_usd' }
    ]
  }
]

const fees = reactive({
  domestic_shipping: 0,
  domestic_box: 0,
  domestic_storage: 0,
  domestic_other: 0,
  oversea_shipping: 0,
  oversea_express: 0,
  oversea_certificate: 0,
  oversea_other: 0,
  finance_service: 0,
  finance_usd: 0,
  remark: ''
})

const history = ref([
  { id: 1, pi: 'OD20250617001', amount: '4750.00', time: '2025-06-14 13:00' },
  { id: 2, pi: 'OD20250617002', amount: '5500.00', time: '2025-06-18 13:00' }
])

const onOrderChange = (e) => {
  currentOrder.value = orders[e.detail.value]
}

const onRoleChange = (e) => {
  currentRole.value = roles[e.detail.value]
}

const sectionTotal = (fields) => {
  return fields.reduce((sum, field) => sum + Number(fees[field.key] || 0), 0)
}

const reset = () => {
  Object.keys(fees).forEach((key) => {
    fees[key] = 0
  })
}

const save = () => {
  uni.showToast({ title: '费用已保存', icon: 'success' })
}
</script>

<style scoped lang="scss">
.page {
  padding: 32rpx;
  background: #f6f7fb;
}
.head {
  display: flex;
  gap: 16rpx;
  margin-bottom: 24rpx;
}
.picker {
  flex: 1;
  background: #fff;
  border-radius: 16rpx;
  padding: 16rpx;
}
.fee-card,
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
.fee-row {
  display: flex;
  justify-content: space-between;
  margin-bottom: 12rpx;
  gap: 16rpx;
}
.fee-row text {
  flex: 1;
  color: #666;
}
.fee-row input {
  flex: 1;
  background: #f7f8fa;
  border-radius: 12rpx;
  padding: 12rpx;
  text-align: right;
}
.total {
  text-align: right;
  color: #1677ff;
  margin-top: 12rpx;
  font-weight: 600;
}
.card textarea {
  height: 160rpx;
  background: #f7f8fa;
  border-radius: 16rpx;
  padding: 16rpx;
}
.footer {
  display: flex;
  gap: 16rpx;
}
.outline {
  flex: 1;
  border: 1rpx solid #1677ff;
  color: #1677ff;
  border-radius: 32rpx;
  background: #fff;
}
.primary {
  flex: 1;
  border-radius: 32rpx;
  background: #1677ff;
  color: #fff;
}
.history {
  margin-top: 24rpx;
}
.history-item {
  background: #fff;
  border-radius: 24rpx;
  padding: 24rpx;
  margin-bottom: 16rpx;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.pi {
  color: #1677ff;
  font-size: 24rpx;
}
.meta {
  color: #666;
  margin-top: 4rpx;
}
.time {
  color: #999;
  font-size: 24rpx;
}
</style>
