import { fieldError } from '../../api'
import { CrudForm } from '../../components/CrudShell'
import { Field, inputClass } from '../../components/ui'

export function emptyParty() {
  return { name: '', email: '', is_active: true }
}

export default function PartyForm({ kind, title, form, setForm, errors, processing, onSubmit, creating }) {
  const set = (name, value) => setForm({ ...form, [name]: value })

  return (
    <CrudForm title={title} onSubmit={onSubmit} processing={processing} cancelTo={kind.listPath} submitLabel={creating ? 'Felvétel' : 'Mentés'}>
      <Field id="name" label="Név" error={fieldError(errors, 'name')}>
        <input id="name" className={inputClass} value={form.name ?? ''} onChange={(event) => set('name', event.target.value)} />
      </Field>
      <Field id="email" label="E-mail *" error={fieldError(errors, 'email')}>
        <input id="email" className={inputClass} type="email" required value={form.email} onChange={(event) => set('email', event.target.value)} />
      </Field>
      <label className="text-sm flex items-center gap-2">
        <input type="checkbox" checked={!!form.is_active} onChange={(event) => set('is_active', event.target.checked)} />
        Aktív
      </label>
    </CrudForm>
  )
}
