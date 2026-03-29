import { useEffect, useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { GitBranch, Package, Loader2, CheckCircle2 } from 'lucide-react'
import { api } from '@/lib/api'

type Channel = 'stable' | 'experimental'

interface ChannelOption {
  value:       Channel
  label:       string
  badge:       string
  badgeClass:  string
  description: string
  icon:        React.ReactNode
}

const OPTIONS: ChannelOption[] = [
  {
    value:      'stable',
    label:      'Stable',
    badge:      'Recommended',
    badgeClass: 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20',
    description: 'Receives official releases published to the WordPress plugin directory. Thoroughly tested and safe for all sites.',
    icon: <Package className="h-5 w-5" />,
  },
  {
    value:      'experimental',
    label:      'Experimental',
    badge:      'Early access',
    badgeClass: 'bg-amber-500/10 text-amber-700 border-amber-500/20',
    description: 'Receives releases directly from the GitHub repository as soon as they are published. Ideal for testing new features early — bugs are expected. Please open issues on GitHub.',
    icon: <GitBranch className="h-5 w-5" />,
  },
]

export function UpdateChannelCard() {
  const [channel,  setChannel]  = useState<Channel>('stable')
  const [saving,   setSaving]   = useState(false)
  const [saved,    setSaved]    = useState(false)
  const [loading,  setLoading]  = useState(true)

  useEffect(() => {
    api.get<{ channel: Channel }>('/update-channel')
      .then(res => setChannel(res.channel))
      .catch(() => {/* leave at default */})
      .finally(() => setLoading(false))
  }, [])

  const handleSelect = async (value: Channel) => {
    if (value === channel || saving) return
    setChannel(value)
    setSaving(true)
    setSaved(false)
    try {
      await api.post('/update-channel', { channel: value })
      setSaved(true)
      setTimeout(() => setSaved(false), 2500)
    } catch {
      // If it fails, revert
      setChannel(channel)
    } finally {
      setSaving(false)
    }
  }

  return (
    <Card className="border-[#390d58]/20 overflow-hidden">
      <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
      <CardHeader>
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-4">
            <div className="rounded-xl bg-[#390d58] p-3 text-white shadow-md shadow-[#390d58]/20">
              <GitBranch className="h-5 w-5" />
            </div>
            <div>
              <CardTitle className="text-lg text-[#390d58]">Update Channel</CardTitle>
              <CardDescription>Choose how you receive Beacon updates</CardDescription>
            </div>
          </div>
          {saving && <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" />}
          {saved  && <span className="flex items-center gap-1.5 text-xs text-emerald-600"><CheckCircle2 className="h-3.5 w-3.5" /> Saved</span>}
        </div>
      </CardHeader>

      <CardContent>
        {loading ? (
          <div className="flex items-center gap-2 text-sm text-muted-foreground py-4">
            <Loader2 className="h-4 w-4 animate-spin" /> Loading…
          </div>
        ) : (
          <div className="grid gap-3 sm:grid-cols-2">
            {OPTIONS.map(opt => {
              const isActive = channel === opt.value
              return (
                <button
                  key={opt.value}
                  onClick={() => handleSelect(opt.value)}
                  disabled={saving}
                  className={`relative text-left rounded-xl border-2 p-4 transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#390d58] ${
                    isActive
                      ? 'border-[#390d58] bg-[#390d58]/5'
                      : 'border-border bg-white hover:border-[#390d58]/40 hover:bg-[#390d58]/[0.02]'
                  }`}
                >
                  {/* Active indicator */}
                  {isActive && (
                    <span className="absolute top-3 right-3 flex h-4 w-4 items-center justify-center rounded-full bg-[#390d58]">
                      <CheckCircle2 className="h-3 w-3 text-white" />
                    </span>
                  )}

                  <div className="flex items-center gap-2 mb-2">
                    <span className={isActive ? 'text-[#390d58]' : 'text-muted-foreground'}>
                      {opt.icon}
                    </span>
                    <span className={`font-semibold text-sm ${isActive ? 'text-[#390d58]' : 'text-foreground'}`}>
                      {opt.label}
                    </span>
                    <Badge variant="outline" className={`text-[10px] ml-auto ${opt.badgeClass}`}>
                      {opt.badge}
                    </Badge>
                  </div>

                  <p className="text-xs text-muted-foreground leading-relaxed">
                    {opt.description}
                  </p>
                </button>
              )
            })}
          </div>
        )}
      </CardContent>
    </Card>
  )
}
