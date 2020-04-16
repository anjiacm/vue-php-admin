/** When your routing table is too long, you can split it into small modules**/

import Blank from '@/views/blank'

const chartsRouter = {
  path: '/charts',
  // 嵌套路由菜单一级指定component为Layout,
  // 非叶子节点 **必须** 指定一个空白路由页面 @/views/blank/index.vue 前端添加菜单时需要指定 blank/index
  // 叶子节点指定具体组件页
  component: Blank, // component: () => import('@/views/blank/index.vue'), 效果一样
  redirect: 'noRedirect',
  name: 'Charts',
  meta: {
    title: '图表',
    icon: 'chart'
  },
  children: [
    {
      path: 'keyboard',
      component: () => import('@/views/charts/keyboard'),
      name: 'KeyboardChart',
      meta: { title: '键盘图表', noCache: true }
    },
    {
      path: 'line',
      component: () => import('@/views/charts/line'),
      name: 'LineChart',
      meta: { title: '折线图', noCache: true }
    },
    {
      path: 'mix-chart',
      component: () => import('@/views/charts/mix-chart'),
      name: 'MixChart',
      meta: { title: '混合图表', noCache: true }
    }
  ]
}

export default chartsRouter
