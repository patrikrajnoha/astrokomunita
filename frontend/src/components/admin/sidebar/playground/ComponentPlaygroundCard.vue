<template>
  <article class="playCard" :class="{ compact }">
    <header class="playCardHeader">
      <div class="titleWrap">
        <p class="category">{{ entry.category }}</p>
        <h3>{{ entry.label }}</h3>
        <p v-if="entry.description" class="description">{{ entry.description }}</p>
      </div>

      <div class="headActions">
        <button v-if="controls.length" type="button" class="ghostBtn" @click="showEditor = !showEditor">
          {{ showEditor ? 'Skryt props' : `Props (${controls.length})` }}
        </button>
        <button type="button" class="ghostBtn" @click="resetToDefaults">Reset</button>
        <button type="button" class="ghostBtn" @click="showCode = !showCode">
          {{ showCode ? 'Skryt payload' : 'Payload' }}
        </button>
      </div>
    </header>

    <div v-if="variantOptions.length" class="variantsRow">
      <span class="variantsLabel">Variant:</span>
      <div class="variantChips">
        <button
          v-for="variant in variantOptions"
          :key="variant.id"
          type="button"
          class="chipBtn"
          :class="{ active: selectedVariantId === variant.id }"
          @click="selectVariant(variant.id)"
        >
          {{ variant.label }}
        </button>
      </div>
    </div>

    <div class="previewPane">
      <component :is="resolvedComponent" v-bind="resolvedProps" />
    </div>

    <div v-if="showEditor" class="editorPane">
      <p class="paneTitle">Editable props</p>

      <div v-if="controls.length === 0" class="emptyProps">
        Tento komponent nema nakonfigurovane editable props.
      </div>

      <div v-else class="controlList">
        <label v-for="control in controls" :key="control.key" class="controlRow">
          <span class="controlLabel">{{ control.label }}</span>

          <small v-if="control.helpText" class="controlHelp">{{ control.helpText }}</small>

          <input
            v-if="isTextLike(control.type)"
            :value="displayValue(control)"
            :type="control.type === CONTROL_TYPES.NUMBER ? 'number' : 'text'"
            class="controlInput"
            :step="control.step"
            :min="control.min"
            :max="control.max"
            @input="updateControl(control, $event.target.value)"
          />

          <textarea
            v-else-if="control.type === CONTROL_TYPES.TEXTAREA"
            :value="displayValue(control)"
            class="controlInput controlInputTextarea"
            rows="3"
            @input="updateControl(control, $event.target.value)"
          ></textarea>

          <select
            v-else-if="control.type === CONTROL_TYPES.SELECT"
            :value="String(displayValue(control))"
            class="controlInput"
            @change="updateControl(control, $event.target.value)"
          >
            <option
              v-for="option in control.options || []"
              :key="String(option.value)"
              :value="String(option.value)"
            >
              {{ option.label }}
            </option>
          </select>

          <label v-else-if="control.type === CONTROL_TYPES.BOOLEAN" class="toggleRow">
            <input
              type="checkbox"
              :checked="Boolean(displayValue(control))"
              @change="updateControl(control, $event.target.checked)"
            />
            <span>{{ Boolean(displayValue(control)) ? 'Zapnute' : 'Vypnute' }}</span>
          </label>

          <div class="defaultValue">Default: {{ describeControlDefault(control) }}</div>
        </label>
      </div>
    </div>

    <div class="metaRow">
      <span class="metaKey">ID: <code>{{ entry.id }}</code></span>
      <span class="metaKey">Zdroj: <code>{{ entry.sourcePath }}</code></span>
    </div>

    <details v-if="showCode" class="codeBlock" open>
      <summary>Aktualny props payload</summary>
      <pre>{{ serializedProps }}</pre>
    </details>
  </article>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import {
  createPropsState,
  describeControlDefault,
  getByPath,
  normalizeControlValue,
  PLAYGROUND_CONTROL_TYPES,
  setByPath,
} from '@/components/admin/sidebar/playground/controls'

