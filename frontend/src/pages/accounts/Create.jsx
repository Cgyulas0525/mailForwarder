import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useNotify } from '../../components/Layout'
import AccountForm, { emptyAccount, saveAccount } from './Form'

export default function AccountsCreate() {
  const navigate = useNavigate()
  const notify = useNotify()
  const [form, setForm] = useState(emptyAccount())
  const [errors, setErrors] = useState({})
  const [processing, setProcessing] = useState(false)

  return (
    <AccountForm
      title="Új postafiók"
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
          await saveAccount(form)
          navigate('/fiokok', { state: { flash: 'Postafiók létrehozva.' } })
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
