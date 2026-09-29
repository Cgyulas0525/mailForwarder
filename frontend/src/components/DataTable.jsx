export default function DataTable({ columns, rows, actions, emptyText = 'Nincs adat.' }) {
  return (
    <div className="overflow-x-auto">
      <table className="w-full text-sm">
        <thead className="bg-brand-800 text-white uppercase text-xs">
          <tr>
            {columns.map((column) => (
              <th key={column.key} className="px-6 py-3 text-left">{column.label}</th>
            ))}
            {actions ? <th className="px-6 py-3" /> : null}
          </tr>
        </thead>
        <tbody className="divide-y divide-sand bg-white">
          {rows.length === 0 ? (
            <tr>
              <td colSpan={columns.length + (actions ? 1 : 0)} className="px-6 py-8 text-center text-gray-400">
                {emptyText}
              </td>
            </tr>
          ) : rows.map((row) => (
            <tr key={row.id} className="hover:bg-sand/60">
              {columns.map((column) => (
                <td key={column.key} className={`px-6 py-3 ${column.className ?? ''}`}>
                  {column.render ? column.render(row) : row[column.key]}
                </td>
              ))}
              {actions ? <td className="px-6 py-3"><div className="flex items-center justify-end gap-1">{actions(row)}</div></td> : null}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
