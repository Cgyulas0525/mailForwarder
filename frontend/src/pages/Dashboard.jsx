import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../api'

function Kpi({ to, label, value, hint, color }) {
  return (
    <Link to={to} className={`${color} rounded-xl px-6 py-5 flex flex-col justify-between min-h-[120px] hover:brightness-110 transition`}>
      <div className="text-xs font-medium text-white/80 uppercase tracking-wide">{label}</div>
      <div className="text-4xl font-bold tabular-nums text-white text-right">{value}</div>
      {hint ? <div className="text-xs text-white/70 mt-1 text-right">{hint}</div> : null}
    </Link>
  )
}

function DarkPanel({ title, actionTo, actionLabel, children }) {
  return (
    <section className="bg-gray-800 rounded-xl overflow-hidden h-full">
      <div className="bg-brand-700 px-5 py-4 flex items-center justify-between">
        <h2 className="text-sm font-semibold text-white">{title}</h2>
        <Link to={actionTo} className="text-sm text-brand-100 hover:text-white">{actionLabel}</Link>
      </div>
      <div className="px-5 py-4 text-sm text-gray-200">{children}</div>
    </section>
  )
}

const statusLabel = {
  queued: 'sorban',
  sent: 'elküldve',
  failed: 'sikertelen',
  skipped: 'kihagyva',
}

export default function Dashboard() {
  const [data, setData] = useState(null)

  useEffect(() => {
    Promise.all([
      api('/accounts'),
      api('/senders'),
      api('/recipients'),
      api('/rules'),
      api('/deliveries'),
      api('/settings/window'),
    ]).then(([accounts, senders, recipients, rules, deliveries, windowSettings]) => {
      setData({
        accounts: accounts.data || [],
        senders: senders.data || [],
        recipients: recipients.data || [],
        rules: rules.data || [],
        deliveries: deliveries.data || [],
        window: windowSettings,
      })
    }).catch(() => setData({ accounts: [], senders: [], recipients: [], rules: [], deliveries: [], window: null }))
  }, [])

  const accounts = data?.accounts ?? []
  const rules = data?.rules ?? []
  const enabledAccounts = accounts.filter((row) => row.is_enabled).length
  const errorAccounts = accounts.filter((row) => row.status === 'error').length
  const activeRules = rules.filter((row) => row.is_active).length

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-lg font-semibold text-white">Indító pult</h1>
        <p className="text-sm text-gray-400">Áttekintés a postafiókokról, szabályokról és a legutóbbi kézbesítésekről.</p>
      </div>

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <Kpi
          to="/fiokok"
          label="Postafiókok"
          value={data ? accounts.length : '…'}
          hint={data ? `${enabledAccounts} engedélyezve${errorAccounts ? ` · ${errorAccounts} hiba` : ''}` : 'Betöltés…'}
          color="bg-brand-800"
        />
        <Kpi
          to="/szabalyok"
          label="Szabályok"
          value={data ? rules.length : '…'}
          hint={data ? `${activeRules} aktív` : 'Betöltés…'}
          color="bg-sky-900"
        />
        <Kpi
          to="/feladok"
          label="Feladók"
          value={data ? data.senders.length : '…'}
          hint="Figyelt címek"
          color="bg-violet-900"
        />
        <Kpi
          to="/cimzettek"
          label="Címzettek"
          value={data ? data.recipients.length : '…'}
          hint="Továbbítás céljai"
          color="bg-amber-900"
        />
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <DarkPanel title="Ellenőrzési időablak" actionTo="/idoablak" actionLabel="Beállítás">
          {data === null ? 'Betöltés…' : (
            data.window ? (
              <>
                <p>Most: <strong className="text-white">{data.window.open_now ? 'nyitva' : 'zárva'}</strong> ({data.window.timezone})</p>
                <p className="mt-1 text-gray-400">
                  {data.window.check_window_start || data.window.check_window_end
                    ? `${data.window.check_window_start || '—'} – ${data.window.check_window_end || '—'}`
                    : 'Teljes nap'}
                </p>
              </>
            ) : 'Nem sikerült betölteni.'
          )}
        </DarkPanel>

        <DarkPanel title="Legutóbbi kézbesítések" actionTo="/naplo" actionLabel="Napló">
          <ul className="divide-y divide-white/10 -mx-5 -my-4">
            {(data?.deliveries ?? []).slice(0, 6).map((row) => (
              <li key={row.id} className="px-5 py-3">
                <div className="font-medium text-white truncate">{row.subject || '—'}</div>
                <div className="text-gray-400">{statusLabel[row.status] || row.status} · {row.to_email}</div>
              </li>
            ))}
            {data && data.deliveries.length === 0 && <li className="px-5 py-6 text-gray-500">Még nincs kézbesítés.</li>}
            {!data && <li className="px-5 py-6 text-gray-500">Betöltés…</li>}
          </ul>
        </DarkPanel>
      </div>
    </div>
  )
}
