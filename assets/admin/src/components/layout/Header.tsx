import { NavLink } from 'react-router-dom'
import { LayoutDashboard, Wrench, Zap, Target, Settings, Bug, Code2, KeyRound } from 'lucide-react'

function HexLogo({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg" className={className} aria-hidden="true">
      <path fill="currentColor" fillRule="evenodd"
        d="M10,2 16,6 16,14 10,18 4,14 4,6Z M10,4.8 13.9,7.4 13.9,12.6 10,15.2 6.1,12.6 6.1,7.4Z" />
      <circle cx="10" cy="10" r="2.2" fill="currentColor" />
    </svg>
  )
}

const navItems = [
  { to: '/',                label: 'Dashboard',      icon: LayoutDashboard, end: true },
  { to: '/workshop',        label: 'Workshop',        icon: Wrench                    },
  { to: '/automations',     label: 'Automations',     icon: Zap                       },
  { to: '/campaigns',       label: 'Campaigns',       icon: Target                    },
  { to: '/development',     label: 'Development',     icon: Code2                     },
  { to: '/configuration',   label: 'Configuration',   icon: Settings                  },
  { to: '/api',             label: 'API',             icon: KeyRound                  },
  { to: '/debug',           label: 'Debug',           icon: Bug                       },
]

export function Header() {
  const version  = window.BeaconData?.pluginVersion ?? ''
  const siteName = window.BeaconData?.siteName      ?? ''
  const siteUrl  = window.BeaconData?.siteUrl       ?? ''

  return (
    <header className="bg-[#390d58] text-white shrink-0">
      {/* Brand row — site/version info wraps below the brand on narrow screens
          rather than competing for horizontal space. */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 px-4 sm:px-6 py-3 sm:py-4">
        <div className="flex items-center gap-3 sm:gap-4">
          <div className="flex items-center justify-center w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-white/15 backdrop-blur shrink-0">
            <HexLogo className="h-6 w-6 sm:h-7 sm:w-7 text-white" />
          </div>
          <div className="leading-tight">
            <p className="text-xl sm:text-2xl font-bold tracking-tight">WP Beacon</p>
            <p className="text-[11px] sm:text-xs text-white/60">by Digital Royalty</p>
          </div>
        </div>

        <div className="flex items-center gap-3 sm:gap-4 flex-wrap">
          {siteName && (
            <a href={siteUrl} target="_blank" rel="noreferrer" className="text-xs truncate max-w-[200px] sm:max-w-none">
              {siteName} ↗
            </a>
          )}
          {version && (
            <div className="flex items-center gap-1.5 text-sm">
              <span className="text-white/50 text-xs">Version</span>
              <span className="font-mono bg-white/10 px-2 py-0.5 rounded text-xs">{version}</span>
            </div>
          )}
        </div>
      </div>

      {/* Nav tabs — colours handled by CSS in index.css (WP admin override).
          Horizontal scroll on mobile so all 8 items remain reachable without
          forcing a hamburger menu. Tighter padding under `sm:` keeps more
          tabs visible without scrolling. */}
      <nav
        className="flex gap-0.5 px-4 sm:px-6 overflow-x-auto whitespace-nowrap"
        style={{ scrollbarWidth: 'none' }}
      >
        {navItems.map(({ to, label, icon: Icon, end }) => (
          <NavLink
            key={to}
            to={to}
            end={end}
            className={({ isActive }) =>
              `flex items-center gap-1.5 sm:gap-2 px-3 sm:px-5 py-2.5 sm:py-3 rounded-t-lg text-xs sm:text-sm font-medium transition-all shrink-0 ${
                isActive ? 'bg-white' : 'hover:bg-white/10'
              }`
            }
          >
            <Icon className="h-4 w-4 shrink-0" />
            {label}
          </NavLink>
        ))}
      </nav>
    </header>
  )
}
