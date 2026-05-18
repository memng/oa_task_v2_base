<template>
  <view class="page">
    <view class="crop-container" v-if="imageInfo">
      <view class="preview-area" :style="previewStyle">
        <view class="image-wrapper">
          <image
            class="source-image"
            :src="src"
            :style="imageStyle"
            mode="widthFix"
            @touchstart="onTouchStart"
            @touchmove="onTouchMove"
            @touchend="onTouchEnd"
          />
        </view>
        <view class="crop-mask">
          <view class="crop-window" />
          <view class="crop-grid">
            <view class="grid-h h1" />
            <view class="grid-h h2" />
            <view class="grid-v v1" />
            <view class="grid-v v2" />
          </view>
          <view class="crop-corner tl" />
          <view class="crop-corner tr" />
          <view class="crop-corner bl" />
          <view class="crop-corner br" />
        </view>
      </view>

      <view class="slider-row">
        <text class="slider-label">缩放</text>
        <slider
          class="slider"
          :value="scale"
          :min="minScale"
          :max="maxScale"
          :step="0.1"
          :activeColor="'#1677ff'"
          @changing="onSliderChange"
        />
      </view>

      <view class="actions">
        <button class="action-btn cancel" @click="goBack">取消</button>
        <button class="action-btn confirm" :loading="cropping" @click="doCrop">确认</button>
      </view>
    </view>

    <view class="loading" v-else>
      <text>加载中...</text>
    </view>

    <canvas
      canvas-id="avatarCanvas"
      class="offscreen-canvas"
    />
  </view>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { uploadFile, api } from '../../utils/request'
import store from '../../store'

const src = ref('')
const imageInfo = ref(null)
const cropping = ref(false)

const scale = ref(1)
const translateX = ref(0)
const translateY = ref(0)

let touchStartX = 0
let touchStartY = 0
let startTranslateX = 0
let startTranslateY = 0
let isTouching = false

const systemInfo = uni.getSystemInfoSync()
const screenWidth = systemInfo.windowWidth
const previewSize = Math.min(screenWidth - 64, 600)
const cropWindowSize = previewSize / 3
const cropWindowLeft = previewSize / 3
const cropWindowRight = previewSize * 2 / 3

const pages = getCurrentPages()
const currentPage = pages[pages.length - 1]

onMounted(() => {
  const options = currentPage.options || {}
  src.value = options.src || ''
  if (!src.value) {
    uni.showToast({ title: '参数错误', icon: 'none' })
    setTimeout(() => uni.navigateBack(), 1000)
    return
  }
  loadImage()
})

const loadImage = () => {
  uni.getImageInfo({
    src: src.value,
    success: (res) => {
      imageInfo.value = res
      resetPosition()
    },
    fail: () => {
      uni.showToast({ title: '图片加载失败', icon: 'none' })
      setTimeout(() => uni.navigateBack(), 1000)
    }
  })
}

const previewStyle = computed(() => ({
  width: `${previewSize}px`,
  height: `${previewSize}px`
}))

const imageDisplayScale = computed(() => {
  if (!imageInfo.value) return 1
  return previewSize / Math.max(imageInfo.value.width, imageInfo.value.height)
})

const minScale = computed(() => {
  if (!imageInfo.value) return 1
  const w = imageInfo.value.width
  const h = imageInfo.value.height
  const minSide = Math.min(w, h)
  const fitScale = cropWindowSize / (minSide * imageDisplayScale.value)
  return Math.max(1, fitScale)
})

const maxScale = computed(() => {
  return Math.max(4, minScale.value * 2)
})

const imageStyle = computed(() => {
  if (!imageInfo.value) return {}
  const w = imageInfo.value.width
  const h = imageInfo.value.height
  const s = imageDisplayScale.value * scale.value
  return {
    width: `${w * s}px`,
    height: `${h * s}px`,
    transform: `translate(${translateX.value}px, ${translateY.value}px)`
  }
})

