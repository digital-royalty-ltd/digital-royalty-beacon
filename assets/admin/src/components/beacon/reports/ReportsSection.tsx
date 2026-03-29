import { useEffect, useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { FileJson, Loader2, ChevronRight, CheckCircle2, Clock, AlertCircle, RefreshCw } from 'lucide-react'
import { api } from '@/lib/api'

interface ReportSummary {
  type:         string
  label:        string
  version:      number
  status:       string
  generated_at: string | null
  submitted_at: string | null
}

interface Props {
  onSelect: (type: string) => void
}

const statusConfig: Record<string, { label: string; icon: React.ReactNode; className: string }> = {
  submitted: {
    label:     'Submitted',
    icon:      <CheckCircle2 className="h-3.5 w-3.5" />,
    className: 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20',
  },
  generated: {
    label:     'Generated',
    icon:      <Clock className="h-3.5 w-3.5" />,
    className: 'bg-amber-500/10 text-amber-600 border-amber-500/20',
  },
  failed: {
    label:     'Failed',
    icon:      <AlertCircle className="h-3.5 w-3.5" />,
    className: 'bg-red-500/10 text-red-600 border-red-500/20',
  },
  pending: {
    label:     'Pending',
    icon:      <Loader2 className="h-3.5 w-3.5 animate-spin" />,
    className: 'bg-[#390d58]/10 text-[#390d58] border-[#390d58]/20',
  },
}

function StatusBadge({ status }: { status: string }) {
  const cfg = statusConfig[status] ?? {
    label: status, icon: null, className: 'bg-muted text-muted-foreground',
  }
  return (
    <Badge variant="outline" className={`gap-1.5 text-xs ${cfg.className}`}>
      {cfg.icon}
      {cfg.label}
    </Badge>
  )
}

function dateLabel(d: string | null): string {
  if (!d) return '—'
  const dt = new Date(d.endsWith('Z') ? d : d + 'Z')
  return dt.toLocaleString(undefined, {
    dateStyle: 'medium', timeStyle: 'short',
  })
}

export function ReportsSection({ onSelect }: Props) {
  const [reports, setReports] = useState<ReportSummary[]>([])
  const [loading, setLoading] = useState(true)
  const [error,   setError]   = useState<string | null>(null)

  const fetch = () => {
    setLoading(true)
    setError(null)
    api.get<ReportSummary[]>('/reports')
      .then(setReports)
      .catch(() => setError('Could not load reports.'))
      .finally(() => setLoading(false))
  }

  useEffect(() => { fetch() }, [])

  return (
    <Card className="border-[#390d58]/20 overflow-hidden">
      <div className="h-1 bg-gradient-to-r from-[#390d58] to-[#5a1a8a]" />
      <CardHeader>
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-4">
            <div className="rounded-xl bg-[#390d58] p-3 text-white shadow-md shadow-[#390d58]/20">
              <FileJson className="h-5 w-5" />
            </div>
            <div>
              <CardTitle className="text-lg text-[#390d58]">Site Reports</CardTitle>
              <CardDescription>
                View and fine-tune the knowledge Beacon holds about your site. Changes are saved locally and resubmitted to Beacon.
              </CardDescription>
            </div>
          </div>
          <Button
            variant="outline" size="sm"
            className="gap-1.5 border-[#390d58]/20 text-[#390d58]"
            onClick={fetch}
            disabled={loading}
          >
            {loading ? <Loader2 className="h-4 w-4 animate-spin" /> : <RefreshCw className="h-4 w-4" />}
            Refresh
          </Button>
        </div>
      </CardHeader>
      <CardContent>
        {loading ? (
          <div className="flex items-center justify-center h-24 text-muted-foreground gap-2">
            <Loader2 className="h-5 w-5 animate-spin text-[#390d58]" />
            <span className="text-sm">Loading reports…</span>
          </div>
        ) : error ? (
          <p className="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">{error}</p>
        ) : reports.length === 0 ? (
          <div className="rounded-xl border border-[#390d58]/10 p-8 text-center text-muted-foreground">
            <FileJson className="h-8 w-8 mx-auto mb-3 opacity-30" />
            <p className="text-sm">No reports generated yet.</p>
            <p className="text-xs mt-1">Run the report pipeline from the Dashboard to populate this list.</p>
          </div>
        ) : (
          <div className="rounded-xl border border-[#390d58]/10 overflow-hidden divide-y divide-[#390d58]/10">
            {reports.map(report => (
              <button
                key={report.type}
                onClick={() => onSelect(report.type)}
                className="w-full flex items-center gap-4 px-5 py-4 text-left hover:bg-[#390d58]/[0.03] transition-colors group"
              >
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-3 mb-1">
                    <span className="text-sm font-semibold text-[#390d58]">{report.label}</span>
                    <StatusBadge status={report.status} />
                    <span className="text-xs text-muted-foreground font-mono">v{report.version}</span>
                  </div>
                  <div className="flex gap-4 text-xs text-muted-foreground">
                    <span>Generated: {dateLabel(report.generated_at)}</span>
                    {report.submitted_at && (
                      <span>Submitted: {dateLabel(report.submitted_at)}</span>
                    )}
                  </div>
                </div>
                <ChevronRight className="h-4 w-4 text-[#390d58]/40 group-hover:text-[#390d58] transition-colors shrink-0" />
              </button>
            ))}
          </div>
        )}
      </CardContent>
    </Card>
  )
}
