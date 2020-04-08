<template>
  <div v-loading="loading" :style="'height:'+ height">
    <iframe :src="src" frameborder="no" style="width: 100%;height: 100%" scrolling="auto" />
  </div>
</template>
<script>
export default {
  name: 'Druid',
  data() {
    return {
      src: 'https://codeigniter.org.cn/userguide3/',
      // src: 'http://vue.ruoyi.vip/prod-api/druid/datasource.html',
      // src: 'http://8.129.19.54:8091/modeler/modeler.html?modelId=6842',
      height: document.documentElement.clientHeight - 94.5 + 'px;',
      loading: true
    }
  },
  mounted: function() {
    setTimeout(() => {
      this.loading = false
    }, 230)
    const that = this
    window.onresize = function temp() {
      that.height = document.documentElement.clientHeight - 94.5 + 'px;'
    }
  }
}
</script>
