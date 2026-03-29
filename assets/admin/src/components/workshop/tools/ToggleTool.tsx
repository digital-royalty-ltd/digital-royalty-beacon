import { useEffect, useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Switch } from '@/components/ui/switch'
import { Loader2 } from 'lucide-react'
import { api } from '@/lib/api'

interface TogglesData {
  svg_support:          boolean
  disable_comments:     boolean
  disable_xmlrpc:       boolean
  disable_file_editing: boolean
  sanitise_filenames:   boolean
}

const toggleMeta: Record<string, { key: keyof TogglesData; label: string; description: string; warning?: string }> = {
  'svg-support': {
    key:         'svg_support',
    label:       'Enable SVG Uploads',
    description: 'Allow SVG files in the media library. Uploaded SVGs are automatically sanitised to remove scripts and event handlers.',
  },
  'disable-comments': {
    key:         'disable_comments',
    label:       'Disable Comments Site-wide',
    description: 'Remove all comment functionality from every post type. Existing comments are not deleted.',
  },
  'disable-xmlrpc': {
    key:         'disable_xmlrpc',
    label:       'Disable XML-RPC',
    description: 'Block the XML-RPC endpoint to reduce attack surface. This also disables Jetpack and apps that use XML-RPC.',
    warning:     'Disabling XML-RPC will break Jetpack and any mobile app connections that rely on it.',
  },
  'disable-file-editing': {
    key:         'disable_file_editing',
    label:       'Disable Theme & Plugin File Editing',
    description: 'Remove the theme and plugin editor from the WordPress admin.',
  },
  'sanitise-filenames': {
    key:         'sanitise_filenames',
    label:       'Sanitise Upload Filenames',
    description: 'Automatically convert uploaded filenames to lowercase with hyphens and no special characters.',
  },
}

interface ToggleToolProps {
  slug: string
}

export function ToggleTool({ slug }: ToggleToolProps) {
  const meta = toggleMeta[slug]
  const [enabled,  setEnabled]  = useState(false)
  const [loading,  setLoading]  = useState(true)
  const [saving,   setSaving]   = useState(false)
  const [saved,    setSaved]    = useState(false)

  useEffect(() => {
    api.get<TogglesData>('/workshop/toggles').then(data => {
      setEnabled(data[meta.key])
    }).finally(() => setLoading(false))
  }, [meta.key])

  const handleToggle = async (checked: boolean) => {
    setSaving(true)
    setSaved(false)
    try {
      await api.post('/workshop/toggles', { key: meta.key.replace(/_/g, '_'), enabled: checked })
      setEnabled(checked)
      setSaved(true)
      setTimeout(() => setSaved(false), 2000)
    } finally {
      setSaving(false)
    }
  }

  if (!meta) return null

  return (
    <Card className="border-[#390d58]/20 overflow-hidden">
      <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
      <CardHeader>
        <CardTitle className="text-lg text-[#390d58]">{meta.label}</CardTitle>
        <CardDescription>{meta.description}</CardDescription>
      </CardHeader>
      <CardContent>
        {meta.warning && (
          <div className="mb-4 p-3 rounded-lg bg-amber-50 border border-amber-200 text-sm text-amber-800">
            {meta.warning}
          </div>
        )}
        <div className="flex items-center justify-between p-4 rounded-xl bg-[#390d58]/5">
          <span className="text-sm font-medium">{enabled ? 'Enabled' : 'Disabled'}</span>
          <div className="flex items-center gap-2">
            {(loading || saving) && <Loader2 className="h-4 w-4 animate-spin text-[#390d58]" />}
            {saved && <span className="text-xs text-emerald-600">Saved</span>}
            <Switch
              checked={enabled}
              onCheckedChange={handleToggle}
              disabled={loading || saving}
            />
          </div>
        </div>
      </CardContent>
    </Card>
  )
}
