import { useState } from 'react'
import { Button } from '@/components/ui/button'
import {
  LayoutDashboard, Wrench, Zap, Target, Settings, Bug, KeyRound,
} from 'lucide-react'
import { api } from '@/lib/api'

// ─── Per-screen config ────────────────────────────────────────────────────────

interface ScreenConfig {
  title:       string
  description: string
  icon:        React.ReactNode
  gradient:    string
}

const SCREENS: Record<string, ScreenConfig> = {
  dashboard: {
    title:       'Your site\'s intelligence, in one place',
    description: 'Beacon analyses your site, builds a deep understanding of your content and goals, and surfaces exactly what needs attention. This is where your competitive edge begins.',
    icon:        <LayoutDashboard className="h-14 w-14 text-white/80" />,
    gradient:    'from-[#390d58] to-[#5a1a8a]',
  },
  workshop: {
    title:       'A faster, stronger website — without the agency bill',
    description: 'Security hardening, performance tweaks, custom code, redirects, SMTP — everything that usually needs a developer is right here. Ship improvements in seconds, not sprints.',
    icon:        <Wrench className="h-14 w-14 text-white/80" />,
    gradient:    'from-indigo-700 to-[#390d58]',
  },
  automations: {
    title:       'Agency intelligence, automated',
    description: 'Beacon runs the content audits, competitor analysis, and draft generation that Digital Royalty performs for agency clients — the same proven workflows, running automatically on your site.',
    icon:        <Zap className="h-14 w-14 text-white/80" />,
    gradient:    'from-[#390d58] to-violet-600',
  },
  campaigns: {
    title:       'Digital Royalty\'s playbooks, executed by AI',
    description: 'Every campaign strategy in Beacon is rooted in what we\'ve proven works over years as a full-service agency. You choose the approach — AI handles the execution, guided by our methodology every step of the way.',
    icon:        <Target className="h-14 w-14 text-white/80" />,
    gradient:    'from-[#2d0a47] to-[#390d58]',
  },
  configuration: {
    title:       'One key. Unlimited potential.',
    description: 'Connect your site to Beacon and unlock the full power of Digital Royalty\'s agency methodology — content strategy, gap analysis, and campaign execution. The more we know about your site, the sharper our output.',
    icon:        <Settings className="h-14 w-14 text-white/80" />,
    gradient:    'from-[#390d58] to-purple-700',
  },
  debug: {
    title:       'Full transparency, zero surprises',
    description: 'Every job, every log entry, every queued action — visible and in your control. Beacon works hard in the background; this tab makes sure it\'s doing exactly what you expect.',
    icon:        <Bug className="h-14 w-14 text-white/80" />,
    gradient:    'from-slate-700 to-[#390d58]',
  },
  api: {
    title:       'Your site, available as an API.',
    description: 'Beacon gives you a ready-to-use REST API for your WordPress content — no custom development required. Create and distribute API keys to developers, apps, or external services, and control exactly which endpoints they can access. Each key carries its own rate limits so you stay in full control of usage.',
    icon:        <KeyRound className="h-14 w-14 text-white/80" />,
    gradient:    'from-[#390d58] to-indigo-700',
  },
}

// ─── Module-level session dismissal (lives for the SPA lifetime) ──────────────

const sessionDismissed = new Set<string>()

// ─── Component ────────────────────────────────────────────────────────────────

interface Props {
  screen: string
}

export function OnboardingOverlay({ screen }: Props) {
  const permanentlyDismissed = (
    window.BeaconData?.dismissedOnboardingScreens ?? []
  ).includes(screen)

  const [dismissed, setDismissed] = useState(
    permanentlyDismissed || sessionDismissed.has(screen)
  )

  if (dismissed) return null

  const config = SCREENS[screen]
  if (!config) return null

  const handleOk = () => {
    sessionDismissed.add(screen)
    setDismissed(true)
  }

  const handleDismissForever = () => {
    sessionDismissed.add(screen)
    setDismissed(true)

    api.post('/onboarding/dismiss', { screen }).then(() => {
      if (window.BeaconData) {
        window.BeaconData.dismissedOnboardingScreens = [
          ...(window.BeaconData.dismissedOnboardingScreens ?? []),
          screen,
        ]
      }
    }).catch(() => {
      // Silent — dismiss happened in the UI; the next page load will re-show if the API failed
    })
  }

  return (
    /* Backdrop */
    <div
      className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
      onClick={handleOk}
    >
      {/* Card — stop propagation so clicking the card doesn't close */}
      <div
        className="bg-white rounded-2xl w-full max-w-sm overflow-hidden shadow-2xl shadow-black/30 animate-in fade-in zoom-in-95 duration-200"
        onClick={e => e.stopPropagation()}
      >
        {/* Illustration area */}
        <div
          className={`h-44 bg-gradient-to-br ${config.gradient} flex items-center justify-center relative overflow-hidden`}
        >
          {/* Background circles for depth */}
          <div className="absolute -top-8 -right-8 w-40 h-40 rounded-full bg-white/5" />
          <div className="absolute -bottom-10 -left-6 w-32 h-32 rounded-full bg-white/5" />
          <div className="absolute top-4 left-8 w-16 h-16 rounded-full bg-white/5" />
          {config.icon}
        </div>

        {/* Content */}
        <div className="px-6 pt-5 pb-6">
          <h2 className="text-lg font-bold text-[#390d58] mb-2 leading-snug">
            {config.title}
          </h2>
          <p className="text-sm text-muted-foreground leading-relaxed mb-6">
            {config.description}
          </p>

          {/* Actions */}
          <div className="flex items-center justify-between gap-3">
            <button
              onClick={handleDismissForever}
              className="text-xs text-muted-foreground hover:text-[#390d58] transition-colors underline underline-offset-2"
            >
              Don't show this again
            </button>
            <Button
              onClick={handleOk}
              className="bg-[#390d58] hover:bg-[#4a1170] text-white px-6"
            >
              OK, got it
            </Button>
          </div>
        </div>
      </div>
    </div>
  )
}
