import { useEffect, useState } from 'react'
import { api } from '../api'
import { CrudIndex } from '../components/CrudShell'
import DataTable from '../components/DataTable'
import { btnPrimary, inputClass } from '../components/ui'

export default function Log() {
  const [page, setPage] = useState(null)
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const [detail, setDetail] = useState(null)

  const load = (url = '/deliveries') => api(url).then(setPage)
  useEffect(() => { load().catch(() => {}) }, [])

  const columns = [
    { key: 'subject', label: 'Tárgy' },
    { key: 'from_email', label: 'Feladó' },
    { key: 'to_email', label: 'Címzett' },
    {
      key: 'status',
      label: 'Állapot',
      render: (row) => (
        <div>
          {row.status}
          {row.last_error ? <div className="text-red-800">{row.last_error}</div> : null}
        </div>
      ),
    },
    {
      key: 'matched_rules',
      label: 'Szabály',
      render: (row) => (row.matched_rules || []).map((rule) => `${rule.rule_name} (${(rule.conditions || []).join(', ')})`).join('; ') || '—',
    },
  ]

  return (
    <CrudIndex title="Feldolgozási napló">
      <form
        className="flex flex-wrap gap-2 px-6 py-4 border-b border-sand"
        onSubmit={(event) => {
          event.preventDefault()
          load(`/deliveries?search=${encodeURIComponent(search)}&status=${status}`)
        }}
      >
        <input className={`${inputClass} mt-0 max-w-xs`} placeholder="Keresés" value={search} onChange={(event) => setSearch(event.target.value)} />
        <select className={`${inputClass} mt-0 w-auto`} value={status} onChange={(event) => setStatus(event.target.value)}>
          <option value="">Minden állapot</option>
          {['pending', 'sending', 'sent', 'failed', 'delivery_unknown'].map((value) => <option key={value}>{value}</option>)}
        </select>
        <button className={btnPrimary} type="submit">Szűrés</button>
      </form>
      <DataTable
        columns={columns}
        rows={page?.data ?? []}
        emptyText="Nincs kézbesítés."
        actions={(row) => (
          <>
            <button type="button" className="underline text-sm mr-2" onClick={() => api(`/deliveries/${row.id}`).then(setDetail)}>Részletek</button>
            {(row.status === 'failed' || row.status === 'delivery_unknown') && (
              <button type="button" className="underline text-sm" onClick={() => api(`/deliveries/${row.id}/retry`, { method: 'POST' }).then(() => load())}>Újraküldés</button>
            )}
          </>
        )}
      />
      {page && page.last_page > 1 && (
        <div className="flex gap-2 px-6 py-4 border-t border-sand text-sm">
          <button type="button" className="underline disabled:opacity-40" disabled={page.current_page <= 1} onClick={() => load(`/deliveries?page=${page.current_page - 1}`)}>Előző</button>
          <span>{page.current_page} / {page.last_page}</span>
          <button type="button" className="underline disabled:opacity-40" disabled={page.current_page >= page.last_page} onClick={() => load(`/deliveries?page=${page.current_page + 1}`)}>Következő</button>
        </div>
      )}
      {detail && (
        <article className="m-6 bg-white rounded p-4">
          <h2 className="font-semibold mb-2">{detail.subject}</h2>
          <p className="text-sm mb-2">Feladó: {detail.from_raw}</p>
          <pre className="whitespace-pre-wrap text-sm bg-sand p-3 rounded">{detail.text_body}</pre>
          {detail.html_safe && <div className="mt-3 text-sm" dangerouslySetInnerHTML={{ __html: detail.html_safe }} />}
        </article>
      )}
    </CrudIndex>
  )
}
