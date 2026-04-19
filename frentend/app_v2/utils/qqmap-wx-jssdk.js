/* eslint-disable */
/**
 * 腾讯地图微信小程序 JavaScript SDK
 * https://lbs.qq.com/service/webService/webServiceGuide/webServiceJavascript
 */
function QQMapWX(options) {
  if (!options || !options.key) {
    throw new Error('key值不能为空')
  }
  this.key = options.key
}

QQMapWX.prototype.buildUrl = function (path, query) {
  let url = 'https://apis.map.qq.com/ws' + path + '?'
  const keys = Object.keys(query)
  keys.forEach((key, index) => {
    const value = query[key] !== undefined ? query[key] : ''
    url += key + '=' + encodeURIComponent(value)
    if (index < keys.length - 1) {
      url += '&'
    }
  })
  return url
}

QQMapWX.prototype.request = function (options) {
  if (!options || !options.url) {
    console.error('请求参数异常：缺少 url')
    return
  }
  uni.request({
    url: options.url,
    method: options.method || 'GET',
    data: options.data || {},
    header: options.header || {},
    success: (res) => {
      const data = res.data || {}
      if (data.status === 0) {
        typeof options.success === 'function' && options.success(data)
      } else {
        typeof options.fail === 'function' && options.fail(data)
      }
    },
    fail: (err) => {
      typeof options.fail === 'function' && options.fail(err)
    },
    complete: () => {
      typeof options.complete === 'function' && options.complete()
    }
  })
}

QQMapWX.prototype.reverseGeocoder = function (opts = {}) {
  const { location, get_poi = 0 } = opts
  if (!location || typeof location.latitude === 'undefined' || typeof location.longitude === 'undefined') {
    throw new Error('reverseGeocoder 调用缺少 location 参数')
  }
  const data = {
    location: location.latitude + ',' + location.longitude,
    key: this.key,
    sig: opts.sig,
    get_poi
  }
  const url = this.buildUrl('/geocoder/v1/', data)
  this.request({
    url,
    success: opts.success,
    fail: opts.fail,
    complete: opts.complete
  })
}

export default QQMapWX
