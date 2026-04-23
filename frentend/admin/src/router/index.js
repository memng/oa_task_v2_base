import { createRouter, createWebHistory } from 'vue-router'
import Layout from '../views/Layout.vue'
import Dashboard from '../views/Dashboard.vue'
import Orders from '../views/Orders.vue'
import OrderDetail from '../views/OrderDetail.vue'
import OrderEdit from '../views/OrderEdit.vue'
import Tasks from '../views/Tasks.vue'
import OrderTaskSettings from '../views/OrderTaskSettings.vue'
import Announcements from '../views/Announcements.vue'
import Departments from '../views/Departments.vue'
import ShiftSchedules from '../views/ShiftSchedules.vue'
import Currencies from '../views/Currencies.vue'
import Voltages from '../views/Voltages.vue'
import UserAudit from '../views/UserAudit.vue'
import Suppliers from '../views/Suppliers.vue'
import Reimburse from '../views/Reimburse.vue'
import Leave from '../views/Leave.vue'
import Login from '../views/Login.vue'
import { ADMIN_TOKEN_KEY } from '../api'
import Inventory from '../views/Inventory.vue'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/login', component: Login },
    {
      path: '/',
      component: Layout,
      children: [
        { path: '', redirect: '/dashboard' },
        { path: 'dashboard', component: Dashboard },
        { path: 'orders', component: Orders },
        { path: 'orders/:id', component: OrderDetail },
        { path: 'orders/:id/edit', component: OrderEdit },
        { path: 'tasks', component: Tasks },
        { path: 'order-settings/tasks', component: OrderTaskSettings },
        { path: 'order-settings/currencies', component: Currencies },
        { path: 'order-settings/voltages', component: Voltages },
        { path: 'announcements', component: Announcements },
        { path: 'departments', component: Departments },
        { path: 'shift-schedules', component: ShiftSchedules },
        { path: 'suppliers', component: Suppliers },
        { path: 'inventory', component: Inventory },
        { path: 'reimburse', component: Reimburse },
        { path: 'leave', component: Leave },
        { path: 'users', component: UserAudit }
      ]
    }
  ]
})

router.beforeEach((to, from, next) => {
  const token = localStorage.getItem(ADMIN_TOKEN_KEY)
  if (to.path === '/login') {
    if (token) {
      next('/dashboard')
      return
    }
    next()
    return
  }
  if (!token) {
    next('/login')
    return
  }
  next()
})

export default router
