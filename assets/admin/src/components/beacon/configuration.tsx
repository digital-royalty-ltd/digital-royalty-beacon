import { useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { Separator } from '@/components/ui/separator'
import {
  CheckCircle2, XCircle, Circle, Key, Globe,
  Building2, Shield, Loader2,
} from 'lucide-react'
import { api, ApiError } from '@/lib/api'

type ConnectionStatus = 'verified' | 'failed' | 'unchecked' | 'saving'

const statusConfig: Record<ConnectionStatus, { label: string; icon: React.ReactNode; className: string }> = {
  verified: {
    label:     'Connected',
    icon:      <CheckCircle2 className="h-4 w-4" />,
    className: 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20',
  },
  failed: {
    label:     'Failed',
    icon:      <XCircle className="h-4 w-4" />,
    className: 'bg-red-500/10 text-red-600 border-red-500/20',
  },
  unchecked: {
    label:     'Not Connected',
    icon:      <Circle className="h-4 w-4" />,
    className: 'bg-muted text-muted-foreground border-border',
  },
  saving: {
    label:     'Verifying…',
    icon:      <Loader2 className="h-4 w-4 animate-spin" />,
    className: 'bg-[#390d58]/10 text-[#390d58] border-[#390d58]/20',
  },
}

export function Configuration() {
  const hasApiKey = window.BeaconData?.hasApiKey ?? false
  const siteUrl   = window.BeaconData?.siteUrl   ?? ''
  const siteName  = window.BeaconData?.siteName  ?? ''

  const [apiKey,    setApiKey]    = useState('')
  const [status,    setStatus]    = useState<ConnectionStatus>(hasApiKey ? 'verified' : 'unchecked')
  const [maskedKey, setMaskedKey] = useState('')
  const [errorMsg,  setErrorMsg]  = useState<string | null>(null)

  const handleSave = async () => {
    if (!apiKey.trim()) return
    setStatus('saving')
    setErrorMsg(null)

    try {
      const res = await api.post<{ ok: boolean; masked_key: string }>('/config/api-key', { api_key: apiKey })
      setMaskedKey(res.masked_key)
      setApiKey('')
      setStatus('verified')
      // Update BeaconData in-memory so other pages see the updated state
      if (window.BeaconData) {
        window.BeaconData.hasApiKey   = true
        window.BeaconData.isConnected = true
      }
    } catch (e) {
      setErrorMsg(e instanceof ApiError ? e.message : 'Verification failed.')
      setStatus('failed')
    }
  }

  const siteInfo = [
    { label: 'Site URL',  value: siteUrl   || '—', icon: <Globe    className="h-4 w-4" /> },
    { label: 'Site Name', value: siteName  || '—', icon: <Building2 className="h-4 w-4" /> },
    ...(maskedKey || (hasApiKey && !maskedKey)
      ? [{ label: 'API Key', value: maskedKey || '••••••••••••', icon: <Key className="h-4 w-4" /> }]
      : []),
  ]

  return (
    <div className="space-y-6">
      {/* API Key Section */}
      <Card className="border-[#390d58]/20 overflow-hidden">
        <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
        <CardHeader>
          <div className="flex items-center gap-4">
            <div className="rounded-xl bg-[#390d58] p-3 text-white shadow-md shadow-[#390d58]/20">
              <Key className="h-5 w-5" />
            </div>
            <div>
              <CardTitle className="text-lg text-[#390d58]">API Configuration</CardTitle>
              <CardDescription>Connect your site to Beacon using your API key</CardDescription>
            </div>
          </div>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="flex gap-3">
            <div className="flex-1">
              <Input
                type="password"
                placeholder={status === 'verified' ? 'Enter a new key to replace the current one' : 'Enter your Beacon API key'}
                value={apiKey}
                onChange={e => setApiKey(e.target.value)}
                onKeyDown={e => e.key === 'Enter' && handleSave()}
                className="font-mono border-[#390d58]/20 focus-visible:ring-[#390d58]"
              />
            </div>
            <Button
              onClick={handleSave}
              className="bg-[#390d58] hover:bg-[#4a1170] text-white"
              disabled={status === 'saving' || !apiKey.trim()}
            >
              {status === 'saving' ? <Loader2 className="h-4 w-4 animate-spin mr-1" /> : null}
              {status === 'saving' ? 'Verifying…' : 'Save & Verify'}
            </Button>
          </div>

          {errorMsg && (
            <p className="text-sm text-red-600">{errorMsg}</p>
          )}

          <div className="flex items-center gap-3 p-4 rounded-xl bg-[#390d58]/5">
            <Shield className="h-5 w-5 text-[#390d58]" />
            <div className="flex-1">
              <span className="text-sm text-muted-foreground">Connection Status</span>
            </div>
            <Badge variant="outline" className={`gap-1.5 ${statusConfig[status].className}`}>
              {statusConfig[status].icon}
              {statusConfig[status].label}
            </Badge>
          </div>
        </CardContent>
      </Card>

      {/* Site Information */}
      <Card className="border-[#390d58]/20 overflow-hidden">
        <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
        <CardHeader>
          <div className="flex items-center gap-4">
            <div className="rounded-xl bg-[#390d58] p-3 text-white shadow-md shadow-[#390d58]/20">
              <Globe className="h-5 w-5" />
            </div>
            <div>
              <CardTitle className="text-lg text-[#390d58]">Site Information</CardTitle>
              <CardDescription>Data Beacon has on record for this site</CardDescription>
            </div>
          </div>
        </CardHeader>
        <CardContent>
          <div className="rounded-xl border border-[#390d58]/10 overflow-hidden">
            {siteInfo.map((item, index) => (
              <div key={item.label}>
                <div className="flex items-center justify-between p-4 bg-[#390d58]/[0.02] hover:bg-[#390d58]/5 transition-colors">
                  <div className="flex items-center gap-3 text-sm text-muted-foreground">
                    <span className="text-[#390d58]">{item.icon}</span>
                    <span>{item.label}</span>
                  </div>
                  <span className="text-sm font-medium font-mono">{item.value}</span>
                </div>
                {index < siteInfo.length - 1 && <Separator className="bg-[#390d58]/10" />}
              </div>
            ))}
          </div>
        </CardContent>
      </Card>

    </div>
  )
}
