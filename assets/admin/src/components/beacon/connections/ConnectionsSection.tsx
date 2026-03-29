import { useEffect, useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Separator } from '@/components/ui/separator'
import { Link2, CheckCircle2, Circle, Loader2, Unlink } from 'lucide-react'
import { api, ApiError } from '@/lib/api'

// ─── Provider metadata ────────────────────────────────────────────────────────

interface ProviderMeta {
  label:   string
  initial: string
  color:   string   // bg colour for the avatar
}

const PROVIDER_META: Record<string, ProviderMeta> = {
  'google-search-console': { label: 'Google Search Console', initial: 'G', color: 'bg-[#4285F4]' },
  'google-analytics':      { label: 'Google Analytics',      initial: 'G', color: 'bg-[#E37400]' },
  'google-ads':            { label: 'Google Ads',            initial: 'G', color: 'bg-[#34A853]' },
  'bing-ads':              { label: 'Bing Ads',              initial: 'B', color: 'bg-[#008373]' },
  'facebook':              { label: 'Facebook',              initial: 'f', color: 'bg-[#1877F2]' },
  'twitter':               { label: 'Twitter / X',           initial: 'X', color: 'bg-black'     },
  'linkedin':              { label: 'LinkedIn',              initial: 'in', color: 'bg-[#0A66C2]' },
}

// ─── Types ────────────────────────────────────────────────────────────────────

interface ProviderStatus {
  key:          string
  connected:    boolean
  connected_at: string | null
}

type ActionState = 'idle' | 'connecting' | 'disconnecting'

// ─── Component ────────────────────────────────────────────────────────────────

export function ConnectionsSection() {
  const [providers,  setProviders]  = useState<ProviderStatus[]>([])
  const [loading,    setLoading]    = useState(true)
  const [actionFor,  setActionFor]  = useState<Record<string, ActionState>>({})
  const [errors,     setErrors]     = useState<Record<string, string>>({})

  useEffect(() => {
    api.get<{ providers: ProviderStatus[] }>('/connections')
      .then(res => setProviders(res.providers))
      .catch(() => {/* silently leave empty */})
      .finally(() => setLoading(false))
  }, [])

  const setAction = (key: string, state: ActionState) =>
    setActionFor(prev => ({ ...prev, [key]: state }))

  const clearError = (key: string) =>
    setErrors(prev => { const n = { ...prev }; delete n[key]; return n })

  const handleConnect = async (key: string) => {
    setAction(key, 'connecting')
    clearError(key)
    try {
      const res = await api.post<{ url: string }>(`/connections/${key}/initiate`, {})
      // Full page redirect — OAuth flow takes the user away and back
      window.location.href = res.url
    } catch (e) {
      setErrors(prev => ({ ...prev, [key]: e instanceof ApiError ? e.message : 'Could not start connection.' }))
      setAction(key, 'idle')
    }
  }

  const handleDisconnect = async (key: string) => {
    if (!confirm(`Disconnect ${PROVIDER_META[key]?.label ?? key}?`)) return
    setAction(key, 'disconnecting')
    clearError(key)
    try {
      await api.delete(`/connections/${key}`)
      setProviders(prev =>
        prev.map(p => p.key === key ? { ...p, connected: false, connected_at: null } : p)
      )
    } catch (e) {
      setErrors(prev => ({ ...prev, [key]: e instanceof ApiError ? e.message : 'Disconnect failed.' }))
    } finally {
      setAction(key, 'idle')
    }
  }

  return (
    <Card className="border-[#390d58]/20 overflow-hidden">
      <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
      <CardHeader>
        <div className="flex items-center gap-4">
          <div className="rounded-xl bg-[#390d58] p-3 text-white shadow-md shadow-[#390d58]/20">
            <Link2 className="h-5 w-5" />
          </div>
          <div>
            <CardTitle className="text-lg text-[#390d58]">Connected Platforms</CardTitle>
            <CardDescription>Link third-party platforms so Beacon can enrich your data and reporting</CardDescription>
          </div>
        </div>
      </CardHeader>

      <CardContent>
        {loading ? (
          <div className="flex items-center justify-center py-10 text-muted-foreground gap-2">
            <Loader2 className="h-4 w-4 animate-spin" />
            <span className="text-sm">Loading connections…</span>
          </div>
        ) : (
          <div className="rounded-xl border border-[#390d58]/10 overflow-hidden">
            {providers.map((p, index) => {
              const meta   = PROVIDER_META[p.key]
              const action = actionFor[p.key] ?? 'idle'
              const err    = errors[p.key]

              return (
                <div key={p.key}>
                  <div className="flex items-center justify-between gap-4 p-4 bg-[#390d58]/[0.02] hover:bg-[#390d58]/5 transition-colors">
                    {/* Avatar + label */}
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
                        {err && <p className="text-xs text-red-600 mt-0.5">{err}</p>}
                      </div>
                    </div>

                    {/* Status + action */}
                    <div className="flex items-center gap-3 shrink-0">
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

                      {p.connected ? (
                        <Button
                          variant="outline"
                          size="sm"
                          className="gap-1.5 text-destructive border-destructive/30 hover:bg-destructive/5"
                          onClick={() => handleDisconnect(p.key)}
                          disabled={action !== 'idle'}
                        >
                          {action === 'disconnecting'
                            ? <Loader2 className="h-3.5 w-3.5 animate-spin" />
                            : <Unlink className="h-3.5 w-3.5" />}
                          Disconnect
                        </Button>
                      ) : (
                        <Button
                          size="sm"
                          className="gap-1.5 bg-[#390d58] hover:bg-[#4a1170] text-white"
                          onClick={() => handleConnect(p.key)}
                          disabled={action !== 'idle'}
                        >
                          {action === 'connecting'
                            ? <Loader2 className="h-3.5 w-3.5 animate-spin" />
                            : <Link2 className="h-3.5 w-3.5" />}
                          Connect
                        </Button>
                      )}
                    </div>
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
