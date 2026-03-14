<template>
  <div class="rich-editor" :class="{ 'is-disabled': disabled }">
    <div class="rich-editor__toolbar" role="toolbar" aria-label="Formatovanie textu">
      <button
        type="button"
        class="rich-editor__tool"
        :disabled="disabled"
        title="Tucne"
        aria-label="Tucne"
        @mousedown.prevent
        @click="applyCommand('bold')"
      >
        <strong>B</strong>
      </button>

      <button
        type="button"
        class="rich-editor__tool"
        :disabled="disabled"
        title="Kurziva"
        aria-label="Kurziva"
        @mousedown.prevent
        @click="applyCommand('italic')"
      >
        <em>I</em>
      </button>

      <label class="rich-editor__format">
        <span>Styl</span>
        <select
          v-model="selectedBlockTag"
          :disabled="disabled"
          @mousedown="captureSelection"
          @change="applyBlockFormat"
        >
          <option
            v-for="option in BLOCK_FORMAT_OPTIONS"
            :key="`block-format-${option.value}`"
            :value="option.value"
          >
            {{ option.label }}
          </option>
        </select>
      </label>

      <label class="rich-editor__size">
        <span>Velkost</span>
        <select
          v-model.number="selectedFontSize"
          :disabled="disabled"
          @mousedown="captureSelection"
          @change="applyFontSize"
        >
          <option
            v-for="size in FONT_SIZE_OPTIONS"
            :key="`font-size-${size}`"
            :value="size"
          >
            {{ size }} px
          </option>
        </select>
      </label>
    </div>

    <div
      ref="editorRef"
      class="rich-editor__surface"
      :class="{ 'is-empty': isEmpty }"
      :style="surfaceStyle"
      :contenteditable="(!disabled).toString()"
      role="textbox"
      aria-multiline="true"
      :data-placeholder="placeholder"
      @input="handleInput"
      @focus="captureSelection"
      @keyup="captureSelection"
      @mouseup="captureSelection"
      @keydown="handleKeydown"
      @blur="handleBlur"
      @paste="handlePaste"
    ></div>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
import {
  hasHtmlMarkup,
  renderArticleContent,
  sanitizeArticleHtml,
  stripHtml,
} from "@/utils/articleContent";

const FONT_SIZE_OPTIONS = [14, 16, 18, 20, 24, 30, 36];
const BLOCK_FORMAT_OPTIONS = [
  { value: "p", label: "Odsek" },
  { value: "h2", label: "Nadpis 2" },
  { value: "h3", label: "Nadpis 3" },
];
const BLOCK_FORMAT_TAGS = new Set(BLOCK_FORMAT_OPTIONS.map((option) => option.value));

