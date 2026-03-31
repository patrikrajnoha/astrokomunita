<template>
  <form class="searchBar" role="search" @submit.prevent="emit('submit')">
    <label :for="inputId" class="searchBar__label">Hľadať</label>

    <div class="searchBar__field">
      <span class="searchBar__icon" aria-hidden="true">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9" d="M21 21l-6-6m1-4a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
      </span>

      <input
        :id="inputId"
        ref="inputRef"
        v-model="localQuery"
        type="text"
        :placeholder="placeholder"
        class="searchBar__input"
        autocomplete="off"
        aria-label="Hľadať"
      />

      <div class="searchBar__actions">
        <button
          v-if="hasQuery"
          type="button"
          class="searchBar__clear"
          aria-label="Vymazať hľadanie"
          @click="clearQuery"
        >
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>

        <span
          v-if="loading"
          class="searchBar__spinner"
          aria-hidden="true"
        ></span>
      </div>
    </div>
  </form>
</template>

<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
  modelValue: {
    type: String,
    default: '',
  },
  loading: {
    type: Boolean,
    default: false,
  },
  placeholder: {
    type: String,
    default: 'Napíš kľúčové slovo',
  },
})

const emit = defineEmits(['update:modelValue', 'submit'])

const inputRef = ref(null)
const inputId = `global-search-${Math.random().toString(36).slice(2, 9)}`

const localQuery = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
})

const hasQuery = computed(() => String(props.modelValue || '').trim().length > 0)

const clearQuery = () => {
  emit('update:modelValue', '')
  inputRef.value?.focus()
}
</script>

<style scoped>
.searchBar {
  display: grid;
  gap: 0.35rem;
}

.searchBar__label {
  display: inline-flex;
  align-items: center;
  margin: 0;
  color: #ABB8C9;
  font-size: 0.74rem;
  font-weight: 600;
  line-height: 1.2;
}

.searchBar__field {
  position: relative;
}

.searchBar__icon {
  pointer-events: none;
  position: absolute;
  left: 0.72rem;
  top: 50%;
  transform: translateY(-50%);
  color: rgba(171, 184, 201, 0.84);
}

.searchBar__input {
  width: 100%;
  min-height: 42px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 0.75rem;
  background: rgba(21, 29, 40, 0.48);
  color: #ffffff;
  padding: 0.6rem 4.3rem 0.6rem 2.2rem;
  font-size: 0.875rem;
  line-height: 1.4;
  transition: border-color 150ms ease, background-color 150ms ease, box-shadow 150ms ease;
}

.searchBar__input::placeholder {
  color: rgba(171, 184, 201, 0.74);
}

.searchBar__input:hover {
  border-color: rgba(255, 255, 255, 0.16);
}

.searchBar__input:focus-visible {
  outline: none;
  border-color: rgba(15, 115, 255, 0.84);
  box-shadow: 0 0 0 2px #0F73FF;
}

.searchBar__actions {
  position: absolute;
  right: 0.55rem;
  top: 50%;
  transform: translateY(-50%);
  display: flex;
  align-items: center;
  gap: 0.3rem;
}

.searchBar__clear {
  width: 28px;
  height: 28px;
  border: 1px solid transparent;
  border-radius: 999px;
  background: transparent;
  color: #ABB8C9;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: color 150ms ease, background-color 150ms ease, border-color 150ms ease;
}

.searchBar__clear:hover {
  color: #ffffff;
  border-color: rgba(255, 255, 255, 0.08);
  background: rgba(21, 29, 40, 0.72);
}

.searchBar__clear:focus-visible {
  outline: none;
  box-shadow: 0 0 0 2px #0F73FF;
}

.searchBar__spinner {
  width: 14px;
  height: 14px;
  border-radius: 999px;
  border: 2px solid rgba(171, 184, 201, 0.3);
  border-top-color: #0F73FF;
  animation: search-spinner 0.7s linear infinite;
}

@keyframes search-spinner {
  to {
    transform: rotate(360deg);
  }
}
</style>
