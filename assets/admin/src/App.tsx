import { createHashRouter, RouterProvider, Outlet } from 'react-router-dom'
import { Header } from '@/components/layout/Header'
import { AppSidebar } from '@/components/layout/AppSidebar'
import { ErrorBoundary } from '@/components/ErrorBoundary'
import { DashboardPage }      from '@/pages/DashboardPage'
import { WorkshopPage }       from '@/pages/WorkshopPage'
import { WorkshopToolPage }   from '@/pages/WorkshopToolPage'
import { AutomationsPage }    from '@/pages/AutomationsPage'
import { CampaignsPage }      from '@/pages/MissionControlPage'
import { ConfigurationPage }  from '@/pages/ConfigurationPage'
import { DebugPage }          from '@/pages/DebugPage'

declare global {
  interface Window {
    BeaconData?: {
      hasApiKey:      boolean
      isConnected:    boolean
      siteUrl:        string
      siteName:       string
      nonce:          string
      restBase:       string
      adminUrl:       string
      pluginVersion:  string
      dismissedOnboardingScreens: string[]
    }
  }
}

function Layout() {
  return (
    <div className="flex flex-col h-screen bg-background overflow-hidden">
      <Header />
      <div className="flex flex-1 overflow-hidden">
        <main className="flex-1 overflow-y-auto px-6 py-8">
          <Outlet />
        </main>
        <AppSidebar />
      </div>
    </div>
  )
}

const router = createHashRouter([
  {
    path: '/',
    element: <Layout />,
    children: [
      { index: true,              element: <DashboardPage />      },
      {
        path: 'workshop',
        element: <WorkshopPage />,
        children: [
          { path: ':slug', element: <WorkshopToolPage /> },
        ],
      },
      { path: 'automations',      element: <AutomationsPage />    },
      { path: 'campaigns',        element: <CampaignsPage /> },
      { path: 'configuration',    element: <ConfigurationPage />  },
      { path: 'debug',            element: <DebugPage />          },
    ],
  },
])

export function App() {
  return (
    <ErrorBoundary>
      <RouterProvider router={router} />
    </ErrorBoundary>
  )
}
