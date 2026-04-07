import { useEffect, useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'
import { Loader2, Save, Copy, ExternalLink } from 'lucide-react'
import { api, ApiError } from '@/lib/api'

interface MaintData {
  enabled: boolean
  headline: string
  message: string
  return_date: string
  bg_color: string
  bg_image_url: string
  response_code: number
  allowed_capability: string
  bypass_url: string
  preview_url: string
  available_capabilities: string[]
}

export function MaintenanceModeTool() {
  const [data, setData] = useState<MaintData>({
    enabled: false,
    headline: '',
    message: '',
    return_date: '',
    bg_color: '#f6f1fb',
    bg_image_url: '',
    response_code: 503,
    allowed_capability: 'manage_options',
    bypass_url: '',
    preview_url: '',
    available_capabilities: ['manage_options'],
  })
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [msg, setMsg] = useState<{ ok: boolean; text: string } | null>(null)

  useEffect(() => {
    api.get<MaintData>('/workshop/maintenance-mode').then(setData).finally(() => setLoading(false))
  }, [])

  const handleSave = async () => {
    setSaving(true)
    setMsg(null)
    try {
      await api.post('/workshop/maintenance-mode', data)
      setMsg({ ok: true, text: 'Maintenance settings saved.' })
    } catch (e) {
      setMsg({ ok: false, text: e instanceof ApiError ? e.message : 'Save failed.' })
    } finally {
      setSaving(false)
    }
  }

  const copyText = async (value: string, label: string) => {
    await navigator.clipboard.writeText(value)
    setMsg({ ok: true, text: `${label} copied.` })
  }

  return (
    <Card className="border-[#390d58]/20 overflow-hidden">
      <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
      <CardHeader>
        <CardTitle className="text-lg text-[#390d58]">Maintenance Mode</CardTitle>
        <CardDescription>Gate visitors with a configurable maintenance page while approved capabilities continue working.</CardDescription>
      </CardHeader>
      <CardContent className="space-y-5">
        {loading ? <Loader2 className="h-5 w-5 animate-spin text-[#390d58]" /> : (
          <>
            <div className="flex items-center justify-between rounded-xl bg-[#390d58]/5 p-4">
              <Label htmlFor="maint-toggle" className="cursor-pointer">Enable maintenance mode</Label>
              <Switch id="maint-toggle" checked={data.enabled} onCheckedChange={enabled => setData(d => ({ ...d, enabled }))} />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-1.5">
                <Label>Headline</Label>
                <Input value={data.headline} onChange={e => setData(d => ({ ...d, headline: e.target.value }))} className="border-[#390d58]/20 focus-visible:ring-[#390d58]" />
              </div>
              <div className="space-y-1.5">
                <Label>Return date</Label>
                <Input value={data.return_date} onChange={e => setData(d => ({ ...d, return_date: e.target.value }))} placeholder="Friday 6pm" className="border-[#390d58]/20 focus-visible:ring-[#390d58]" />
              </div>
            </div>

            <div className="space-y-1.5">
              <Label>Message</Label>
              <Textarea rows={5} value={data.message} onChange={e => setData(d => ({ ...d, message: e.target.value }))} className="border-[#390d58]/20 focus-visible:ring-[#390d58]" />
            </div>

            <div className="grid gap-4 sm:grid-cols-3">
              <div className="space-y-1.5">
                <Label>Background colour</Label>
                <Input type="color" value={data.bg_color} onChange={e => setData(d => ({ ...d, bg_color: e.target.value }))} className="h-10 border-[#390d58]/20 p-1" />
              </div>
              <div className="space-y-1.5 sm:col-span-2">
                <Label>Background image URL</Label>
                <Input value={data.bg_image_url} onChange={e => setData(d => ({ ...d, bg_image_url: e.target.value }))} placeholder="https://example.com/background.jpg" className="border-[#390d58]/20 focus-visible:ring-[#390d58]" />
              </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-1.5">
                <Label>Response code</Label>
                <Select value={String(data.response_code)} onValueChange={value => setData(d => ({ ...d, response_code: parseInt(value, 10) }))}>
                  <SelectTrigger className="border-[#390d58]/20"><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="503">503 Maintenance</SelectItem>
                    <SelectItem value="200">200 Coming Soon</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-1.5">
                <Label>Allowed capability</Label>
                <Select value={data.allowed_capability} onValueChange={value => setData(d => ({ ...d, allowed_capability: value }))}>
                  <SelectTrigger className="border-[#390d58]/20"><SelectValue /></SelectTrigger>
                  <SelectContent>
                    {data.available_capabilities.map(capability => (
                      <SelectItem key={capability} value={capability}>{capability}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
              <div className="rounded-xl border border-[#390d58]/10 p-4 space-y-2">
                <p className="text-sm font-medium text-[#390d58]">Preview URL</p>
                <div className="flex gap-2">
                  <Input value={data.preview_url} readOnly className="border-[#390d58]/20 bg-white" />
                  <Button variant="outline" onClick={() => copyText(data.preview_url, 'Preview URL')}><Copy className="h-4 w-4" /></Button>
                  <Button variant="outline" asChild>
                    <a href={data.preview_url} target="_blank" rel="noreferrer"><ExternalLink className="h-4 w-4" /></a>
                  </Button>
                </div>
                <p className="text-xs text-muted-foreground">Use this while logged in to preview the maintenance screen without signing out.</p>
              </div>
              <div className="rounded-xl border border-[#390d58]/10 p-4 space-y-2">
                <p className="text-sm font-medium text-[#390d58]">Bypass URL</p>
                <div className="flex gap-2">
                  <Input value={data.bypass_url} readOnly className="border-[#390d58]/20 bg-white" />
                  <Button variant="outline" onClick={() => copyText(data.bypass_url, 'Bypass URL')}><Copy className="h-4 w-4" /></Button>
                </div>
                <p className="text-xs text-muted-foreground">Opening this link sets a temporary bypass cookie for users without the allowed capability.</p>
              </div>
            </div>

            <div
              className="rounded-2xl border border-[#390d58]/10 p-6 text-center"
              style={{
                backgroundColor: data.bg_color,
                backgroundImage: data.bg_image_url ? `url(${data.bg_image_url})` : undefined,
                backgroundSize: 'cover',
                backgroundPosition: 'center',
              }}
            >
              <div className="mx-auto max-w-md rounded-2xl bg-white/85 p-6">
                <p className="text-xs uppercase tracking-[0.2em] text-[#390d58]">{window.BeaconData?.siteName ?? 'Site Preview'}</p>
                <h3 className="mt-3 text-2xl font-semibold text-[#390d58]">{data.headline || 'Scheduled Maintenance'}</h3>
                <div className="mt-3 text-sm text-muted-foreground whitespace-pre-wrap">{data.message || 'Your message will appear here.'}</div>
                {data.return_date && <p className="mt-4 text-xs text-muted-foreground">Expected return: {data.return_date}</p>}
              </div>
            </div>

            <div className="flex items-center justify-between">
              {msg && <p className={`text-sm ${msg.ok ? 'text-emerald-600' : 'text-red-600'}`}>{msg.text}</p>}
              <Button onClick={handleSave} disabled={saving} className="ml-auto bg-[#390d58] hover:bg-[#4a1170] text-white gap-2">
                {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
                Save
              </Button>
            </div>
          </>
        )}
      </CardContent>
    </Card>
  )
}
