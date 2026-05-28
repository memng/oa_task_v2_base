const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp']
const PDF_EXTENSIONS = ['pdf']

export const ATTACHMENT_CONFIG = {
  image: {
    icon: '📷',
    bgColor: '#f0f5ff',
    label: '图片'
  },
  pdf: {
    icon: '📕',
    bgColor: '#fff7e6',
    label: 'PDF'
  },
  other: {
    icon: '📄',
    bgColor: '#f6ffed',
    label: '文件'
  }
}

export const getFileExtension = (fileName) => {
  if (!fileName) return ''
  return fileName.split('.').pop()?.toLowerCase() || ''
}

export const isImageFile = (fileName) => {
  if (!fileName) return false
  return IMAGE_EXTENSIONS.includes(getFileExtension(fileName))
}

export const isPdfFile = (fileName) => {
  if (!fileName) return false
  return PDF_EXTENSIONS.includes(getFileExtension(fileName))
}

export const getFileTypeLabel = (fileName) => {
  if (isImageFile(fileName)) return '图片'
  if (isPdfFile(fileName)) return 'PDF'
  const ext = getFileExtension(fileName)
  return ext ? ext.toUpperCase() : '文件'
}

export const getFileIcon = (fileName) => {
  if (isImageFile(fileName)) return ''
  if (isPdfFile(fileName)) return '📕'
  return '📄'
}

export const getFileTypeKey = (fileName) => {
  if (isImageFile(fileName)) return 'image'
  if (isPdfFile(fileName)) return 'pdf'
  return 'other'
}

export const getAttachmentStyle = (fileName) => {
  const key = getFileTypeKey(fileName)
  return ATTACHMENT_CONFIG[key]
}

export const classifyFiles = (files) => {
  const images = []
  const pdfs = []
  const others = []
  
  files.forEach(f => {
    const name = f.file_name || f.name || ''
    if (isImageFile(name)) {
      images.push(f)
    } else if (isPdfFile(name)) {
      pdfs.push(f)
    } else {
      others.push(f)
    }
  })
  
  return { images, pdfs, others, total: files.length }
}

const openPdf = (file, resolveUrlFn) => {
  return new Promise((resolve, reject) => {
    const fullUrl = resolveUrlFn(file.url)
    uni.showLoading({ title: '加载中...' })
    uni.downloadFile({
      url: fullUrl,
      success: (res) => {
        uni.hideLoading()
        if (res.statusCode === 200) {
          uni.openDocument({
            filePath: res.tempFilePath,
            fileType: 'pdf',
            showMenu: true,
            success: () => resolve({ type: 'pdf', filePath: res.tempFilePath }),
            fail: (err) => {
              uni.showToast({ title: '无法打开此文件', icon: 'none' })
              reject(err)
            }
          })
        } else {
          uni.showToast({ title: '下载失败', icon: 'none' })
          reject(new Error('Download failed'))
        }
      },
      fail: () => {
        uni.hideLoading()
        uni.showToast({ title: '下载失败', icon: 'none' })
        reject(new Error('Download failed'))
      }
    })
  })
}

const openOther = (file, resolveUrlFn) => {
  return new Promise((resolve) => {
    const fullUrl = resolveUrlFn(file.url)
    const name = file.file_name || file.name || 'attachment'
    const ext = getFileExtension(name)
    uni.showModal({
      title: '查看附件',
      content: `${ext.toUpperCase()} 文件\n当前不支持预览此类型的文件，是否复制链接？`,
      confirmText: '复制链接',
      cancelText: '关闭',
      success: (res) => {
        if (res.confirm) {
          uni.setClipboardData({
            data: fullUrl,
            success: () => {
              uni.showToast({ title: '链接已复制', icon: 'success' })
              resolve({ type: 'other', action: 'copy', url: fullUrl })
            },
            fail: () => resolve({ type: 'other', action: 'cancel' })
          })
        } else {
          resolve({ type: 'other', action: 'cancel' })
        }
      },
      fail: () => resolve({ type: 'other', action: 'cancel' })
    })
  })
}

