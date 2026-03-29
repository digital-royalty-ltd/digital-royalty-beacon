import { useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Loader2, Search, ExternalLink } from 'lucide-react'
import { api } from '@/lib/api'

interface AuditItem {
  id?:         number
  title?:      string
  post_type?:  string
  url?:        string
  issues?:     string[]
  // extra keys for specific audits
  [key: string]: unknown
}

interface AuditResult {
  total: number
  items: AuditItem[]
}

interface AuditConfig {
  slug:        string
  title:       string
  description: string
  columns:     AuditColumn[]
}

interface AuditColumn {
  key:     string
  label:   string
  render?: (item: AuditItem) => React.ReactNode
}

const ISSUE_LABELS: Record<string, string> = {
  title_too_short:       'Title too short',
  title_too_long:        'Title too long',
  no_meta_description:   'No meta description',
  missing_h1:            'Missing H1',
  multiple_h1:           'Multiple H1s',
  skipped_heading_level: 'Skipped heading level',
}

const AUDIT_CONFIGS: Record<string, AuditConfig> = {
  'meta-auditor': {
    slug:        'meta',
    title:       'Meta Auditor',
    description: 'Check title length and SEO meta description coverage.',
    columns: [
      { key: 'title', label: 'Title' },
      { key: 'post_type', label: 'Type' },
      {
        key: 'issues',
        label: 'Issues',
        render: (item) => (
          <div className="flex flex-wrap gap-1">
            {(item.issues as string[]).map(issue => (
              <Badge key={issue} variant="outline" className="text-amber-600 border-amber-300 bg-amber-50 text-[10px]">
                {ISSUE_LABELS[issue] ?? issue}
              </Badge>
            ))}
          </div>
        ),
      },
      {
        key: 'url',
        label: '',
        render: (item) => item.url ? (
          <a href={item.url as string} target="_blank" rel="noreferrer" className="text-[#390d58] hover:opacity-70">
            <ExternalLink className="h-3.5 w-3.5" />
          </a>
        ) : null,
      },
    ],
  },

  'heading-structure': {
    slug:        'headings',
    title:       'Heading Structure',
    description: 'Find pages with missing H1s, multiple H1s, or skipped heading levels.',
    columns: [
      { key: 'title', label: 'Title' },
      { key: 'post_type', label: 'Type' },
      {
        key: 'issues',
        label: 'Issues',
        render: (item) => (
          <div className="flex flex-wrap gap-1">
            {(item.issues as string[]).map(issue => (
              <Badge key={issue} variant="outline" className="text-amber-600 border-amber-300 bg-amber-50 text-[10px]">
                {ISSUE_LABELS[issue] ?? issue}
              </Badge>
            ))}
          </div>
        ),
      },
      {
        key: 'url',
        label: '',
        render: (item) => item.url ? (
          <a href={item.url as string} target="_blank" rel="noreferrer" className="text-[#390d58] hover:opacity-70">
            <ExternalLink className="h-3.5 w-3.5" />
          </a>
        ) : null,
      },
    ],
  },

  'orphaned-content': {
    slug:        'orphans',
    title:       'Orphaned Content',
    description: 'Posts and pages not linked to from any other content.',
    columns: [
      { key: 'title', label: 'Title' },
      { key: 'post_type', label: 'Type' },
      {
        key: 'url',
        label: '',
        render: (item) => item.url ? (
          <a href={item.url as string} target="_blank" rel="noreferrer" className="text-[#390d58] hover:opacity-70">
            <ExternalLink className="h-3.5 w-3.5" />
          </a>
        ) : null,
      },
    ],
  },

  'duplicate-titles': {
    slug:        'duplicates',
    title:       'Duplicate Titles',
    description: 'Published posts and pages sharing the same title.',
    columns: [
      { key: 'title', label: 'Title' },
      {
        key: 'count',
        label: 'Count',
        render: (item) => (
          <Badge variant="outline" className="bg-red-50 text-red-600 border-red-200">
            {item.count as number}
          </Badge>
        ),
      },
      {
        key: 'urls',
        label: 'Pages',
        render: (item) => (
          <div className="flex flex-wrap gap-1">
            {(item.urls as string[]).map((url, i) => (
              <a key={i} href={url} target="_blank" rel="noreferrer"
                className="text-[10px] text-[#390d58] underline underline-offset-2">
                #{(item.ids as number[])[i]}
              </a>
            ))}
          </div>
        ),
      },
    ],
  },

  'image-alt-auditor': {
    slug:        'image-alt',
    title:       'Image Alt Auditor',
    description: 'Images with missing or empty alt attributes.',
    columns: [
      { key: 'title', label: 'Page' },
      { key: 'src', label: 'Image' },
      {
        key: 'url',
        label: '',
        render: (item) => item.url ? (
          <a href={item.url as string} target="_blank" rel="noreferrer" className="text-[#390d58] hover:opacity-70">
            <ExternalLink className="h-3.5 w-3.5" />
          </a>
        ) : null,
      },
    ],
  },

  'unused-media': {
    slug:        'unused-media',
    title:       'Unused Media',
    description: 'Attachments not referenced in any post content or as a featured image.',
    columns: [
      { key: 'title', label: 'File' },
      {
        key: 'url',
        label: '',
        render: (item) => item.url ? (
          <a href={item.url as string} target="_blank" rel="noreferrer" className="text-[#390d58] hover:opacity-70">
            <ExternalLink className="h-3.5 w-3.5" />
          </a>
        ) : null,
      },
    ],
  },

  'noindex-checker': {
    slug:        'noindex',
    title:       'Noindex Checker',
    description: 'Published pages currently set to noindex via Yoast SEO or AIOSEO.',
    columns: [
      { key: 'title', label: 'Title' },
      { key: 'post_type', label: 'Type' },
      {
        key: 'url',
        label: '',
        render: (item) => item.url ? (
          <a href={item.url as string} target="_blank" rel="noreferrer" className="text-[#390d58] hover:opacity-70">
            <ExternalLink className="h-3.5 w-3.5" />
          </a>
        ) : null,
      },
    ],
  },

  'redirect-chains': {
    slug:        'redirect-chains',
    title:       'Redirect Chains',
    description: 'Redirect chains (3+ hops) and loops in your redirect table.',
    columns: [
      {
        key: 'type',
        label: 'Type',
        render: (item) => (
          <Badge variant="outline"
            className={item.type === 'loop'
              ? 'bg-red-50 text-red-600 border-red-200'
              : 'bg-amber-50 text-amber-600 border-amber-200'}>
            {item.type as string}
          </Badge>
        ),
      },
      {
        key: 'length',
        label: 'Hops',
        render: (item) => <span>{item.length as number}</span>,
      },
      {
        key: 'chain',
        label: 'Chain',
        render: (item) => (
          <div className="text-xs font-mono text-muted-foreground">
            {(item.chain as string[]).join(' → ')}
          </div>
        ),
      },
    ],
  },

  'broken-links': {
    slug:        'broken-links',
    title:       'Broken Links',
    description: 'Internal links pointing to slugs that no longer exist.',
    columns: [
      { key: 'title', label: 'Page' },
      { key: 'broken_url', label: 'Broken URL' },
      {
        key: 'url',
        label: '',
        render: (item) => item.url ? (
          <a href={item.url as string} target="_blank" rel="noreferrer" className="text-[#390d58] hover:opacity-70">
            <ExternalLink className="h-3.5 w-3.5" />
          </a>
        ) : null,
      },
    ],
  },
}