const resetPosition = () => {
  if (!imageInfo.value) return
  const w = imageInfo.value.width
  const h = imageInfo.value.height
  const s = imageDisplayScale.value
  scale.value = minScale.value
  const actualS = s * scale.value
  translateX.value = (previewSize - w * actualS) / 2
  translateY.value = (previewSize - h * actualS) / 2
}

const onTouchStart = (e) => {
  if (e.touches.length !== 1) return
  isTouching = true
  touchStartX = e.touches[0].clientX
  touchStartY = e.touches[0].clientY
  startTranslateX = translateX.value
  startTranslateY = translateY.value
}

const onTouchMove = (e) => {
  if (!isTouching || e.touches.length !== 1) return
  const dx = e.touches[0].clientX - touchStartX
  const dy = e.touches[0].clientY - touchStartY
  translateX.value = startTranslateX + dx
  translateY.value = startTranslateY + dy
}

const onTouchEnd = () => {
  isTouching = false
  clampPosition()
}

const clampPosition = () => {
  if (!imageInfo.value) return
  const w = imageInfo.value.width
  const h = imageInfo.value.height
  const s = imageDisplayScale.value * scale.value
  const imgW = w * s
  const imgH = h * s

  const minX = cropWindowRight - imgW
  const maxX = cropWindowLeft
  const minY = cropWindowRight - imgH
  const maxY = cropWindowLeft

  translateX.value = Math.max(Math.min(translateX.value, maxX), minX)
  translateY.value = Math.max(Math.min(translateY.value, maxY), minY)
}

const onSliderChange = (e) => {
  const newScale = e.detail.value
  const previewCenterX = previewSize / 2
  const previewCenterY = previewSize / 2

  const imgCenterX = (imageInfo.value.width * imageDisplayScale.value * scale.value) / 2 + translateX.value
  const imgCenterY = (imageInfo.value.height * imageDisplayScale.value * scale.value) / 2 + translateY.value

  const scaleRatio = newScale / scale.value

  const newImgCenterX = previewCenterX - (previewCenterX - imgCenterX) * scaleRatio
  const newImgCenterY = previewCenterY - (previewCenterY - imgCenterY) * scaleRatio

  const newImgW = imageInfo.value.width * imageDisplayScale.value * newScale
  const newImgH = imageInfo.value.height * imageDisplayScale.value * newScale

  translateX.value = newImgCenterX - newImgW / 2
  translateY.value = newImgCenterY - newImgH / 2

  scale.value = newScale
  clampPosition()
}

const doCrop = () => {
  if (cropping.value || !imageInfo.value) return
  cropping.value = true
  uni.showLoading({ title: '处理中', mask: true })

  const imgW = imageInfo.value.width
  const imgH = imageInfo.value.height
  const s = imageDisplayScale.value * scale.value

  const previewCenterX = previewSize / 2
  const previewCenterY = previewSize / 2

  const imgCenterX = previewCenterX - translateX.value
  const imgCenterY = previewCenterY - translateY.value

  const actualImgCenterX = imgCenterX / s
  const actualImgCenterY = imgCenterY / s

  const sourceCropSize = cropWindowSize / s

  const sx = actualImgCenterX - sourceCropSize / 2
  const sy = actualImgCenterY - sourceCropSize / 2
  const sw = sourceCropSize
  const sh = sourceCropSize

  const canvasSize = 400
  const ctx = uni.createCanvasContext('avatarCanvas', currentPage)
  ctx.setFillStyle('#ffffff')
  ctx.fillRect(0, 0, canvasSize, canvasSize)

  ctx.drawImage(
    src.value,
    sx,
    sy,
    sw,
    sh,
    0,
    0,
    canvasSize,
    canvasSize
  )

  ctx.draw(false, () => {
    setTimeout(() => {
      uni.canvasToTempFilePath({
        canvasId: 'avatarCanvas',
        width: canvasSize,
        height: canvasSize,
        destWidth: canvasSize,
        destHeight: canvasSize,
        fileType: 'jpg',
        quality: 0.9,
        success: async (res) => {
          try {
            const uploadRes = await uploadFile(res.tempFilePath)
            if (!uploadRes || !uploadRes.url) {
              throw new Error('upload failed')
            }
            const result = await api.updateProfile({ avatar_url: uploadRes.url })
            if (result && result.profile) {
              store.setProfile(result.profile)
            }
            uni.hideLoading()
            uni.showToast({ title: '头像已更新', icon: 'success' })
            setTimeout(() => uni.navigateBack(), 800)
          } catch (error) {
            console.warn('crop and upload failed', error)
            uni.hideLoading()
            uni.showToast({ title: '头像更新失败', icon: 'none' })
          } finally {
            cropping.value = false
          }
        },
        fail: (err) => {
          console.warn('canvas to temp file failed', err)
          uni.hideLoading()
          uni.showToast({ title: '裁剪失败', icon: 'none' })
          cropping.value = false
        }
      }, currentPage)
    }, 100)
  })
}

