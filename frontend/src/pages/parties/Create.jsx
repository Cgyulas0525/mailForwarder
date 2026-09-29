import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { api } from '../../api'
import { useNotify } from '../../components/Layout'
import PartyForm, { emptyParty } from './Form'
import { partyKinds } from './kinds'

export default function PartyCreate({ kind: kindKey }) {
  const kind = partyKinds[kindKey]
  const navigate = useNavigate()
  const notify = useNotify()
  const [form, setForm] = useState(emptyParty())
  const [errors, setErrors] = useState({})
  const [processing, setProcessing] = useState(false)

  return (
    <PartyForm
      kind={kind}
      title={kind.createTitle}
      form={form}
      setForm={setForm}
      errors={errors}
      processing={processing}
      creating
      onSubmit={async (event) => {
        event.preventDefault()
        setProcessing(true)
        setErrors({})
        try {
          await api(kind.api, { method: 'POST', body: JSON.stringify(form) })
          navigate(kind.listPath, { state: { flash: kind.createdFlash } })
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
