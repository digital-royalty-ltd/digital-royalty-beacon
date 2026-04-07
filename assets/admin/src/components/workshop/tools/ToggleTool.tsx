import { useEffect, useMemo, useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Switch } from '@/components/ui/switch'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Loader2, Save, ShieldAlert, ShieldCheck } from 'lucide-react'
import { api } from '@/lib/api'

interface RoleOption {
  value: string
  label: string
}

interface PostTypeOption {
  value: string
  label: string
}

interface SvgSupportData {
  enabled: boolean
  allowed_roles: string[]
  inline_rendering: boolean
  sanitiser: string
  security_state: string
  status_message: string
  updated_at: string
  bypass_active: boolean
}

interface DisableCommentsData {
  enabled: boolean
  mode: 'all' | 'selected'
  post_types: string[]
  cleanup: Record<string, boolean>
}

interface DisableXmlRpcData {
  enabled: boolean
  mode: 'full' | 'pingback'
  effective_state: string
  source: string
  warnings: string[]
  endpoint: string
  test: {
    ok: boolean
    code?: number
    summary: string
  }
}

interface DisableFileEditingData {
  enabled: boolean
  mode: 'editor' | 'mods'
  effective: {
    editor_disabled: boolean
    mods_disabled: boolean
    source: string
    conflict: boolean
    warnings: string[]
  }
}

interface SanitiseFilenamesData {
  enabled: boolean
  lowercase: boolean
  transliterate: boolean
  separator: 'hyphen' | 'underscore'
  sample_input: string
  sample_output: string
}

interface TogglesData {
  available_roles: RoleOption[]
  available_post_types: PostTypeOption[]
  svg_support: SvgSupportData
  disable_comments: DisableCommentsData
  disable_xmlrpc: DisableXmlRpcData
  disable_file_editing: DisableFileEditingData
  sanitise_filenames: SanitiseFilenamesData
}

type ToolKey = keyof Pick<TogglesData, 'svg_support' | 'disable_comments' | 'disable_xmlrpc' | 'disable_file_editing' | 'sanitise_filenames'>

const toggleMeta: Record<string, { key: ToolKey; label: string; description: string; warning?: string }> = {
  'svg-support': {
    key: 'svg_support',
    label: 'Enable SVG Uploads',
    description: 'Allow sanitised SVG uploads with role-based access and clear security state reporting.',
  },
  'disable-comments': {
    key: 'disable_comments',
    label: 'Disable Comments',
    description: 'Disable comments globally or only on selected post types, while keeping historical comments intact.',
  },
  'disable-xmlrpc': {
    key: 'disable_xmlrpc',
    label: 'Disable XML-RPC',
    description: 'Block XML-RPC fully or only disable pingbacks, with runtime state and verification.',
    warning: 'Full disable can break Jetpack and older XML-RPC clients. Pingback-only mode is the safer compatibility option.',
  },
  'disable-file-editing': {
    key: 'disable_file_editing',
    label: 'Disable File Editing',
    description: 'Remove the built-in editors or apply a broader file-modification lockdown.',
  },
  'sanitise-filenames': {
    key: 'sanitise_filenames',
    label: 'Sanitise Upload Filenames',
    description: 'Normalise filenames on new uploads with configurable transliteration and separator rules.',
  },
}

interface ToggleToolProps {
  slug: string
}

function sanitisePreview(filename: string, settings: SanitiseFilenamesData) {
  const parts = filename.split('.')
  const ext = parts.length > 1 ? `.${parts.pop()!.toLowerCase()}` : ''
  let name = parts.join('.')
  const separator = settings.separator === 'underscore' ? '_' : '-'

  if (settings.transliterate) {
    name = name.normalize('NFD').replace(/[\u0300-\u036f]/g, '')
  }

  if (settings.lowercase) {
    name = name.toLowerCase()
  }

  name = name.replace(/[\s_]+/g, separator)
  name = name.replace(/[^a-zA-Z0-9\-_.]/g, '')
  name = name.replace(separator === '_' ? /_{2,}/g : /-{2,}/g, separator)
  name = name.replace(/^[-_.]+|[-_.]+$/g, '')

  return `${name || 'file'}${ext}`
}