const goBack = () => {
  uni.navigateBack()
}
</script>

<style scoped lang="scss">
.page {
  min-height: 100vh;
  background: #000;
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 32rpx 0;
  box-sizing: border-box;
}
.loading {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #999;
}
.crop-container {
  width: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 0 32rpx;
  box-sizing: border-box;
}
.preview-area {
  position: relative;
  overflow: hidden;
  background: #1a1a1a;
}
.image-wrapper {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
}
.source-image {
  position: absolute;
  top: 0;
  left: 0;
  will-change: transform;
}
.crop-mask {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  pointer-events: none;
  background: rgba(0, 0, 0, 0.6);
}
.crop-window {
  position: absolute;
  top: 33.333%;
  left: 33.333%;
  width: 33.333%;
  height: 33.333%;
  background: transparent;
  box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.6);
  border: 2rpx solid rgba(255, 255, 255, 0.8);
  box-sizing: border-box;
}
.crop-grid {
  position: absolute;
  top: 33.333%;
  left: 33.333%;
  width: 33.333%;
  height: 33.333%;
  pointer-events: none;
}
.grid-h {
  position: absolute;
  left: 0;
  right: 0;
  height: 1rpx;
  background: rgba(255, 255, 255, 0.4);
}
.grid-h.h1 { top: 33.333%; }
.grid-h.h2 { top: 66.666%; }
.grid-v {
  position: absolute;
  top: 0;
  bottom: 0;
  width: 1rpx;
  background: rgba(255, 255, 255, 0.4);
}
.grid-v.v1 { left: 33.333%; }
.grid-v.v2 { left: 66.666%; }
.crop-corner {
  position: absolute;
  width: 40rpx;
  height: 40rpx;
  border: 4rpx solid #fff;
  box-sizing: border-box;
}
.crop-corner.tl {
  top: 33.333%;
  left: 33.333%;
  border-right: none;
  border-bottom: none;
}
.crop-corner.tr {
  top: 33.333%;
  right: 33.333%;
  border-left: none;
  border-bottom: none;
}
.crop-corner.bl {
  bottom: 33.333%;
  left: 33.333%;
  border-right: none;
  border-top: none;
}
.crop-corner.br {
  bottom: 33.333%;
  right: 33.333%;
  border-left: none;
  border-top: none;
}
.slider-row {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 24rpx;
  padding: 48rpx 24rpx;
  box-sizing: border-box;
}
.slider-label {
  color: #fff;
  font-size: 28rpx;
  width: 80rpx;
}
.slider {
  flex: 1;
}
.actions {
  width: 100%;
  display: flex;
  gap: 32rpx;
  padding: 0 32rpx 32rpx;
  box-sizing: border-box;
  margin-top: auto;
}
.action-btn {
  flex: 1;
  height: 88rpx;
  border-radius: 44rpx;
  font-size: 30rpx;
}
.action-btn.cancel {
  background: rgba(255, 255, 255, 0.15);
  color: #fff;
  border: none;
}
.action-btn.confirm {
  background: #1677ff;
  color: #fff;
  border: none;
}
.offscreen-canvas {
  position: fixed;
  left: -1000px;
  top: -1000px;
  width: 400px;
  height: 400px;
}
</style>
