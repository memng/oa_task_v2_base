import store from '../store'
import { api } from './request'

const POLL_INTERVAL = 15000
let timer = null
const TAB_PAGES = ['pages/home/index', 'pages/tasks/index', 'pages/messages/index', 'pages/mine/index']

export async function refreshMessageSummary() {
  if (!store.state.token) {
    return
  }
  try {
    const data = await api.messageUnread()
    store.setMessageSummary(data || {})
    applyBadge(store.state.notifications || 0)
  } catch (error) {
    console.warn('message summary fetch failed', error)
  }
}

export function startMessagePolling() {
  if (timer || !store.state.token) {
    if (!timer && store.state.token) {
      refreshMessageSummary()
    }
    return
  }
  refreshMessageSummary()
  timer = setInterval(() => {
    refreshMessageSummary()
  }, POLL_INTERVAL)
}

export function stopMessagePolling() {
  if (timer) {
    clearInterval(timer)
    timer = null
  }
  store.setMessageSummary({})
  try {
    uni.removeTabBarBadge({ index: 2 })
  } catch (error) {
    console.warn('remove badge failed', error)
  }
}

function applyBadge(count) {
  if (!isTabBarPage()) {
    return
  }
  if (count > 0) {
    const text = count > 99 ? '99+' : String(count)
    uni.setTabBarBadge({
      index: 2,
      text,
      fail: (err) => {
        console.warn('set tab badge failed', err)
      }
    })
  } else {
    uni.removeTabBarBadge({
      index: 2,
      fail: (err) => {
        console.warn('remove tab badge failed', err)
      }
    })
  }
}

function isTabBarPage() {
  if (typeof getCurrentPages !== 'function') return false
  const pages = getCurrentPages()
  if (!pages || !pages.length) return false
  const current = pages[pages.length - 1]
  const rawRoute = (current.route || current.$page?.fullPath || '').replace(/^\//, '')
  return TAB_PAGES.includes(rawRoute)
}
