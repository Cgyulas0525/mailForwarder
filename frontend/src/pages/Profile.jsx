import { useEffect, useState } from 'react'
import { api, fieldError } from '../api'
import { useNotify, useUser } from '../components/Layout'
import { btnPrimary, Field, inputClass } from '../components/ui'

export default function Profile() {
  const notify = useNotify()
  const { user, setUser } = useUser()
  const [form, setForm] = useState({
    name: user?.name || '',
    email: user?.email || '',
    current_password: '',
    password: '',
    password_confirmation: '',
  })
  const [errors, setErrors] = useState({})
  const [processing, setProcessing] = useState(false)

  useEffect(() => {
    if (user) {
      setForm((current) => ({ ...current, name: user.name || '', email: user.email || '' }))
    }
  }, [user])

  const setField = (name) => (event) => setForm({ ...form, [name]: event.target.value })

  return (
    <section className="max-w-2xl bg-paper rounded-xl shadow">
      <div className="px-6 py-4 border-b border-sand">
        <h1 className="text-lg font-semibold text-brand-800">Profil</h1>
        <p className="mt-1 text-sm text-gray-600">Név, e-mail és jelszó módosítása.</p>
      </div>
      <form
        className="px-6 py-4 space-y-4"
        onSubmit={async (event) => {
          event.preventDefault()
          setProcessing(true)
          setErrors({})
          try {
            const payload = { name: form.name, email: form.email }
            if (form.password) {
              payload.password = form.password
              payload.password_confirmation = form.password_confirmation
              payload.current_password = form.current_password
            }
            const updated = await api('/me', { method: 'PUT', body: JSON.stringify(payload) })
            setUser(updated)
            setForm({ ...form, current_password: '', password: '', password_confirmation: '' })
            notify('Profil mentve.')
          } catch (error) {
            setErrors(error.errors || {})
            notify(error.message)
          } finally {
            setProcessing(false)
          }
        }}
      >
        <Field id="name" label="Név" error={fieldError(errors, 'name')}>
          <input id="name" className={inputClass} value={form.name} onChange={setField('name')} required autoComplete="name" />
        </Field>
        <Field id="email" label="E-mail" error={fieldError(errors, 'email')}>
          <input id="email" className={inputClass} type="email" value={form.email} onChange={setField('email')} required autoComplete="username" />
        </Field>
        <Field id="current_password" label="Jelenlegi jelszó (csak jelszóváltáshoz)" error={fieldError(errors, 'current_password')}>
          <input id="current_password" className={inputClass} type="password" value={form.current_password} onChange={setField('current_password')} autoComplete="current-password" />
        </Field>
        <Field id="password" label="Új jelszó" error={fieldError(errors, 'password')}>
          <input id="password" className={inputClass} type="password" value={form.password} onChange={setField('password')} autoComplete="new-password" />
        </Field>
        <Field id="password_confirmation" label="Új jelszó mégegyszer">
          <input id="password_confirmation" className={inputClass} type="password" value={form.password_confirmation} onChange={setField('password_confirmation')} autoComplete="new-password" />
        </Field>
        <div className="pt-2">
          <button type="submit" disabled={processing} className={btnPrimary}>Mentés</button>
        </div>
      </form>
    </section>
  )
}
