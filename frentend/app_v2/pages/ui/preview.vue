<template>
  <scroll-view scroll-y class="page">
    <view class="intro">
      <text>以下为内置的 UI 设计稿，可点击任意缩略图查看大图。</text>
    </view>
    <view class="grid">
      <view
        class="item"
        v-for="design in designs"
        :key="design.title"
        @click="preview(design)"
      >
        <image class="thumb" :src="design.src" mode="widthFix" lazy-load />
        <view class="title">{{ design.title }}</view>
      </view>
    </view>
  </scroll-view>
</template>

<script setup>
const designs = [
  { title: '首页', src: '/static/ui/首页.png' },
  { title: '首页（统计）', src: '/static/ui/首页(1).png' },
  { title: '标签栏', src: '/static/ui/标签栏.png' },
  { title: '消息', src: '/static/ui/消息.png' },
  { title: '通知', src: '/static/ui/通知.png' },
  { title: '我的', src: '/static/ui/我的.png' },
  { title: '群聊', src: '/static/ui/群聊.png' },
  { title: '新建任务', src: '/static/ui/新建任务.png' },
  { title: '任务 - 查看详情', src: '/static/ui/查看详情.png' },
  { title: '任务 - 打卡', src: '/static/ui/打卡.png' },
  { title: '任务 - 报销', src: '/static/ui/报销.png' },
  { title: '订单任务', src: '/static/ui/订单任务.png' },
  { title: '订单铭牌', src: '/static/ui/订单铭牌.png' },
  { title: '订单费用', src: '/static/ui/订单费用.png' },
  { title: '订单收尾文件', src: '/static/ui/订单收尾文件.png' },
  { title: '订单收尾文件-上传', src: '/static/ui/订单收尾文件-上传文件.png' },
  { title: '订单收尾文件-审核', src: '/static/ui/订单收尾文件-审核.png' },
  { title: '个人信息', src: '/static/ui/个人信息.png' },
  { title: '统计', src: '/static/ui/统计.png' },
  { title: '工厂订单看板', src: '/static/ui/工厂订单看板.png' },
  { title: '机器管理任务', src: '/static/ui/机器管理任务.png' },
  { title: '客户验厂', src: '/static/ui/客户验厂.png' },
  { title: '临时任务', src: '/static/ui/临时任务.png' },
  { title: '意向订单', src: '/static/ui/意向订单.png' }
]

const preview = (item) => {
  uni.previewImage({
    urls: designs.map((d) => d.src),
    current: item.src
  })
}
</script>

<style scoped lang="scss">
.page {
  padding: 24rpx;
}
.intro {
  background: #fff;
  padding: 24rpx;
  border-radius: 16rpx;
  color: #666;
  margin-bottom: 24rpx;
  font-size: 26rpx;
}
.grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 24rpx;
}
.item {
  background: #fff;
  border-radius: 16rpx;
  overflow: hidden;
  box-shadow: 0 8rpx 24rpx rgba(0, 0, 0, 0.05);
}
.thumb {
  width: 100%;
  display: block;
}
.title {
  padding: 16rpx;
  font-size: 26rpx;
  text-align: center;
}
</style>
