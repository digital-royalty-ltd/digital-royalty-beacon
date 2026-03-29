import { Component, type ErrorInfo, type ReactNode } from 'react'
import { AlertTriangle } from 'lucide-react'
import { Button } from '@/components/ui/button'

interface Props { children: ReactNode }
interface State { error: Error | null }

export class ErrorBoundary extends Component<Props, State> {
  state: State = { error: null }

  static getDerivedStateFromError(error: Error): State {
    return { error }
  }

  componentDidCatch(error: Error, info: ErrorInfo) {
    // Log to console for debugging; could post to a REST endpoint in future.
    console.error('[Beacon] Unhandled error:', error, info.componentStack)
  }

  render() {
    if (this.state.error) {
      return (
        <div className="flex h-screen items-center justify-center bg-background p-8">
          <div className="max-w-md text-center space-y-4">
            <div className="mx-auto rounded-2xl bg-red-50 p-5 w-fit">
              <AlertTriangle className="h-10 w-10 text-red-500" />
            </div>
            <h2 className="text-xl font-semibold text-[#390d58]">Something went wrong</h2>
            <p className="text-sm text-muted-foreground">
              An unexpected error occurred in the Beacon interface.
            </p>
            <pre className="text-left text-xs bg-muted rounded-lg p-3 overflow-auto max-h-32 text-red-600">
              {this.state.error.message}
            </pre>
            <Button
              onClick={() => this.setState({ error: null })}
              className="bg-[#390d58] hover:bg-[#4a1170] text-white"
            >
              Try again
            </Button>
          </div>
        </div>
      )
    }

    return this.props.children
  }
}
