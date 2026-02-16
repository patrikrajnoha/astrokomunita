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
