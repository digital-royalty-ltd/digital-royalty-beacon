import { useEffect, useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Label } from '@/components/ui/label'
import { Loader2, Save } from 'lucide-react'
import { api, ApiError } from '@/lib/api'

interface BrandData { logo_url: string; bg_color: string; bg_image_url: string; custom_css: string }

export function LoginBrandingTool() {
  const [data,    setData]    = useState<BrandData>({ logo_url: '', bg_color: '', bg_image_url: '', custom_css: '' })
  const [loading, setLoading] = useState(true)
  const [saving,  setSaving]  = useState(false)
  const [msg,     setMsg]     = useState<{ ok: boolean; text: string } | null>(null)

  useEffect(() => {
    api.get<BrandData>('/workshop/login-branding').then(setData).finally(() => setLoading(false))
  }, [])

  const set = <K extends keyof BrandData>(k: K, v: BrandData[K]) => setData(d => ({ ...d, [k]: v }))

  const handleSave = async () => {
    setSaving(true); setMsg(null)
    try {
      await api.post('/workshop/login-branding', data)
      setMsg({ ok: true, text: 'Saved.' })
    } catch (e) {
      setMsg({ ok: false, text: e instanceof ApiError ? e.message : 'Save failed.' })
    } finally {
      setSaving(false)
    }
  }

  if (loading) return <Loader2 className="h-5 w-5 animate-spin text-[#390d58] m-6" />

  const field = (label: string, el: React.ReactNode) => (
    <div className="space-y-1.5"><Label>{label}</Label>{el}</div>
  )

  return (
    <Card className="border-[#390d58]/20 overflow-hidden">
      <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
      <CardHeader>
        <CardTitle className="text-lg text-[#390d58]">Login Page Branding</CardTitle>
        <CardDescription>Customise the WordPress login page with your logo and colours.</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        {field('Logo URL', <Input value={data.logo_url} onChange={e => set('logo_url', e.target.value)} placeholder="https://example.com/logo.png" className="border-[#390d58]/20 focus-visible:ring-[#390d58]" />)}
        <div className="grid sm:grid-cols-2 gap-4">
          {field('Background Colour',
            <div className="flex gap-2">
              <Input type="color" value={data.bg_color || '#f0f0f1'} onChange={e => set('bg_color', e.target.value)} className="w-12 h-9 p-1 border-[#390d58]/20 cursor-pointer" />
              <Input value={data.bg_color} onChange={e => set('bg_color', e.target.value)} placeholder="#f0f0f1" className="border-[#390d58]/20 focus-visible:ring-[#390d58]" />
            </div>
          )}
          {field('Background Image URL', <Input value={data.bg_image_url} onChange={e => set('bg_image_url', e.target.value)} placeholder="https://example.com/bg.jpg" className="border-[#390d58]/20 focus-visible:ring-[#390d58]" />)}
        </div>
        {field('Custom CSS',
          <Textarea rows={6} value={data.custom_css} onChange={e => set('custom_css', e.target.value)} placeholder="/* custom login page CSS */" className="font-mono text-xs border-[#390d58]/20 focus-visible:ring-[#390d58]" />
        )}
        <div className="flex items-center justify-between">
          {msg && <p className={`text-sm ${msg.ok ? 'text-emerald-600' : 'text-red-600'}`}>{msg.text}</p>}
          <Button onClick={handleSave} disabled={saving} className="ml-auto bg-[#390d58] hover:bg-[#4a1170] text-white gap-2">
            {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
            Save
          </Button>
        </div>
      </CardContent>
    </Card>
  )
}
