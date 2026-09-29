import { useEffect, useState } from 'react'
import { api } from '../../api'
import { CrudIndex } from '../../components/CrudShell'
import DataTable from '../../components/DataTable'
import { useNotify } from '../../components/Layout'
import { DeleteIcon, EditLink, IconButton, ToggleIcon, matchesSearch } from '../../components/ui'
import { partyKinds } from './kinds'

export default function PartyIndex({ kind: kindKey }) {
  const kind = partyKinds[kindKey]
  const notify = useNotify()
  const [rows, setRows] = useState([])
  const [search, setSearch] = useState('')
  const [busyId, setBusyId] = useState(null)

  const load = () => api(kind.api).then((data) => setRows(data.data))
  useEffect(() => { load().catch((error) => notify(error.message)) }, [kind.api])

  const visible = rows.filter((row) => matchesSearch(row, search, ['name', 'email']))

  const columns = [
    { key: 'name', label: 'Név', render: (row) => row.name || '—' },
    { key: 'email', label: 'E-mail' },
    { key: 'is_active', label: 'Állapot', render: (row) => (row.is_active ? 'aktív' : 'inaktív') },
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
            await api(`${kind.api}/${row.id}`, { method: 'PUT', body: JSON.stringify({ ...row, is_active: !row.is_active }) })
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
      <EditLink to={`${kind.listPath}/${row.id}/szerkesztes`} />
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
            await api(`${kind.api}/${row.id}`, { method: 'DELETE' })
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
    <CrudIndex title={kind.title} search={search} onSearch={setSearch} createTo={`${kind.listPath}/uj`} createLabel={kind.createLabel}>
      <DataTable columns={columns} rows={visible} actions={actions} emptyText={kind.emptyText} />
    </CrudIndex>
  )
}
