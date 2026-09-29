import { useEffect, useState } from 'react'
import { api } from '../api'
import { CrudForm } from '../components/CrudShell'
import { Field, inputClass } from '../components/ui'

export default function Preview() {
  const [rules, setRules] = useState([])
  const [ruleId, setRuleId] = useState('')
  const [sample, setSample] = useState({ from: '', subject: '', text: '', html: '' })
  const [hits, setHits] = useState(null)
  const [processing, setProcessing] = useState(false)

  useEffect(() => { api('/rules').then((data) => setRules(data.data)) }, [])

  return (
    <>
      <CrudForm
        title="Szabályteszt"
        processing={processing}
        submitLabel="Teszt futtatása"
        onSubmit={async (event) => {
          event.preventDefault()
          setProcessing(true)
          try {
            const data = await api('/rules/preview', { method: 'POST', body: JSON.stringify({ rule_id: Number(ruleId), sample }) })
            setHits(data.data)
          } finally {
            setProcessing(false)
          }
        }}
      >
        <p className="text-sm text-gray-700">A teszt nem küld levelet.</p>
        <Field id="rule_id" label="Szabály">
          <select id="rule_id" className={inputClass} required value={ruleId} onChange={(event) => setRuleId(event.target.value)}>
            <option value="">Válassz</option>
            {rules.map((rule) => <option key={rule.id} value={rule.id}>{rule.name}</option>)}
          </select>
        </Field>
        <Field id="from" label="Feladó">
          <input id="from" className={inputClass} value={sample.from} onChange={(event) => setSample({ ...sample, from: event.target.value })} />
        </Field>
        <Field id="subject" label="Tárgy">
          <input id="subject" className={inputClass} value={sample.subject} onChange={(event) => setSample({ ...sample, subject: event.target.value })} />
        </Field>
        <Field id="text" label="Szöveg">
          <textarea id="text" className={inputClass} rows="3" value={sample.text} onChange={(event) => setSample({ ...sample, text: event.target.value })} />
        </Field>
        <Field id="html" label="HTML">
          <textarea id="html" className={inputClass} rows="4" value={sample.html} onChange={(event) => setSample({ ...sample, html: event.target.value })} />
        </Field>
      </CrudForm>
      {hits && (
        <ul className="mt-4 max-w-2xl text-sm bg-paper rounded-xl p-4">
          {hits.map((hit, index) => (
            <li key={index} className="border-t border-sand py-2 first:border-t-0 first:pt-0">
              Illeszkedik: {hit.matched ? 'igen' : 'nem'} · Feltételek: {(hit.conditions || []).join(', ') || '—'} · Címzettek: {(hit.recipients || []).join(', ') || '—'}
            </li>
          ))}
          {hits.length === 0 && <li>Nincs találat.</li>}
        </ul>
      )}
    </>
  )
}
