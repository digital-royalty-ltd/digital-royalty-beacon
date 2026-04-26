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
import { ApiPage }            from '@/pages/ApiPage'
import { DevelopmentPage }   from '@/pages/DevelopmentPage'

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
      beaconApiBase:  string
      dismissedOnboardingScreens: string[]
    }
  }
}

function Layout() {
  // Desktop: full-viewport split — header at top, scrollable main + sticky
  // sidebar below. Mobile: single column, page scrolls naturally with the
  // sidebar stacked under main content (the sidebar's width would dominate
  // the viewport otherwise).
  return (
    <div className="flex flex-col min-h-screen lg:h-screen bg-background lg:overflow-hidden">
      <Header />
      <div className="flex flex-col lg:flex-row flex-1 lg:overflow-hidden">
        <main className="flex-1 lg:overflow-y-auto px-4 sm:px-6 py-6 sm:py-8 min-w-0">
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
      { path: 'api',              element: <ApiPage />            },
      { path: 'development',     element: <DevelopmentPage />   },
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
