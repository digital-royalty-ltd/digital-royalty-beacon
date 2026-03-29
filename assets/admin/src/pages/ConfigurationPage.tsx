import { useState } from 'react'
import { Configuration } from '@/components/beacon/configuration'
import { DangerZoneCard } from '@/components/beacon/DangerZoneCard'
import { UpdateChannelCard } from '@/components/beacon/UpdateChannelCard'
import { ConnectionsSection } from '@/components/beacon/connections/ConnectionsSection'
import { ReportsSection } from '@/components/beacon/reports/ReportsSection'
import { ReportEditor } from '@/components/beacon/reports/ReportEditor'
import { OnboardingOverlay } from '@/components/beacon/OnboardingOverlay'

export function ConfigurationPage() {
  const [editingReport, setEditingReport] = useState<string | null>(null)

  if (editingReport) {
    return (
      <ReportEditor
        reportType={editingReport}
        onBack={() => setEditingReport(null)}
      />
    )
  }

  return (
    <div className="space-y-6">
      <OnboardingOverlay screen="configuration" />
      <Configuration />
      <UpdateChannelCard />
      <ConnectionsSection />
      <ReportsSection onSelect={setEditingReport} />
      <DangerZoneCard />
    </div>
  )
}
