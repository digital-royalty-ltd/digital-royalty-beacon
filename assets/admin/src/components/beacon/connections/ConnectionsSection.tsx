import { useEffect, useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Separator } from '@/components/ui/separator'
import { Link2, CheckCircle2, Circle, Loader2, ExternalLink } from 'lucide-react'
import { api } from '@/lib/api'

// ─── Provider metadata ────────────────────────────────────────────────────────

interface ProviderMeta {
  label:   string
  initial: string
  color:   string
}

const PROVIDER_META: Record<string, ProviderMeta> = {
  'google-search-console': { label: 'Google Search Console', initial: 'G', color: 'bg-[#4285F4]' },
  'google-analytics':      { label: 'Google Analytics',      initial: 'G', color: 'bg-[#E37400]' },
  'google-ads':            { label: 'Google Ads',            initial: 'G', color: 'bg-[#34A853]' },
  'bing-ads':              { label: 'Bing Ads',              initial: 'B', color: 'bg-[#008373]' },
  'facebook':              { label: 'Facebook',              initial: 'f', color: 'bg-[#1877F2]' },
  'twitter':               { label: 'X (Twitter)',           initial: 'X', color: 'bg-black'     },
  'linkedin':              { label: 'LinkedIn',              initial: 'in', color: 'bg-[#0A66C2]' },
}

// ─── Types ────────────────────────────────────────────────────────────────────

interface ProviderStatus {
  key:          string
  connected:    boolean
  connected_at: string | null
}

// ─── Component ────────────────────────────────────────────────────────────────

export function ConnectionsSection() {
  const hasApiKey    = window.BeaconData?.hasApiKey ?? false
  const dashboardUrl = window.BeaconData?.dashboardUrl ?? null
  const projectId    = window.BeaconData?.projectId ?? null

  const [providers, setProviders] = useState<ProviderStatus[]>([])
  const [loading,   setLoading]   = useState(true)

  useEffect(() => {
    if (!hasApiKey) { setLoading(false); return }
    api.get<{ providers: ProviderStatus[] }>('/connections')
      .then(res => setProviders(res.providers))
      .catch(() => {})
      .finally(() => setLoading(false))
  }, [hasApiKey])

  const connectionsUrl = dashboardUrl && projectId
    ? `${dashboardUrl}/dashboard/projects/${projectId}/connections`
    : dashboardUrl
      ? `${dashboardUrl}/dashboard/projects/overview`
      : null

  return (
    <Card className="border-[#390d58]/20 overflow-hidden">
      <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
      <CardHeader>
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-4">
            <div className="rounded-xl bg-[#390d58] p-3 text-white shadow-md shadow-[#390d58]/20">
              <Link2 className="h-5 w-5" />
            </div>
            <div>
              <CardTitle className="text-lg text-[#390d58]">Connected Platforms</CardTitle>
              <CardDescription>Manage connections in your Digital Royalty dashboard</CardDescription>
            </div>
          </div>
          {connectionsUrl && (
            <Button
              variant="outline"
              size="sm"
              className="gap-1.5 border-[#390d58]/20 text-[#390d58]"
              onClick={() => window.open(connectionsUrl, '_blank', 'noopener,noreferrer')}
            >
              <ExternalLink className="h-3.5 w-3.5" />
              Manage
            </Button>
          )}
        </div>
      </CardHeader>

      <CardContent>
        {!hasApiKey ? (
          <div className="rounded-xl border border-[#390d58]/10 bg-[#390d58]/[0.02] p-6 text-center">
            <p className="text-sm text-muted-foreground">
              Enter your Beacon API key above to see connection status.
            </p>
          </div>
        ) : loading ? (
          <div className="flex items-center justify-center py-10 text-muted-foreground gap-2">
            <Loader2 className="h-4 w-4 animate-spin" />
            <span className="text-sm">Loading connections...</span>
          </div>
        ) : (
          <div className="rounded-xl border border-[#390d58]/10 overflow-hidden">
            {providers.map((p, index) => {
              const meta = PROVIDER_META[p.key]

              return (
                <div key={p.key}>
                  <div className="flex items-center justify-between gap-4 p-4 bg-[#390d58]/[0.02]">
                    <div className="flex items-center gap-3">
                      <div className={`w-9 h-9 rounded-lg ${meta?.color ?? 'bg-slate-400'} flex items-center justify-center shrink-0`}>
                        <span className="text-white text-xs font-bold">{meta?.initial ?? '?'}</span>
                      </div>
                      <div>
                        <p className="text-sm font-medium">{meta?.label ?? p.key}</p>
                        {p.connected && p.connected_at && (
                          <p className="text-xs text-muted-foreground">
                            Connected {new Date(p.connected_at).toLocaleDateString()}
                          </p>
                        )}
                      </div>
                    </div>

                    <Badge
                      variant="outline"
                      className={p.connected
                        ? 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20 gap-1.5'
                        : 'bg-muted text-muted-foreground border-border gap-1.5'}
                    >
                      {p.connected
                        ? <CheckCircle2 className="h-3.5 w-3.5" />
                        : <Circle className="h-3.5 w-3.5" />}
                      {p.connected ? 'Connected' : 'Not connected'}
                    </Badge>
                  </div>
                  {index < providers.length - 1 && <Separator className="bg-[#390d58]/10" />}
                </div>
              )
            })}
          </div>
        )}
      </CardContent>
    </Card>
  )
}
