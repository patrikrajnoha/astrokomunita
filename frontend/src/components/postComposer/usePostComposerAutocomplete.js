import { ref } from 'vue'
import api from '@/services/api'

export function usePostComposerAutocomplete({
  autoResize,
  content,
  err,
  isSubmitDisabled,
  submit,
  textareaRef,
}) {
  const showAutocomplete = ref(false)
  const suggestions = ref([])
  const selectedIndex = ref(0)
  const autocompletePosition = ref({ top: 0, left: 0 })
  const currentHashtagStart = ref(0)
  const suggestionCache = ref(new Map())
  let debounceTimer = null
  let blurTimer = null

  function updateAutocompletePosition(textarea) {
    const rect = textarea.getBoundingClientRect()
    const lineHeight = 24
    const lineHeightPx = parseInt(getComputedStyle(textarea).lineHeight, 10) || lineHeight

    const lines = content.value.substring(0, textarea.selectionStart).split('\n')
    const currentLine = lines.length - 1
    const charInLine = lines[lines.length - 1].length

    autocompletePosition.value = {
      top: rect.top + window.scrollY + currentLine * lineHeightPx + lineHeightPx,
      left: rect.left + window.scrollX + Math.min(charInLine * 8, rect.width - 200),
    }
  }

  async function fetchSuggestions(query) {
    if (suggestionCache.value.has(query)) {
      suggestions.value = suggestionCache.value.get(query)
      selectedIndex.value = 0
      return
    }

    if (debounceTimer !== null) {
      clearTimeout(debounceTimer)
    }

    debounceTimer = window.setTimeout(async () => {
      try {
        const res = await api.get(`/tags/suggest?q=${encodeURIComponent(query)}&limit=8`)
        const data = res.data || []
        suggestions.value = data
        selectedIndex.value = 0
        suggestionCache.value.set(query, data)
      } catch {
        suggestions.value = []
      }
    }, 200)
  }

  function hideAutocomplete() {
    showAutocomplete.value = false
    suggestions.value = []
    selectedIndex.value = 0
  }

  function selectSuggestion(suggestion) {
    if (!suggestion) return

    const cursorPos = textareaRef.value?.selectionStart || content.value.length
    const beforeHashtag = content.value.substring(0, currentHashtagStart.value)
    const afterHashtag = content.value.substring(cursorPos)

    content.value = `${beforeHashtag}#${suggestion.name} ${afterHashtag}`

    window.setTimeout(() => {
      const newCursorPos = beforeHashtag.length + suggestion.name.length + 2
      if (textareaRef.value) {
        textareaRef.value.setSelectionRange(newCursorPos, newCursorPos)
        textareaRef.value.focus()
        autoResize()
      }
    }, 0)

    hideAutocomplete()
  }

  function onTyping(event) {
    autoResize()
    if (err.value && content.value.length <= 2000) err.value = ''

    const target = event?.target
    if (!target) return

    const cursorPos = target.selectionStart
    const textBefore = content.value.substring(0, cursorPos)
    const hashtagMatch = textBefore.match(/#([a-zA-Z0-9_]*)$/)

    if (hashtagMatch) {
      const query = hashtagMatch[1]
      if (query.length >= 1) {
        showAutocomplete.value = true
        currentHashtagStart.value = cursorPos - hashtagMatch[0].length
        fetchSuggestions(query)
        updateAutocompletePosition(target)
      } else {
        hideAutocomplete()
      }
      return
    }

    hideAutocomplete()
  }

  function onKeydown(event) {
    if (showAutocomplete.value && suggestions.value.length > 0) {
      switch (event.key) {
        case 'ArrowDown':
          event.preventDefault()
          selectedIndex.value = (selectedIndex.value + 1) % suggestions.value.length
          return
        case 'ArrowUp':
          event.preventDefault()
          selectedIndex.value = selectedIndex.value === 0 ? suggestions.value.length - 1 : selectedIndex.value - 1
          return
        case 'Enter':
        case 'Tab':
          event.preventDefault()
          if (selectedIndex.value >= 0 && selectedIndex.value < suggestions.value.length) {
            selectSuggestion(suggestions.value[selectedIndex.value])
          }
          return
        case 'Escape':
          event.preventDefault()
          hideAutocomplete()
          return
      }
    }

    if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
      event.preventDefault()
      if (!isSubmitDisabled.value) {
        submit()
      }
    }
  }

  function onBlur() {
    if (blurTimer !== null) {
      clearTimeout(blurTimer)
    }
    blurTimer = window.setTimeout(hideAutocomplete, 200)
  }

  function cleanup() {
    if (debounceTimer !== null) {
      clearTimeout(debounceTimer)
      debounceTimer = null
    }
    if (blurTimer !== null) {
      clearTimeout(blurTimer)
      blurTimer = null
    }
  }

  return {
    autocompletePosition,
    cleanupAutocomplete: cleanup,
    onBlur,
    onKeydown,
    onTyping,
    selectSuggestion,
    selectedIndex,
    showAutocomplete,
    suggestions,
  }
}
