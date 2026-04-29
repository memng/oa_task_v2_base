export const NOTIFICATION_TYPES = {
  ORDER_CREATED: 'order_created',
  TASK_ASSIGNED: 'task_assigned',
  LEAVE_APPROVED: 'leave_approved',
  LEAVE_REJECTED: 'leave_rejected',
  REIMBURSE_APPROVED: 'reimburse_approved',
  REIMBURSE_REJECTED: 'reimburse_rejected',
}

export const TASK_TYPES = {
  PROCUREMENT: 'procurement',
  NAMEPLATE: 'nameplate',
  MACHINE_DATA: 'machine_data',
  ACCEPTANCE: 'acceptance',
  PACKAGING: 'packaging',
  SHIPMENT: 'shipment',
  INSPECTION: 'inspection',
  TEMPORARY: 'temporary',
  FACTORY_ORDER: 'factory_order',
  FEE: 'fee',
  DOCUMENT: 'document',
  ANNOUNCEMENT: 'announcement',
  OTHER: 'other',
}

export function getPageUrlByNotification(notification) {
  if (!notification || !notification.payload) {
    return null
  }

  const payload = notification.payload
  const type = payload.type || notification.template_code

  switch (type) {
    case NOTIFICATION_TYPES.ORDER_CREATED:
      if (payload.order_id) {
        return `/pages/order/detail?id=${payload.order_id}`
      }
      return '/pages/order/list'

    case NOTIFICATION_TYPES.TASK_ASSIGNED:
      if (payload.task_id) {
        const taskType = payload.task_type
        const taskPageMap = {
          [TASK_TYPES.INSPECTION]: '/pages/tasks/customer-inspection',
          [TASK_TYPES.MACHINE_DATA]: '/pages/tasks/machine',
        }
        if (taskPageMap[taskType]) {
          return `${taskPageMap[taskType]}?id=${payload.task_id}`
        }
        return `/pages/tasks/detail?id=${payload.task_id}`
      }
      return '/pages/tasks/list'

    case NOTIFICATION_TYPES.LEAVE_APPROVED:
    case NOTIFICATION_TYPES.LEAVE_REJECTED:
      if (payload.leave_id) {
        return `/pages/leave/detail?id=${payload.leave_id}`
      }
      return '/pages/leave/index'

    case NOTIFICATION_TYPES.REIMBURSE_APPROVED:
    case NOTIFICATION_TYPES.REIMBURSE_REJECTED:
      if (payload.reimburse_id) {
        return `/pages/finance/reimburse-detail?id=${payload.reimburse_id}`
      }
      return '/pages/finance/reimburse'

    default:
      return null
  }
}

export function getActionLabelByNotification(notification) {
  if (!notification || !notification.payload) {
    return '查看详情'
  }

  const payload = notification.payload
  const type = payload.type || notification.template_code

  const labelMap = {
    [NOTIFICATION_TYPES.ORDER_CREATED]: '查看订单',
    [NOTIFICATION_TYPES.TASK_ASSIGNED]: '查看任务',
    [NOTIFICATION_TYPES.LEAVE_APPROVED]: '查看请假',
    [NOTIFICATION_TYPES.LEAVE_REJECTED]: '查看请假',
    [NOTIFICATION_TYPES.REIMBURSE_APPROVED]: '查看报销',
    [NOTIFICATION_TYPES.REIMBURSE_REJECTED]: '查看报销',
  }

  return labelMap[type] || '查看详情'
}

export function canNavigateToDetail(notification) {
  const url = getPageUrlByNotification(notification)
  return url !== null
}

export function navigateToDetail(notification) {
  const url = getPageUrlByNotification(notification)
  if (url) {
    uni.navigateTo({ url })
    return true
  }
  return false
}
