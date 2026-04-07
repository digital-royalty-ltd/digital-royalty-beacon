import { useEffect, useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Separator } from '@/components/ui/separator'
import { Loader2, Trash2, Eye, Save } from 'lucide-react'
import { api, ApiError } from '@/lib/api'

interface CountsResponse {
  counts: Record<string, number>
  estimated_savings: Record<string, number>
  table_sizes: Record<string, number>
  settings: {
    enabled: boolean
    frequency: 'daily' | 'weekly'
    types: string[]
    next_run: string | null
  }
}

const items: { key: string; label: string }[] = [
  { key: 'revisions', label: 'Post Revisions' },
  { key: 'auto_drafts', label: 'Auto-drafts' },
  { key: 'trashed_posts', label: 'Trashed Posts' },
  { key: 'spam_comments', label: 'Spam Comments' },
  { key: 'trashed_comments', label: 'Trashed Comments' },
  { key: 'orphan_postmeta', label: 'Orphaned Post Meta' },
  { key: 'orphan_commentmeta', label: 'Orphaned Comment Meta' },
  { key: 'transients', label: 'Expired Transients' },
]

function formatBytes(bytes: number) {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

export function DatabaseCleanupTool() {
  const [counts, setCounts] = useState<Record<string, number>>({})
  const [estimatedSavings, setEstimatedSavings] = useState<Record<string, number>>({})
  const [tableSizes, setTableSizes] = useState<Record<string, number>>({})
  const [scheduleEnabled, setScheduleEnabled] = useState(false)
  const [scheduleFrequency, setScheduleFrequency] = useState<'daily' | 'weekly'>('weekly')
  const [scheduleTypes, setScheduleTypes] = useState<string[]>([])
  const [nextRun, setNextRun] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)
  const [running, setRunning] = useState<string | null>(null)
  const [previewing, setPreviewing] = useState<string | null>(null)
  const [savingSchedule, setSavingSchedule] = useState(false)
  const [msg, setMsg] = useState<{ ok: boolean; text: string } | null>(null)

  const fetchCounts = () => {
    setLoading(true)
    api.get<CountsResponse>('/workshop/database-cleanup')
      .then(result => {
        setCounts(result.counts)
        setEstimatedSavings(result.estimated_savings)
        setTableSizes(result.table_sizes)
        setScheduleEnabled(result.settings.enabled)
        setScheduleFrequency(result.settings.frequency)
        setScheduleTypes(result.settings.types)
        setNextRun(result.settings.next_run)
      })
      .finally(() => setLoading(false))
  }

  useEffect(() => { fetchCounts() }, [])

  const handlePreview = async (key: string) => {
    setPreviewing(key)
    setMsg(null)
    try {
      const result = await api.post<{ matched: number }>('/workshop/database-cleanup', { type: key, dry_run: true })
      setMsg({ ok: true, text: `${result.matched} items would be removed from ${items.find(item => item.key === key)?.label}.` })
    } catch (error) {
      setMsg({ ok: false, text: error instanceof ApiError ? error.message : 'Preview failed.' })
    } finally {
      setPreviewing(null)
    }
  }

  const handleClean = async (key: string) => {
    const count = counts[key] ?? 0
    if (!confirm(`Delete ${count} items from ${items.find(item => item.key === key)?.label}?`)) return

    setRunning(key)
    setMsg(null)
    try {
      const res = await api.post<{ ok: boolean; deleted: number; reclaimed_bytes?: number }>('/workshop/database-cleanup', { type: key, confirm: true })
      const reclaimedNote = res.reclaimed_bytes != null && res.reclaimed_bytes > 0 ? ` Reclaimed ${formatBytes(res.reclaimed_bytes)}.` : ''
      setMsg({ ok: true, text: `Removed ${res.deleted} items.${reclaimedNote}` })
      fetchCounts()
    } catch (error) {
      setMsg({ ok: false, text: error instanceof ApiError ? error.message : 'Cleanup failed.' })
    } finally {
      setRunning(null)
    }
  }

  const saveSchedule = async () => {
    setSavingSchedule(true)
    try {
      const result = await api.post<{ next_run: string | null }>('/workshop/database-cleanup/settings', {
        enabled: scheduleEnabled,
        frequency: scheduleFrequency,
        types: scheduleTypes,
      })
      setNextRun(result.next_run)
      setMsg({ ok: true, text: 'Cleanup schedule saved.' })
    } catch (error) {
      setMsg({ ok: false, text: error instanceof ApiError ? error.message : 'Failed to save schedule.' })
    } finally {
      setSavingSchedule(false)
    }
  }

  return (
    <Card className="border-[#390d58]/20 overflow-hidden">
      <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
      <CardHeader>
        <CardTitle className="text-lg text-[#390d58]">Database Cleanup</CardTitle>
        <CardDescription>Preview each cleanup category before deletion, then optionally schedule a recurring cleanup plan.</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        {msg && (
          <div className={`rounded-lg border p-3 text-sm ${msg.ok ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-red-200 bg-red-50 text-red-700'}`}>
            {msg.text}
          </div>
        )}

        <div className="grid gap-4 lg:grid-cols-[1fr_320px]">
          <div className="rounded-xl border border-[#390d58]/10 overflow-hidden">
            {items.map((item, idx) => (
              <div key={item.key}>
                <div className="flex flex-col gap-3 bg-[#390d58]/[0.02] px-4 py-3 hover:bg-[#390d58]/5 lg:flex-row lg:items-center lg:justify-between">
                  <div>
                    <span className="text-sm">{item.label}</span>
                    <p className="text-xs text-muted-foreground">
                      Estimated savings: {formatBytes(estimatedSavings[item.key] ?? 0)}
                    </p>
                  </div>
                  <div className="flex items-center gap-3">
                    {loading ? (
                      <span className="w-12 text-right text-xs text-muted-foreground">...</span>
                    ) : (
                      <span className={`font-mono text-sm font-medium ${(counts[item.key] ?? 0) > 0 ? 'text-amber-600' : 'text-muted-foreground'}`}>
                        {(counts[item.key] ?? 0).toLocaleString()}
                      </span>
                    )}
                    <Button size="sm" variant="outline" disabled={previewing === item.key || loading} onClick={() => handlePreview(item.key)} className="h-7 gap-1 text-xs">
                      {previewing === item.key ? <Loader2 className="h-3 w-3 animate-spin" /> : <Eye className="h-3 w-3" />}
                      Preview
                    </Button>
                    <Button size="sm" variant="outline" disabled={running === item.key || loading || (counts[item.key] ?? 0) === 0} onClick={() => handleClean(item.key)} className="h-7 gap-1 text-xs text-red-600 hover:border-red-300 hover:bg-red-50 hover:text-red-700">
                      {running === item.key ? <Loader2 className="h-3 w-3 animate-spin" /> : <Trash2 className="h-3 w-3" />}
                      Clean
                    </Button>
                  </div>
                </div>
                {idx < items.length - 1 && <Separator className="bg-[#390d58]/10" />}
              </div>
            ))}
          </div>

          <div className="space-y-4">
            <div className="rounded-xl border border-[#390d58]/20 bg-[#390d58]/[0.02] p-4 space-y-3">
              <p className="text-sm font-medium text-[#390d58]">Scheduled cleanup</p>
              <label className="flex items-center gap-2 text-sm text-muted-foreground">
                <input type="checkbox" checked={scheduleEnabled} onChange={e => setScheduleEnabled(e.target.checked)} />
                Enable recurring cleanup
              </label>
              <select value={scheduleFrequency} onChange={e => setScheduleFrequency(e.target.value as 'daily' | 'weekly')} className="w-full rounded-lg border border-[#390d58]/20 px-3 py-2 text-sm">
                <option value="daily">Daily</option>
                <option value="weekly">Weekly</option>
              </select>
              <div className="space-y-2">
                {items.map(item => (
                  <label key={item.key} className="flex items-center gap-2 text-xs text-muted-foreground">
                    <input
                      type="checkbox"
                      checked={scheduleTypes.includes(item.key)}
                      onChange={() => setScheduleTypes(current => current.includes(item.key) ? current.filter(value => value !== item.key) : [...current, item.key])}
                    />
                    {item.label}
                  </label>
                ))}
              </div>
              <p className="text-xs text-muted-foreground">{nextRun ? `Next run: ${nextRun}` : 'No scheduled cleanup is currently queued.'}</p>
              <Button size="sm" onClick={saveSchedule} disabled={savingSchedule} className="gap-2 bg-[#390d58] text-white hover:bg-[#4a1170]">
                {savingSchedule ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Save className="h-3.5 w-3.5" />}
                Save Schedule
              </Button>
            </div>

            <div className="rounded-xl border border-[#390d58]/20 bg-[#390d58]/[0.02] p-4 space-y-2">
              <p className="text-sm font-medium text-[#390d58]">Measured table footprint</p>
              {Object.entries(tableSizes).map(([table, size]) => (
                <div key={table} className="flex items-center justify-between text-xs text-muted-foreground">
                  <span>{table}</span>
                  <span>{formatBytes(size)}</span>
                </div>
              ))}
            </div>
          </div>
        </div>
      </CardContent>
    </Card>
  )
}
