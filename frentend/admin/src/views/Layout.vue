<template>
  <div class="layout">
    <aside class="sider">
      <div class="logo">OA任务后台</div>
      <el-menu :default-active="active" @select="go" router background-color="#001529" text-color="#fff" active-text-color="#ffd666">
        <el-menu-item index="/dashboard">总览</el-menu-item>
        <el-menu-item index="/orders">订单</el-menu-item>
        <el-menu-item index="/tasks">任务</el-menu-item>
        <el-sub-menu index="/order-settings">
          <template #title>订单任务</template>
          <el-menu-item index="/order-settings/tasks">任务分配</el-menu-item>
          <el-menu-item index="/order-settings/currencies">币种管理</el-menu-item>
          <el-menu-item index="/order-settings/voltages">电压管理</el-menu-item>
        </el-sub-menu>
        <el-menu-item index="/announcements">公告</el-menu-item>
        <el-menu-item index="/announcement-stats">公告已读统计</el-menu-item>
        <el-menu-item index="/departments">部门管理</el-menu-item>
        <el-menu-item index="/shift-schedules">班次设置</el-menu-item>
        <el-menu-item index="/suppliers">供应商</el-menu-item>
        <el-menu-item index="/inventory">库存</el-menu-item>
        <el-menu-item index="/reimburse">报销审批</el-menu-item>
        <el-menu-item index="/leave">请假审批</el-menu-item>
        <el-menu-item index="/users">注册审核</el-menu-item>
      </el-menu>
    </aside>
    <section class="content">
      <div class="content-header">
        <div class="welcome">欢迎，{{ profileName }}</div>
        <el-button size="small" @click="logout">退出登录</el-button>
      </div>
      <router-view />
    </section>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ADMIN_TOKEN_KEY } from '../api'

const route = useRoute()
const router = useRouter()
const active = ref(route.path)

watch(
  () => route.path,
  (val) => {
    active.value = val
  }
)

const go = (path) => {
  router.push(path)
}

const profile = ref(JSON.parse(localStorage.getItem('OA_ADMIN_PROFILE') || '{}'))
const profileName = computed(() => profile.value.name || profile.value.username || '管理员')

const logout = () => {
  localStorage.removeItem(ADMIN_TOKEN_KEY)
  localStorage.removeItem('OA_ADMIN_PROFILE')
  router.push('/login')
}
</script>

<style scoped>
.layout {
  display: flex;
  min-height: 100vh;
}
.sider {
  width: 220px;
  background: #001529;
  color: #fff;
  display: flex;
  flex-direction: column;
}
.logo {
  padding: 20px;
  font-weight: 600;
}
.content {
  flex: 1;
  padding: 24px;
  background: #f5f6fa;
}
.content-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}
.welcome {
  font-size: 16px;
  font-weight: 500;
}
</style>
