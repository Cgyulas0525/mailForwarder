import { useState } from 'react'
import { api, setToken } from '../api'

export default function Login({ onSuccess }) {
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState('')
  const [processing, setProcessing] = useState(false)

  return (
    <main className="min-h-screen grid place-items-center p-4">
      <form
        className="w-full max-w-md bg-paper rounded-lg p-6 shadow"
        onSubmit={async (event) => {
          event.preventDefault()
          setError('')
          setProcessing(true)
          try {
            const data = await api('/login', { method: 'POST', body: JSON.stringify({ email, password }) })
            setToken(data.token)
            onSuccess(data.token)
          } catch (err) {
            setError(err.message)
          } finally {
            setProcessing(false)
          }
        }}
      >
        <h1 className="text-2xl font-semibold text-brand-800 mb-4">Belépés</h1>
        {error && <p className="mb-3 text-red-800" role="alert">{error}</p>}
        <label className="block text-sm mb-3">E-mail
          <input className="mt-1 w-full border rounded px-3 py-2 bg-white" type="email" value={email} onChange={(event) => setEmail(event.target.value)} required />
        </label>
        <label className="block text-sm mb-4">Jelszó
          <input className="mt-1 w-full border rounded px-3 py-2 bg-white" type="password" value={password} onChange={(event) => setPassword(event.target.value)} required />
        </label>
        <button className="bg-brand-600 text-white rounded px-4 py-2 disabled:opacity-50" type="submit" disabled={processing}>Belépés</button>
      </form>
    </main>
  )
}
