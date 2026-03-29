import { Debug } from '@/components/beacon/debug'
import { OnboardingOverlay } from '@/components/beacon/OnboardingOverlay'

export function DebugPage() {
  return (
    <>
      <OnboardingOverlay screen="debug" />
      <Debug />
    </>
  )
}
