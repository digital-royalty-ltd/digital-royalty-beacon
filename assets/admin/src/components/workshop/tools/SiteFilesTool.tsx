import { useEffect, useMemo, useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Textarea } from '@/components/ui/textarea'
import { Loader2, Save, RotateCcw, AlertTriangle } from 'lucide-react'
import { api, ApiError } from '@/lib/api'

interface SiteFileToolProps { slug: 'robots-editor' | 'htaccess-editor' }

interface RobotsData {
  content: string
  physical_exists: boolean
  physical_content: string
  effective_source: 'physical' | 'virtual'
  default_content: string
  served_url: string
}

interface HtaccessData {
  content: string
  writable: boolean
  backup_content: string
  backup_at: string
  has_wp_rewrites: boolean
}

export function SiteFilesTool({ slug }: SiteFileToolProps) {
  const isRobots = slug === 'robots-editor'
  const endpoint = isRobots ? '/workshop/robots' : '/workshop/htaccess'
  const [content, setContent] = useState('')
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [meta, setMeta] = useState<RobotsData | HtaccessData | null>(null)
  const [msg, setMsg] = useState<{ ok: boolean; text: string } | null>(null)
  const [confirmWrite, setConfirmWrite] = useState(false)
  const [robotsTest, setRobotsTest] = useState<{ ok: boolean; status: number; content: string; url: string; message: string } | null>(null)

  const load = () => {
    setLoading(true)
    const req = isRobots
      ? api.get<RobotsData>(endpoint)
      : api.get<HtaccessData>(endpoint)

    req.then(d => {
      setMeta(d)
      setContent(d.content)
    }).finally(() => setLoading(false))
  }

  useEffect(() => { load() }, [endpoint, isRobots])

  const handleSave = async () => {
    setSaving(true)
    setMsg(null)
    try {
      await api.post(endpoint, {
        content,
        require_wp_rewrites: !isRobots,
        confirm_write: isRobots ? true : confirmWrite,
      })
      setMsg({ ok: true, text: 'Saved.' })
      load()
    } catch (e) {
      setMsg({ ok: false, text: e instanceof ApiError ? e.message : 'Save failed.' })
    } finally {
      setSaving(false)
    }
  }

  const handleResetOrRestore = async () => {
    setSaving(true)
    setMsg(null)
    try {
      await api.post(endpoint, isRobots ? { reset: true } : { restore_backup: true })
      setMsg({ ok: true, text: isRobots ? 'robots.txt reset to default.' : 'Backup restored.' })
      load()
    } catch (e) {
      setMsg({ ok: false, text: e instanceof ApiError ? e.message : 'Action failed.' })
    } finally {
      setSaving(false)
    }
  }

  const diffSummary = useMemo(() => {
    if (!meta || isRobots) return null
    const current = (meta as HtaccessData).content
    if (current === content) return 'No unsaved changes.'

    const currentLines = current.split(/\r?\n/)
    const nextLines = content.split(/\r?\n/)
    const added = nextLines.filter(line => !currentLines.includes(line)).length
    const removed = currentLines.filter(line => !nextLines.includes(line)).length
    return `${added} added line${added !== 1 ? 's' : ''}, ${removed} removed line${removed !== 1 ? 's' : ''}.`
  }, [content, isRobots, meta])

  const lineDiff = useMemo(() => {
    if (isRobots || !meta) return []
    const current = (meta as HtaccessData).content.split(/\r?\n/)
    const next = content.split(/\r?\n/)
    const max = Math.max(current.length, next.length)
    return Array.from({ length: max }).map((_, index) => ({
      before: current[index] ?? '',
      after: next[index] ?? '',
      changed: (current[index] ?? '') !== (next[index] ?? ''),
    })).filter(line => line.changed)
  }, [content, isRobots, meta])

  const handleRobotsTest = async () => {
    setSaving(true)
    setMsg(null)
    try {
      const result = await api.get<{ ok: boolean; status: number; content: string; url: string; message: string }>('/workshop/robots/test')
      setRobotsTest(result)
    } catch (e) {
      setMsg({ ok: false, text: e instanceof ApiError ? e.message : 'Live test failed.' })
    } finally {
      setSaving(false)
    }
  }

  const robotsMeta = isRobots ? meta as RobotsData | null : null
  const htaccessMeta = !isRobots ? meta as HtaccessData | null : null

  return (
    <Card className="border-[#390d58]/20 overflow-hidden">
      <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
      <CardHeader>
        <CardTitle className="text-lg text-[#390d58]">{isRobots ? 'Robots.txt Editor' : '.htaccess Editor'}</CardTitle>
        <CardDescription>
          {isRobots
            ? 'Edit the virtual robots.txt response with validation and override warnings.'
            : 'Edit your root .htaccess file with backup awareness and rewrite validation.'}
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        {loading ? <Loader2 className="h-5 w-5 animate-spin text-[#390d58]" /> : (
          <>
            {isRobots && robotsMeta?.physical_exists && (
              <div className="flex gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                <AlertTriangle className="h-4 w-4 shrink-0" />
                A physical `robots.txt` exists in the site root and currently overrides the virtual file edited here.
              </div>
            )}

            {!isRobots && htaccessMeta && (
              <>
                {!htaccessMeta.writable && (
                  <div className="flex gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                    <AlertTriangle className="h-4 w-4 shrink-0" />
                    `.htaccess` is not writable. Make it writable before saving from Beacon.
                  </div>
                )}
                <div className="rounded-xl border border-[#390d58]/10 bg-[#390d58]/5 p-4 text-sm text-muted-foreground space-y-1">
                  <p>Last backup: {htaccessMeta.backup_at || 'none available'}</p>
                  <p>WordPress rewrite markers present: {htaccessMeta.has_wp_rewrites ? 'Yes' : 'No'}</p>
                  <p>Pending diff summary: {diffSummary}</p>
                </div>
              </>
            )}

            {isRobots && robotsMeta && (
              <div className="rounded-xl border border-[#390d58]/10 bg-[#390d58]/5 p-4 text-sm text-muted-foreground space-y-2">
                <p>Effective source: {robotsMeta.effective_source === 'physical' ? 'physical robots.txt file' : 'virtual WordPress output'}</p>
                <p>Served URL: {robotsMeta.served_url}</p>
                <Button size="sm" variant="outline" onClick={handleRobotsTest}>Test live response</Button>
              </div>
            )}

            {robotsTest && (
              <div className="rounded-xl border border-[#390d58]/10 bg-[#390d58]/[0.02] p-4">
                <p className="text-sm font-medium text-[#390d58]">Live robots.txt response</p>
                <p className="mt-1 text-xs text-muted-foreground">{robotsTest.status} · {robotsTest.url}</p>
                <pre className="mt-3 overflow-x-auto rounded-lg bg-white p-3 text-xs">{robotsTest.content || robotsTest.message}</pre>
              </div>
            )}

            <Textarea
              rows={16}
              value={content}
              onChange={e => setContent(e.target.value)}
              disabled={!isRobots && !htaccessMeta?.writable}
              className="font-mono text-xs border-[#390d58]/20 focus-visible:ring-[#390d58]"
            />

            {!isRobots && lineDiff.length > 0 && (
              <div className="rounded-xl border border-[#390d58]/10 bg-[#390d58]/[0.02] p-4">
                <p className="text-sm font-medium text-[#390d58]">Changed lines</p>
                <div className="mt-3 space-y-2 text-xs font-mono">
                  {lineDiff.slice(0, 20).map((line, index) => (
                    <div key={index} className="grid gap-2 sm:grid-cols-2">
                      <div className="rounded bg-red-50 px-2 py-1 text-red-700">{line.before || '[empty]'}</div>
                      <div className="rounded bg-emerald-50 px-2 py-1 text-emerald-700">{line.after || '[empty]'}</div>
                    </div>
                  ))}
                </div>
              </div>
            )}

            {!isRobots && (
              <label className="flex items-center gap-2 text-sm text-muted-foreground">
                <input type="checkbox" checked={confirmWrite} onChange={e => setConfirmWrite(e.target.checked)} />
                I have reviewed the line changes and want to write this .htaccess file.
              </label>
            )}

            <div className="flex items-center justify-between">
              {msg && <p className={`text-sm ${msg.ok ? 'text-emerald-600' : 'text-red-600'}`}>{msg.text}</p>}
              <div className="ml-auto flex gap-2">
                <Button
                  variant="outline"
                  onClick={handleResetOrRestore}
                  disabled={saving || (!isRobots && !htaccessMeta?.backup_content)}
                  className="gap-2"
                >
                  <RotateCcw className="h-4 w-4" />
                  {isRobots ? 'Reset Default' : 'Restore Backup'}
                </Button>
                <Button
                  onClick={handleSave}
                  disabled={saving || (!isRobots && (!htaccessMeta?.writable || !confirmWrite))}
                  className="bg-[#390d58] hover:bg-[#4a1170] text-white gap-2"
                >
                  {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
                  Save
                </Button>
              </div>
            </div>
          </>
        )}
      </CardContent>
    </Card>
  )
}
