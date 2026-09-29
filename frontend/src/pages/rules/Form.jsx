import { useEffect, useState } from 'react'
import { api, fieldError } from '../../api'
import { CrudForm } from '../../components/CrudShell'
import { Field, MultiCheck, inputClass } from '../../components/ui'

export function emptyRule() {
  return {
    name: '',
    match_mode: 'any',
    checks_invoice_link: true,
    is_active: true,
    account_ids: [],
    sender_ids: [],
    recipient_ids: [],
  }
}

export function ruleFromRow(row) {
  return {
    name: row.name ?? '',
    match_mode: row.match_mode ?? 'any',
    checks_invoice_link: !!row.checks_invoice_link,
    is_active: !!row.is_active,
    account_ids: (row.accounts || []).map((item) => item.id),
    sender_ids: (row.senders || []).map((item) => item.id),
    recipient_ids: (row.recipients || []).map((item) => item.id),
  }
}

export function rulePayload(form) {
  return {
    name: form.name,
    match_mode: form.match_mode,
    checks_invoice_link: !!form.checks_invoice_link,
    is_active: !!form.is_active,
    account_ids: form.account_ids,
    sender_ids: form.sender_ids,
    recipient_ids: form.recipient_ids,
  }
}

export default function RuleForm({ title, form, setForm, errors, processing, onSubmit, creating }) {
  const [options, setOptions] = useState({ accounts: [], senders: [], recipients: [] })
  const set = (name, value) => setForm({ ...form, [name]: value })

  useEffect(() => {
    Promise.all([api('/accounts'), api('/senders'), api('/recipients')]).then(([accounts, senders, recipients]) => {
      setOptions({ accounts: accounts.data, senders: senders.data, recipients: recipients.data })
    })
  }, [])

  return (
    <CrudForm title={title} onSubmit={onSubmit} processing={processing} cancelTo="/szabalyok" submitLabel={creating ? 'Felvétel' : 'Mentés'}>
      <Field id="name" label="Név *" error={fieldError(errors, 'name')}>
        <input id="name" className={inputClass} required value={form.name} onChange={(event) => set('name', event.target.value)} />
      </Field>
      <Field id="match_mode" label="Kapcsolat" error={fieldError(errors, 'match_mode')}>
        <select id="match_mode" className={inputClass} value={form.match_mode} onChange={(event) => set('match_mode', event.target.value)}>
          <option value="any">Bármelyik feltétel (VAGY)</option>
          <option value="all">Minden feltétel (ÉS)</option>
        </select>
      </Field>
      <label className="text-sm flex items-center gap-2">
        <input type="checkbox" checked={!!form.checks_invoice_link} onChange={(event) => set('checks_invoice_link', event.target.checked)} />
        Számlázz.hu letöltési link
      </label>
      <label className="text-sm flex items-center gap-2">
        <input type="checkbox" checked={!!form.is_active} onChange={(event) => set('is_active', event.target.checked)} />
        Aktív
      </label>
      <MultiCheck
        label="Postafiókok"
        options={options.accounts}
        value={form.account_ids}
        onChange={(account_ids) => set('account_ids', account_ids)}
        getLabel={(row) => row.email}
      />
      <MultiCheck
        label="Feladók"
        options={options.senders}
        value={form.sender_ids}
        onChange={(sender_ids) => set('sender_ids', sender_ids)}
        getLabel={(row) => row.name ? `${row.name} (${row.email})` : row.email}
      />
      <MultiCheck
        label="Címzettek"
        options={options.recipients}
        value={form.recipient_ids}
        onChange={(recipient_ids) => set('recipient_ids', recipient_ids)}
        getLabel={(row) => row.name ? `${row.name} (${row.email})` : row.email}
      />
    </CrudForm>
  )
}
