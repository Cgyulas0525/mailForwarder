import { createContext, useCallback, useContext, useEffect, useState } from 'react'
import { Link, NavLink, useLocation } from 'react-router-dom'
import { api, getSessionUser, setSessionUser, setToken } from '../api'

const NoticeContext = createContext(() => {})
const UserContext = createContext({ user: null, setUser: () => {} })

export function useNotify() {
  return useContext(NoticeContext)
}

export function useUser() {
  return useContext(UserContext)
}

const icons = {
  home: (
    <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
    </svg>
  ),
  inbox: (
    <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
    </svg>
  ),
  senders: (
    <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
    </svg>
  ),
  recipients: (
    <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
    </svg>
  ),
  rules: (
    <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
    </svg>
  ),
  log: (
    <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
  ),
  test: (
    <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
    </svg>
  ),
  window: (
    <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
    </svg>
  ),
}

const nav = [
  { href: '/indito-pult', label: 'Indító pult', icon: icons.home, end: true },
  { href: '/fiokok', label: 'Postafiókok', icon: icons.inbox },
  { href: '/feladok', label: 'Feladók', icon: icons.senders },
  { href: '/cimzettek', label: 'Címzettek', icon: icons.recipients },
  { href: '/szabalyok', label: 'Szabályok', icon: icons.rules },
  { href: '/naplo', label: 'Napló', icon: icons.log },
  { href: '/teszt', label: 'Szabályteszt', icon: icons.test },
  { href: '/idoablak', label: 'Időablak', icon: icons.window },
]

const titles = [
  ['/indito-pult', 'Indító pult'],
  ['/fiokok/uj', 'Postafiókok / Új'],
  ['/feladok/uj', 'Feladók / Új'],
  ['/cimzettek/uj', 'Címzettek / Új'],
  ['/szabalyok/uj', 'Szabályok / Új'],
  ['/fiokok/', 'Postafiókok / Szerkesztés'],
  ['/feladok/', 'Feladók / Szerkesztés'],
  ['/cimzettek/', 'Címzettek / Szerkesztés'],
  ['/szabalyok/', 'Szabályok / Szerkesztés'],
  ['/fiokok', 'Postafiókok'],
  ['/feladok', 'Feladók'],
  ['/cimzettek', 'Címzettek'],
  ['/szabalyok', 'Szabályok'],
  ['/naplo', 'Napló'],
  ['/teszt', 'Szabályteszt'],
  ['/idoablak', 'Időablak'],
  ['/profil', 'Profil'],
]

function pageTitle(pathname) {
  const match = titles.find(([prefix]) => pathname === prefix || pathname.startsWith(prefix))
  return match ? match[1] : 'E-mail továbbító'
}

function UserMenu({ user, onLogout }) {
  const [open, setOpen] = useState(false)

  return (
    <div className="relative">
      <button
        type="button"
        className="flex items-center gap-2 text-sm text-brand-100 hover:text-white focus:outline-none"
        aria-expanded={open}
        aria-haspopup="menu"
        onClick={() => setOpen((current) => !current)}
      >
        <span className="h-8 w-8 rounded-full bg-brand-600 flex items-center justify-center text-white font-semibold text-xs">
          {(user?.name || '?').charAt(0).toUpperCase()}
        </span>
        <span className="hidden sm:block font-medium">{user?.name ?? ''}</span>
        <svg className="h-4 w-4 text-brand-300" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
          <path fillRule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clipRule="evenodd" />
        </svg>
      </button>
      {open && (
        <>
          <div className="fixed inset-0 z-40" onClick={() => setOpen(false)} />
          <div className="absolute right-0 z-50 mt-2 w-48 origin-top-right rounded-md bg-white py-1 shadow-lg ring-1 ring-black/5" role="menu">
            <Link
              to="/profil"
              role="menuitem"
              className="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 hover:bg-gray-100"
              onClick={() => setOpen(false)}
            >
              Profil
            </Link>
            <button
              type="button"
              role="menuitem"
              className="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 hover:bg-gray-100"
              onClick={async () => {
                setOpen(false)
                await api('/logout', { method: 'POST' }).catch(() => {})
                setToken(null)
                onLogout()
              }}
            >
              Kijelentkezés
            </button>
          </div>
        </>
      )}
    </div>
  )
}

