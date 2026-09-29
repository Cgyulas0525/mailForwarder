import { useEffect, useState } from 'react'
import { api } from '../api'
import { CrudForm } from '../components/CrudShell'
import { useNotify } from '../components/Layout'
import { Field, inputClass } from '../components/ui'

export default function WindowSettings() {
  const notify = useNotify()
  const [form, setForm] = useState({ check_window_start: '', check_window_end: '' })
  const [meta, setMeta] = useState(null)
  const [processing, setProcessing] = useState(false)

  useEffect(() => {
    api('/settings/window').then((data) => {
      setMeta(data)
      setForm({ check_window_start: data.check_window_start || '', check_window_end: data.check_window_end || '' })
    })
  }, [])

  return (
    <CrudForm
      title="Napi ellenőrzési időablak"
      processing={processing}
      submitLabel="Mentés"
      onSubmit={async (event) => {
        event.preventDefault()
        setProcessing(true)
        try {
          await api('/settings/window', {
            method: 'PUT',
            body: JSON.stringify({
              check_window_start: form.check_window_start ? form.check_window_start.slice(0, 5) : null,
              check_window_end: form.check_window_end ? form.check_window_end.slice(0, 5) : null,
            }),
          })
          notify('Időablak mentve.')
        } catch (error) {
          notify(error.message)
        } finally {
          setProcessing(false)
        }
      }}
    >
      <p className="text-sm text-gray-700">
        Üres mezők mellett a teljes nap számít, Europe/Budapest időzónában, tízpercenként. Most: {meta?.open_now ? 'nyitva' : 'zárva'}.
      </p>
      <Field id="check_window_start" label="Kezdet">
        <input id="check_window_start" className={inputClass} type="time" value={form.check_window_start} onChange={(event) => setForm({ ...form, check_window_start: event.target.value })} />
      </Field>
      <Field id="check_window_end" label="Vége">
        <input id="check_window_end" className={inputClass} type="time" value={form.check_window_end} onChange={(event) => setForm({ ...form, check_window_end: event.target.value })} />
      </Field>
    </CrudForm>
  )
}
