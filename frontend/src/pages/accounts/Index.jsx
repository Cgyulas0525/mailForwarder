import { useEffect, useState } from 'react'
import { api } from '../../api'
import { CrudIndex } from '../../components/CrudShell'
import DataTable from '../../components/DataTable'
import { useNotify } from '../../components/Layout'
import { EditLink, IconButton, ToggleIcon, matchesSearch } from '../../components/ui'

export default function AccountsIndex() {
  const notify = useNotify()
  const [rows, setRows] = useState([])
  const [search, setSearch] = useState('')
  const [busyId, setBusyId] = useState(null)

  const load = () => api('/accounts').then((data) => setRows(data.data))
  useEffect(() => { load().catch((error) => notify(error.message)) }, [])

  const visible = rows.filter((row) => matchesSearch(row, search, ['email', 'display_name', 'status', 'provider']))

  const run = async (id, work) => {
    setBusyId(id)
    try {
      await work()
      await load()
    } catch (error) {
      notify(error.message)
    } finally {
      setBusyId(null)
    }
  }

  const columns = [
    {
      key: 'email',
      label: 'E-mail',
      render: (row) => (
        <div>
          <div className="font-medium">{row.display_name || row.email}</div>
          <div className="text-gray-600">{row.email}</div>
        </div>
      ),
    },
    {
      key: 'provider',
      label: 'Szolgáltató',
      render: (row) => (
        <span className="inline-block rounded bg-sand px-2 py-0.5 text-xs">{row.provider === 'custom' ? 'Egyéni IMAP' : 'Gmail'}</span>
      ),
    },
    {
      key: 'status',
      label: 'Állapot',
      render: (row) => `${row.status}${row.has_password ? '' : ' · jelszó hiányzik'}`,
    },
    { key: 'is_enabled', label: 'Engedélyezve', render: (row) => (row.is_enabled ? 'igen' : 'nem') },
    { key: 'last_error', label: 'Utolsó hiba', className: 'max-w-xs truncate', render: (row) => row.last_error || '—' },
  ]

  const actions = (row) => (
    <>
      <IconButton
        title={row.is_enabled ? 'Letilt' : 'Engedélyez'}
        variant="sky"
        disabled={busyId === row.id}
        onClick={() => run(row.id, () => api(`/accounts/${row.id}/enabled`, { method: 'PATCH', body: JSON.stringify({ is_enabled: !row.is_enabled }) }))}
      >
        <ToggleIcon />
      </IconButton>
      <button
        type="button"
        className="px-2 py-1 text-xs rounded border bg-white hover:bg-sand disabled:opacity-40"
        disabled={busyId === row.id}
        onClick={() => run(row.id, async () => {
          const data = await api(`/accounts/${row.id}/test`, { method: 'POST', body: JSON.stringify({}) })
          notify(data.message)
        })}
      >
        Teszt
      </button>
      <button
        type="button"
        className="px-2 py-1 text-xs rounded border bg-white hover:bg-sand disabled:opacity-40"
        disabled={busyId === row.id}
        onClick={() => run(row.id, async () => {
          const data = await api(`/accounts/${row.id}/sync`, { method: 'POST' })
          notify(`${data.message} Új: ${data.stored}`)
        })}
      >
        Szinkron
      </button>
      <EditLink to={`/fiokok/${row.id}/szerkesztes`} />
    </>
  )

  return (
    <CrudIndex title="Postafiókok" search={search} onSearch={setSearch} createTo="/fiokok/uj" createLabel="+ Új postafiók">
      <DataTable columns={columns} rows={visible} actions={actions} emptyText="Még nincs postafiók." />
    </CrudIndex>
  )
}
