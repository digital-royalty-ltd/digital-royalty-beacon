import { useEffect, useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { Loader2, Save } from 'lucide-react'
import { api, ApiError } from '@/lib/api'

interface SnippetSlot {
  enabled: boolean
  code: string
  post_types: string[]
  url_contains: string
  location: 'all' | 'singular' | 'archive' | '404'
  homepage_only: boolean
  logged_in_only: boolean
  user_roles: string[]
  updated_at: string
}

interface HistoryItem {
  saved_at: string
  header: SnippetSlot
  footer: SnippetSlot
}

interface CodeData {
  header: SnippetSlot
  footer: SnippetSlot
  history: HistoryItem[]
}

const emptySlot = (): SnippetSlot => ({
  enabled: false,
  code: '',
  post_types: [],
  url_contains: '',
  location: 'all',
  homepage_only: false,
  logged_in_only: false,
  user_roles: [],
  updated_at: '',
})

export function CodeInjectionTool() {
  const [data, setData] = useState<CodeData>({ header: emptySlot(), footer: emptySlot(), history: [] })
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [msg, setMsg] = useState<{ ok: boolean; text: string } | null>(null)

  useEffect(() => {
    api.get<CodeData>('/workshop/code-injection').then(setData).finally(() => setLoading(false))
  }, [])

  const updateSlot = (slot: 'header' | 'footer', patch: Partial<SnippetSlot>) => {
    setData(current => ({ ...current, [slot]: { ...current[slot], ...patch } }))
  }

  const handleSave = async () => {
    setSaving(true)
    setMsg(null)
    try {
      await api.post('/workshop/code-injection', {
        header: data.header,
        footer: data.footer,
      })
      const fresh = await api.get<CodeData>('/workshop/code-injection')
      setData(fresh)
      setMsg({ ok: true, text: 'Snippet settings saved.' })
    } catch (e) {
      setMsg({ ok: false, text: e instanceof ApiError ? e.message : 'Save failed.' })
    } finally {
      setSaving(false)
    }
  }

  const handleRestore = async (savedAt: string) => {
    setSaving(true)
    setMsg(null)
    try {
      await api.post('/workshop/code-injection/restore', { saved_at: savedAt })
      const fresh = await api.get<CodeData>('/workshop/code-injection')
      setData(fresh)
      setMsg({ ok: true, text: `Restored revision from ${savedAt}.` })
    } catch (e) {
      setMsg({ ok: false, text: e instanceof ApiError ? e.message : 'Restore failed.' })
    } finally {
      setSaving(false)
    }
  }

  const renderSlot = (slot: 'header' | 'footer', label: string, description: string) => {
    const entry = data[slot]

    return (
      <div className="rounded-xl border border-[#390d58]/10 p-4 space-y-4">
        <div className="flex items-start justify-between gap-4">
          <div>
            <p className="text-sm font-medium text-[#390d58]">{label}</p>
            <p className="text-xs text-muted-foreground">{description}</p>
          </div>
          <div className="flex items-center gap-2">
            <span className="text-xs text-muted-foreground">{entry.enabled ? 'Enabled' : 'Disabled'}</span>
            <Switch checked={entry.enabled} onCheckedChange={enabled => updateSlot(slot, { enabled })} />
          </div>
        </div>

        <div className="space-y-1.5">
          <Label>Code</Label>
          <Textarea
            rows={7}
            value={entry.code}
            onChange={e => updateSlot(slot, { code: e.target.value })}
            className="font-mono text-xs border-[#390d58]/20 focus-visible:ring-[#390d58]"
            placeholder={slot === 'header' ? '<script>/* head snippet */</script>' : '<script>/* footer snippet */</script>'}
          />
        </div>

        <div className="grid gap-4 sm:grid-cols-2">
          <div className="space-y-1.5">
            <Label>Page scope</Label>
            <select
              value={entry.location}
              onChange={e => updateSlot(slot, { location: e.target.value as SnippetSlot['location'] })}
              className="h-10 w-full rounded-md border border-[#390d58]/20 bg-white px-3 text-sm"
            >
              <option value="all">All frontend pages</option>
              <option value="singular">Singular content only</option>
              <option value="archive">Archive pages only</option>
              <option value="404">404 pages only</option>
            </select>
          </div>
          <div className="space-y-1.5">
            <Label>Post types</Label>
            <Input
              value={entry.post_types.join(', ')}
              onChange={e => updateSlot(slot, {
                post_types: e.target.value.split(',').map(v => v.trim()).filter(Boolean),
              })}
              className="border-[#390d58]/20 focus-visible:ring-[#390d58]"
              placeholder="post, page, product"
            />
            <p className="text-xs text-muted-foreground">Leave blank to inject globally.</p>
          </div>

          <div className="space-y-1.5">
            <Label>URL contains</Label>
            <Input
              value={entry.url_contains}
              onChange={e => updateSlot(slot, { url_contains: e.target.value })}
              className="border-[#390d58]/20 focus-visible:ring-[#390d58]"
              placeholder="/checkout"
            />
            <p className="text-xs text-muted-foreground">Optional simple path match.</p>
          </div>
        </div>

        <div className="flex flex-wrap gap-4 text-sm text-muted-foreground">
          <label className="flex items-center gap-2">
            <input type="checkbox" checked={entry.homepage_only} onChange={e => updateSlot(slot, { homepage_only: e.target.checked })} />
            Homepage only
          </label>
          <label className="flex items-center gap-2">
            <input type="checkbox" checked={entry.logged_in_only} onChange={e => updateSlot(slot, { logged_in_only: e.target.checked })} />
            Logged-in visitors only
          </label>
        </div>

        <div className="space-y-1.5">
          <Label>User roles</Label>
          <Input
            value={entry.user_roles.join(', ')}
            onChange={e => updateSlot(slot, {
              user_roles: e.target.value.split(',').map(v => v.trim()).filter(Boolean),
            })}
            className="border-[#390d58]/20 focus-visible:ring-[#390d58]"
            placeholder="administrator, editor"
          />
          <p className="text-xs text-muted-foreground">Restrict to specific user roles. Leave blank to show to all visitors (or combine with logged-in toggle).</p>
        </div>

        {entry.updated_at && (
          <p className="text-xs text-muted-foreground">Last saved: {entry.updated_at}</p>
        )}
      </div>
    )
  }

  return (
    <Card className="border-[#390d58]/20 overflow-hidden">
      <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
      <CardHeader>
        <CardTitle className="text-lg text-[#390d58]">Header &amp; Footer Code</CardTitle>
        <CardDescription>Manage global snippets with enable toggles and lightweight targeting rules.</CardDescription>
      </CardHeader>
      <CardContent className="space-y-5">
        {loading ? <Loader2 className="h-5 w-5 animate-spin text-[#390d58]" /> : (
          <>
            {renderSlot('header', 'Header Snippet', 'Injected before </head>.')}
            {renderSlot('footer', 'Footer Snippet', 'Injected before </body>.')}

            <div className="rounded-xl border border-[#390d58]/10 p-4 space-y-2">
              <p className="text-sm font-medium text-[#390d58]">Recent saves</p>
              {data.history.length === 0 ? (
                <p className="text-xs text-muted-foreground">No saved history yet.</p>
              ) : (
                <div className="space-y-2">
                  {data.history.map(item => (
                    <div key={item.saved_at} className="flex items-center justify-between gap-3 rounded-lg bg-[#390d58]/5 px-3 py-2 text-xs text-muted-foreground">
                      <div>
                        <span className="font-medium text-foreground">{item.saved_at}</span>
                        {' '}Header: {item.header.enabled ? 'on' : 'off'}, Footer: {item.footer.enabled ? 'on' : 'off'}
                      </div>
                      <Button size="sm" variant="outline" disabled={saving} onClick={() => handleRestore(item.saved_at)}>Restore</Button>
                    </div>
                  ))}
                </div>
              )}
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
