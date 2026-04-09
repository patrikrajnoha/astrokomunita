import api from '@/services/api'

export function listObservations(params = {}) {
  const query = {}

  if (params.mine) query.mine = 1
  if (params.public_only) query.public_only = 1
  if (params.page) query.page = Number(params.page)
  if (params.per_page) query.per_page = Number(params.per_page)
  if (params.event_id) query.event_id = Number(params.event_id)
  if (params.user_id) query.user_id = Number(params.user_id)
  if (params.sort) {
    const sort = String(params.sort).toLowerCase()
    if (sort === 'newest' || sort === 'oldest') {
      query.sort = sort
    }
  }

  return api.get('/observations', {
    params: query,
    meta: {
      requiresAuth: Boolean(query.mine),
      skipAuthRedirect: Boolean(query.mine),
    },
  })
}

export function getObservation(observationId, config = {}) {
  return api.get(`/observations/${encodeURIComponent(observationId)}`, config)
}

export function createObservation(payload = {}, config = {}) {
  const body = new FormData()
  appendObservationFields(body, payload, { requireTitle: true, requireObservedAt: true })
  appendImages(body, payload.images)

  return api.post('/observations', body, {
    ...config,
    meta: {
      ...(config.meta || {}),
      requiresAuth: true,
    },
  })
}

export function updateObservation(observationId, payload = {}, config = {}) {
  const body = new FormData()
  appendObservationFields(body, payload, { requireTitle: false, requireObservedAt: false })
  appendImages(body, payload.images)
  appendRemoveMediaIds(body, payload.remove_media_ids)
  body.append('_method', 'PATCH')

  return api.post(`/observations/${encodeURIComponent(observationId)}`, body, {
    ...config,
    meta: {
      ...(config.meta || {}),
      requiresAuth: true,
    },
  })
}

export function deleteObservation(observationId, config = {}) {
  return api.delete(`/observations/${encodeURIComponent(observationId)}`, {
    ...config,
    meta: {
      ...(config.meta || {}),
      requiresAuth: true,
    },
  })
}

function appendObservationFields(formData, payload, options = {}) {
  const { requireTitle = false, requireObservedAt = false } = options

  if (requireTitle || Object.prototype.hasOwnProperty.call(payload, 'title')) {
    formData.append('title', String(payload?.title || '').trim())
  }

  if (Object.prototype.hasOwnProperty.call(payload, 'description')) {
    formData.append('description', String(payload?.description || '').trim())
  }

  if (requireObservedAt || Object.prototype.hasOwnProperty.call(payload, 'observed_at')) {
    formData.append('observed_at', String(payload?.observed_at || '').trim())
  }

  if (Object.prototype.hasOwnProperty.call(payload, 'event_id')) {
    const eventId = Number(payload?.event_id || 0)
    formData.append('event_id', Number.isInteger(eventId) && eventId > 0 ? String(eventId) : '')
  }

  if (Object.prototype.hasOwnProperty.call(payload, 'location_lat')) {
    const value = payload?.location_lat
    formData.append('location_lat', value === null || value === undefined ? '' : String(value).trim())
  }

  if (Object.prototype.hasOwnProperty.call(payload, 'location_lng')) {
    const value = payload?.location_lng
    formData.append('location_lng', value === null || value === undefined ? '' : String(value).trim())
  }

  if (Object.prototype.hasOwnProperty.call(payload, 'location_name')) {
    formData.append('location_name', String(payload?.location_name || '').trim())
  }

  if (Object.prototype.hasOwnProperty.call(payload, 'visibility_rating')) {
    const rating = Number(payload?.visibility_rating || 0)
    formData.append('visibility_rating', Number.isInteger(rating) && rating > 0 ? String(rating) : '')
  }

  if (Object.prototype.hasOwnProperty.call(payload, 'equipment')) {
    formData.append('equipment', String(payload?.equipment || '').trim())
  }

  if (Object.prototype.hasOwnProperty.call(payload, 'is_public')) {
    formData.append('is_public', payload?.is_public ? '1' : '0')
  }
}

function appendImages(formData, images) {
  if (!Array.isArray(images)) return

  images.forEach((image) => {
    if (image instanceof Blob) {
      formData.append('images[]', image)
    }
  })
}

function appendRemoveMediaIds(formData, removeMediaIds) {
  if (!Array.isArray(removeMediaIds)) return

  removeMediaIds
    .map((id) => Number(id || 0))
    .filter((id) => Number.isInteger(id) && id > 0)
    .forEach((id) => {
      formData.append('remove_media_ids[]', String(id))
    })
}
