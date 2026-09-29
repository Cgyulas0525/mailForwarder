import { api, fieldError } from '../../api'
import { CrudForm } from '../../components/CrudShell'
import { Field, inputClass } from '../../components/ui'

export function emptyAccount() {
  return {
    display_name: '',
    email: '',
    password: '',
    provider: 'gmail',
    imap_username: '',
    imap_mailbox: 'INBOX',
    imap_host: '',
    imap_port: 993,
    imap_encryption: 'ssl',
    smtp_host: '',
    smtp_port: 587,
    smtp_encryption: 'tls',
    is_enabled: true,
  }
}

export function accountFromRow(row) {
  return { ...emptyAccount(), ...row, password: '', is_enabled: !!row.is_enabled }
}

export function accountPayload(form, creating) {
  const payload = {
    display_name: form.display_name,
    email: form.email,
    provider: form.provider,
    imap_username: form.imap_username,
    imap_mailbox: form.imap_mailbox,
    imap_host: form.imap_host,
    imap_port: Number(form.imap_port) || null,
    imap_encryption: form.imap_encryption,
    smtp_host: form.smtp_host,
    smtp_port: Number(form.smtp_port) || null,
    smtp_encryption: form.smtp_encryption,
    is_enabled: !!form.is_enabled,
  }
  if (creating || form.password) {
    payload.password = form.password
  }
  return payload
}

function applyProvider(form, provider) {
  if (provider === 'gmail') {
    return {
      ...form,
      provider,
      imap_username: '',
      imap_mailbox: 'INBOX',
      imap_host: '',
      imap_port: 993,
      imap_encryption: 'ssl',
      smtp_host: '',
      smtp_port: 587,
      smtp_encryption: 'tls',
    }
  }
  return {
    ...form,
    provider,
    imap_username: form.imap_username || 'menteshe',
    imap_mailbox: form.imap_mailbox === 'INBOX' ? 'INBOX.info@menteshetes_hu' : (form.imap_mailbox || 'INBOX.info@menteshetes_hu'),
    imap_host: form.imap_host || 'mail.menteshetes.hu',
    imap_port: form.imap_port || 993,
    imap_encryption: form.imap_encryption || 'ssl',
    smtp_host: form.smtp_host || 'mail.menteshetes.hu',
    smtp_port: form.smtp_port || 587,
    smtp_encryption: form.smtp_encryption || 'tls',
  }
}

export default function AccountForm({ title, form, setForm, errors, processing, onSubmit, creating }) {
  const set = (name, value) => setForm({ ...form, [name]: value })

  return (
    <CrudForm title={title} onSubmit={onSubmit} processing={processing} cancelTo="/fiokok" submitLabel={creating ? 'Felvétel' : 'Mentés'}>
      <Field id="display_name" label="Megjelenő név" error={fieldError(errors, 'display_name')}>
        <input id="display_name" className={inputClass} value={form.display_name} onChange={(event) => set('display_name', event.target.value)} />
      </Field>
      <Field id="email" label="E-mail *" error={fieldError(errors, 'email')}>
        <input id="email" className={inputClass} type="email" required value={form.email} onChange={(event) => set('email', event.target.value)} />
      </Field>
      <Field id="password" label={creating ? 'Alkalmazásjelszó *' : 'Alkalmazásjelszó'} error={fieldError(errors, 'password')}>
        <input id="password" className={inputClass} type="password" required={creating} placeholder={creating ? '' : 'Üresen hagyva nem változik'} value={form.password} onChange={(event) => set('password', event.target.value)} />
      </Field>
      <Field id="provider" label="Szolgáltató" error={fieldError(errors, 'provider')}>
        <select id="provider" className={inputClass} value={form.provider} onChange={(event) => setForm(applyProvider(form, event.target.value))}>
          <option value="gmail">Gmail</option>
          <option value="custom">Egyéni IMAP</option>
        </select>
      </Field>
      <Field id="imap_username" label="IMAP felhasználónév" error={fieldError(errors, 'imap_username')}>
        <input id="imap_username" className={inputClass} value={form.imap_username ?? ''} onChange={(event) => set('imap_username', event.target.value)} />
      </Field>
      <Field id="imap_mailbox" label="IMAP mappa" error={fieldError(errors, 'imap_mailbox')}>
        <input id="imap_mailbox" className={inputClass} value={form.imap_mailbox ?? ''} onChange={(event) => set('imap_mailbox', event.target.value)} />
      </Field>
      {form.provider === 'custom' && (
        <div className="grid md:grid-cols-2 gap-3">
          <Field id="imap_host" label="IMAP host *" error={fieldError(errors, 'imap_host')}>
            <input id="imap_host" className={inputClass} required value={form.imap_host ?? ''} onChange={(event) => set('imap_host', event.target.value)} />
          </Field>
          <Field id="imap_port" label="IMAP port" error={fieldError(errors, 'imap_port')}>
            <input id="imap_port" className={inputClass} type="number" value={form.imap_port ?? ''} onChange={(event) => set('imap_port', event.target.value)} />
          </Field>
          <Field id="imap_encryption" label="IMAP titkosítás" error={fieldError(errors, 'imap_encryption')}>
            <select id="imap_encryption" className={inputClass} value={form.imap_encryption} onChange={(event) => set('imap_encryption', event.target.value)}>
              <option value="ssl">ssl</option>
              <option value="tls">tls</option>
              <option value="none">nincs</option>
            </select>
          </Field>
          <Field id="smtp_host" label="SMTP host *" error={fieldError(errors, 'smtp_host')}>
            <input id="smtp_host" className={inputClass} required value={form.smtp_host ?? ''} onChange={(event) => set('smtp_host', event.target.value)} />
          </Field>
          <Field id="smtp_port" label="SMTP port" error={fieldError(errors, 'smtp_port')}>
            <input id="smtp_port" className={inputClass} type="number" value={form.smtp_port ?? ''} onChange={(event) => set('smtp_port', event.target.value)} />
          </Field>
          <Field id="smtp_encryption" label="SMTP titkosítás" error={fieldError(errors, 'smtp_encryption')}>
            <select id="smtp_encryption" className={inputClass} value={form.smtp_encryption} onChange={(event) => set('smtp_encryption', event.target.value)}>
              <option value="ssl">ssl</option>
              <option value="tls">tls</option>
              <option value="none">nincs</option>
            </select>
          </Field>
        </div>
      )}
      <label className="text-sm flex items-center gap-2">
        <input type="checkbox" checked={!!form.is_enabled} onChange={(event) => set('is_enabled', event.target.checked)} />
        Engedélyezve
      </label>
    </CrudForm>
  )
}

export async function saveAccount(form, id) {
  const creating = !id
  const payload = accountPayload(form, creating)
  if (creating) {
    await api('/accounts', { method: 'POST', body: JSON.stringify(payload) })
  } else {
    await api(`/accounts/${id}`, { method: 'PUT', body: JSON.stringify(payload) })
  }
}
