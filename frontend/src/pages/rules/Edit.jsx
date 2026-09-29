import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { api } from '../../api'
import { useNotify } from '../../components/Layout'
import RuleForm, { emptyRule, ruleFromRow, rulePayload } from './Form'

export default function RulesEdit() {
  const { id } = useParams()
  const navigate = useNavigate()
  const notify = useNotify()
  const [form, setForm] = useState(emptyRule())
  const [errors, setErrors] = useState({})
  const [processing, setProcessing] = useState(false)
  const [missing, setMissing] = useState(false)

  useEffect(() => {
    api(`/rules/${id}`)
      .then((data) => setForm(ruleFromRow(data.data)))
      .catch((error) => {
        if (error.status === 404) {
          setMissing(true)
        } else {
          notify(error.message)
        }
      })
  }, [id, notify])

  if (missing) {
    return (
      <p className="text-sm">
        A szabály nem található.{' '}
        <Link className="underline" to="/szabalyok">Vissza a listához</Link>
      </p>
    )
  }

  return (
    <RuleForm
      title="Szabály szerkesztése"
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
          await api(`/rules/${id}`, { method: 'PUT', body: JSON.stringify(rulePayload(form)) })
          navigate('/szabalyok', { state: { flash: 'Szabály mentve.' } })
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