const props = defineProps({
  modelValue: {
    type: String,
    default: "",
  },
  placeholder: {
    type: String,
    default: "Napis obsah...",
  },
  minHeight: {
    type: Number,
    default: 280,
  },
  disabled: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(["update:modelValue"]);

const ELEMENT_NODE = 1;
const TEXT_NODE = 3;

const editorRef = ref(null);
const isEmpty = ref(true);
const lastEmittedValue = ref("");
const selectedFontSize = ref(16);
const selectedBlockTag = ref("p");
const selectionRange = ref(null);

const surfaceStyle = computed(() => ({
  minHeight: `${Math.max(160, Number(props.minHeight) || 280)}px`,
}));

function toEditorHtml(value) {
  const raw = String(value || "").trim();
  if (!raw) return "";

  if (hasHtmlMarkup(raw)) {
    return sanitizeArticleHtml(raw);
  }

  return renderArticleContent(raw).html;
}

function buildModelValueFromEditor() {
  const editor = editorRef.value;
  if (!editor) return "";

  const rawHtml = String(editor.innerHTML || "");
  if (!stripHtml(rawHtml)) {
    return "";
  }

  return sanitizeArticleHtml(rawHtml);
}

function updateEmptyState() {
  const editor = editorRef.value;
  if (!editor) {
    isEmpty.value = true;
    return;
  }

  isEmpty.value = !stripHtml(editor.innerHTML || "");
}

function syncEditorFromModel(value) {
  const editor = editorRef.value;
  if (!editor) return;

  const nextHtml = toEditorHtml(value);
  if (editor.innerHTML !== nextHtml) {
    editor.innerHTML = nextHtml;
  }

  updateEmptyState();
  syncSelectionFormattingState();
}

function emitEditorValue() {
  const nextValue = buildModelValueFromEditor();
  lastEmittedValue.value = nextValue;
  emit("update:modelValue", nextValue);
  updateEmptyState();
}

function editorContainsNode(node) {
  const editor = editorRef.value;
  if (!editor || !node) return false;
  return editor === node || editor.contains(node);
}

function captureSelection() {
  if (props.disabled || typeof window === "undefined") return;
  const selection = window.getSelection?.();
  if (!selection || selection.rangeCount === 0) return;

  const range = selection.getRangeAt(0);
  if (!editorContainsNode(range.commonAncestorContainer)) return;
  selectionRange.value = range.cloneRange();
}

function restoreSelection() {
  const editor = editorRef.value;
  if (!editor || typeof window === "undefined") return;
  const selection = window.getSelection?.();
  if (!selection) return;

  editor.focus();

  if (!selectionRange.value) return;
  try {
    selection.removeAllRanges();
    selection.addRange(selectionRange.value);
  } catch {
    // Ignore stale ranges from DOM updates.
  }
}

function focusEditor() {
  if (props.disabled) return;
  editorRef.value?.focus();
}

function applyCommand(command) {
  if (props.disabled) return;
  restoreSelection();
  focusEditor();
  document.execCommand(command, false);
  emitEditorValue();
  captureSelection();
}

function setCaretPosition(selection, node, offset) {
  const range = document.createRange();
  range.setStart(node, Math.max(0, offset));
  range.collapse(true);
  selection.removeAllRanges();
  selection.addRange(range);
}

function resolveSelectionBlockTag() {
  if (typeof window === "undefined") return "p";
  const selection = window.getSelection?.();
  if (!selection || selection.rangeCount === 0) return "p";

  let node = selection.anchorNode || selection.getRangeAt(0).startContainer;
  if (!editorContainsNode(node)) return "p";

  if (node.nodeType === TEXT_NODE) {
    node = node.parentNode;
  }

  const editor = editorRef.value;
  while (node && node !== editor) {
    if (node.nodeType === ELEMENT_NODE) {
      const tag = String(node.nodeName || "").toLowerCase();
      if (BLOCK_FORMAT_TAGS.has(tag)) {
        return tag;
      }
      if (tag === "div" || tag === "section" || tag === "article") {
        return "p";
      }
    }
    node = node.parentNode;
  }

  return "p";
}

function syncSelectionFormattingState() {
  selectedBlockTag.value = resolveSelectionBlockTag();
}

function applyBlockFormat() {
  if (props.disabled) return;

  const normalizedTag = BLOCK_FORMAT_TAGS.has(selectedBlockTag.value)
    ? selectedBlockTag.value
    : "p";

  restoreSelection();
  focusEditor();
  document.execCommand("formatBlock", false, `<${normalizedTag}>`);
  emitEditorValue();
  captureSelection();
  syncSelectionFormattingState();
}

function removePreviousIndent() {
  if (typeof window === "undefined") return false;
  const selection = window.getSelection?.();
  if (!selection || selection.rangeCount === 0) return false;

  const range = selection.getRangeAt(0);
  if (!range.collapsed) return false;

  let node = range.startContainer;
  let offset = range.startOffset;

  if (!editorContainsNode(node)) return false;

  if (node.nodeType !== TEXT_NODE) {
    const previous = node.childNodes[offset - 1];
    if (!previous || previous.nodeType !== TEXT_NODE) return false;
    node = previous;
    offset = previous.textContent?.length || 0;
  }

  const text = String(node.textContent || "");
  if (!text || offset <= 0) return false;

  const beforeCaret = text.slice(0, offset);
  if (beforeCaret.endsWith("\u00a0\u00a0\u00a0\u00a0")) {
    const nextText = `${text.slice(0, offset - 4)}${text.slice(offset)}`;
    node.textContent = nextText;
    setCaretPosition(selection, node, offset - 4);
    return true;
  }

  if (beforeCaret.endsWith("\t")) {
    const nextText = `${text.slice(0, offset - 1)}${text.slice(offset)}`;
    node.textContent = nextText;
    setCaretPosition(selection, node, offset - 1);
    return true;
  }

  return false;
}

function replaceLegacyFontTags(sizePx) {
  const editor = editorRef.value;
  if (!editor) return;

  editor.querySelectorAll("font[size]").forEach((fontNode) => {
    const span = document.createElement("span");
    span.style.fontSize = `${sizePx}px`;
    span.innerHTML = fontNode.innerHTML;
    fontNode.replaceWith(span);
  });

  editor.querySelectorAll("span[style]").forEach((spanNode) => {
    const style = String(spanNode.getAttribute("style") || "");
    if (!/font-size\s*:/i.test(style)) return;
    if (
      /xx-small|x-small|small|medium|large|x-large|xx-large|xxx-large|smaller|larger/i.test(
        style
      )
    ) {
      spanNode.style.fontSize = `${sizePx}px`;
    }
  });
}

function applyFontSize() {
  if (props.disabled) return;

  const requested = Number(selectedFontSize.value || 16);
  const sizePx = Number.isFinite(requested)
    ? Math.min(72, Math.max(10, Math.round(requested)))
    : 16;

  restoreSelection();
  focusEditor();
  document.execCommand("styleWithCSS", false, true);
  document.execCommand("fontSize", false, "7");
  replaceLegacyFontTags(sizePx);
  emitEditorValue();
  captureSelection();
}

function handleInput() {
  emitEditorValue();
  captureSelection();
  syncSelectionFormattingState();
}

function handleKeydown(event) {
  if (props.disabled) return;
  if (event.key !== "Tab") return;

  event.preventDefault();
  restoreSelection();
  focusEditor();

  if (event.shiftKey) {
    if (removePreviousIndent()) {
      emitEditorValue();
      captureSelection();
    }
    return;
  }

  document.execCommand("insertHTML", false, "&nbsp;&nbsp;&nbsp;&nbsp;");
  emitEditorValue();
  captureSelection();
}

function handleSelectionChange() {
  if (props.disabled) return;
  captureSelection();
  syncSelectionFormattingState();
}

function handleBlur() {
  const editor = editorRef.value;
  if (!editor) return;

  const normalizedValue = buildModelValueFromEditor();
  const normalizedHtml = toEditorHtml(normalizedValue);
  if (editor.innerHTML !== normalizedHtml) {
    editor.innerHTML = normalizedHtml;
  }

  lastEmittedValue.value = normalizedValue;
  emit("update:modelValue", normalizedValue);
  updateEmptyState();
  syncSelectionFormattingState();
}

function handlePaste(event) {
  if (props.disabled) return;

  event.preventDefault();
  const plainText = event.clipboardData?.getData("text/plain") || "";
  document.execCommand("insertText", false, plainText);
  emitEditorValue();
}

watch(
  () => props.modelValue,
  (nextValue) => {
    const normalizedIncoming = String(nextValue || "");
    if (normalizedIncoming === lastEmittedValue.value) {
      return;
    }

    syncEditorFromModel(normalizedIncoming);
  },
  { immediate: true }
);

onMounted(() => {
  syncEditorFromModel(props.modelValue);
  document.addEventListener("selectionchange", handleSelectionChange);

  nextTick(() => {
    updateEmptyState();
    syncSelectionFormattingState();
  });
});

onBeforeUnmount(() => {
  document.removeEventListener("selectionchange", handleSelectionChange);
});
</script>

<style scoped>
.rich-editor {
  border: 1px solid var(--border, rgb(var(--color-text-secondary-rgb) / 0.2));
  border-radius: 10px;
  background: rgb(var(--color-bg-rgb) / 0.46);
  overflow: hidden;
}

.rich-editor.is-disabled {
  opacity: 0.75;
}

.rich-editor__toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  padding: 8px;
  border-bottom: 1px solid var(--divider-color);
  background: rgb(var(--color-bg-rgb) / 0.6);
}

.rich-editor__tool {
  min-width: 36px;
  min-height: 32px;
  padding: 0 10px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.28);
  border-radius: 8px;
  background: rgb(var(--color-text-secondary-rgb) / 0.1);
  color: inherit;
  cursor: pointer;
}

