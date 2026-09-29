import { Link } from 'react-router-dom'

export const inputClass = 'mt-1 w-full border rounded px-3 py-2 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-brand-600'
export const btnPrimary = 'bg-brand-600 text-white rounded px-4 py-2 text-sm hover:bg-brand-700 disabled:opacity-50'
export const btnSecondary = 'border border-gray-300 rounded px-4 py-2 text-sm bg-white hover:bg-sand'

export function Field({ id, label, error, children }) {
  return (
    <div>
      <label htmlFor={id} className="block text-sm font-medium text-gray-700">{label}</label>
      {children}
      {error ? <p className="text-red-700 text-xs mt-1" role="alert">{error}</p> : null}
    </div>
  )
}

export function IconButton({ title, onClick, disabled, variant = 'green', children }) {
  const colors = {
    green: 'bg-brand-600 hover:bg-brand-700',
    red: 'bg-red-600 hover:bg-red-700',
    sky: 'bg-sky-500 hover:bg-sky-600',
    gray: 'bg-gray-500 hover:bg-gray-600',
  }
  return (
    <button
      type="button"
      title={title}
      disabled={disabled}
      onClick={onClick}
      className={`inline-flex items-center justify-center w-8 h-8 rounded text-white disabled:opacity-40 ${colors[variant]}`}
    >
      {children}
    </button>
  )
}

export function EditIcon() {
  return (
    <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
    </svg>
  )
}

export function DeleteIcon() {
  return (
    <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
    </svg>
  )
}

export function ToggleIcon() {
  return (
    <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5.636 5.636a9 9 0 1 0 12.728 0M12 3v9" />
    </svg>
  )
}

export function EditLink({ to, title = 'Szerkeszt' }) {
  return (
    <Link to={to} className="inline-flex items-center justify-center w-8 h-8 rounded bg-brand-600 text-white hover:bg-brand-700" title={title}>
      <EditIcon />
    </Link>
  )
}

export function matchesSearch(row, search, fields) {
  const query = search.trim().toLowerCase()
  if (!query) {
    return true
  }
  return fields.some((field) => String(row[field] ?? '').toLowerCase().includes(query))
}

export function MultiCheck({ label, options, value, onChange, getLabel }) {
  const toggle = (id) => {
    onChange(value.includes(id) ? value.filter((item) => item !== id) : [...value, id])
  }

  return (
    <fieldset>
      <legend className="text-sm font-medium text-gray-700 mb-1">{label}</legend>
      <div className="max-h-40 overflow-auto border rounded bg-white p-2 space-y-1">
        {options.length === 0 && <p className="text-sm text-gray-500">Nincs választható elem.</p>}
        {options.map((option) => (
          <label key={option.id} className="flex items-center gap-2 text-sm">
            <input type="checkbox" checked={value.includes(option.id)} onChange={() => toggle(option.id)} />
            {getLabel(option)}
          </label>
        ))}
      </div>
    </fieldset>
  )
}
