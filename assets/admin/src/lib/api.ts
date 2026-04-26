/**
 * Typed fetch client for the Beacon admin REST API (beacon/v1/admin/*).
 *
 * All requests are authenticated with the WP REST nonce injected by
 * AdminAssets::scriptData() via wp_localize_script.
 *
 * Failures (non-2xx + network errors) are fire-and-forget reported to
 * /admin/log/client-error so they reach the plugin's Debug Log table
 * instead of disappearing into a generic UI toast.
 */

declare global {
  interface Window {
    __beaconErrorHandlersInstalled?: boolean
  }
}

function getRestBase(): string {
  return window.BeaconData?.restBase ?? ''
}

function getNonce(): string {
  return window.BeaconData?.nonce ?? ''
}

export class ApiError extends Error {
  constructor(
    message: string,
    public readonly status: number,
    public readonly code?: string,
  ) {
    super(message)
    this.name = 'ApiError'
  }
}

// Module-scope rate limit: drop client-error reports beyond this many per minute.
// The server has its own (5-minute / 60-event) window — this just spares an
// outage from posting hundreds of duplicates per second from one tab.
const REPORT_LIMIT_PER_MINUTE = 30
const reportTimestamps: number[] = []

function shouldReport(): boolean {
  const now = Date.now()
  while (reportTimestamps.length > 0 && now - reportTimestamps[0] > 60_000) {
    reportTimestamps.shift()
  }
  if (reportTimestamps.length >= REPORT_LIMIT_PER_MINUTE) {
    return false
  }
  reportTimestamps.push(now)
  return true
}

interface ClientErrorReport {
  type: 'api' | 'network' | 'parse' | 'javascript'
  path: string
  method?: string
  status?: number
  code?: string
  message: string
  screen?: string
}

function reportClientError(report: ClientErrorReport): void {
  // Never recurse: reports themselves must not trigger more reports if they fail.
  if (!shouldReport()) return
  if (report.path === '/log/client-error') return

  try {
    const url = `${getRestBase()}/log/client-error`
    const body = JSON.stringify({
      ...report,
      screen: report.screen ?? window.location.hash ?? window.location.pathname,
    })

    // sendBeacon survives page unload and is no-throw; preferred when available.
    // It can't set the WP REST nonce header, so fall back to fetch when authenticated reads matter.
    // For a low-volume diagnostic post with manage_options gate, fetch keepalive is fine.
    void fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': getNonce() },
      body,
      keepalive: true,
    }).catch(() => {
      /* swallow — never recurse */
    })
  } catch {
    /* never throw from the reporter */
  }
}

async function request<T>(
  method: string,
  path: string,
  body?: unknown,
): Promise<T> {
  const url = `${getRestBase()}${path}`

  const headers: Record<string, string> = {
    'X-WP-Nonce': getNonce(),
  }

  let fetchBody: BodyInit | undefined

  if (body instanceof FormData) {
    // Let the browser set Content-Type with the boundary
    fetchBody = body
  } else if (body !== undefined) {
    headers['Content-Type'] = 'application/json'
    fetchBody = JSON.stringify(body)
  }

  let res: Response
  try {
    res = await fetch(url, { method, headers, body: fetchBody })
  } catch (e) {
    // Network failure (DNS, offline, CORS, abort, etc). Fetch never reached the server.
    const message = e instanceof Error ? e.message : String(e)
    reportClientError({ type: 'network', path, method, message })
    throw e
  }

  if (!res.ok) {
    const data = await res.json().catch(() => ({})) as Record<string, unknown>
    const apiError = new ApiError(
      (data.message as string | undefined) ?? `HTTP ${res.status}`,
      res.status,
      data.code as string | undefined,
    )
    reportClientError({
      type: 'api',
      path,
      method,
      status: res.status,
      code: apiError.code,
      message: apiError.message,
    })
    throw apiError
  }

  // 204 No Content
  if (res.status === 204) {
    return undefined as unknown as T
  }

  try {
    return await (res.json() as Promise<T>)
  } catch (e) {
    const message = e instanceof Error ? e.message : String(e)
    reportClientError({ type: 'parse', path, method, status: res.status, message })
    throw e
  }
}

// Surface uncaught JS errors and unhandled promise rejections inside the admin SPA.
// These are typically bugs in our own code, not API failures — log them at error
// level so they're easy to spot.
if (typeof window !== 'undefined' && !window.__beaconErrorHandlersInstalled) {
  window.__beaconErrorHandlersInstalled = true

  window.addEventListener('error', (event) => {
    if (!event?.error && !event?.message) return
    const message = event.error instanceof Error ? event.error.message : String(event.message ?? '')
    if (!message) return
    reportClientError({
      type: 'javascript',
      path: event.filename ?? 'window',
      message: message.slice(0, 480),
    })
  })

  window.addEventListener('unhandledrejection', (event) => {
    const reason = event?.reason
    const message = reason instanceof Error ? reason.message : String(reason ?? 'Unhandled promise rejection')
    reportClientError({
      type: 'javascript',
      path: 'unhandledrejection',
      message: message.slice(0, 480),
    })
  })
}

export const api = {
  get:    <T>(path: string)                  => request<T>('GET',    path),
  post:   <T>(path: string, body?: unknown)  => request<T>('POST',   path, body),
  put:    <T>(path: string, body?: unknown)  => request<T>('PUT',    path, body),
  patch:  <T>(path: string, body?: unknown)  => request<T>('PATCH',  path, body),
  delete: <T>(path: string, body?: unknown)  => request<T>('DELETE', path, body),

  /** For file uploads — pass a FormData instance as body. */
  upload: <T>(path: string, form: FormData) => request<T>('POST', path, form),
}