export const previewAttachment = (file, siblings = [], resolveUrlFn = (url) => url) => {
  if (!file || !file.url) {
    uni.showToast({ title: '附件不存在', icon: 'none' })
    return Promise.reject(new Error('Attachment not found'))
  }

  const name = file.file_name || file.name || ''
  
  if (isImageFile(name)) {
    const imageFiles = (siblings.length > 0 ? siblings : [file]).filter(f => isImageFile(f.file_name || f.name))
    const imageUrls = imageFiles.map(f => resolveUrlFn(f.url))
    const idx = imageFiles.findIndex(f => f.url === file.url)
    uni.previewImage({ urls: imageUrls, current: Math.max(0, idx) })
    return Promise.resolve({ type: 'image' })
  }
  
  if (isPdfFile(name)) {
    return openPdf(file, resolveUrlFn)
  }
  
  return openOther(file, resolveUrlFn)
}

const showBatchPreviewMenu = (images, pdfs, others, resolveUrlFn) => {
  const itemList = []
  const actions = []
  
  if (images.length > 0) {
    itemList.push(`📷 预览全部图片（${images.length}张）`)
    actions.push({ type: 'images', data: images })
  }
  if (pdfs.length === 1) {
    itemList.push(`📕 打开PDF文件`)
    actions.push({ type: 'pdf', data: pdfs })
  }
  if (pdfs.length > 1) {
    itemList.push(`📕 选择PDF文件（${pdfs.length}个）`)
    actions.push({ type: 'pdfs', data: pdfs })
  }
  if (others.length === 1) {
    itemList.push(`📄 查看其他文件`)
    actions.push({ type: 'other', data: others })
  }
  if (others.length > 1) {
    itemList.push(`📄 选择其他文件（${others.length}个）`)
    actions.push({ type: 'others', data: others })
  }
  itemList.push('取消')

  uni.showActionSheet({
    itemList,
    success: (res) => {
      if (res.tapIndex >= actions.length) return
      
      const action = actions[res.tapIndex]
      
      if (action.type === 'images') {
        const imageUrls = action.data.map(f => resolveUrlFn(f.url))
        uni.previewImage({ urls: imageUrls, current: 0 })
      } else if (action.type === 'pdf') {
        openPdf(action.data[0], resolveUrlFn).catch(() => {})
      } else if (action.type === 'pdfs') {
        const names = action.data.map((f, i) => {
          const name = f.file_name || f.name || `PDF_${i + 1}`
          return name.length > 20 ? name.substring(0, 20) + '...' : name
        })
        uni.showActionSheet({
          itemList: [...names, '取消'],
          success: (r) => {
            if (r.tapIndex < action.data.length) {
              openPdf(action.data[r.tapIndex], resolveUrlFn).catch(() => {})
            }
          }
        })
      } else if (action.type === 'other') {
        openOther(action.data[0], resolveUrlFn)
      } else if (action.type === 'others') {
        const names = action.data.map((f, i) => {
          const name = f.file_name || f.name || `文件_${i + 1}`
          return name.length > 20 ? name.substring(0, 20) + '...' : name
        })
        uni.showActionSheet({
          itemList: [...names, '取消'],
          success: (r) => {
            if (r.tapIndex < action.data.length) {
              openOther(action.data[r.tapIndex], resolveUrlFn)
            }
          }
        })
      }
    }
  })
}

export const previewBatchAttachments = (files, resolveUrlFn = (url) => url) => {
  if (!files || files.length === 0) {
    uni.showToast({ title: '请选择要预览的附件', icon: 'none' })
    return
  }

  const { images, pdfs, others, total } = classifyFiles(files)

  const detailParts = []
  if (images.length > 0) detailParts.push(`📷 图片 ${images.length} 张`)
  if (pdfs.length > 0) detailParts.push(`📕 PDF ${pdfs.length} 个`)
  if (others.length > 0) detailParts.push(`📄 其他 ${others.length} 个`)

  const summary = `已选择 ${total} 个附件\n\n${detailParts.join('\n')}\n\n请选择预览方式：`

  uni.showModal({
    title: '批量预览',
    content: summary,
    confirmText: '继续',
    cancelText: '取消',
    success: (res) => {
      if (res.confirm) {
        showBatchPreviewMenu(images, pdfs, others, resolveUrlFn)
      }
    }
  })
}

export default {
  getFileExtension,
  isImageFile,
  isPdfFile,
  getFileTypeLabel,
  classifyFiles,
  previewAttachment,
  previewBatchAttachments
}
