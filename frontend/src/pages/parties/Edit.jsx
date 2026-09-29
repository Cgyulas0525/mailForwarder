import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { api } from '../../api'
import { useNotify } from '../../components/Layout'
import PartyForm, { emptyParty } from './Form'
import { partyKinds } from './kinds'

export default function PartyEdit({ kind: kindKey }) {
  const kind = partyKinds[kindKey]
  const { id } = useParams()
  const navigate = useNavigate()
  const notify = useNotify()
  const [form, setForm] = useState(emptyParty())
  const [errors, setErrors] = useState({})
  const [processing, setProcessing] = useState(false)
  const [missing, setMissing] = useState(false)

  useEffect(() => {
    api(`${kind.api}/${id}`)
      .then((data) => setForm({ name: data.data.name ?? '', email: data.data.email, is_active: !!data.data.is_active }))
      .catch((error) => {
        if (error.status === 404) {
          setMissing(true)
        } else {
          notify(error.message)
        }
      })
  }, [kind.api, id, notify])

  if (missing) {
    return (
      <p className="text-sm">
        A rekord nem található.{' '}
        <Link className="underline" to={kind.listPath}>Vissza a listához</Link>
      </p>
    )
  }

  return (
    <PartyForm
      kind={kind}
      title={kind.editTitle}
      form={form}
      setForm={setForm}
      errors={errors}
      processing={processing}
      creating={false}
      onSubmit={async (event) => {
        event.preventDefault()
        setProcessing(true)
        setErrors({})
        try {
          await api(`${kind.api}/${id}`, { method: 'PUT', body: JSON.stringify(form) })
          navigate(kind.listPath, { state: { flash: kind.savedFlash } })
        } catch (error) {
          setErrors(error.errors || {})
          notify(error.message)
        } finally {
          setProcessing(false)
        }
      }}
    />
  )
}
