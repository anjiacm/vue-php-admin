const getters = {
  sidebar: state => state.app.sidebar,
  size: state => state.app.size,
  device: state => state.app.device,
  visitedViews: state => state.tagsView.visitedViews,
  cachedViews: state => state.tagsView.cachedViews,
  token: state => state.user.token,
  avatar: state => state.user.avatar,
  name: state => state.user.name,
  introduction: state => state.user.introduction,
  roles: state => state.user.roles,
  permission_routes: state => state.permission.routes,
  addRoutes: state => state.permission.addRoutes,
  errorLogs: state => state.errorLog.logs,

  status: state => state.user.status,
  phone: state => state.user.phone,
  identify: state => state.user.identify,
  ctrlperm: state => state.user.ctrlperm,

  // 获取商品总价 getters 与 state 都是 store 里面的关键字 作用不同，state 存储固定数据，getters 返回动态计算数据
  totalPrice: state => {
    let totalPrice = 0
    console.log('state.goods.goods---------------', state.goods.goods)
    const goods = state.goods.goods
    goods.forEach((v) => {
      totalPrice += v.num * v.price
    })
    return totalPrice
  },
  // 获取商品并计算每件商品的总价 state 参数是该store里的 state仓库
  goodsx: state => {
    // console.log('state.goods.goods.................', state.goods.goods)
    const goods = state.goods.goods
    goods.forEach((v) => {
      v.totalPrice = v.num * v.price
    })
    // console.log('getters#####goods###',goods)
    return goods
  }
}
export default getters
