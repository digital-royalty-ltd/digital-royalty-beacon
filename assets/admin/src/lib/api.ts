/**
 * Typed fetch client for the Beacon admin REST API (beacon/v1/admin/*).
 *
 * All requests are authenticated with the WP REST nonce injected by
 * AdminAssets::scriptData() via wp_localize_script.
 */

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

  const res = await fetch(url, { method, headers, body: fetchBody })

  if (!res.ok) {
    const data = await res.json().catch(() => ({})) as Record<string, unknown>
    throw new ApiError(
      (data.message as string | undefined) ?? `HTTP ${res.status}`,
      res.status,
      data.code as string | undefined,
    )
  }

  // 204 No Content
  if (res.status === 204) {
    return undefined as unknown as T
  }

  return res.json() as Promise<T>
}

export const api = {
  get:    <T>(path: string)                  => request<T>('GET',    path),
  post:   <T>(path: string, body?: unknown)  => request<T>('POST',   path, body),
  put:    <T>(path: string, body?: unknown)  => request<T>('PUT',    path, body),
  patch:  <T>(path: string, body?: unknown)  => request<T>('PATCH',  path, body),
  delete: <T>(path: string)                  => request<T>('DELETE', path),

  /** For file uploads — pass a FormData instance as body. */
  upload: <T>(path: string, form: FormData) => request<T>('POST', path, form),
}
