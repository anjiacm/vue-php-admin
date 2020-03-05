import request from '@/utils/request'

export function loginByUsername(username, password) {
  const data = {
    username,
    password
  }
  return request({
    url: '/sys/user/login',
    method: 'post',
    data
  })
}

export function logout() {
  return request({
    url: '/sys/user/logout',
    method: 'post'
  })
}

export function getUserInfo(token) {
  return request({
    url: '/sys/user/info',
    method: 'get',
    params: { token }
  })
}

// github 微信认证
export function githubAuth(code) {
  return request({
    url: '/sys/user/githubauth',
    method: 'get',
    params: { code }
  })
}

export function checkRefreshToken() {
  return request({
    url: '/sys/user/refreshtoken',
    method: 'post'
  })
}
