import { NavLink, Outlet, useNavigate } from 'react-router-dom'
import { useAuth } from '../contexts/AuthContext'

const navLink = ({ isActive }) =>
  isActive
    ? 'text-blue-600 font-semibold'
    : 'text-gray-600 hover:text-gray-900 transition-colors'

export default function Layout() {
  const { logout } = useAuth()
  const navigate = useNavigate()

  const handleLogout = async () => {
    await logout()
    navigate('/login')
  }

  return (
    <div className="min-h-screen bg-gray-50 flex flex-col">
      <nav className="bg-white border-b border-gray-200 px-6 py-3 flex items-center gap-6 shadow-sm">
        <span className="font-bold text-gray-900 text-lg tracking-tight">My Domains</span>
        <NavLink to="/dashboard" className={navLink}>Dashboard</NavLink>
        <NavLink to="/domains" className={navLink}>Domains</NavLink>
        <NavLink to="/settings" className={navLink}>Settings</NavLink>
        <button
          onClick={handleLogout}
          className="ml-auto text-sm text-gray-500 hover:text-gray-900 transition-colors"
        >
          Logout
        </button>
      </nav>
      <main className="flex-1 max-w-6xl mx-auto w-full px-6 py-8">
        <Outlet />
      </main>
    </div>
  )
}
