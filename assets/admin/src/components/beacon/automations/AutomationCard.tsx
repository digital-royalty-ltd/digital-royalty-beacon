import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { CheckCircle2, Clock, AlertCircle, ArrowRight, Loader2, Play, Eye } from 'lucide-react'

export interface AutomationDependencyItem {
  report_type: string
  label: string
  max_age_days: number | null
  status: 'ok' | 'missing' | 'stale'
  submitted_at: string | null
}

export interface AutomationListItem {
  key: string
  label: string
  description: string
  deferred_key: string | null
  dependencies: AutomationDependencyItem[]
  deps_met: boolean
  status: 'tool' | 'ready' | 'dependencies_missing' | 'running' | 'completed' | 'failed'
  latest_run: { id: number; status: string; created_at: string; updated_at: string } | null
}

interface Props {
  automation: AutomationListItem
  onOpenTool?: () => void
  onRun?: () => void
  onViewResults?: () => void
  running?: boolean
}

export function AutomationCard({ automation, onOpenTool, onRun, onViewResults, running }: Props) {
  const isTool = automation.deferred_key === null
  const hasResults = automation.status === 'completed'

  return (
    <Card className={`group transition-all ${
      automation.status === 'dependencies_missing'
        ? 'opacity-80 border-dashed'
        : 'hover:shadow-lg hover:shadow-[#390d58]/10 hover:border-[#390d58]/30 border-[#390d58]/20'
    }`}>
      <CardHeader className="pb-3">
        <div className="flex items-start justify-between gap-3">
          <div className="flex-1">
            <CardTitle className="text-base text-[#390d58]">{automation.label}</CardTitle>
            <CardDescription className="text-sm leading-relaxed mt-1">
              {automation.description}
            </CardDescription>
          </div>
          <StatusBadge status={automation.status} />
        </div>
      </CardHeader>

      {automation.dependencies.length > 0 && (
        <CardContent className="pt-0 pb-3">
          <p className="text-xs font-medium text-[#390d58]/70 mb-2">Required reports</p>
          <div className="flex flex-wrap gap-1.5">
            {automation.dependencies.map(dep => (
              <DependencyChip key={dep.report_type} dep={dep} />
            ))}
          </div>
        </CardContent>
      )}

      <CardContent className="pt-0">
        {isTool ? (
          <Button
            onClick={onOpenTool}
            disabled={automation.status === 'dependencies_missing'}
            className="w-full gap-2 bg-[#390d58] hover:bg-[#4a1170] text-white group-hover:gap-3 transition-all disabled:opacity-50"
          >
            Open Tool <ArrowRight className="h-4 w-4" />
          </Button>
        ) : (
          <div className="flex gap-2">
            <Button
              onClick={onRun}
              disabled={!automation.deps_met || running || automation.status === 'running'}
              className="flex-1 gap-2 bg-[#390d58] hover:bg-[#4a1170] text-white disabled:opacity-50"
            >
              {running || automation.status === 'running'
                ? <><Loader2 className="h-4 w-4 animate-spin" /> Running…</>
                : <><Play className="h-4 w-4" /> Run Analysis</>}
            </Button>
            {hasResults && (
              <Button
                onClick={onViewResults}
                variant="outline"
                className="gap-1.5 border-[#390d58]/20 text-[#390d58] hover:bg-[#390d58]/10"
              >
                <Eye className="h-4 w-4" /> Results
              </Button>
            )}
          </div>
        )}
      </CardContent>
    </Card>
  )
}

function StatusBadge({ status }: { status: AutomationListItem['status'] }) {
  if (status === 'tool') {
    return <Badge className="text-xs bg-[#390d58] text-white shrink-0">Tool</Badge>
  }
  if (status === 'completed') {
    return <Badge className="text-xs bg-green-600 text-white shrink-0">Done</Badge>
  }
  if (status === 'running') {
    return <Badge className="text-xs bg-blue-600 text-white shrink-0">Running</Badge>
  }
  if (status === 'failed') {
    return <Badge className="text-xs bg-red-600 text-white shrink-0">Failed</Badge>
  }
  if (status === 'dependencies_missing') {
    return <Badge variant="outline" className="text-xs text-muted-foreground shrink-0">Needs Reports</Badge>
  }
  return <Badge variant="outline" className="text-xs text-[#390d58] border-[#390d58]/30 shrink-0">Ready</Badge>
}

function DependencyChip({ dep }: { dep: AutomationDependencyItem }) {
  const colours = {
    ok:      'bg-green-50 text-green-700 border-green-200',
    missing: 'bg-red-50 text-red-600 border-red-200',
    stale:   'bg-amber-50 text-amber-700 border-amber-200',
  }

  const icons = {
    ok:      <CheckCircle2 className="h-3 w-3" />,
    missing: <AlertCircle className="h-3 w-3" />,
    stale:   <Clock className="h-3 w-3" />,
  }

  return (
    <span className={`inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[10px] font-medium ${colours[dep.status]}`}>
      {icons[dep.status]}
      {dep.label}
      {dep.max_age_days && dep.status !== 'missing' && (
        <span className="opacity-60">· {dep.max_age_days}d</span>
      )}
    </span>
  )
}
