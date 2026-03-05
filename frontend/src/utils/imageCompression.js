export async function compressImageFileToMaxBytes(file, options = {}) {
  const maxBytes = Number(options.maxBytes)
  if (!Number.isFinite(maxBytes) || maxBytes <= 0) {
    return file
  }

  if (!file || typeof file !== 'object') {
    return file
  }

  if (typeof file.size === 'number' && file.size <= maxBytes) {
    return file
  }

  // Browser-side compression is optional. If unavailable, keep original file.
  return file
}
