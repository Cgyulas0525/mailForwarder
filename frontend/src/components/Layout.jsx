import { createContext, useCallback, useContext, useState } from 'react'
import { NavLink, useLocation } from 'react-router-dom'
import { api, setToken } from '../api'

const NoticeContext = createContext(() => {})

export function useNotify() {
  return useContext(NoticeContext)
}

const nav = [
  ['/fiokok', 'Postafiókok'],
  ['/feladok', 'Feladók'],
  ['/cimzettek', 'Címzettek'],
  ['/szabalyok', 'Szabályok'],
  ['/naplo', 'Napló'],
  ['/teszt', 'Szabályteszt'],
  ['/idoablak', 'Időablak'],
]

const titles = [
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
]

function pageTitle(pathname) {
  const match = titles.find(([prefix]) => pathname === prefix || pathname.startsWith(prefix))
  return match ? match[1] : 'E-mail továbbító'
}

export default function Layout({ children, onLogout }) {
  const location = useLocation()
  const [open, setOpen] = useState(false)
  const [notice, setNotice] = useState('')
  const notify = useCallback((message) => {
    setNotice(message)
    window.setTimeout(() => setNotice(''), 4000)
  }, [])

  return (
    <NoticeContext.Provider value={notify}>
      <div className="min-h-screen md:flex">
        <aside className={`${open ? 'block' : 'hidden'} md:block w-full md:w-64 bg-brand-800 text-white md:min-h-screen`}>
          <div className="px-5 py-4 text-lg font-semibold">mailForwarder</div>
          <nav className="flex flex-col" aria-label="Főmenü">
            {nav.map(([href, label]) => (
              <NavLink
                key={href}
                to={href}
                onClick={() => setOpen(false)}
                className={({ isActive }) => `px-5 py-3 text-sm ${isActive ? 'bg-brand-600' : 'hover:bg-brand-700'}`}
              >
                {label}
              </NavLink>
            ))}
          </nav>
        </aside>
        <div className="flex-1 min-w-0">
          <header className="bg-brand-800 text-white px-4 py-3 flex items-center gap-3">
            <button type="button" className="md:hidden border border-white/40 rounded px-2 py-1" aria-expanded={open} onClick={() => setOpen((current) => !current)}>
              Menü
            </button>
            <div className="font-semibold">{pageTitle(location.pathname)}</div>
            <button
              type="button"
              className="ml-auto text-sm underline"
              onClick={async () => {
                await api('/logout', { method: 'POST' }).catch(() => {})
                setToken(null)
                onLogout()
              }}
            >
              Kijelentkezés
            </button>
          </header>
          <main className="p-4 md:p-6">
            {notice && <div role="status" className="mb-4 rounded bg-brand-600 text-white px-4 py-2">{notice}</div>}
            {children}
          </main>
        </div>
      </div>
    </NoticeContext.Provider>
  )
}
