import { NavLink } from 'react-router-dom'
import { LayoutDashboard, Wrench, Zap, Target, Settings, Bug, Code2, KeyRound, Sparkles } from 'lucide-react'

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
  { to: '/insights',        label: 'Insights',        icon: Sparkles                  },
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
      {/* Brand row */}
      <div className="flex items-center justify-between px-6 py-4">
        <div className="flex items-center gap-4">
          <div className="flex items-center justify-center w-11 h-11 rounded-xl bg-white/15 backdrop-blur shrink-0">
            <HexLogo className="h-7 w-7 text-white" />
          </div>
          <div className="leading-tight">
            <p className="text-2xl font-bold tracking-tight">WP Beacon</p>
            <p className="text-xs text-white/60">by Digital Royalty</p>
          </div>
        </div>

        <div className="flex items-center gap-4">
          {siteName && (
            <a href={siteUrl} target="_blank" rel="noreferrer" className="text-xs">
              {siteName} ↗
            </a>
          )}
          {version && (
            <div className="flex items-center gap-1.5 text-sm">
              <span className="text-white/50">Version</span>
              <span className="font-mono bg-white/10 px-2 py-0.5 rounded text-xs">{version}</span>
            </div>
          )}
        </div>
      </div>

      {/* Nav tabs — colours handled by CSS in index.css (WP admin override) */}
      <nav className="px-6 flex gap-0.5">
        {navItems.map(({ to, label, icon: Icon, end }) => (
          <NavLink
            key={to}
            to={to}
            end={end}
            className={({ isActive }) =>
              `flex items-center gap-2 px-5 py-3 rounded-t-lg text-sm font-medium transition-all ${
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