export default function Layout({ children, onLogout }) {
  const location = useLocation()
  const [sidebarOpen, setSidebarOpen] = useState(false)
  const [notice, setNotice] = useState('')
  const [user, setUserState] = useState(getSessionUser)
  const notify = useCallback((message) => {
    setNotice(message)
    window.setTimeout(() => setNotice(''), 4000)
  }, [])
  const setUser = useCallback((value) => {
    setUserState(value)
    setSessionUser(value)
  }, [])

  useEffect(() => {
    const handleResize = () => {
      if (window.innerWidth < 640) {
        setSidebarOpen(false)
      }
    }
    setSidebarOpen(window.innerWidth >= 640)
    window.addEventListener('resize', handleResize)
    return () => window.removeEventListener('resize', handleResize)
  }, [])

  useEffect(() => {
    api('/me').then(setUser).catch((error) => {
      if (error.status === 401) {
        setToken(null)
        onLogout()
      }
    })
  }, [onLogout, setUser])

  const closeSidebarOnMobile = () => {
    if (window.innerWidth < 640) {
      setSidebarOpen(false)
    }
  }

  return (
    <NoticeContext.Provider value={notify}>
      <UserContext.Provider value={{ user, setUser }}>
      <div className="flex h-screen bg-cream">
        {sidebarOpen && (
          <div className="fixed inset-0 z-40 bg-black/50 sm:hidden" onClick={() => setSidebarOpen(false)} />
        )}

        <aside
          className={[
            'fixed inset-y-0 left-0 z-50 flex flex-col bg-brand-800 text-white transition-all duration-200',
            'sm:static sm:inset-auto sm:z-auto',
            sidebarOpen ? 'w-64 sm:w-56 translate-x-0' : 'w-64 sm:w-16 -translate-x-full sm:translate-x-0',
          ].join(' ')}
        >
          <div className="flex items-center gap-3 px-4 py-4 border-b border-brand-700">
            <div className="flex-shrink-0 w-8 h-8 rounded-full bg-white text-brand-800 grid place-items-center text-sm font-bold">mF</div>
            {sidebarOpen && <span className="text-lg font-bold tracking-wide">mailForwarder</span>}
          </div>
          <nav className="flex-1 py-2 overflow-y-auto" aria-label="Főmenü">
            {nav.map((item) => (
              <NavLink
                key={item.href}
                to={item.href}
                end={item.end}
                title={!sidebarOpen ? item.label : undefined}
                onClick={closeSidebarOnMobile}
                className={({ isActive }) => `flex items-center gap-3 px-4 py-2.5 text-sm font-medium transition-colors ${
                  isActive ? 'bg-brand-600 text-white' : 'text-brand-100 hover:bg-brand-700 hover:text-white'
                }`}
              >
                <span className="flex-shrink-0">{item.icon}</span>
                {sidebarOpen && <span>{item.label}</span>}
              </NavLink>
            ))}
          </nav>
        </aside>

        <div className="flex flex-col flex-1 min-w-0 overflow-hidden">
          <header className="flex items-center justify-between h-14 bg-brand-800 border-b border-brand-700 px-4 flex-shrink-0 text-white">
            <div className="flex items-center gap-3">
              <button
                type="button"
                className="text-brand-100 hover:text-white"
                aria-expanded={sidebarOpen}
                aria-label={sidebarOpen ? 'Menü becsukása' : 'Menü kinyitása'}
                onClick={() => setSidebarOpen((current) => !current)}
              >
                <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                </svg>
              </button>
              <div className="text-sm font-semibold">{pageTitle(location.pathname)}</div>
            </div>
            <UserMenu user={user} onLogout={onLogout} />
          </header>
          <main className={`flex-1 overflow-y-auto p-4 md:p-6 ${location.pathname === '/indito-pult' ? 'bg-gray-900' : ''}`}>
            {notice && <div role="status" className="mb-4 rounded bg-brand-600 text-white px-4 py-2">{notice}</div>}
            {children}
          </main>
        </div>
      </div>
      </UserContext.Provider>
    </NoticeContext.Provider>
  )
}
