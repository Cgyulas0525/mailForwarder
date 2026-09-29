import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { api } from '../../api'
import { useNotify } from '../../components/Layout'
import RuleForm, { emptyRule, rulePayload } from './Form'

export default function RulesCreate() {
  const navigate = useNavigate()
  const notify = useNotify()
  const [form, setForm] = useState(emptyRule())
  const [errors, setErrors] = useState({})
  const [processing, setProcessing] = useState(false)

  return (
    <RuleForm
      title="Új szabály"
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
          await api('/rules', { method: 'POST', body: JSON.stringify(rulePayload(form)) })
          navigate('/szabalyok', { state: { flash: 'Szabály létrehozva.' } })
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
