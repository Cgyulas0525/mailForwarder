import { Link, useLocation } from 'react-router-dom'
import { btnPrimary, btnSecondary } from './ui'

export function CrudIndex({ title, search, onSearch, searchPlaceholder = 'Keresés...', createTo, createLabel, children }) {
  const flash = useLocation().state?.flash

  return (
    <section className="bg-paper rounded-xl shadow">
      <div className="flex items-center justify-between px-6 py-4 border-b border-sand gap-4 flex-wrap">
        <h1 className="text-lg font-semibold text-brand-800 shrink-0">{title}</h1>
        {onSearch ? (
          <div className="relative flex-1 min-w-[160px] max-w-xs">
            <svg className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" />
            </svg>
            <input
              type="search"
              value={search}
              onChange={(event) => onSearch(event.target.value)}
              placeholder={searchPlaceholder}
              className="w-full pl-9 pr-3 py-1.5 border rounded text-sm bg-white focus:outline-none focus:ring-2 focus:ring-brand-600"
            />
          </div>
        ) : null}
        {createTo ? (
          <Link to={createTo} className="px-4 py-2 bg-brand-600 text-white text-sm rounded hover:bg-brand-700 shrink-0">
            {createLabel}
          </Link>
        ) : null}
      </div>
      {flash ? <div className="mx-6 mt-4 px-4 py-2 bg-green-100 text-green-800 rounded text-sm">{flash}</div> : null}
      {children}
    </section>
  )
}

export function CrudForm({ title, onSubmit, processing, cancelTo, submitLabel = 'Mentés', children }) {
  return (
    <section className="max-w-2xl bg-paper rounded-xl shadow">
      <div className="px-6 py-4 border-b border-sand">
        <h1 className="text-lg font-semibold text-brand-800">{title}</h1>
      </div>
      <form onSubmit={onSubmit} className="px-6 py-4 space-y-4">
        {children}
        <div className="flex gap-2 pt-2">
          <button type="submit" disabled={processing} className={btnPrimary}>{submitLabel}</button>
          {cancelTo ? <Link to={cancelTo} className={btnSecondary}>Mégsem</Link> : null}
        </div>
      </form>
    </section>
  )
}
