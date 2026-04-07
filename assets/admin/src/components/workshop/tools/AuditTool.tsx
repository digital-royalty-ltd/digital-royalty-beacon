import { useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { CalendarClock, CheckSquare, Download, ExternalLink, Loader2, Pencil, Save, Search, Trash2, Unlink } from 'lucide-react'
import { api, ApiError } from '@/lib/api'

type AuditItem = Record<string, unknown>
type AuditResult = {
  total: number
  items: AuditItem[]
  reclaimable_size_total?: string
  exclusions?: string[]
}

const ISSUE_LABELS: Record<string, string> = {
  title_too_short: 'Title too short',
  title_too_long: 'Title too long',
  no_meta_description: 'No meta description',
  meta_too_short: 'Meta too short',
  meta_too_long: 'Meta too long',
  duplicate_title: 'Duplicate title',
  duplicate_meta_description: 'Duplicate meta',
  missing_h1: 'Missing H1',
  multiple_h1: 'Multiple H1s',
  skipped_heading_level: 'Skipped heading level',
  empty_heading: 'Empty heading',
}

const CONFIGS: Record<string, { slug: string; title: string; description: string }> = {
  'meta-auditor': { slug: 'meta', title: 'Meta Auditor', description: 'Audit titles and descriptions, then fix them inline.' },
  'heading-structure': { slug: 'headings', title: 'Heading Structure', description: 'Review structural issues and heading outlines before editing.' },
  'orphaned-content': { slug: 'orphans', title: 'Orphaned Content', description: 'Find isolated URLs with enough context to prioritise fixes.' },
  'duplicate-titles': { slug: 'duplicates', title: 'Duplicate Titles', description: 'Group conflicting titles by cluster so editors can resolve them quickly.' },
  'image-alt-auditor': { slug: 'image-alt', title: 'Image Alt Auditor', description: 'Audit media-library and in-content image alt gaps from one screen.' },
  'unused-media': { slug: 'unused-media', title: 'Unused Media', description: 'Review likely-unused media, exclusions, and reclaimable space before deleting.' },
  'noindex-checker': { slug: 'noindex', title: 'Noindex Checker', description: 'See where noindex comes from and toggle post-level directives.' },
  'redirect-chains': { slug: 'redirect-chains', title: 'Redirect Chains', description: 'Collapse multi-hop redirect paths when it is safe to do so.' },
  'broken-links': { slug: 'broken-links', title: 'Broken Links', description: 'Turn broken links into active remediation work instead of passive warnings.' },
}

function openIcon(url?: unknown) {
  return typeof url === 'string' && url !== '' ? (
    <a href={url} target="_blank" rel="noreferrer" className="text-[#390d58] hover:opacity-70">
      <ExternalLink className="h-3.5 w-3.5" />
    </a>
  ) : null
}

function issueBadges(item: AuditItem) {
  const issues = Array.isArray(item.issues) ? item.issues as string[] : []
  return (
    <div className="flex flex-wrap gap-1">
      {issues.map(issue => (
        <Badge key={issue} variant="outline" className="border-amber-300 bg-amber-50 text-[10px] text-amber-700">
          {ISSUE_LABELS[issue] ?? issue}
        </Badge>
      ))}
    </div>
  )
}

function toCsv(items: AuditItem[]) {
  if (items.length === 0) return ''
  const keys = Array.from(new Set(items.flatMap(item => Object.keys(item).filter(key => ['image_tag', 'anchor_html'].includes(key) === false))))
  const rows = items.map(item => keys.map(key => {
    const value = item[key]
    const text = Array.isArray(value) ? JSON.stringify(value) : String(value ?? '')
    return `"${text.replaceAll('"', '""')}"`
  }).join(','))
  return [keys.join(','), ...rows].join('\n')
}

export function AuditTool({ toolSlug }: { toolSlug: string }) {
  const config = CONFIGS[toolSlug]
  const [result, setResult] = useState<AuditResult | null>(null)
  const [scanning, setScanning] = useState(false)
  const [saving, setSaving] = useState<string | null>(null)
  const [message, setMessage] = useState<string | null>(null)
  const [search, setSearch] = useState('')
  const [filter, setFilter] = useState('all')
  const [drafts, setDrafts] = useState<Record<string, string>>({})
  const [unusedPatterns, setUnusedPatterns] = useState('')
  const [bulkSelected, setBulkSelected] = useState<Set<string>>(new Set())
  const [bulkSaving, setBulkSaving] = useState(false)
  const [scheduleFreq, setScheduleFreq] = useState('off')
  const [scheduleMsg, setScheduleMsg] = useState<string | null>(null)

  if (!config) {
    return <p className="text-sm text-muted-foreground">Unknown audit tool: {toolSlug}</p>
  }

  const scan = async () => {
    setScanning(true)
    setMessage(null)
    try {
      const data = await api.get<AuditResult>(`/workshop/audit/${config.slug}`)
      setResult(data)
      if (toolSlug === 'unused-media') {
        setUnusedPatterns((data.exclusions ?? []).join('\n'))
      }
    } catch (error) {
      setMessage(error instanceof ApiError ? error.message : 'Scan failed.')
    } finally {
      setScanning(false)
    }
  }

  const act = async (key: string, fn: () => Promise<unknown>) => {
    setSaving(key)
    setMessage(null)
    try {
      await fn()
      await scan()
    } catch (error) {
      setMessage(error instanceof ApiError ? error.message : 'Action failed.')
    } finally {
      setSaving(null)
    }
  }

  const toggleBulk = (id: string) => {
    setBulkSelected(prev => {
      const next = new Set(prev)
      if (next.has(id)) next.delete(id); else next.add(id)
      return next
    })
  }

  const toggleBulkAll = () => {
    if (bulkSelected.size === filteredItems.length) {
      setBulkSelected(new Set())
    } else {
      setBulkSelected(new Set(filteredItems.map(item => String(item.id))))
    }
  }

  const bulkSave = async () => {
    if (bulkSelected.size === 0) return
    setBulkSaving(true)
    setMessage(null)
    try {
      const items = filteredItems.filter(item => bulkSelected.has(String(item.id)))
      if (toolSlug === 'meta-auditor') {
        await api.post('/workshop/audit/meta/bulk-update', {
          updates: items.map(item => {
            const id = String(item.id)
            return {
              post_id: item.id,
              post_title: drafts[`${id}:title`] ?? item.title,
              meta_description: drafts[`${id}:meta`] ?? item.meta_description,
            }
          }),
        })
      } else if (toolSlug === 'duplicate-titles') {
        await api.post('/workshop/audit/duplicates/bulk-rename', {
          updates: items.flatMap(item =>
            ((item.conflicts as AuditItem[] | undefined) ?? [])
              .filter(conflict => drafts[`dup:${conflict.id}`] != null)
              .map(conflict => ({
                post_id: conflict.id,
                post_title: drafts[`dup:${conflict.id}`],
              })),
          ),
        })
      }
      setBulkSelected(new Set())
      await scan()
    } catch (error) {
      setMessage(error instanceof ApiError ? error.message : 'Bulk save failed.')
    } finally {
      setBulkSaving(false)
    }
  }

  const saveSchedule = async () => {
    setScheduleMsg(null)
    try {
      await api.post('/workshop/audit/broken-links/schedule', { frequency: scheduleFreq })
      setScheduleMsg(scheduleFreq === 'off' ? 'Scheduled scan disabled.' : `Scan scheduled: ${scheduleFreq}.`)
    } catch {
      setScheduleMsg('Failed to save schedule.')
    }
  }

  const exportRows = () => {
    if (!result || result.items.length === 0) return
    const csv = toCsv(filteredItems)
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `${config.slug}.csv`
    link.click()
    URL.revokeObjectURL(url)
  }

  const filteredItems = (result?.items ?? []).filter(item => {
    const haystack = JSON.stringify(item).toLowerCase()
    if (search && haystack.includes(search.toLowerCase()) === false) return false

    if (filter === 'all') return true
    if (toolSlug === 'meta-auditor' || toolSlug === 'heading-structure') {
      return Array.isArray(item.issues) && (item.issues as string[]).includes(filter)
    }
    if (toolSlug === 'image-alt-auditor') {
      return String(item.context ?? '') === filter
    }
    if (toolSlug === 'duplicate-titles') {
      return ((item.conflicts as AuditItem[] | undefined) ?? []).some(conflict => String(conflict.post_type ?? conflict.post_status ?? '') === filter)
    }
    if (toolSlug === 'broken-links') {
      return String(item.link_type ?? '') === filter
    }
    if (toolSlug === 'noindex-checker') {
      return filter === 'priority'
        ? Boolean(item.is_homepage) || Boolean(item.is_nav_linked)
        : String(item.primary_source ?? '') === filter
    }
    return String(item.post_type ?? '') === filter
  })

  const options = (() => {
    if (!result) return []
    if (toolSlug === 'meta-auditor' || toolSlug === 'heading-structure') {
      return Array.from(new Set(result.items.flatMap(item => Array.isArray(item.issues) ? item.issues as string[] : [])))
    }
    if (toolSlug === 'image-alt-auditor') {
      return ['library', 'content']
    }
    if (toolSlug === 'broken-links') {
      return ['internal', 'external']
    }
    if (toolSlug === 'noindex-checker') {
      return ['priority', ...Array.from(new Set(result.items.map(item => String(item.primary_source ?? ''))))]
    }
    return Array.from(new Set(result.items.map(item => String(item.post_type ?? '')).filter(Boolean)))
  })()

  const renderRows = () => {
    if (toolSlug === 'meta-auditor') {
      return filteredItems.map(item => {
        const id = String(item.id)
        const titleKey = `${id}:title`
        const metaKey = `${id}:meta`
        return (
          <TableRow key={id}>
            <TableCell className="w-8">
              <input type="checkbox" checked={bulkSelected.has(id)} onChange={() => toggleBulk(id)} />
            </TableCell>
            <TableCell className="space-y-2">
              <Input
                value={drafts[titleKey] ?? String(item.title ?? '')}
                onChange={e => setDrafts(current => ({ ...current, [titleKey]: e.target.value }))}
                className="border-[#390d58]/20"
              />
              <Textarea
                rows={3}
                value={drafts[metaKey] ?? String(item.meta_description ?? '')}
                onChange={e => setDrafts(current => ({ ...current, [metaKey]: e.target.value }))}
                className="border-[#390d58]/20"
              />
            </TableCell>
            <TableCell>{issueBadges(item)}</TableCell>
            <TableCell className="text-xs text-muted-foreground">{String(item.title_length ?? 0)} / {String(item.meta_length ?? 0)}</TableCell>
            <TableCell className="text-xs text-muted-foreground">{String(item.meta_source ?? 'none')}</TableCell>
            <TableCell className="space-x-2 text-right">
              <Button size="sm" variant="outline" onClick={() => act(`meta:${id}`, () => api.post('/workshop/audit/meta/update', { post_id: item.id, post_title: drafts[titleKey] ?? item.title, meta_description: drafts[metaKey] ?? item.meta_description }))}>
                {saving === `meta:${id}` ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Save className="h-3.5 w-3.5" />}
              </Button>
              {openIcon(item.edit_url)}
            </TableCell>
          </TableRow>
        )
      })
    }

    if (toolSlug === 'heading-structure') {
      return filteredItems.map(item => (
        <TableRow key={String(item.id)}>
          <TableCell>
            <div className="font-medium">{String(item.title ?? '')}</div>
            <div className="mt-2 space-y-1 text-xs text-muted-foreground">
              {((item.outline as AuditItem[] | undefined) ?? []).map((node, index) => (
                <div key={index} style={{ paddingLeft: `${(Number(node.level ?? 1) - 1) * 12}px` }}>
                  {`H${String(node.level ?? '')}: ${String(node.text ?? '(empty)')}`}
                </div>
              ))}
            </div>
          </TableCell>
          <TableCell>{issueBadges(item)}</TableCell>
          <TableCell>{openIcon(item.edit_url)}</TableCell>
        </TableRow>
      ))
    }

    if (toolSlug === 'orphaned-content') {
      return filteredItems.map(item => (
        <TableRow key={String(item.id)}>
          <TableCell>
            <div className="font-medium">{String(item.title ?? '')}</div>
            <div className="mt-1 text-xs text-muted-foreground">{String(item.modified_at ?? '')} · {String(item.word_count ?? 0)} words</div>
            <div className="mt-2 text-xs text-muted-foreground">{((item.suggested_actions as string[] | undefined) ?? []).join(' ')}</div>
          </TableCell>
          <TableCell>{String(item.post_type ?? '')}</TableCell>
          <TableCell className="text-right">
            <Button size="sm" variant="outline" onClick={() => act(`orphan:${item.id}`, () => api.post('/workshop/audit/orphans/exceptions', { post_id: item.id, ignored: !item.ignored }))}>
              {Boolean(item.ignored) ? 'Unignore' : 'Ignore'}
            </Button>
            <span className="ml-2">{openIcon(item.edit_url)}</span>
          </TableCell>
        </TableRow>
      ))
    }

    if (toolSlug === 'duplicate-titles') {
      return filteredItems.map((item, index) => (
        <TableRow key={index}>
          <TableCell className="w-8">
            <input type="checkbox" checked={bulkSelected.has(String(index))} onChange={() => toggleBulk(String(index))} />
          </TableCell>
          <TableCell className="font-medium">{String(item.title ?? '')}</TableCell>
          <TableCell><Badge variant="outline">{String(item.count ?? 0)}</Badge></TableCell>
          <TableCell>
            <div className="space-y-2 text-xs">
              {((item.conflicts as AuditItem[] | undefined) ?? []).map(conflict => {
                const draftKey = `dup:${conflict.id}`
                return (
                  <div key={String(conflict.id)} className="flex items-center gap-2">
                    <span className="shrink-0 text-muted-foreground">#{String(conflict.id)} · {String(conflict.post_type ?? '')}</span>
                    <Input
                      value={drafts[draftKey] ?? String(conflict.post_title ?? item.title ?? '')}
                      onChange={e => setDrafts(current => ({ ...current, [draftKey]: e.target.value }))}
                      className="h-7 border-[#390d58]/20 text-xs"
                    />
                    <Button size="sm" variant="outline" className="h-7 shrink-0" onClick={() => act(`dup:${conflict.id}`, () => api.post('/workshop/audit/duplicates/rename', { post_id: conflict.id, post_title: drafts[draftKey] ?? conflict.post_title ?? item.title }))}>
                      {saving === `dup:${conflict.id}` ? <Loader2 className="h-3 w-3 animate-spin" /> : <Pencil className="h-3 w-3" />}
                    </Button>
                    <a href={String(conflict.edit_url ?? '#')} target="_blank" rel="noreferrer" className="text-[#390d58]">
                      <ExternalLink className="h-3 w-3" />
                    </a>
                  </div>
                )
              })}
            </div>
          </TableCell>
        </TableRow>
      ))
    }

    if (toolSlug === 'image-alt-auditor') {
      return filteredItems.map(item => {
        const key = `${item.context}:${item.id ?? item.attachment_id}`
        return (
          <TableRow key={key}>
            <TableCell className="w-16">{typeof item.thumbnail_url === 'string' ? <img src={item.thumbnail_url} alt="" className="h-12 w-12 rounded object-cover" /> : null}</TableCell>
            <TableCell>
              <div className="font-medium">{String(item.filename ?? '')}</div>
              <div className="text-xs text-muted-foreground">{String(item.context ?? '')} · {String(item.title ?? '')}</div>
            </TableCell>
            <TableCell>
              <Input value={drafts[key] ?? ''} onChange={e => setDrafts(current => ({ ...current, [key]: e.target.value }))} className="border-[#390d58]/20" />
            </TableCell>
            <TableCell className="text-right">
              <Button size="sm" variant="outline" onClick={() => act(`alt:${key}`, () => api.post('/workshop/audit/image-alt/update', { context: item.context, attachment_id: item.attachment_id, post_id: item.post_id, image_src: item.image_src, alt_text: drafts[key] ?? '' }))}>
                {saving === `alt:${key}` ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Save className="h-3.5 w-3.5" />}
              </Button>
              <span className="ml-2">{openIcon(item.edit_url)}</span>
            </TableCell>
          </TableRow>
        )
      })
    }

    if (toolSlug === 'unused-media') {
      return filteredItems.map(item => (
        <TableRow key={String(item.id)}>
          <TableCell className="w-16">{typeof item.thumbnail_url === 'string' ? <img src={item.thumbnail_url} alt="" className="h-12 w-12 rounded object-cover" /> : null}</TableCell>
          <TableCell>
            <div className="font-medium">{String(item.title ?? '')}</div>
            <div className="text-xs text-muted-foreground">{String(item.filename ?? '')}</div>
          </TableCell>
          <TableCell>{String(item.size ?? '')}</TableCell>
          <TableCell className="text-right">
            <Button size="sm" variant="outline" onClick={() => {
              if (window.confirm(`Delete ${String(item.filename ?? item.title ?? 'this file')} permanently?`) === false) return
              void act(`media:${item.id}`, () => api.post('/workshop/audit/unused-media/delete', { attachment_id: item.id, confirm: true }))
            }}>
              {saving === `media:${item.id}` ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Trash2 className="h-3.5 w-3.5" />}
            </Button>
            <span className="ml-2">{openIcon(item.edit_url)}</span>
          </TableCell>
        </TableRow>
      ))
    }

    if (toolSlug === 'noindex-checker') {
      return filteredItems.map(item => (
        <TableRow key={String(item.id)}>
          <TableCell>
            <div className="font-medium">{String(item.title ?? '')}</div>
            <div className="mt-1 flex flex-wrap gap-1">
              {Boolean(item.is_homepage) && <Badge variant="outline" className="text-[10px]">Homepage</Badge>}
              {Boolean(item.is_nav_linked) && <Badge variant="outline" className="text-[10px]">Nav linked</Badge>}
            </div>
          </TableCell>
          <TableCell className="text-xs text-muted-foreground">
            {((item.sources as AuditItem[] | undefined) ?? []).map((source, index) => (
              <div key={index}>{String(source.label ?? '')}: {String(source.value ?? '')}</div>
            ))}
          </TableCell>
          <TableCell className="text-right">
            {Boolean(item.can_toggle) && (
              <Button size="sm" variant="outline" onClick={() => act(`noindex:${item.id}`, () => api.post('/workshop/audit/noindex/update', { post_id: item.id, enabled: false }))}>
                Clear Noindex
              </Button>
            )}
            <span className="ml-2">{openIcon(item.edit_url)}</span>
          </TableCell>
        </TableRow>
      ))
    }

    if (toolSlug === 'redirect-chains') {
      return filteredItems.map((item, index) => (
        <TableRow key={index}>
          <TableCell><Badge variant="outline" className={String(item.type) === 'loop' ? 'border-red-300 bg-red-50 text-red-700' : 'border-amber-300 bg-amber-50 text-amber-700'}>{String(item.type ?? '')}</Badge></TableCell>
          <TableCell>{String(item.length ?? '')}</TableCell>
          <TableCell className="text-xs font-mono text-muted-foreground">{((item.chain as string[] | undefined) ?? []).join(' -> ')}</TableCell>
          <TableCell className="text-right">
            {Boolean(item.can_collapse) && <Button size="sm" variant="outline" onClick={() => act(`chain:${item.redirect_id}`, () => api.post('/workshop/audit/redirect-chains/collapse', { redirect_id: item.redirect_id, final_url: item.final_url }))}>Collapse</Button>}
          </TableCell>
        </TableRow>
      ))
    }

    if (toolSlug === 'broken-links') {
      return filteredItems.map(item => {
        const key = `broken:${item.signature}`
        return (
          <TableRow key={String(item.signature)}>
            <TableCell>
              <div className="font-medium">{String(item.title ?? '')}</div>
              <div className="text-xs text-muted-foreground">{String(item.anchor_text ?? '')}</div>
              <div className="text-xs text-muted-foreground">{String(item.last_checked ?? '')}</div>
            </TableCell>
            <TableCell className="text-xs">
              <div>{String(item.broken_url ?? '')}</div>
              <div className="mt-1 text-muted-foreground">{String(item.http_label ?? '')}</div>
            </TableCell>
            <TableCell>
              <Input value={drafts[key] ?? ''} onChange={e => setDrafts(current => ({ ...current, [key]: e.target.value }))} placeholder="Replacement URL" className="border-[#390d58]/20" />
            </TableCell>
            <TableCell className="space-x-2 text-right">
              <Button size="sm" variant="outline" onClick={() => act(`broken-update:${item.signature}`, () => api.post('/workshop/audit/broken-links/update', { post_id: item.post_id, original_url: item.broken_url, replacement_url: drafts[key] ?? '' }))}>
                {saving === `broken-update:${item.signature}` ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Save className="h-3.5 w-3.5" />}
              </Button>
              <Button size="sm" variant="outline" onClick={() => act(`broken-unlink:${item.signature}`, () => api.post('/workshop/audit/broken-links/unlink', { post_id: item.post_id, anchor_html: item.anchor_html }))}>
                <Unlink className="h-3.5 w-3.5" />
              </Button>
              <Button size="sm" variant="outline" onClick={() => act(`broken-dismiss:${item.signature}`, () => api.post('/workshop/audit/broken-links/dismiss', { signature: item.signature, dismissed: true }))}>
                Dismiss
              </Button>
              {openIcon(item.edit_url)}
            </TableCell>
          </TableRow>
        )
      })
    }

    return null
  }

  return (
    <Card className="overflow-hidden border-[#390d58]/20">
      <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
      <CardHeader>
        <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <CardTitle className="text-lg text-[#390d58]">{config.title}</CardTitle>
            <CardDescription>
              {config.description}
              {result && ` ${result.total} issue${result.total !== 1 ? 's' : ''} found.`}
            </CardDescription>
          </div>
          <div className="flex flex-wrap gap-2">
            {result && result.items.length > 0 && (
              <Button size="sm" variant="outline" className="gap-2" onClick={exportRows}>
                <Download className="h-3.5 w-3.5" /> Export CSV
              </Button>
            )}
            <Button size="sm" onClick={scan} disabled={scanning} className="gap-2 bg-[#390d58] text-white hover:bg-[#4a1170]">
              {scanning ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Search className="h-3.5 w-3.5" />}
              {scanning ? 'Scanning...' : result ? 'Re-scan' : 'Scan Now'}
            </Button>
          </div>
        </div>
      </CardHeader>

      {result && (
        <CardContent className="space-y-4">
          {toolSlug === 'broken-links' && (
            <div className="flex flex-wrap items-center gap-3 rounded-xl border border-[#390d58]/10 bg-[#390d58]/[0.02] p-4">
              <CalendarClock className="h-4 w-4 text-[#390d58]" />
              <span className="text-sm font-medium text-[#390d58]">Scheduled scan</span>
              <select value={scheduleFreq} onChange={e => setScheduleFreq(e.target.value)} className="h-8 rounded-md border border-[#390d58]/20 bg-white px-2 text-sm">
                <option value="off">Off</option>
                <option value="daily">Daily</option>
                <option value="weekly">Weekly</option>
              </select>
              <Button size="sm" variant="outline" onClick={saveSchedule}>Save schedule</Button>
              {scheduleMsg && <span className="text-xs text-muted-foreground">{scheduleMsg}</span>}
            </div>
          )}

          {(toolSlug === 'meta-auditor' || toolSlug === 'duplicate-titles') && bulkSelected.size > 0 && (
            <div className="flex items-center gap-3 rounded-xl border border-[#390d58]/10 bg-[#390d58]/[0.02] p-3">
              <CheckSquare className="h-4 w-4 text-[#390d58]" />
              <span className="text-sm text-[#390d58]">{bulkSelected.size} selected</span>
              <Button size="sm" onClick={bulkSave} disabled={bulkSaving} className="gap-1 bg-[#390d58] hover:bg-[#4a1170] text-white">
                {bulkSaving ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Save className="h-3.5 w-3.5" />}
                Bulk Save
              </Button>
              <Button size="sm" variant="outline" onClick={() => setBulkSelected(new Set())}>Clear</Button>
              <Button size="sm" variant="outline" onClick={toggleBulkAll}>
                {bulkSelected.size === filteredItems.length ? 'Deselect all' : 'Select all'}
              </Button>
            </div>
          )}

          {toolSlug === 'unused-media' && (
            <div className="grid gap-4 rounded-2xl border border-[#390d58]/10 bg-[#390d58]/[0.02] p-4 lg:grid-cols-[1fr_auto]">
              <div>
                <div className="text-sm font-medium text-[#390d58]">Estimated reclaimable space: {result.reclaimable_size_total ?? '0 B'}</div>
                <div className="mt-1 text-xs text-muted-foreground">Add one exclusion pattern per line to protect files, folders, or prefixes from cleanup.</div>
                <Textarea rows={4} value={unusedPatterns} onChange={e => setUnusedPatterns(e.target.value)} className="mt-3 border-[#390d58]/20" />
              </div>
              <div className="flex items-end">
                <Button variant="outline" onClick={() => act('unused-patterns', () => api.post('/workshop/audit/unused-media/exclusions', { patterns: unusedPatterns.split('\n').map(value => value.trim()).filter(Boolean) }))}>
                  {saving === 'unused-patterns' ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : 'Save exclusions'}
                </Button>
              </div>
            </div>
          )}

          <div className="flex flex-col gap-3 lg:flex-row">
            <Input value={search} onChange={e => setSearch(e.target.value)} placeholder="Search results" className="border-[#390d58]/20 lg:max-w-sm" />
            {options.length > 0 && (
              <select value={filter} onChange={e => setFilter(e.target.value)} className="h-10 rounded-md border border-[#390d58]/20 bg-white px-3 text-sm">
                <option value="all">All</option>
                {options.map(option => <option key={option} value={option}>{option}</option>)}
              </select>
            )}
          </div>

          {message && <p className="text-sm text-red-600">{message}</p>}

          {filteredItems.length === 0 ? (
            <p className="py-8 text-center text-sm text-muted-foreground">No matching issues found.</p>
          ) : (
            <div className="overflow-hidden rounded-xl border border-[#390d58]/10">
              <Table>
                <TableHeader>
                  <TableRow className="bg-[#390d58]/5 hover:bg-[#390d58]/5">
                    <TableHead className="font-semibold text-[#390d58]">Item</TableHead>
                    <TableHead className="font-semibold text-[#390d58]">Details</TableHead>
                    <TableHead className="font-semibold text-[#390d58]">Context</TableHead>
                    <TableHead className="text-right font-semibold text-[#390d58]">Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>{renderRows()}</TableBody>
              </Table>
            </div>
          )}
        </CardContent>
      )}
    </Card>
  )
}
