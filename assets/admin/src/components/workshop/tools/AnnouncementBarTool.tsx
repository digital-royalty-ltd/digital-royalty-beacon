import { useCallback, useEffect, useRef, useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { Loader2, Save } from 'lucide-react'
import { api, ApiError } from '@/lib/api'

interface BarData {
  enabled: boolean
  message: string
  button_label: string
  button_url: string
  bg_color: string
  text_color: string
  button_color: string
  start_at: string
  end_at: string
  dismissible: boolean
  dismiss_version: number
}

export function AnnouncementBarTool() {
  const [data, setData] = useState<BarData>({
    enabled: false,
    message: '',
    button_label: '',
    button_url: '',
    bg_color: '#1d2327',
    text_color: '#ffffff',
    button_color: '#ffffff',
    start_at: '',
    end_at: '',
    dismissible: false,
    dismiss_version: 1,
  })
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [msg, setMsg] = useState<{ ok: boolean; text: string } | null>(null)

  useEffect(() => {
    api.get<BarData>('/workshop/announcement-bar').then(setData).finally(() => setLoading(false))
  }, [])

  const set = <K extends keyof BarData>(key: K, value: BarData[K]) => setData(current => ({ ...current, [key]: value }))

  const handleSave = async () => {
    setSaving(true)
    setMsg(null)
    try {
      await api.post('/workshop/announcement-bar', data)
      setMsg({ ok: true, text: 'Announcement bar saved.' })
    } catch (e) {
      setMsg({ ok: false, text: e instanceof ApiError ? e.message : 'Save failed.' })
    } finally {
      setSaving(false)
    }
  }

  const editorRef = useRef<HTMLDivElement>(null)

  const execCmd = useCallback((cmd: string, value?: string) => {
    editorRef.current?.focus()
    document.execCommand(cmd, false, value)
    if (editorRef.current) {
      set('message', editorRef.current.innerHTML)
    }
  }, [])

  const handleEditorInput = useCallback(() => {
    if (editorRef.current) {
      set('message', editorRef.current.innerHTML)
    }
  }, [])

  const insertLink = useCallback(() => {
    const url = prompt('Enter URL:', 'https://')
    if (url) execCmd('createLink', url)
  }, [execCmd])

  if (loading) return <Loader2 className="m-6 h-5 w-5 animate-spin text-[#390d58]" />

  return (
    <Card className="border-[#390d58]/20 overflow-hidden">
      <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
      <CardHeader>
        <CardTitle className="text-lg text-[#390d58]">Announcement Bar</CardTitle>
        <CardDescription>Configure a top-of-site announcement bar with scheduling, CTA, and dismiss behavior.</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="flex items-center justify-between rounded-xl bg-[#390d58]/5 p-4">
          <Label htmlFor="bar-toggle" className="cursor-pointer">Enable announcement bar</Label>
          <Switch id="bar-toggle" checked={data.enabled} onCheckedChange={value => set('enabled', value)} />
        </div>

        <div className="space-y-1.5">
          <Label>Message</Label>
          <div className="rounded-md border border-[#390d58]/20 overflow-hidden">
            <div className="flex flex-wrap gap-1 border-b border-[#390d58]/10 bg-[#390d58]/5 px-2 py-1.5">
              <Button type="button" size="sm" variant="ghost" className="h-7 px-2 text-xs font-bold" onClick={() => execCmd('bold')}>B</Button>
              <Button type="button" size="sm" variant="ghost" className="h-7 px-2 text-xs italic" onClick={() => execCmd('italic')}>I</Button>
              <Button type="button" size="sm" variant="ghost" className="h-7 px-2 text-xs underline" onClick={() => execCmd('underline')}>U</Button>
              <span className="mx-1 w-px self-stretch bg-[#390d58]/10" />
              <Button type="button" size="sm" variant="ghost" className="h-7 px-2 text-xs" onClick={insertLink}>Link</Button>
              <Button type="button" size="sm" variant="ghost" className="h-7 px-2 text-xs" onClick={() => execCmd('unlink')}>Unlink</Button>
              <span className="mx-1 w-px self-stretch bg-[#390d58]/10" />
              <Button type="button" size="sm" variant="ghost" className="h-7 px-2 text-xs" onClick={() => execCmd('removeFormat')}>Clear</Button>
            </div>
            <div
              ref={editorRef}
              contentEditable
              onInput={handleEditorInput}
              onBlur={handleEditorInput}
              dangerouslySetInnerHTML={{ __html: data.message }}
              className="min-h-[100px] px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-[#390d58]/30"
              suppressContentEditableWarning
            />
          </div>
          <p className="text-xs text-muted-foreground">Use the toolbar for formatting. HTML is preserved.</p>
        </div>

        <div className="grid gap-4 sm:grid-cols-2">
          <div className="space-y-1.5">
            <Label>Button label</Label>
            <Input value={data.button_label} onChange={e => set('button_label', e.target.value)} className="border-[#390d58]/20 focus-visible:ring-[#390d58]" placeholder="Learn more" />
          </div>
          <div className="space-y-1.5">
            <Label>Button URL</Label>
            <Input value={data.button_url} onChange={e => set('button_url', e.target.value)} className="border-[#390d58]/20 focus-visible:ring-[#390d58]" placeholder="https://example.com" />
          </div>
        </div>

        <div className="grid gap-4 sm:grid-cols-3">
          <div className="space-y-1.5">
            <Label>Background colour</Label>
            <Input type="color" value={data.bg_color} onChange={e => set('bg_color', e.target.value)} className="h-10 border-[#390d58]/20 p-1" />
          </div>
          <div className="space-y-1.5">
            <Label>Text colour</Label>
            <Input type="color" value={data.text_color} onChange={e => set('text_color', e.target.value)} className="h-10 border-[#390d58]/20 p-1" />
          </div>
          <div className="space-y-1.5">
            <Label>Button colour</Label>
            <Input type="color" value={data.button_color} onChange={e => set('button_color', e.target.value)} className="h-10 border-[#390d58]/20 p-1" />
          </div>
        </div>

        <div className="grid gap-4 sm:grid-cols-2">
          <div className="space-y-1.5">
            <Label>Start date/time</Label>
            <Input type="datetime-local" value={data.start_at} onChange={e => set('start_at', e.target.value)} className="border-[#390d58]/20 focus-visible:ring-[#390d58]" />
          </div>
          <div className="space-y-1.5">
            <Label>End date/time</Label>
            <Input type="datetime-local" value={data.end_at} onChange={e => set('end_at', e.target.value)} className="border-[#390d58]/20 focus-visible:ring-[#390d58]" />
          </div>
        </div>

        <div className="flex items-center justify-between rounded-xl bg-[#390d58]/5 p-4">
          <Label htmlFor="dismissible-toggle" className="cursor-pointer">Allow visitors to dismiss</Label>
          <Switch id="dismissible-toggle" checked={data.dismissible} onCheckedChange={value => set('dismissible', value)} />
        </div>

        {data.dismissible && (
          <div className="flex items-center justify-between rounded-xl border border-[#390d58]/10 p-4 text-sm">
            <div>
              <p className="font-medium text-[#390d58]">Dismiss reset version</p>
              <p className="text-muted-foreground">Current version: {data.dismiss_version}</p>
            </div>
            <Button type="button" variant="outline" onClick={() => set('dismiss_version', data.dismiss_version + 1)}>
              Reset visitor dismissals
            </Button>
          </div>
        )}

        <div className="rounded-xl p-4 text-sm" style={{ background: data.bg_color, color: data.text_color }}>
          <div className="flex flex-wrap items-center justify-center gap-3">
            <div dangerouslySetInnerHTML={{ __html: data.message || 'Announcement preview' }} />
            {data.button_label && (
              <span className="rounded-full px-3 py-1 text-xs font-semibold" style={{ background: data.button_color, color: data.bg_color }}>
                {data.button_label}
              </span>
            )}
            {data.dismissible && <span className="text-xs opacity-80">Dismissible</span>}
          </div>
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