.rich-editor__tool:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.rich-editor__format,
.rich-editor__size {
  margin-left: auto;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: rgb(var(--color-surface-rgb) / 0.7);
}

.rich-editor__format {
  margin-left: 0;
}

.rich-editor__format select,
.rich-editor__size select {
  min-height: 32px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.28);
  border-radius: 8px;
  background: rgb(var(--color-bg-rgb) / 0.72);
  color: inherit;
  padding: 0 8px;
}

.rich-editor__surface {
  width: 100%;
  padding: 12px;
  outline: none;
  line-height: 1.7;
}

.rich-editor__surface.is-empty::before {
  content: attr(data-placeholder);
  color: rgb(var(--color-surface-rgb) / 0.5);
}

.rich-editor__surface :deep(p) {
  margin: 0 0 10px;
}

.rich-editor__surface :deep(p:last-child) {
  margin-bottom: 0;
}

.rich-editor__surface :deep(h2),
.rich-editor__surface :deep(h3) {
  margin: 12px 0 8px;
  line-height: 1.32;
}

.rich-editor__surface :deep(h2) {
  font-size: 1.24rem;
}

.rich-editor__surface :deep(h3) {
  font-size: 1.08rem;
}

.rich-editor__surface :deep(ul),
.rich-editor__surface :deep(ol) {
  margin: 0 0 10px;
  padding-left: 20px;
}

.rich-editor__surface :deep(strong) {
  font-weight: 700;
}

.rich-editor__surface :deep(em) {
  font-style: italic;
}

.rich-editor__surface :deep(a) {
  color: var(--color-primary);
  text-decoration: underline;
}
</style>
