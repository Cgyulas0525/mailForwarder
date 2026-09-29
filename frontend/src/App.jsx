import { useState } from 'react'
import { Navigate, Route, Routes } from 'react-router-dom'
import { getToken } from './api'
import Dashboard from './pages/Dashboard'
import Layout from './components/Layout'
import AccountsCreate from './pages/accounts/Create'
import AccountsEdit from './pages/accounts/Edit'
import AccountsIndex from './pages/accounts/Index'
import Log from './pages/Log'
import Login from './pages/Login'
import PartyCreate from './pages/parties/Create'
import PartyEdit from './pages/parties/Edit'
import PartyIndex from './pages/parties/Index'
import Preview from './pages/Preview'
import RulesCreate from './pages/rules/Create'
import RulesEdit from './pages/rules/Edit'
import RulesIndex from './pages/rules/Index'
import WindowSettings from './pages/WindowSettings'
import Profile from './pages/Profile'

export default function App() {
  const [token, setTokenState] = useState(getToken())

  if (!token) {
    return <Login onSuccess={(value) => setTokenState(value)} />
  }

  return (
    <Layout onLogout={() => setTokenState(null)}>
      <Routes>
        <Route path="/" element={<Navigate to="/indito-pult" replace />} />
        <Route path="/indito-pult" element={<Dashboard />} />
        <Route path="/fiokok" element={<AccountsIndex />} />
        <Route path="/fiokok/uj" element={<AccountsCreate />} />
        <Route path="/fiokok/:id/szerkesztes" element={<AccountsEdit />} />
        <Route path="/feladok" element={<PartyIndex kind="senders" />} />
        <Route path="/feladok/uj" element={<PartyCreate kind="senders" />} />
        <Route path="/feladok/:id/szerkesztes" element={<PartyEdit kind="senders" />} />
        <Route path="/cimzettek" element={<PartyIndex kind="recipients" />} />
        <Route path="/cimzettek/uj" element={<PartyCreate kind="recipients" />} />
        <Route path="/cimzettek/:id/szerkesztes" element={<PartyEdit kind="recipients" />} />
        <Route path="/szabalyok" element={<RulesIndex />} />
        <Route path="/szabalyok/uj" element={<RulesCreate />} />
        <Route path="/szabalyok/:id/szerkesztes" element={<RulesEdit />} />
        <Route path="/naplo" element={<Log />} />
        <Route path="/teszt" element={<Preview />} />
        <Route path="/idoablak" element={<WindowSettings />} />
        <Route path="/profil" element={<Profile />} />
        <Route path="*" element={<Navigate to="/indito-pult" replace />} />
      </Routes>
    </Layout>
  )
}
