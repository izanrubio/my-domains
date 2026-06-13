import { Link } from 'react-router-dom'

export default function EmptyState({ icon = '🌐', title, description, action }) {
  return (
    <div className="flex flex-col items-center justify-center py-20 text-center">
      <div className="text-5xl mb-4">{icon}</div>
      <h3 className="text-lg font-semibold text-gray-800 mb-2">{title}</h3>
      <p className="text-gray-500 text-sm max-w-sm mb-6">{description}</p>
      {action && (
        <Link
          to={action.to}
          className="inline-flex items-center gap-2 bg-blue-600 text-white text-sm font-medium px-4 py-2 rounded hover:bg-blue-700 transition-colors"
        >
          {action.label}
        </Link>
      )}
    </div>
  )
}
