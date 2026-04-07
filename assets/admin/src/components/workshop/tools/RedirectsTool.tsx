import { useEffect, useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Loader2, Plus, Pencil, Trash2, Check, X, Download, Upload, Search, FlaskConical } from 'lucide-react'
import { api, ApiError } from '@/lib/api'

interface RedirectRow {
  id: number
  source_path: string
  target_url: string
  redirect_type: number
  regex_enabled: number
  is_active: number
  hit_count: number
  last_accessed_at: string | null
  created_at: string
}

interface RedirectCondition {
  type: 'user_agent' | 'referrer' | 'query_string'
  operator: 'contains' | 'not_contains' | 'equals'
  value: string
}

interface EditState {
  source_path: string
  target_url: string
  redirect_type: number
  regex_enabled: boolean
  is_active: boolean
  conditions: RedirectCondition[]
}

const emptyEdit = (): EditState => ({
  source_path: '',
  target_url: '',
  redirect_type: 301,
  regex_enabled: false,
  is_active: true,
  conditions: [],
})

function safePath(targetUrl: string) {
  try {
    return new URL(targetUrl, window.location.origin).pathname
  } catch {
    return ''
  }
}

export function RedirectsTool() {
  const [rows, setRows] = useState<RedirectRow[]>([])
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [editId, setEditId] = useState<number | 'new' | null>(null)
  const [form, setForm] = useState<EditState>(emptyEdit())
  const [message, setMessage] = useState<{ ok: boolean; text: string } | null>(null)
  const [search, setSearch] = useState('')
  const [selectedIds, setSelectedIds] = useState<number[]>([])
  const [importText, setImportText] = useState('')
  const [testing, setTesting] = useState(false)
  const [testResult, setTestResult] = useState<{ matched: boolean; redirect?: RedirectRow | null } | null>(null)
  const [showImport, setShowImport] = useState(false)
  const diagnostics = [
    !form.regex_enabled && form.source_path !== '' && form.source_path.startsWith('/') === false ? 'Source paths should start with /.' : '',
    !form.regex_enabled && form.source_path !== '' && form.source_path === safePath(form.target_url) ? 'Source and target resolve to the same path.' : '',
    rows.some(row => row.source_path === form.source_path && row.id !== editId) ? 'A redirect with this source already exists.' : '',
  ].filter(Boolean)

  const fetchRows = (query = search) => {
    setLoading(true)
    api.get<RedirectRow[]>(`/workshop/redirects${query ? `?search=${encodeURIComponent(query)}` : ''}`)
      .then(setRows)
      .finally(() => setLoading(false))
  }

  useEffect(() => { fetchRows('') }, [])

  const startNew = () => { setEditId('new'); setForm(emptyEdit()); setMessage(null) }
  const startEdit = (row: RedirectRow) => {
    setEditId(row.id)
    setForm({
      source_path: row.source_path,
      target_url: row.target_url,
      redirect_type: row.redirect_type,
      regex_enabled: row.regex_enabled === 1,
      is_active: row.is_active === 1,
      conditions: ((row as Record<string, unknown>).conditions as RedirectCondition[] | undefined) ?? [],
    })
    setMessage(null)
  }

  const handleSave = async () => {
    setSaving(true)
    setMessage(null)
    try {
      if (editId === 'new') {
        await api.post<RedirectRow>('/workshop/redirects', form)
        setMessage({ ok: true, text: 'Redirect created.' })
      } else {
        await api.put<RedirectRow>(`/workshop/redirects/${editId}`, form)
        setMessage({ ok: true, text: 'Redirect updated.' })
      }
      setEditId(null)
      fetchRows()
    } catch (error) {
      setMessage({ ok: false, text: error instanceof ApiError ? error.message : 'Save failed.' })
    } finally {
      setSaving(false)
    }
  }

  const handleDelete = async (id: number) => {
    if (!confirm('Delete this redirect?')) return
    await api.delete(`/workshop/redirects/${id}`)
    setSelectedIds(prev => prev.filter(item => item !== id))
    fetchRows()
  }

  const handleBulkDelete = async () => {
    if (selectedIds.length === 0 || !confirm(`Delete ${selectedIds.length} redirects?`)) return
    await api.delete('/workshop/redirects', { ids: selectedIds })
    setSelectedIds([])
    fetchRows()
  }

  const handleExport = async () => {
    const result = await api.get<{ exported_at: string; items: RedirectRow[] }>('/workshop/redirects/export')
    setImportText(JSON.stringify(result.items, null, 2))
    setShowImport(true)
    setMessage({ ok: true, text: `Loaded ${result.items.length} redirects into the import/export box.` })
  }

  const handleImport = async () => {
    try {
      let items: unknown
      if (importText.trim().startsWith('[')) {
        items = JSON.parse(importText)
      } else {
        const lines = importText.split(/\r?\n/).map(line => line.trim()).filter(Boolean)
        const [, ...csvRows] = lines
        items = csvRows.map(line => {
          const [source_path = '', target_url = '', redirect_type = '301', regex_enabled = 'false'] = line.split(',').map(value => value.trim())
          return { source_path, target_url, redirect_type: Number(redirect_type), regex_enabled: regex_enabled === 'true' || regex_enabled === '1' }
        })
      }
      const result = await api.post<{ created: number; skipped: number }>('/workshop/redirects/import', { items })
      setMessage({ ok: true, text: `Imported ${result.created} redirects. Skipped ${result.skipped}.` })
      fetchRows()
    } catch (error) {
      setMessage({ ok: false, text: error instanceof ApiError ? error.message : 'Import failed. Use valid JSON.' })
    }
  }

  const handleTest = async () => {
    if (!form.source_path.trim()) return
    setTesting(true)
    setTestResult(null)
    try {
      const result = await api.post<{ matched: boolean; redirect?: RedirectRow | null }>('/workshop/redirects/test', {
        source_path: form.source_path,
      })
      setTestResult(result)
    } finally {
      setTesting(false)
    }
  }

  const toggleSelected = (id: number, checked: boolean) => {
    setSelectedIds(prev => checked ? [...prev, id] : prev.filter(item => item !== id))
  }

  const allSelected = rows.length > 0 && rows.every(row => selectedIds.includes(row.id))

  return (
    <Card className="border-[#390d58]/20 overflow-hidden">
      <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
      <CardHeader>
        <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <CardTitle className="text-lg text-[#390d58]">Redirects Manager</CardTitle>
            <CardDescription>{rows.length} redirect{rows.length !== 1 ? 's' : ''} loaded. Search, validate, bulk-delete, import, and export are now built in.</CardDescription>
          </div>
          <div className="flex flex-wrap gap-2">
            <Button size="sm" variant="outline" onClick={() => setShowImport(prev => !prev)} className="gap-1">
              <Upload className="h-3.5 w-3.5" /> Import / Export
            </Button>
            <Button size="sm" variant="outline" onClick={handleExport} className="gap-1">
              <Download className="h-3.5 w-3.5" /> Export
            </Button>
            <Button size="sm" onClick={startNew} className="gap-1 bg-[#390d58] hover:bg-[#4a1170] text-white">
              <Plus className="h-3.5 w-3.5" /> Add Redirect
            </Button>
          </div>
        </div>
      </CardHeader>

      <CardContent className="space-y-4">
        {message && (
          <div className={`rounded-lg border px-3 py-2 text-sm ${message.ok ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-red-200 bg-red-50 text-red-700'}`}>
            {message.text}
          </div>
        )}

        <div className="flex flex-col gap-3 lg:flex-row lg:items-center">
          <div className="flex flex-1 gap-2">
            <input
              type="text"
              value={search}
              onChange={e => setSearch(e.target.value)}
              onKeyDown={e => e.key === 'Enter' && fetchRows()}
              placeholder="Search source or target"
              className="flex-1 rounded-lg border border-[#390d58]/20 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#390d58]/30"
            />
            <Button size="sm" variant="outline" onClick={() => fetchRows()} className="gap-1">
              <Search className="h-3.5 w-3.5" /> Search
            </Button>
          </div>
          <Button size="sm" variant="outline" disabled={selectedIds.length === 0} onClick={handleBulkDelete} className="gap-1 text-red-600 hover:bg-red-50 hover:border-red-300">
            <Trash2 className="h-3.5 w-3.5" /> Delete Selected
          </Button>
        </div>

        {showImport && (
          <div className="rounded-xl border border-[#390d58]/20 bg-[#390d58]/[0.02] p-4 space-y-3">
            <p className="text-sm font-medium text-[#390d58]">Import / Export JSON</p>
            <textarea
              value={importText}
              onChange={e => setImportText(e.target.value)}
              rows={8}
              className="w-full rounded-lg border border-[#390d58]/20 px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[#390d58]/30"
              placeholder='[{"source_path":"/old","target_url":"/new","redirect_type":301,"regex_enabled":false}]'
            />
            <div className="flex gap-2">
              <Button size="sm" onClick={handleImport} className="bg-[#390d58] hover:bg-[#4a1170] text-white gap-1">
                <Upload className="h-3.5 w-3.5" /> Import
              </Button>
              <p className="text-xs text-muted-foreground self-center">Import accepts JSON arrays or CSV rows with `source_path,target_url,redirect_type,regex_enabled`.</p>
            </div>
          </div>
        )}

        {editId !== null && (
          <div className="rounded-xl border border-[#390d58]/20 p-4 space-y-3 bg-[#390d58]/[0.02]">
            <p className="text-sm font-medium text-[#390d58]">{editId === 'new' ? 'New Redirect' : 'Edit Redirect'}</p>
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <div>
                <label className="mb-1 block text-xs text-muted-foreground">Source path or regex</label>
                <input
                  type="text"
                  value={form.source_path}
                  onChange={e => setForm(prev => ({ ...prev, source_path: e.target.value }))}
                  placeholder={form.regex_enabled ? '^/old/(.*)$' : '/old-url'}
                  className="w-full rounded-lg border border-[#390d58]/20 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#390d58]/30"
                />
              </div>
              <div>
                <label className="mb-1 block text-xs text-muted-foreground">Target URL</label>
                <input
                  type="text"
                  value={form.target_url}
                  onChange={e => setForm(prev => ({ ...prev, target_url: e.target.value }))}
                  placeholder="/new-url or https://..."
                  className="w-full rounded-lg border border-[#390d58]/20 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#390d58]/30"
                />
              </div>
            </div>
            <div className="flex flex-wrap items-center gap-4">
              <select
                value={form.redirect_type}
                onChange={e => setForm(prev => ({ ...prev, redirect_type: parseInt(e.target.value) }))}
                className="rounded-lg border border-[#390d58]/20 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#390d58]/30"
              >
                <option value={301}>301 Permanent</option>
                <option value={302}>302 Temporary</option>
              </select>
              <label className="flex items-center gap-2 text-sm text-muted-foreground">
                <input type="checkbox" checked={form.regex_enabled} onChange={e => setForm(prev => ({ ...prev, regex_enabled: e.target.checked }))} />
                Regex match
              </label>
              <label className="flex items-center gap-2 text-sm text-muted-foreground">
                <input type="checkbox" checked={form.is_active} onChange={e => setForm(prev => ({ ...prev, is_active: e.target.checked }))} />
                Active
              </label>
            </div>
            <div className="space-y-2">
              <div className="flex items-center justify-between">
                <label className="text-xs font-medium text-[#390d58]">Conditions (optional)</label>
                <Button size="sm" variant="ghost" className="h-6 text-xs" onClick={() => setForm(prev => ({ ...prev, conditions: [...prev.conditions, { type: 'user_agent', operator: 'contains', value: '' }] }))}>
                  + Add condition
                </Button>
              </div>
              {form.conditions.map((cond, idx) => (
                <div key={idx} className="flex flex-wrap items-center gap-2">
                  <select value={cond.type} onChange={e => { const c = [...form.conditions]; c[idx] = { ...c[idx], type: e.target.value as RedirectCondition['type'] }; setForm(prev => ({ ...prev, conditions: c })) }} className="rounded-lg border border-[#390d58]/20 px-2 py-1 text-xs">
                    <option value="user_agent">User Agent</option>
                    <option value="referrer">Referrer</option>
                    <option value="query_string">Query String</option>
                  </select>
                  <select value={cond.operator} onChange={e => { const c = [...form.conditions]; c[idx] = { ...c[idx], operator: e.target.value as RedirectCondition['operator'] }; setForm(prev => ({ ...prev, conditions: c })) }} className="rounded-lg border border-[#390d58]/20 px-2 py-1 text-xs">
                    <option value="contains">Contains</option>
                    <option value="not_contains">Does not contain</option>
                    <option value="equals">Equals</option>
                  </select>
                  <input type="text" value={cond.value} onChange={e => { const c = [...form.conditions]; c[idx] = { ...c[idx], value: e.target.value }; setForm(prev => ({ ...prev, conditions: c })) }} placeholder="Value" className="flex-1 rounded-lg border border-[#390d58]/20 px-2 py-1 text-xs focus:outline-none focus:ring-2 focus:ring-[#390d58]/30" />
                  <button onClick={() => setForm(prev => ({ ...prev, conditions: prev.conditions.filter((_, i) => i !== idx) }))} className="text-red-500 hover:text-red-700"><X className="h-3.5 w-3.5" /></button>
                </div>
              ))}
              {form.conditions.length > 0 && <p className="text-[10px] text-muted-foreground">All conditions must match for the redirect to fire.</p>}
            </div>
            {diagnostics.length > 0 && (
              <div className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-700">
                {diagnostics.map(item => <p key={item}>{item}</p>)}
              </div>
            )}
            <div className="flex flex-wrap gap-2">
              <Button size="sm" onClick={handleSave} disabled={saving || diagnostics.length > 0} className="gap-1 bg-[#390d58] hover:bg-[#4a1170] text-white">
                {saving ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Check className="h-3.5 w-3.5" />}
                Save
              </Button>
              <Button size="sm" variant="outline" onClick={handleTest} disabled={testing || !form.source_path.trim()} className="gap-1">
                {testing ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <FlaskConical className="h-3.5 w-3.5" />}
                Test Match
              </Button>
              <Button size="sm" variant="outline" onClick={() => setEditId(null)} className="gap-1">
                <X className="h-3.5 w-3.5" /> Cancel
              </Button>
            </div>
            {testResult && (
              <div className={`rounded-lg border px-3 py-2 text-sm ${testResult.matched ? 'border-amber-200 bg-amber-50 text-amber-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700'}`}>
                {testResult.matched
                  ? `This source currently matches an existing redirect to ${testResult.redirect?.target_url}.`
                  : 'No existing redirect matches this source.'}
              </div>
            )}
          </div>
        )}

        {loading ? (
          <div className="flex justify-center py-8"><Loader2 className="h-5 w-5 animate-spin text-[#390d58]" /></div>
        ) : rows.length === 0 ? (
          <p className="text-sm text-muted-foreground text-center py-8">No redirects configured yet.</p>
        ) : (
          <div className="rounded-xl border border-[#390d58]/10 overflow-hidden">
            <Table>
              <TableHeader>
                <TableRow className="bg-[#390d58]/5 hover:bg-[#390d58]/5">
                  <TableHead className="w-10">
                    <input type="checkbox" checked={allSelected} onChange={e => setSelectedIds(e.target.checked ? rows.map(row => row.id) : [])} />
                  </TableHead>
                  <TableHead className="text-[#390d58] font-semibold">Source</TableHead>
                  <TableHead className="text-[#390d58] font-semibold">Target</TableHead>
                  <TableHead className="w-24 text-[#390d58] font-semibold">Type</TableHead>
                  <TableHead className="w-20 text-[#390d58] font-semibold text-right">Hits</TableHead>
                  <TableHead className="w-36 text-[#390d58] font-semibold">Last Hit</TableHead>
                  <TableHead className="w-24" />
                </TableRow>
              </TableHeader>
              <TableBody>
                {rows.map((row, idx) => (
                  <TableRow key={row.id} className={`font-mono text-xs ${idx % 2 === 0 ? 'bg-white' : 'bg-[#390d58]/[0.02]'} ${row.is_active === 0 ? 'opacity-50' : ''}`}>
                    <TableCell>
                      <input type="checkbox" checked={selectedIds.includes(row.id)} onChange={e => toggleSelected(row.id, e.target.checked)} />
                    </TableCell>
                    <TableCell className="text-[#390d58]">
                      <div className="flex items-center gap-2">
                        <span>{row.source_path}</span>
                        {row.regex_enabled === 1 && <Badge variant="outline" className="text-[9px]">regex</Badge>}
                      </div>
                    </TableCell>
                    <TableCell className="max-w-[220px] truncate text-muted-foreground">{row.target_url}</TableCell>
                    <TableCell><Badge variant="outline" className="text-[10px]">{row.redirect_type}</Badge></TableCell>
                    <TableCell className="text-right text-muted-foreground">{row.hit_count}</TableCell>
                    <TableCell className="text-muted-foreground">{row.last_accessed_at || 'Never'}</TableCell>
                    <TableCell>
                      <div className="flex justify-end gap-1">
                        <button onClick={() => startEdit(row)} className="rounded p-1 text-muted-foreground hover:text-[#390d58] hover:bg-[#390d58]/10">
                          <Pencil className="h-3.5 w-3.5" />
                        </button>
                        <button onClick={() => handleDelete(row.id)} className="rounded p-1 text-muted-foreground hover:text-red-600 hover:bg-red-50">
                          <Trash2 className="h-3.5 w-3.5" />
                        </button>
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>
        )}
      </CardContent>
    </Card>
  )
}
