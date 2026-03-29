import { useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { AlertTriangle, Loader2, Trash2 } from 'lucide-react'
import { api } from '@/lib/api'

export function DangerZoneCard() {
  const hasApiKey = window.BeaconData?.hasApiKey ?? false
  const [disconnecting, setDisconnecting] = useState(false)

  if (!hasApiKey) return null

  const handleDisconnect = async () => {
    if (!confirm('Disconnect Beacon and remove API key?')) return
    setDisconnecting(true)
    try {
      await api.delete('/config/api-key')
      if (window.BeaconData) {
        window.BeaconData.hasApiKey   = false
        window.BeaconData.isConnected = false
      }
      // Full reload — the whole configuration view depends on connection state
      window.location.reload()
    } finally {
      setDisconnecting(false)
    }
  }

  return (
    <Card className="border-destructive/30 overflow-hidden">
      <div className="h-1 bg-gradient-to-r from-destructive to-red-400" />
      <CardHeader>
        <div className="flex items-center gap-4">
          <div className="rounded-xl bg-destructive/10 p-3 text-destructive">
            <AlertTriangle className="h-5 w-5" />
          </div>
          <div>
            <CardTitle className="text-lg text-destructive">Danger Zone</CardTitle>
            <CardDescription>Irreversible actions that affect your Beacon connection</CardDescription>
          </div>
        </div>
      </CardHeader>
      <CardContent>
        <div className="flex items-center justify-between p-5 rounded-xl bg-destructive/5 border border-destructive/20">
          <div>
            <p className="font-medium text-destructive">Disconnect &amp; Clear Data</p>
            <p className="text-sm text-muted-foreground mt-1">
              Remove API key and reset all local Beacon data
            </p>
          </div>
          <Button
            variant="destructive"
            className="gap-2 shadow-md shadow-destructive/20"
            onClick={handleDisconnect}
            disabled={disconnecting}
          >
            {disconnecting
              ? <Loader2 className="h-4 w-4 animate-spin" />
              : <Trash2 className="h-4 w-4" />}
            Disconnect
          </Button>
        </div>
      </CardContent>
    </Card>
  )
}