const CONTROL_TYPES = PLAYGROUND_CONTROL_TYPES

const props = defineProps({
  entry: {
    type: Object,
    required: true,
  },
  compact: {
    type: Boolean,
    default: false,
  },
})

const selectedVariantId = ref('')
const propsState = ref({})
const showCode = ref(false)
const showEditor = ref(false)

const controls = computed(() => {
  return Array.isArray(props.entry?.editableProps) ? props.entry.editableProps : []
})

const variantOptions = computed(() => {
  return Array.isArray(props.entry?.variants) ? props.entry.variants : []
})

const resolvedComponent = computed(() => {
  return props.entry?.component || 'div'
})

const resolvedProps = computed(() => {
  if (typeof props.entry?.resolveProps === 'function') {
    return props.entry.resolveProps(propsState.value)
  }

  return propsState.value
})

const serializedProps = computed(() => {
  return JSON.stringify(resolvedProps.value, null, 2)
})

const initializeState = () => {
  const firstVariantId = variantOptions.value[0]?.id || ''
  selectedVariantId.value = String(firstVariantId || '')
  propsState.value = createPropsState(props.entry, selectedVariantId.value)
  showEditor.value = false
  showCode.value = false
}

const selectVariant = (variantId) => {
  selectedVariantId.value = String(variantId || '')
  propsState.value = createPropsState(props.entry, selectedVariantId.value)
}

const resetToDefaults = () => {
  propsState.value = createPropsState(props.entry, selectedVariantId.value)
}

const updateControl = (control, rawValue) => {
  const normalizedValue = normalizeControlValue(control, rawValue)
  propsState.value = setByPath(propsState.value, control.key, normalizedValue)
}

const displayValue = (control) => {
  return getByPath(propsState.value, control.key)
}

const isTextLike = (type) => {
  return type === CONTROL_TYPES.TEXT || type === CONTROL_TYPES.NUMBER
}

watch(
  () => props.entry?.id,
  () => {
    initializeState()
  },
  { immediate: true },
)
</script>

<style scoped>
.playCard {
  display: grid;
  gap: 0.58rem;
  border-radius: 0.84rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.24);
  background: rgb(var(--color-bg-rgb) / 0.22);
  padding: 0.66rem;
}

.playCard.compact {
  gap: 0.46rem;
  border-radius: 0.72rem;
  padding: 0.54rem;
}

.playCardHeader {
  display: flex;
  justify-content: space-between;
  gap: 0.56rem;
}

.playCard.compact .playCardHeader {
  gap: 0.42rem;
}

.titleWrap h3 {
  margin: 0.08rem 0 0;
  font-size: 0.92rem;
}

.playCard.compact .titleWrap h3 {
  margin-top: 0.04rem;
  font-size: 0.84rem;
}

.category {
  margin: 0;
  color: var(--color-text-secondary);
  font-size: 0.64rem;
  text-transform: uppercase;
  letter-spacing: 0.06em;
}

.description {
  margin: 0.18rem 0 0;
  color: var(--color-text-secondary);
  font-size: 0.72rem;
  line-height: 1.35;
}

.playCard.compact .description {
  margin-top: 0.12rem;
  font-size: 0.68rem;
  line-height: 1.28;
}

.headActions {
  display: inline-flex;
  align-items: flex-start;
  gap: 0.26rem;
  flex-wrap: wrap;
}

.playCard.compact .headActions {
  gap: 0.2rem;
}

.ghostBtn {
  min-height: 1.7rem;
  border-radius: 0.58rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.33);
  background: rgb(var(--color-bg-rgb) / 0.26);
  color: var(--color-surface);
  padding: 0.24rem 0.48rem;
  font-size: 0.68rem;
  font-weight: 600;
}

.playCard.compact .ghostBtn {
  min-height: 1.56rem;
  border-radius: 0.5rem;
  padding: 0.2rem 0.42rem;
  font-size: 0.64rem;
}

.variantsRow {
  display: flex;
  align-items: center;
  gap: 0.34rem;
  flex-wrap: wrap;
}

