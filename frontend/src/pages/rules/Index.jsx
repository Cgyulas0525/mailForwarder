import { useEffect, useState } from 'react'
import { api } from '../../api'
import { CrudIndex } from '../../components/CrudShell'
import DataTable from '../../components/DataTable'
import { useNotify } from '../../components/Layout'
import { DeleteIcon, EditLink, IconButton, ToggleIcon, matchesSearch } from '../../components/ui'
import { rulePayload } from './Form'

export default function RulesIndex() {
  const notify = useNotify()
  const [rows, setRows] = useState([])
  const [search, setSearch] = useState('')
  const [busyId, setBusyId] = useState(null)

  const load = () => api('/rules').then((data) => setRows(data.data))
  useEffect(() => { load().catch((error) => notify(error.message)) }, [])

  const visible = rows.filter((row) => matchesSearch(row, search, ['name', 'match_mode']))

  const columns = [
    { key: 'name', label: 'Név' },
    { key: 'match_mode', label: 'Kapcsolat', render: (row) => (row.match_mode === 'all' ? 'ÉS' : 'VAGY') },
    { key: 'checks_invoice_link', label: 'Számlalink', render: (row) => (row.checks_invoice_link ? 'igen' : 'nem') },
    { key: 'is_active', label: 'Állapot', render: (row) => (row.is_active ? 'aktív' : 'inaktív') },
    {
      key: 'accounts',
      label: 'Fiókok',
      className: 'max-w-xs',
      render: (row) => (row.accounts || []).map((item) => item.email).join(', ') || '—',
    },
    {
      key: 'recipients',
      label: 'Címzettek',
      className: 'max-w-xs',
      render: (row) => (row.recipients || []).map((item) => item.email).join(', ') || '—',
    },
  ]

  const actions = (row) => (
    <>
      <IconButton
        title={row.is_active ? 'Inaktívra állít' : 'Aktívra állít'}
        variant="sky"
        disabled={busyId === row.id}
        onClick={async () => {
          setBusyId(row.id)
          try {
            await api(`/rules/${row.id}`, {
              method: 'PUT',
              body: JSON.stringify(rulePayload({
                ...row,
                is_active: !row.is_active,
                account_ids: (row.accounts || []).map((item) => item.id),
                sender_ids: (row.senders || []).map((item) => item.id),
                recipient_ids: (row.recipients || []).map((item) => item.id),
              })),
            })
            await load()
          } catch (error) {
            notify(error.message)
          } finally {
            setBusyId(null)
          }
        }}
      >
        <ToggleIcon />
      </IconButton>
      <EditLink to={`/szabalyok/${row.id}/szerkesztes`} />
      <IconButton
        title="Töröl"
        variant="red"
        disabled={busyId === row.id}
        onClick={async () => {
          if (!window.confirm('Törlés?')) {
            return
          }
          setBusyId(row.id)
          try {
            await api(`/rules/${row.id}`, { method: 'DELETE' })
            await load()
          } catch (error) {
            notify(error.message)
          } finally {
            setBusyId(null)
          }
        }}
      >
        <DeleteIcon />
      </IconButton>
    </>
  )

  return (
    <CrudIndex title="Szabályok" search={search} onSearch={setSearch} createTo="/szabalyok/uj" createLabel="+ Új szabály">
      <DataTable columns={columns} rows={visible} actions={actions} emptyText="Nincs szabály. Üres szabály nem illeszkedik egyetlen levélre sem." />
    </CrudIndex>
  )
}
