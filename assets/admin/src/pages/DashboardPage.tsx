import { Dashboard } from '@/components/beacon/dashboard'
import { OnboardingOverlay } from '@/components/beacon/OnboardingOverlay'

export function DashboardPage() {
  return (
    <>
      <OnboardingOverlay screen="dashboard" />
      <Dashboard />
    </>
  )
}
