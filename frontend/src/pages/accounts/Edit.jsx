import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { api } from '../../api'
import { useNotify } from '../../components/Layout'
import AccountForm, { accountFromRow, emptyAccount, saveAccount } from './Form'

export default function AccountsEdit() {
  const { id } = useParams()
  const navigate = useNavigate()
  const notify = useNotify()
  const [form, setForm] = useState(emptyAccount())
  const [errors, setErrors] = useState({})
  const [processing, setProcessing] = useState(false)
  const [missing, setMissing] = useState(false)

  useEffect(() => {
    api(`/accounts/${id}`)
      .then((data) => setForm(accountFromRow(data.data)))
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
        A postafiók nem található.{' '}
        <Link className="underline" to="/fiokok">Vissza a listához</Link>
      </p>
    )
  }

  return (
    <AccountForm
      title="Postafiók szerkesztése"
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
          await saveAccount(form, id)
          navigate('/fiokok', { state: { flash: 'Postafiók mentve.' } })
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
