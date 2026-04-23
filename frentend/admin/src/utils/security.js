export function sanitizeXss(value) {
  if (typeof value !== 'string') {
    return value
  }

  value = value.trim()

  const patterns = [
    { pattern: /<script\b[^>]*>[\s\S]*?<\/script>/gi, replacement: '' },
    { pattern: /<script\b[^>]*>/gi, replacement: '' },
    { pattern: /<\/script>/gi, replacement: '' },
    { pattern: /on\w+\s*=/gi, replacement: 'onremoved=' },
    { pattern: /javascript\s*:/gi, replacement: 'javascript_removed:' },
    { pattern: /<iframe\b[^>]*>[\s\S]*?<\/iframe>/gi, replacement: '' },
    { pattern: /<object\b[^>]*>[\s\S]*?<\/object>/gi, replacement: '' },
    { pattern: /<embed\b[^>]*>/gi, replacement: '' },
    { pattern: /<\/embed>/gi, replacement: '' },
    { pattern: /<link\b[^>]*>/gi, replacement: '' },
    { pattern: /<style\b[^>]*>[\s\S]*?<\/style>/gi, replacement: '' },
    { pattern: /eval\s*\(/gi, replacement: 'eval_removed(' },
    { pattern: /expression\s*\(/gi, replacement: 'expression_removed(' },
  ]

  patterns.forEach(({ pattern, replacement }) => {
    value = value.replace(pattern, replacement)
  })

  value = value
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;')

  return value
}

export function sanitizeObject(obj, fields) {
  if (!obj || typeof obj !== 'object') {
    return obj
  }

  const result = { ...obj }
  fields.forEach((field) => {
    if (result[field] && typeof result[field] === 'string') {
      result[field] = sanitizeXss(result[field])
    }
  })
  return result
}

export function sanitizeRecursive(data) {
  if (Array.isArray(data)) {
    return data.map(item => sanitizeRecursive(item))
  } else if (data && typeof data === 'object') {
    const result = {}
    for (const key in data) {
      if (data.hasOwnProperty(key)) {
        result[key] = sanitizeRecursive(data[key])
      }
    }
    return result
  } else if (typeof data === 'string') {
    return sanitizeXss(data)
  }
  return data
}
