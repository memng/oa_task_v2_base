<template>
  <div class="dashboard">
    <div class="cards">
      <div class="card">
        <div class="label">在制订单</div>
        <div class="value">{{ summary.orders?.in_progress || 0 }}</div>
      </div>
      <div class="card">
        <div class="label">待审核任务</div>
        <div class="value">{{ summary.tasks?.waiting_audit || 0 }}</div>
      </div>
      <div class="card">
        <div class="label">公告数</div>
        <div class="value">{{ summary.announcements?.length || 0 }}</div>
      </div>
    </div>
    <el-card>
      <template #header>意向订单</template>
      <el-table :data="summary.intent_orders || []" stripe>
        <el-table-column prop="customer_name" label="客户" />
        <el-table-column prop="product_name" label="产品" />
        <el-table-column prop="status" label="状态" />
      </el-table>
    </el-card>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { api } from '../api'

const summary = ref({})

onMounted(async () => {
  const { data } = await api.summary()
  summary.value = data.data
})
</script>

<style scoped>
.dashboard {
  display: flex;
  flex-direction: column;
  gap: 20px;
}
.cards {
  display: flex;
  gap: 16px;
}
.card {
  flex: 1;
  background: #fff;
  border-radius: 12px;
  padding: 24px;
}
.label {
  color: #999;
}
.value {
  font-size: 32px;
  font-weight: 600;
}
</style>
