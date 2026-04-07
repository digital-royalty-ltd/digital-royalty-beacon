import { useEffect, useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Label } from '@/components/ui/label'
import { Loader2, Save, ImagePlus } from 'lucide-react'
import { api, ApiError } from '@/lib/api'

interface BrandData {
  logo_url: string
  bg_color: string
  bg_image_url: string
  logo_link_url: string
  logo_alt_text: string
  custom_css: string
  preview_url: string
}

declare global {
  interface Window {
    wp?: {
      media?: (options: Record<string, unknown>) => {
        on: (event: string, callback: () => void) => void
        open: () => void
        state: () => { get: (name: string) => { first: () => { toJSON: () => { url: string } } } }
      }
    }
  }
}

export function LoginBrandingTool() {
  const [data, setData] = useState<BrandData>({
    logo_url: '',
    bg_color: '#f0f0f1',
    bg_image_url: '',
    logo_link_url: window.BeaconData?.siteUrl ?? '',
    logo_alt_text: window.BeaconData?.siteName ?? '',
    custom_css: '',
    preview_url: '',
  })
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [msg, setMsg] = useState<{ ok: boolean; text: string } | null>(null)

  useEffect(() => {
    api.get<BrandData>('/workshop/login-branding').then(setData).finally(() => setLoading(false))
  }, [])

  const set = <K extends keyof BrandData>(key: K, value: BrandData[K]) => setData(current => ({ ...current, [key]: value }))

  const openMediaPicker = (field: 'logo_url' | 'bg_image_url') => {
    const media = window.wp?.media
    if (!media) return

    const frame = media({
      title: field === 'logo_url' ? 'Select logo' : 'Select background image',
      multiple: false,
    })

    frame.on('select', () => {
      const attachment = frame.state().get('selection').first().toJSON()
      set(field, attachment.url)
    })
    frame.open()
  }

  const handleSave = async () => {
    setSaving(true)
    setMsg(null)
    try {
      await api.post('/workshop/login-branding', data)
      setMsg({ ok: true, text: 'Login branding saved.' })
    } catch (e) {
      setMsg({ ok: false, text: e instanceof ApiError ? e.message : 'Save failed.' })
    } finally {
      setSaving(false)
    }
  }

  if (loading) return <Loader2 className="m-6 h-5 w-5 animate-spin text-[#390d58]" />

  return (
    <Card className="border-[#390d58]/20 overflow-hidden">
      <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
      <CardHeader>
        <CardTitle className="text-lg text-[#390d58]">Login Page Branding</CardTitle>
        <CardDescription>Set logo, background, link metadata, and custom CSS with a live preview panel.</CardDescription>
      </CardHeader>
      <CardContent className="space-y-5">
        <div className="grid gap-5 lg:grid-cols-[1.3fr_0.9fr]">
          <div className="space-y-4">
            <div className="grid gap-3 sm:grid-cols-[1fr_auto]">
              <div className="space-y-1.5">
                <Label>Logo URL</Label>
                <Input value={data.logo_url} onChange={e => set('logo_url', e.target.value)} className="border-[#390d58]/20 focus-visible:ring-[#390d58]" />
              </div>
              <Button type="button" variant="outline" onClick={() => openMediaPicker('logo_url')} className="mt-auto gap-2">
                <ImagePlus className="h-4 w-4" />
                Choose
              </Button>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-1.5">
                <Label>Background colour</Label>
                <Input type="color" value={data.bg_color || '#f0f0f1'} onChange={e => set('bg_color', e.target.value)} className="h-10 border-[#390d58]/20 p-1" />
              </div>
              <div className="space-y-1.5">
                <Label>Background image URL</Label>
                <div className="flex gap-2">
                  <Input value={data.bg_image_url} onChange={e => set('bg_image_url', e.target.value)} className="border-[#390d58]/20 focus-visible:ring-[#390d58]" />
                  <Button type="button" variant="outline" onClick={() => openMediaPicker('bg_image_url')} className="gap-2">
                    <ImagePlus className="h-4 w-4" />
                    Choose
                  </Button>
                </div>
              </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-1.5">
                <Label>Logo link URL</Label>
                <Input value={data.logo_link_url} onChange={e => set('logo_link_url', e.target.value)} className="border-[#390d58]/20 focus-visible:ring-[#390d58]" />
              </div>
              <div className="space-y-1.5">
                <Label>Logo alt text</Label>
                <Input value={data.logo_alt_text} onChange={e => set('logo_alt_text', e.target.value)} className="border-[#390d58]/20 focus-visible:ring-[#390d58]" />
              </div>
            </div>

            <div className="space-y-1.5">
              <Label>Custom CSS</Label>
              <Textarea rows={7} value={data.custom_css} onChange={e => set('custom_css', e.target.value)} className="font-mono text-xs border-[#390d58]/20 focus-visible:ring-[#390d58]" />
            </div>
          </div>

          <div className="space-y-3">
            <div
              className="rounded-2xl border border-[#390d58]/10 p-6"
              style={{
                backgroundColor: data.bg_color || '#f0f0f1',
                backgroundImage: data.bg_image_url ? `url(${data.bg_image_url})` : undefined,
                backgroundSize: 'cover',
                backgroundPosition: 'center',
              }}
            >
              <div className="mx-auto max-w-sm rounded-2xl bg-white/90 p-6 shadow-sm">
                <div className="mb-5 flex justify-center">
                  {data.logo_url ? (
                    <img src={data.logo_url} alt={data.logo_alt_text || 'Login logo'} className="max-h-16 max-w-full object-contain" />
                  ) : (
                    <div className="text-sm font-medium text-[#390d58]">{data.logo_alt_text || window.BeaconData?.siteName}</div>
                  )}
                </div>
                <div className="space-y-3">
                  <Input readOnly value="Username or email" />
                  <Input readOnly value="Password" />
                  <Button className="w-full bg-[#390d58] hover:bg-[#4a1170] text-white">Log In</Button>
                </div>
                <p className="mt-4 text-center text-xs text-muted-foreground">Logo links to: {data.logo_link_url || window.BeaconData?.siteUrl}</p>
              </div>
            </div>
            {data.preview_url && (
              <div className="rounded-2xl border border-[#390d58]/10 overflow-hidden">
                <p className="bg-[#390d58]/5 px-3 py-2 text-xs font-medium text-[#390d58]">Live login page preview</p>
                <iframe src={data.preview_url} title="Login page preview" className="h-[420px] w-full border-0" sandbox="allow-same-origin" />
              </div>
            )}
          </div>
        </div>

        <div className="flex items-center justify-between">
          {msg && <p className={`text-sm ${msg.ok ? 'text-emerald-600' : 'text-red-600'}`}>{msg.text}</p>}
          <div className="ml-auto flex gap-2">
            {data.preview_url && (
              <Button variant="outline" asChild>
                <a href={data.preview_url} target="_blank" rel="noreferrer">Open real login page</a>
              </Button>
            )}
            <Button onClick={handleSave} disabled={saving} className="bg-[#390d58] hover:bg-[#4a1170] text-white gap-2">
              {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
              Save
            </Button>
          </div>
        </div>
      </CardContent>
    </Card>
  )
}
