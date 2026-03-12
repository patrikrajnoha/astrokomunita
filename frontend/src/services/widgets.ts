import api from './api'

export type UpcomingEventWidgetItem = {
  id: number
  title: string
  slug: string | null
  start_at: string | null
}

export type UpcomingEventsWidgetPayload = {
  items: UpcomingEventWidgetItem[]
  generated_at: string
}

export async function getUpcomingEventsWidget(): Promise<UpcomingEventsWidgetPayload> {
  const response = await api.get<UpcomingEventsWidgetPayload>('/events/widget/upcoming')
  return response.data
}

export type MoonPhaseWidgetItem = {
  key: string
  label: string
  start_at: string
  end_at: string
  start_date: string
  end_date: string
  is_current: boolean
}

export type MoonPhasesWidgetPayload = {
  reference_at: string
  reference_date: string
  timezone: string
  current_phase: string
  phases: MoonPhaseWidgetItem[]
  source: {
    provider: string
    label: string
    url: string
    api_key_required: boolean
  }
}

export type MoonPhasesWidgetQuery = {
  lat?: number
  lon?: number
  tz?: string
  date?: string
}

export async function getMoonPhasesWidget(query: MoonPhasesWidgetQuery = {}): Promise<MoonPhasesWidgetPayload> {
  const response = await api.get<MoonPhasesWidgetPayload>('/sky/moon-phases', {
    params: query,
  })

  return response.data
}
