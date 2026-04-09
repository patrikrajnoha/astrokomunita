import {
  extractFirstUploadValidationMessage,
  resolveUploadRequestMessage,
} from '@/utils/imageUpload'

export function extractObservationError(error, fallbackMessage = 'Request failed.') {
  const responseData = error?.response?.data
  const validationMessage = extractFirstUploadValidationMessage(
    responseData?.errors,
    '',
    { context: 'observation' },
  )
  if (validationMessage) return validationMessage

  return resolveUploadRequestMessage(error, fallbackMessage, { context: 'observation' })
}
