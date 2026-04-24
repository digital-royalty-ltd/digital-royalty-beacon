import { AlertTriangle, X } from 'lucide-react'
import { Button } from '@/components/ui/button'

interface Props {
  changedFields: string[]
  currentDay:    number | null
  totalDays:     number
  onConfirm:     () => void
  onCancel:      () => void
}

const FIELD_LABELS: Record<string, string> = {
  goal:         'Goal',
  primary_kpi:  'Primary KPI',
  agent:        'Agent',
}

/**
 * Modal shown before saving any edit that would restart the 90-day warm-up.
 *
 * Changes to goal, KPI, or the assigned agent reset the clock — the client
 * needs to confirm they understand before we persist the edit.
 */
export function WarmupResetModal({ changedFields, currentDay, totalDays, onConfirm, onCancel }: Props) {
  const daysCompleted = currentDay ?? 0
  const labels = changedFields.map(f => FIELD_LABELS[f] ?? f).join(' and ')

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div className="w-full max-w-md bg-white rounded-2xl shadow-xl overflow-hidden">
        <div className="px-5 py-4 border-b flex items-center justify-between">
          <div className="flex items-center gap-2 text-amber-600">
            <AlertTriangle className="h-4 w-4" />
            <h3 className="text-sm font-semibold">Restart warm-up?</h3>
          </div>
          <button onClick={onCancel} className="text-muted-foreground hover:text-foreground">
            <X className="h-4 w-4" />
          </button>
        </div>

        <div className="px-5 py-4 space-y-3 text-sm">
          <p>
            Changing <span className="font-medium">{labels}</span> will restart the 90-day warm-up for this channel.
          </p>
          {daysCompleted > 0 && (
            <p className="text-muted-foreground text-xs">
              You're currently on day {daysCompleted} of {totalDays}. That progress will reset.
            </p>
          )}
          <p className="text-xs text-muted-foreground">
            During warm-up your agent focuses on audit, analysis, and initial setup rather than aggressive execution.
            You can't judge channel performance reliably during this period.
          </p>
        </div>

        <div className="flex items-center justify-end gap-2 px-5 py-3 border-t bg-muted/20">
          <Button variant="outline" size="sm" onClick={onCancel}>
            Cancel
          </Button>
          <Button
            size="sm"
            onClick={onConfirm}
            className="bg-amber-600 hover:bg-amber-700 text-white"
          >
            Restart warm-up
          </Button>
        </div>
      </div>
    </div>
  )
}
