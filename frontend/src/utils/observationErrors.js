export function extractObservationError(error, fallbackMessage = 'Request failed.') {
  const responseData = error?.response?.data
  const validationErrors = responseData?.errors

  if (validationErrors && typeof validationErrors === 'object') {
    const imagesEntry = validationErrors.images
    if (Array.isArray(imagesEntry) && imagesEntry.length > 0) {
      return String(imagesEntry[0] || fallbackMessage)
    }

    const firstKey = Object.keys(validationErrors)[0]
    const firstValue = firstKey ? validationErrors[firstKey] : null
    if (Array.isArray(firstValue) && firstValue.length > 0) {
      return String(firstValue[0] || fallbackMessage)
    }
  }

  return responseData?.message || error?.userMessage || fallbackMessage
}