export function ToggleTool({ slug }: ToggleToolProps) {
  const meta = toggleMeta[slug]
  const [data, setData] = useState<TogglesData | null>(null)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [saved, setSaved] = useState(false)
  const [xmlrpcTesting, setXmlrpcTesting] = useState(false)
  const [filenamePreview, setFilenamePreview] = useState('')

  const load = () => {
    setLoading(true)
    api.get<TogglesData>('/workshop/toggles')
      .then(response => {
        setData(response)
        setFilenamePreview(response.sanitise_filenames.sample_input)
      })
      .finally(() => setLoading(false))
  }

  useEffect(() => {
    load()
  }, [])

  const tool = data?.[meta.key]

  const previewOutput = useMemo(() => {
    if (!data) return ''
    return sanitisePreview(filenamePreview, data.sanitise_filenames)
  }, [data, filenamePreview])

  const updateTool = (next: unknown) => {
    if (!data) return
    setData({ ...data, [meta.key]: next } as TogglesData)
  }

  const handleSave = async () => {
    if (!data || !tool) return

    setSaving(true)
    setSaved(false)

    try {
      await api.post('/workshop/toggles', { key: meta.key, enabled: tool.enabled, settings: tool })
      setSaved(true)
      load()
      setTimeout(() => setSaved(false), 2000)
    } finally {
      setSaving(false)
    }
  }

  const runXmlRpcTest = async () => {
    if (!data) return
    setXmlrpcTesting(true)
    try {
      const test = await api.post<DisableXmlRpcData['test']>('/workshop/toggles/xmlrpc-test')
      setData({
        ...data,
        disable_xmlrpc: {
          ...data.disable_xmlrpc,
          test,
        },
      })
    } finally {
      setXmlrpcTesting(false)
    }
  }

  if (!meta || !tool) return null

  return (
    <Card className="border-[#390d58]/20 overflow-hidden">
      <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
      <CardHeader>
        <CardTitle className="text-lg text-[#390d58]">{meta.label}</CardTitle>
        <CardDescription>{meta.description}</CardDescription>
      </CardHeader>
      <CardContent className="space-y-5">
        {loading || !data ? <Loader2 className="h-5 w-5 animate-spin text-[#390d58]" /> : (
          <>
            {meta.warning && (
              <div className="rounded-lg bg-amber-50 border border-amber-200 p-3 text-sm text-amber-800">
                {meta.warning}
              </div>
            )}

            <div className="flex items-center justify-between rounded-xl bg-[#390d58]/5 p-4">
              <div>
                <p className="text-sm font-medium text-[#390d58]">{tool.enabled ? 'Enabled' : 'Disabled'}</p>
                <p className="text-xs text-muted-foreground">Changes take effect immediately after saving.</p>
              </div>
              <Switch
                checked={tool.enabled}
                onCheckedChange={checked => updateTool({ ...tool, enabled: checked })}
                disabled={saving}
              />
            </div>

            {meta.key === 'svg_support' && (
              <>
                <div className="rounded-xl border border-[#390d58]/10 p-4 space-y-4">
                  <div className="space-y-2">
                    <Label>Allowed upload roles</Label>
                    <div className="flex flex-wrap gap-3">
                      {data.available_roles.map(role => (
                        <label key={role.value} className="flex items-center gap-2 text-sm text-muted-foreground">
                          <input
                            type="checkbox"
                            checked={tool.allowed_roles.includes(role.value)}
                            onChange={() => updateTool({
                              ...tool,
                              allowed_roles: tool.allowed_roles.includes(role.value)
                                ? tool.allowed_roles.filter(current => current !== role.value)
                                : [...tool.allowed_roles, role.value],
                            })}
                          />
                          {role.label}
                        </label>
                      ))}
                    </div>
                  </div>

                  <div className="flex items-center justify-between rounded-lg bg-[#390d58]/5 p-3">
                    <div>
                      <p className="text-sm font-medium">Inline rendering</p>
                      <p className="text-xs text-muted-foreground">Flag whether SVGs may be treated as inline-safe assets by Beacon-aware templates.</p>
                    </div>
                    <Switch
                      checked={tool.inline_rendering}
                      onCheckedChange={checked => updateTool({ ...tool, inline_rendering: checked })}
                    />
                  </div>
                </div>

                <div className={`rounded-xl border p-4 ${tool.security_state === 'protected' ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50'}`}>
                  <div className="flex items-start gap-3">
                    {tool.security_state === 'protected'
                      ? <ShieldCheck className="mt-0.5 h-4 w-4 text-emerald-600" />
                      : <ShieldAlert className="mt-0.5 h-4 w-4 text-amber-600" />}
                    <div className="space-y-1 text-sm">
                      <p className="font-medium">Security state: {tool.security_state}</p>
                      <p>{tool.status_message}</p>
                      <p className="text-xs text-muted-foreground">Sanitiser: {tool.sanitiser}{tool.updated_at ? ` • Last update ${tool.updated_at}` : ''}</p>
                      {tool.bypass_active && <p className="text-xs text-amber-700">Sanitisation bypass is active somewhere else in the stack.</p>}
                    </div>
                  </div>
                </div>
              </>
            )}

            {meta.key === 'disable_comments' && (
              <>
                <div className="grid gap-4 sm:grid-cols-2">
                  <div className="space-y-1.5">
                    <Label>Scope</Label>
                    <Select value={tool.mode} onValueChange={value => updateTool({ ...tool, mode: value as DisableCommentsData['mode'] })}>
                      <SelectTrigger className="border-[#390d58]/20"><SelectValue /></SelectTrigger>
                      <SelectContent>
                        <SelectItem value="all">Disable everywhere</SelectItem>
                        <SelectItem value="selected">Only selected post types</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                  <div className="rounded-xl border border-[#390d58]/10 p-4 text-sm text-muted-foreground">
                    Existing comments are preserved. This only closes new comment entry points and removes comment UI.
                  </div>
                </div>

                {tool.mode === 'selected' && (
                  <div className="rounded-xl border border-[#390d58]/10 p-4 space-y-3">
                    <Label>Target post types</Label>
                    <div className="flex flex-wrap gap-3">
                      {data.available_post_types.map(postType => (
                        <label key={postType.value} className="flex items-center gap-2 text-sm text-muted-foreground">
                          <input
                            type="checkbox"
                            checked={tool.post_types.includes(postType.value)}
                            onChange={() => updateTool({
                              ...tool,
                              post_types: tool.post_types.includes(postType.value)
                                ? tool.post_types.filter(current => current !== postType.value)
                                : [...tool.post_types, postType.value],
                            })}
                          />
                          {postType.label}
                        </label>
                      ))}
                    </div>
                  </div>
                )}

                <div className="rounded-xl border border-[#390d58]/10 p-4 space-y-2">
                  <p className="text-sm font-medium text-[#390d58]">Applied cleanup</p>
                  {Object.entries(tool.cleanup).map(([key, active]) => (
                    <div key={key} className="flex items-center justify-between text-sm">
                      <span className="capitalize">{key.replace(/_/g, ' ')}</span>
                      <span className={active ? 'text-emerald-600' : 'text-muted-foreground'}>{active ? 'Disabled' : 'Unchanged'}</span>
                    </div>
                  ))}
                </div>
              </>
            )}

            {meta.key === 'disable_xmlrpc' && (
              <>
                <div className="grid gap-4 sm:grid-cols-2">
                  <div className="space-y-1.5">
                    <Label>Mode</Label>
                    <Select value={tool.mode} onValueChange={value => updateTool({ ...tool, mode: value as DisableXmlRpcData['mode'] })}>
                      <SelectTrigger className="border-[#390d58]/20"><SelectValue /></SelectTrigger>
                      <SelectContent>
                        <SelectItem value="full">Disable XML-RPC fully</SelectItem>
                        <SelectItem value="pingback">Disable pingbacks only</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                  <div className="rounded-xl border border-[#390d58]/10 p-4 text-sm">
                    <p className="font-medium text-[#390d58]">Runtime state</p>
                    <p className="mt-1 text-muted-foreground">Effective state: {tool.effective_state} • Source: {tool.source}</p>
                  </div>
                </div>

                {tool.warnings.length > 0 && (
                  <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 space-y-1">
                    {tool.warnings.map(warning => <p key={warning}>{warning}</p>)}
                  </div>
                )}

                <div className="rounded-xl border border-[#390d58]/10 p-4 space-y-3">
                  <div className="flex items-center justify-between gap-3">
                    <div>
                      <p className="text-sm font-medium text-[#390d58]">Endpoint check</p>
                      <p className="text-xs text-muted-foreground">{tool.endpoint}</p>
                    </div>
                    <Button variant="outline" onClick={runXmlRpcTest} disabled={xmlrpcTesting} className="gap-2">
                      {xmlrpcTesting && <Loader2 className="h-4 w-4 animate-spin" />}
                      Run check
                    </Button>
                  </div>
                  <p className="text-sm text-muted-foreground">{tool.test.summary}{tool.test.code ? ` (HTTP ${tool.test.code})` : ''}</p>
                </div>
              </>
            )}

            {meta.key === 'disable_file_editing' && (
              <>
                <div className="space-y-1.5">
                  <Label>Protection level</Label>
                  <Select value={tool.mode} onValueChange={value => updateTool({ ...tool, mode: value as DisableFileEditingData['mode'] })}>
                    <SelectTrigger className="border-[#390d58]/20"><SelectValue /></SelectTrigger>
                    <SelectContent>
                      <SelectItem value="editor">Disable theme/plugin editors only</SelectItem>
                      <SelectItem value="mods">Disable all file modifications</SelectItem>
                    </SelectContent>
                  </Select>
                </div>

                <div className="rounded-xl border border-[#390d58]/10 p-4 space-y-2 text-sm">
                  <div className="flex items-center justify-between">
                    <span>Editor pages</span>
                    <span className={tool.effective.editor_disabled ? 'text-emerald-600' : 'text-muted-foreground'}>{tool.effective.editor_disabled ? 'Blocked' : 'Available'}</span>
                  </div>
                  <div className="flex items-center justify-between">
                    <span>File installs and updates</span>
                    <span className={tool.effective.mods_disabled ? 'text-emerald-600' : 'text-muted-foreground'}>{tool.effective.mods_disabled ? 'Blocked' : 'Available'}</span>
                  </div>
                  <div className="flex items-center justify-between">
                    <span>Effective source</span>
                    <span>{tool.effective.source}</span>
                  </div>
                </div>

                {(tool.effective.conflict || tool.effective.warnings.length > 0) && (
                  <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 space-y-1">
                    {tool.effective.conflict && <p>Beacon cannot weaken a stronger file-mods lock already enforced elsewhere.</p>}
                    {tool.effective.warnings.map(warning => <p key={warning}>{warning}</p>)}
                  </div>
                )}
              </>
            )}

            {meta.key === 'sanitise_filenames' && (
              <>
                <div className="grid gap-4 sm:grid-cols-3">
                  <label className="flex items-center gap-2 text-sm text-muted-foreground">
                    <input
                      type="checkbox"
                      checked={tool.lowercase}
                      onChange={event => updateTool({ ...tool, lowercase: event.target.checked })}
                    />
                    Lowercase output
                  </label>
                  <label className="flex items-center gap-2 text-sm text-muted-foreground">
                    <input
                      type="checkbox"
                      checked={tool.transliterate}
                      onChange={event => updateTool({ ...tool, transliterate: event.target.checked })}
                    />
                    Transliterate accents
                  </label>
                  <div className="space-y-1.5">
                    <Label>Separator</Label>
                    <Select value={tool.separator} onValueChange={value => updateTool({ ...tool, separator: value as SanitiseFilenamesData['separator'] })}>
                      <SelectTrigger className="border-[#390d58]/20"><SelectValue /></SelectTrigger>
                      <SelectContent>
                        <SelectItem value="hyphen">Hyphen</SelectItem>
                        <SelectItem value="underscore">Underscore</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                </div>

                <div className="rounded-xl border border-[#390d58]/10 p-4 space-y-3">
                  <div className="space-y-1.5">
                    <Label>Preview a filename</Label>
                    <Input
                      value={filenamePreview}
                      onChange={event => setFilenamePreview(event.target.value)}
                      className="border-[#390d58]/20 focus-visible:ring-[#390d58]"
                    />
                  </div>
                  <div className="rounded-lg bg-[#390d58]/5 p-3">
                    <p className="text-xs uppercase tracking-[0.18em] text-[#390d58]">Preview output</p>
                    <p className="mt-1 text-sm font-medium">{previewOutput}</p>
                  </div>
                  <p className="text-xs text-muted-foreground">Only new uploads are affected. Existing media files are not renamed.</p>
                </div>
              </>
            )}

            <div className="flex items-center justify-between">
              <div className="text-xs text-muted-foreground">
                {saved ? <span className="text-emerald-600">Saved</span> : 'Save to apply changes.'}
              </div>
              <Button onClick={handleSave} disabled={saving} className="bg-[#390d58] hover:bg-[#4a1170] text-white gap-2">
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
