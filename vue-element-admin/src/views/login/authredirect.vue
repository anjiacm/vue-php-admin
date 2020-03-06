<script>
export default {
  name: 'AuthRedirect',
  created() {
    this.githubLogin()
  },
  methods: {
    githubLogin() {
      console.log('in AuthRedirect ... this.$store.state.user.code', this.$store.state.user.code)
      console.log(window.location)
      // 1.  授权成功后, github 返回给 AuthRedirect子窗口的浏览器 回调地址 并带上 ?code=8789d613d1fa9a19732a 参数
      //     地址栏URL如 http://localhost:9527/auth-redirect?code=8789d613d1fa9a19732a
      //     其中 http://localhost:9527/auth-redirect 是定义 githubHandleClick() 里定义的回调地址
      //     const url = 'https://github.com/login/oauth/authorize?client_id=94aae05609c96ffb7d3b&redirect_uri=http://localhost:9527/auth-redirect'
      //     此时 window.location.href   => http://localhost:9527/auth-redirect?code=8789d613d1fa9a19732a
      //         window.location.search  => ?code=8789d613d1fa9a19732a
      // 2. 调用 window.opener 方法 给 父窗口 的 location.href 赋值 => http://localhost:9527/?code=8789d613d1fa9a19732a
      window.opener.location.href = window.location.origin + '/' + window.location.search
      //    可在此 使用未定义的变量 来 debug  // Error in created hook: "ReferenceError: hash is not defined" found in window.opener.location.href = window.location.origin + '?' + hash
      // 3. 关闭 AuthRedirect 子窗口。同时代码逻辑至父窗口 在 permission.js => router.beforeEach 进行 code 处理
      window.close()
    }
  }
}
</script>
