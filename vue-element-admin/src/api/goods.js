import request from '@/utils/request'

export function getgoods(token) {
  return request({
    url: '/uploadimg/goods',
    method: 'get'
  })
}