.playCard.compact .variantsRow {
  gap: 0.26rem;
}

.variantsLabel {
  color: var(--color-text-secondary);
  font-size: 0.68rem;
}

.variantChips {
  display: inline-flex;
  align-items: center;
  gap: 0.22rem;
  flex-wrap: wrap;
}

.chipBtn {
  min-height: 1.56rem;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.3);
  background: transparent;
  color: var(--color-text-secondary);
  padding: 0.14rem 0.46rem;
  font-size: 0.64rem;
  font-weight: 700;
}

.chipBtn.active {
  border-color: rgb(var(--color-primary-rgb) / 0.52);
  background: rgb(var(--color-primary-rgb) / 0.2);
  color: var(--color-surface);
}

.previewPane {
  border-radius: 0.72rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.18);
  background: rgb(var(--color-bg-rgb) / 0.25);
  padding: 0.52rem;
  min-height: 8.8rem;
  display: grid;
  align-content: start;
  gap: 0.42rem;
}

.playCard.compact .previewPane {
  border-radius: 0.62rem;
  padding: 0.44rem;
  min-height: 7.5rem;
  gap: 0.34rem;
}

.editorPane {
  border-radius: 0.72rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.18);
  background: rgb(var(--color-bg-rgb) / 0.22);
  padding: 0.52rem;
}

.playCard.compact .editorPane {
  border-radius: 0.62rem;
  padding: 0.44rem;
}

.paneTitle {
  margin: 0;
  font-size: 0.7rem;
  color: var(--color-text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.emptyProps {
  margin-top: 0.32rem;
  color: var(--color-text-secondary);
  font-size: 0.72rem;
}

.controlList {
  margin-top: 0.42rem;
  display: grid;
  gap: 0.42rem;
}

.playCard.compact .controlList {
  margin-top: 0.34rem;
  gap: 0.34rem;
}

.controlRow {
  display: grid;
  gap: 0.18rem;
}

.controlLabel {
  font-size: 0.69rem;
  color: var(--color-surface);
  font-weight: 600;
}

.controlHelp {
  color: var(--color-text-secondary);
  font-size: 0.64rem;
}

.controlInput {
  min-height: 1.84rem;
  border-radius: 0.58rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.32);
  background: rgb(var(--color-bg-rgb) / 0.36);
  color: var(--color-surface);
  font-size: 0.72rem;
  padding: 0.32rem 0.44rem;
}

.playCard.compact .controlInput {
  min-height: 1.72rem;
  border-radius: 0.5rem;
  font-size: 0.68rem;
  padding: 0.28rem 0.38rem;
}

.controlInputTextarea {
  resize: vertical;
  min-height: 3.1rem;
}

.toggleRow {
  display: inline-flex;
  align-items: center;
  gap: 0.34rem;
  color: var(--color-text-secondary);
  font-size: 0.7rem;
}

.defaultValue {
  font-size: 0.62rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.84);
}

.metaRow {
  display: inline-flex;
  flex-wrap: wrap;
  gap: 0.38rem;
}

.playCard.compact .metaRow {
  gap: 0.28rem;
}

.metaKey {
  font-size: 0.64rem;
  color: var(--color-text-secondary);
}

.playCard.compact .metaKey {
  font-size: 0.6rem;
}

.metaKey code {
  color: var(--color-surface);
}

.codeBlock {
  border-radius: 0.66rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2);
  background: rgb(var(--color-bg-rgb) / 0.4);
  padding: 0.36rem 0.46rem;
}

.playCard.compact .codeBlock {
  border-radius: 0.56rem;
  padding: 0.3rem 0.38rem;
}

.codeBlock summary {
  cursor: pointer;
  font-size: 0.68rem;
  color: var(--color-text-secondary);
}

.codeBlock pre {
  margin: 0.36rem 0 0;
  overflow: auto;
  font-size: 0.66rem;
  color: var(--color-surface);
}

@media (max-width: 760px) {
  .playCardHeader {
    flex-direction: column;
  }
}
</style>
