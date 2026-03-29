import { useEffect, useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { Loader2, Save } from 'lucide-react'
import { api, ApiError } from '@/lib/api'

interface BarData { enabled: boolean; message: string; link: string; bg_color: string; text_color: string; dismissible: boolean }

export function AnnouncementBarTool() {
  const [data,    setData]    = useState<BarData>({ enabled: false, message: '', link: '', bg_color: '#1d2327', text_color: '#ffffff', dismissible: false })
  const [loading, setLoading] = useState(true)
  const [saving,  setSaving]  = useState(false)
  const [msg,     setMsg]     = useState<{ ok: boolean; text: string } | null>(null)

  useEffect(() => {
    api.get<BarData>('/workshop/announcement-bar').then(setData).finally(() => setLoading(false))
  }, [])

  const set = <K extends keyof BarData>(k: K, v: BarData[K]) => setData(d => ({ ...d, [k]: v }))

  const handleSave = async () => {
    setSaving(true); setMsg(null)
    try {
      await api.post('/workshop/announcement-bar', data)
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
        <CardTitle className="text-lg text-[#390d58]">Announcement Bar</CardTitle>
        <CardDescription>Show a fixed banner at the bottom of every page with optional dismiss.</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        {data.message && (
          <div className="p-3 rounded-lg text-sm font-medium text-center" style={{ background: data.bg_color, color: data.text_color }}>
            {data.message || 'Preview of your announcement bar'}
          </div>
        )}
        <div className="flex items-center justify-between p-4 rounded-xl bg-[#390d58]/5">
          <Label htmlFor="bar-toggle" className="cursor-pointer">Enable Announcement Bar</Label>
          <Switch id="bar-toggle" checked={data.enabled} onCheckedChange={v => set('enabled', v)} />
        </div>
        {field('Message', <Input value={data.message} onChange={e => set('message', e.target.value)} placeholder="🚀 We just launched something new!" className="border-[#390d58]/20 focus-visible:ring-[#390d58]" />)}
        {field('Link URL (optional)', <Input value={data.link} onChange={e => set('link', e.target.value)} placeholder="https://example.com/news" className="border-[#390d58]/20 focus-visible:ring-[#390d58]" />)}
        <div className="grid sm:grid-cols-2 gap-4">
          {field('Background Colour',
            <div className="flex gap-2">
              <Input type="color" value={data.bg_color} onChange={e => set('bg_color', e.target.value)} className="w-12 h-9 p-1 border-[#390d58]/20 cursor-pointer" />
              <Input value={data.bg_color} onChange={e => set('bg_color', e.target.value)} className="border-[#390d58]/20 focus-visible:ring-[#390d58]" />
            </div>
          )}
          {field('Text Colour',
            <div className="flex gap-2">
              <Input type="color" value={data.text_color} onChange={e => set('text_color', e.target.value)} className="w-12 h-9 p-1 border-[#390d58]/20 cursor-pointer" />
              <Input value={data.text_color} onChange={e => set('text_color', e.target.value)} className="border-[#390d58]/20 focus-visible:ring-[#390d58]" />
            </div>
          )}
        </div>
        <div className="flex items-center justify-between p-4 rounded-xl bg-[#390d58]/5">
          <Label htmlFor="dismissible-toggle" className="cursor-pointer">Allow visitors to dismiss</Label>
          <Switch id="dismissible-toggle" checked={data.dismissible} onCheckedChange={v => set('dismissible', v)} />
        </div>
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