export function AuditTool({ toolSlug }: { toolSlug: string }) {
  const config = AUDIT_CONFIGS[toolSlug]
  const [result,   setResult]   = useState<AuditResult | null>(null)
  const [scanning, setScanning] = useState(false)

  if (!config) {
    return <p className="text-sm text-muted-foreground">Unknown audit tool: {toolSlug}</p>
  }

  const handleScan = async () => {
    setScanning(true)
    setResult(null)
    try {
      const data = await api.get<AuditResult>(`/workshop/audit/${config.slug}`)
      setResult(data)
    } finally {
      setScanning(false)
    }
  }

  return (
    <Card className="border-[#390d58]/20 overflow-hidden">
      <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
      <CardHeader>
        <div className="flex items-start justify-between">
          <div>
            <CardTitle className="text-lg text-[#390d58]">{config.title}</CardTitle>
            <CardDescription>
              {config.description}
              {result && ` ${result.total} issue${result.total !== 1 ? 's' : ''} found.`}
            </CardDescription>
          </div>
          <Button size="sm" onClick={handleScan} disabled={scanning}
            className="gap-2 bg-[#390d58] hover:bg-[#4a1170] text-white">
            {scanning
              ? <Loader2 className="h-3.5 w-3.5 animate-spin" />
              : <Search className="h-3.5 w-3.5" />}
            {scanning ? 'Scanning…' : result ? 'Re-scan' : 'Scan Now'}
          </Button>
        </div>
      </CardHeader>

      {result && (
        <CardContent>
          {result.total === 0 ? (
            <p className="text-sm text-muted-foreground text-center py-8">No issues found.</p>
          ) : (
            <div className="rounded-xl border border-[#390d58]/10 overflow-hidden">
              <Table>
                <TableHeader>
                  <TableRow className="bg-[#390d58]/5 hover:bg-[#390d58]/5">
                    {config.columns.map(col => (
                      <TableHead key={col.key} className="text-[#390d58] font-semibold">
                        {col.label}
                      </TableHead>
                    ))}
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {result.items.map((item, idx) => (
                    <TableRow key={idx}
                      className={`text-sm ${idx % 2 === 0 ? 'bg-white' : 'bg-[#390d58]/[0.02]'}`}>
                      {config.columns.map(col => (
                        <TableCell key={col.key}>
                          {col.render
                            ? col.render(item)
                            : String(item[col.key] ?? '')}
                        </TableCell>
                      ))}
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          )}
        </CardContent>
      )}
    </Card>
  )
}
