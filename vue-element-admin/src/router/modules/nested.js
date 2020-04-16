/** When your routing table is too long, you can split it into small modules **/

import Blank from '@/views/blank'

const nestedRouter = {
  path: '/nested',
  // 嵌套路由菜单一级指定component为Layout,
  // 非叶子节点 **必须** 指定一个空白路由页面 @/views/blank/index.vue 前端添加菜单时需要指定 blank/index
  // 叶子节点指定具体组件页
  component: Blank, // component: () => import('@/views/blank/index.vue'), 效果一样
  redirect: '/nested/menu1/menu1-1',
  name: 'Nested',
  meta: {
    title: '路由嵌套',
    icon: 'nested'
  },
  children: [
    {
      path: 'menu1',
      component: () => import('@/views/nested/menu1/index'), // Parent router-view
      name: 'Menu1',
      meta: { title: '菜单1' },
      redirect: '/nested/menu1/menu1-1',
      children: [
        {
          path: 'menu1-1',
          component: () => import('@/views/nested/menu1/menu1-1'),
          name: 'Menu1-1',
          meta: { title: '菜单1-1' }
        },
        {
          path: 'menu1-2',
          component: () => import('@/views/nested/menu1/menu1-2'),
          name: 'Menu1-2',
          redirect: '/nested/menu1/menu1-2/menu1-2-1',
          meta: { title: '菜单1-2' },
          children: [
            {
              path: 'menu1-2-1',
              component: () => import('@/views/nested/menu1/menu1-2/menu1-2-1'),
              name: 'Menu1-2-1',
              meta: { title: '菜单1-2-1' }
            },
            {
              path: 'menu1-2-2',
              component: () => import('@/views/nested/menu1/menu1-2/menu1-2-2'),
              name: 'Menu1-2-2',
              meta: { title: '菜单1-2-2' }
            }
          ]
        },
        {
          path: 'menu1-3',
          component: () => import('@/views/nested/menu1/menu1-3'),
          name: 'Menu1-3',
          meta: { title: '菜单1-3' }
        }
      ]
    },
    {
      path: 'menu2',
      name: 'Menu2',
      component: () => import('@/views/nested/menu2/index'),
      meta: { title: '菜单2' }
    }
  ]
}

export default nestedRouter
