const base = import.meta.env.VITE_API_URL || '/api'

export function getToken() {
  return sessionStorage.getItem('mf_token')
}

export function setToken(token) {
  if (token) {
    sessionStorage.setItem('mf_token', token)
  } else {
    sessionStorage.removeItem('mf_token')
    sessionStorage.removeItem('mf_user')
  }
}

export function getSessionUser() {
  try {
    return JSON.parse(sessionStorage.getItem('mf_user') || 'null')
  } catch {
    return null
  }
}

export function setSessionUser(user) {
  if (user) {
    sessionStorage.setItem('mf_user', JSON.stringify(user))
  } else {
    sessionStorage.removeItem('mf_user')
  }
}

export async function api(path, options = {}) {
  const headers = { Accept: 'application/json', ...(options.headers || {}) }
  if (options.body) {
    headers['Content-Type'] = 'application/json'
  }
  const token = getToken()
  if (token) {
    headers.Authorization = `Bearer ${token}`
  }

  const response = await fetch(`${base}${path}`, { ...options, headers })
  const data = await response.json().catch(() => ({}))
  if (!response.ok) {
    const error = new Error(data.message || 'A kérés sikertelen.')
    error.status = response.status
    error.data = data
    error.errors = data.errors || {}
    throw error
  }
  return data
}

export function fieldError(errors, name) {
  const value = errors?.[name]
  if (!value) {
    return ''
  }
  return Array.isArray(value) ? value[0] : String(value)
}
