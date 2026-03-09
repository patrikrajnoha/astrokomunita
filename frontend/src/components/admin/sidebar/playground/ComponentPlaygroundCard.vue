<template>
  <article class="playCard" :class="{ compact }">
    <header class="playCardHeader">
      <div class="titleWrap">
        <h3>{{ entry.label }}</h3>
        <p v-if="entry.description" class="description">{{ entry.description }}</p>
        <p class="microMeta">ID: <code>{{ entry.id }}</code></p>
      </div>

      <div class="headActions">
        <button
          v-if="hasAdvancedContent"
          type="button"
          class="ghostBtn"
          @click="showAdvanced = !showAdvanced"
        >
          {{ showAdvanced ? 'Skryt detail' : 'Detail' }}
        </button>
        <button v-if="isDirty" type="button" class="ghostBtn" @click="resetToDefaults">
          Reset
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

    <section v-if="showAdvanced" class="advancedPane">
      <div v-if="controls.length" class="editorPane">
        <p class="paneTitle">Nastavenie widgetu</p>

        <div class="controlList">
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

      <p v-else class="emptyProps">Tento widget nema editovatelne nastavenia.</p>

      <details class="codeBlock">
        <summary>Payload</summary>
        <pre>{{ serializedProps }}</pre>
      </details>

      <p class="sourceLine">
        Zdroj: <code>{{ entry.sourcePath }}</code>
      </p>
    </section>
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
const showAdvanced = ref(false)

const controls = computed(() => {
  return Array.isArray(props.entry?.editableProps) ? props.entry.editableProps : []
})

const variantOptions = computed(() => {
  return Array.isArray(props.entry?.variants) ? props.entry.variants : []
})

const hasAdvancedContent = computed(() => {
  return controls.value.length > 0 || Boolean(props.entry?.sourcePath)
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

const defaultStateSnapshot = computed(() => {
  return JSON.stringify(createPropsState(props.entry, selectedVariantId.value))
})

const currentStateSnapshot = computed(() => JSON.stringify(propsState.value))

const isDirty = computed(() => {
  return currentStateSnapshot.value !== defaultStateSnapshot.value
})

const initializeState = () => {
  const firstVariantId = variantOptions.value[0]?.id || ''
  selectedVariantId.value = String(firstVariantId || '')
  propsState.value = createPropsState(props.entry, selectedVariantId.value)
  showAdvanced.value = false
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
  gap: 0.48rem;
  border-radius: 0.76rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2);
  background: rgb(var(--color-bg-rgb) / 0.14);
  padding: 0.58rem;
}

.playCard.compact {
  gap: 0.44rem;
  padding: 0.54rem;
}

.playCardHeader {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 0.5rem;
}

.titleWrap {
  min-width: 0;
}

.titleWrap h3 {
  margin: 0;
  font-size: 0.86rem;
  line-height: 1.25;
}

.description {
  margin: 0.16rem 0 0;
  color: var(--color-text-secondary);
  font-size: 0.71rem;
  line-height: 1.35;
}

.microMeta {
  margin: 0.18rem 0 0;
  color: var(--color-text-secondary);
  font-size: 0.66rem;
}

.microMeta code {
  color: var(--color-surface);
}

.headActions {
  display: inline-flex;
  align-items: center;
  gap: 0.24rem;
  flex-wrap: wrap;
}

.ghostBtn {
  min-height: 1.68rem;
  border-radius: 0.5rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.3);
  background: transparent;
  color: var(--color-text-secondary);
  padding: 0.24rem 0.5rem;
  font-size: 0.68rem;
  font-weight: 700;
}

.variantsRow {
  display: flex;
  align-items: center;
  gap: 0.3rem;
  flex-wrap: wrap;
}

.variantsLabel {
  color: var(--color-text-secondary);
  font-size: 0.67rem;
}

.variantChips {
  display: inline-flex;
  align-items: center;
  gap: 0.2rem;
  flex-wrap: wrap;
}

.chipBtn {
  min-height: 1.52rem;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.28);
  background: transparent;
  color: var(--color-text-secondary);
  padding: 0.12rem 0.42rem;
  font-size: 0.64rem;
  font-weight: 700;
}

.chipBtn.active {
  border-color: rgb(var(--color-primary-rgb) / 0.5);
  background: rgb(var(--color-primary-rgb) / 0.16);
  color: var(--color-surface);
}

.previewPane {
  border-radius: 0.64rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.16);
  background: rgb(var(--color-bg-rgb) / 0.2);
  padding: 0.48rem;
  min-height: 8.3rem;
  display: grid;
  align-content: start;
  gap: 0.4rem;
}

.advancedPane {
  border-top: 1px solid rgb(var(--color-text-secondary-rgb) / 0.14);
  padding-top: 0.42rem;
  display: grid;
  gap: 0.42rem;
}

.editorPane {
  display: grid;
  gap: 0.34rem;
}

.paneTitle {
  margin: 0;
  font-size: 0.68rem;
  color: var(--color-text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.emptyProps {
  margin: 0;
  color: var(--color-text-secondary);
  font-size: 0.72rem;
}

.controlList {
  display: grid;
  gap: 0.38rem;
}

.controlRow {
  display: grid;
  gap: 0.16rem;
}

.controlLabel {
  font-size: 0.68rem;
  color: var(--color-surface);
  font-weight: 600;
}

.controlHelp {
  color: var(--color-text-secondary);
  font-size: 0.63rem;
}

.controlInput {
  min-height: 1.78rem;
  border-radius: 0.52rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.3);
  background: rgb(var(--color-bg-rgb) / 0.32);
  color: var(--color-surface);
  font-size: 0.7rem;
  padding: 0.3rem 0.4rem;
}

.controlInputTextarea {
  resize: vertical;
  min-height: 3rem;
}

.toggleRow {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  color: var(--color-text-secondary);
  font-size: 0.68rem;
}

.defaultValue {
  font-size: 0.62rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.82);
}

.codeBlock {
  border-radius: 0.58rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.18);
  background: rgb(var(--color-bg-rgb) / 0.28);
  padding: 0.28rem 0.38rem;
}

.codeBlock summary {
  cursor: pointer;
  font-size: 0.67rem;
  color: var(--color-text-secondary);
}

.codeBlock pre {
  margin: 0.3rem 0 0;
  overflow: auto;
  font-size: 0.64rem;
  color: var(--color-surface);
}

.sourceLine {
  margin: 0;
  color: var(--color-text-secondary);
  font-size: 0.65rem;
}

.sourceLine code {
  color: var(--color-surface);
}

@media (max-width: 760px) {
  .playCardHeader {
    flex-direction: column;
  }

  .headActions {
    width: 100%;
  }
}
</style>
