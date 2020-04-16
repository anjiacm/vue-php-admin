/** When your routing table is too long, you can split it into small modules **/

import Blank from '@/views/blank'

const tableRouter = {
  path: '/table',
  // 嵌套路由菜单一级指定component为Layout,
  // 非叶子节点 **必须** 指定一个空白路由页面 @/views/blank/index.vue 前端添加菜单时需要指定 blank/index
  // 叶子节点指定具体组件页
  component: Blank, // component: () => import('@/views/blank/index.vue'), 效果一样
  redirect: '/table/complex-table',
  name: 'Table',
  meta: {
    title: 'Table',
    icon: 'table'
  },
  children: [
    {
      path: 'dynamic-table',
      component: () => import('@/views/table/dynamic-table/index'),
      name: 'DynamicTable',
      meta: { title: '动态Table' }
    },
    {
      path: 'drag-table',
      component: () => import('@/views/table/drag-table'),
      name: 'DragTable',
      meta: { title: '拖拽Table' }
    },
    {
      path: 'inline-edit-table',
      component: () => import('@/views/table/inline-edit-table'),
      name: 'InlineEditTable',
      meta: { title: 'Table内编辑' }
    },
    {
      path: 'complex-table',
      component: () => import('@/views/table/complex-table'),
      name: 'ComplexTable',
      meta: { title: '综合Table' }
    }
  ]
}
export default tableRouter
