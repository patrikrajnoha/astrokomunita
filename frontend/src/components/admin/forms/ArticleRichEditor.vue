<template>
  <div class="rich-editor" :class="{ 'is-disabled': disabled, 'is-dragging-image': isDragOverImage }">
    <div class="rich-editor__toolbar" role="toolbar" aria-label="Formatovanie textu">
      <button
        type="button"
        class="rich-editor__tool"
        :disabled="disabled"
        title="Tučné"
        aria-label="Tučné"
        @mousedown.prevent
        @click="applyCommand('bold')"
      >
        <strong>B</strong>
      </button>

      <button
        type="button"
        class="rich-editor__tool"
        :disabled="disabled"
        title="Kurzíva"
        aria-label="Kurzíva"
        @mousedown.prevent
        @click="applyCommand('italic')"
      >
        <em>I</em>
      </button>

      <button
        type="button"
        class="rich-editor__tool"
        :disabled="disabled"
        title="Vložiť obrázok"
        aria-label="Vložiť obrázok"
        @mousedown.prevent
        @click="openImageDialog"
      >
        <svg class="rich-editor__tool-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7Z" />
          <path d="m8 15 2.5-2.5 2 2 2.5-3 3 3.5" />
          <circle cx="9" cy="10" r="1.2" />
        </svg>
      </button>

      <label class="rich-editor__format">
        <span>Štýl</span>
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
        <span>Veľkosť</span>
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

    <div v-if="imageDialogVisible" class="rich-editor__image-dialog" @mousedown.stop>
      <input
        v-model="imageDialogUrl"
        class="rich-editor__image-input"
        type="url"
        placeholder="https://..."
        autofocus
        @keydown.enter.prevent="confirmImageInsert"
        @keydown.esc.prevent="imageDialogVisible = false"
      />
      <button
        type="button"
        class="rich-editor__tool"
        :disabled="isUploadingImage"
        @click="openImageFileDialog"
      >Nahrať súbor</button>
      <button type="button" class="rich-editor__tool" @click="confirmImageInsert">Vložiť</button>
      <button type="button" class="rich-editor__tool" @click="imageDialogVisible = false">Zrušiť</button>
    </div>

    <input
      ref="imageFileInputRef"
      class="rich-editor__image-file-input"
      type="file"
      accept="image/*"
      @change="handleImageFileChange"
    />

    <div v-if="isUploadingImage || imageUploadError" class="rich-editor__status">
      <span v-if="isUploadingImage">Nahrávam obrázok...</span>
      <span v-else class="rich-editor__status-error">{{ imageUploadError }}</span>
    </div>

    <div
      class="rich-editor__surface-wrap"
      :class="{ 'is-drag-over': isDragOverImage, 'is-uploading': isUploadingImage }"
    >
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
        @dragenter="handleDragEnter"
        @dragover="handleDragOver"
        @dragleave="handleDragLeave"
        @drop="handleDrop"
      ></div>

      <div v-if="isDragOverImage" class="rich-editor__drop-hint">
        Pusti obrázok sem a pridám ho do článku.
      </div>
    </div>
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
  { value: "p", label: "Normálny text" },
  { value: "h2", label: "Nadpis 2" },
  { value: "h3", label: "Nadpis 3" },
  { value: "ul", label: "Odrážky" },
  { value: "ol", label: "1. Číslovanie" },
];
const BLOCK_FORMAT_TAGS = new Set(["p", "h2", "h3"]);

const props = defineProps({
  modelValue: {
    type: String,
    default: "",
  },
  placeholder: {
    type: String,
    default: "Napíš obsah...",
  },
  minHeight: {
    type: Number,
    default: 280,
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  imageUploadHandler: {
    type: Function,
    default: null,
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
const imageDialogVisible = ref(false);
const imageDialogUrl = ref("");
const imageFileInputRef = ref(null);
const isUploadingImage = ref(false);
const imageUploadError = ref("");
const isDragOverImage = ref(false);

const surfaceStyle = computed(() => ({
  minHeight: `${Math.max(160, Number(props.minHeight) || 280)}px`,
}));

function escapeAttributeValue(value) {
  return String(value || "")
    .replace(/&/g, "&amp;")
    .replace(/"/g, "&quot;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;");
}

function extractImageFile(dataTransfer) {
  if (!dataTransfer) return null;

  const files = Array.from(dataTransfer.files || []);
  const fileFromList = files.find(
    (file) => typeof file?.type === "string" && file.type.toLowerCase().startsWith("image/")
  );
  if (fileFromList) return fileFromList;

  const items = Array.from(dataTransfer.items || []);
  for (const item of items) {
    if (item?.kind !== "file") continue;
    const file = item.getAsFile?.();
    if (!file) continue;
    if (typeof file.type === "string" && file.type.toLowerCase().startsWith("image/")) {
      return file;
    }
  }

  return null;
}

function hasImageFile(dataTransfer) {
  return Boolean(extractImageFile(dataTransfer));
}

function resetDragState() {
  isDragOverImage.value = false;
}

function placeCaretFromPoint(clientX, clientY) {
  if (typeof document === "undefined" || typeof window === "undefined") return;

  const selection = window.getSelection?.();
  if (!selection) return;

  let range = null;
  if (typeof document.caretRangeFromPoint === "function") {
    range = document.caretRangeFromPoint(clientX, clientY);
  } else if (typeof document.caretPositionFromPoint === "function") {
    const position = document.caretPositionFromPoint(clientX, clientY);
    if (position?.offsetNode) {
      range = document.createRange();
      range.setStart(position.offsetNode, position.offset || 0);
      range.collapse(true);
    }
  }

  if (!range || !editorContainsNode(range.commonAncestorContainer)) return;

  selection.removeAllRanges();
  selection.addRange(range);
  selectionRange.value = range.cloneRange();
}

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

  const savedRange = selectionRange.value;
  editor.focus();

  if (!savedRange) return;
  try {
    selection.removeAllRanges();
    selection.addRange(savedRange);
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
  if (typeof window === "undefined") return null;
  const selection = window.getSelection?.();
  if (!selection || selection.rangeCount === 0) return null;

  let node = selection.anchorNode || selection.getRangeAt(0).startContainer;
  if (!editorContainsNode(node)) return null;

  if (node.nodeType === TEXT_NODE) {
    node = node.parentNode;
  }

  const editor = editorRef.value;
  while (node && node !== editor) {
    if (node.nodeType === ELEMENT_NODE) {
      const tag = String(node.nodeName || "").toLowerCase();
      if (tag === "li") {
        const parentTag = String(node.parentNode?.nodeName || "").toLowerCase();
        if (parentTag === "ul" || parentTag === "ol") return parentTag;
      }
      if (tag === "ul" || tag === "ol") return tag;
      if (BLOCK_FORMAT_TAGS.has(tag)) return tag;
      if (tag === "div" || tag === "section" || tag === "article") return "p";
    }
    node = node.parentNode;
  }

  return "p";
}

function syncSelectionFormattingState() {
  const resolvedTag = resolveSelectionBlockTag();
  if (!resolvedTag) return;
  selectedBlockTag.value = resolvedTag;
}

function applyBlockFormat() {
  if (props.disabled) return;

  restoreSelection();
  focusEditor();

  if (selectedBlockTag.value === "ul") {
    document.execCommand("insertUnorderedList", false);
  } else if (selectedBlockTag.value === "ol") {
    document.execCommand("insertOrderedList", false);
  } else if (BLOCK_FORMAT_TAGS.has(selectedBlockTag.value)) {
    document.execCommand("formatBlock", false, selectedBlockTag.value);
  }

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

function openImageDialog() {
  if (props.disabled) return;
  captureSelection();
  imageUploadError.value = "";
  imageDialogUrl.value = "";
  imageDialogVisible.value = true;
}

function insertImageAtSelection(url, alt = "") {
  restoreSelection();
  focusEditor();
  const safeUrl = escapeAttributeValue(url);
  const safeAlt = escapeAttributeValue(alt);
  document.execCommand("insertHTML", false, `<img src="${safeUrl}" alt="${safeAlt}" />`);
  emitEditorValue();
  captureSelection();
  syncSelectionFormattingState();
}

function resolveUploadErrorMessage(error) {
  const responseMessage = String(error?.response?.data?.message || "").trim();
  if (responseMessage) return responseMessage;

  const validationMessage = String(error?.response?.data?.errors?.image?.[0] || "").trim();
  if (validationMessage) return validationMessage;

  const localMessage = String(error?.message || "").trim();
  if (localMessage) return localMessage;

  return "Nepodarilo sa nahrať obrázok.";
}

async function uploadImageFromFile(file) {
  if (props.disabled || !file) return false;
  if (isUploadingImage.value) return false;

  const mime = String(file.type || "").toLowerCase();
  if (!mime.startsWith("image/")) {
    imageUploadError.value = "Vyber súbor typu obrázok.";
    return false;
  }

  if (typeof props.imageUploadHandler !== "function") {
    imageUploadError.value = "Upload obrázka nie je dostupný.";
    return false;
  }

  imageUploadError.value = "";
  isUploadingImage.value = true;

  try {
    const response = await props.imageUploadHandler(file);
    const uploadedUrl =
      typeof response === "string" ? response.trim() : String(response?.url || "").trim();

    if (!uploadedUrl) {
      throw new Error("Server nevrátil URL obrázka.");
    }

    insertImageAtSelection(uploadedUrl, file.name || "");
    imageDialogVisible.value = false;
    return true;
  } catch (error) {
    imageUploadError.value = resolveUploadErrorMessage(error);
    return false;
  } finally {
    isUploadingImage.value = false;
  }
}

function openImageFileDialog() {
  if (props.disabled || isUploadingImage.value) return;
  captureSelection();
  imageFileInputRef.value?.click?.();
}

async function handleImageFileChange(event) {
  const input = event?.target;
  const file = input?.files?.[0] || null;
  if (input) {
    input.value = "";
  }

  if (!file) return;
  await uploadImageFromFile(file);
}

function confirmImageInsert() {
  const url = imageDialogUrl.value.trim();
  imageDialogVisible.value = false;
  if (!url) return;

  imageUploadError.value = "";
  insertImageAtSelection(url);
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

function handleDragEnter(event) {
  if (props.disabled || isUploadingImage.value) return;
  if (!hasImageFile(event.dataTransfer)) return;

  event.preventDefault();
  isDragOverImage.value = true;
}

function handleDragOver(event) {
  if (props.disabled || isUploadingImage.value) return;
  if (!hasImageFile(event.dataTransfer)) return;

  event.preventDefault();
  if (event.dataTransfer) {
    event.dataTransfer.dropEffect = "copy";
  }
  isDragOverImage.value = true;
}

function handleDragLeave(event) {
  if (!isDragOverImage.value) return;

  const relatedTarget = event.relatedTarget;
  if (relatedTarget && editorContainsNode(relatedTarget)) {
    return;
  }

  isDragOverImage.value = false;
}

async function handleDrop(event) {
  if (props.disabled || isUploadingImage.value) return;
  event.preventDefault();
  const imageFile = extractImageFile(event.dataTransfer);
  resetDragState();
  if (!imageFile) return;

  placeCaretFromPoint(event.clientX, event.clientY);
  captureSelection();
  await uploadImageFromFile(imageFile);
}

async function handlePaste(event) {
  if (props.disabled) return;

  const imageFile = extractImageFile(event.clipboardData);
  if (imageFile) {
    event.preventDefault();
    captureSelection();
    await uploadImageFromFile(imageFile);
    return;
  }

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
  resetDragState();
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
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 36px;
  min-height: 32px;
  padding: 0 10px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.28);
  border-radius: 8px;
  background: rgb(var(--color-text-secondary-rgb) / 0.1);
  color: inherit;
  cursor: pointer;
}

.rich-editor__tool-icon {
  width: 16px;
  height: 16px;
  display: block;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
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

.rich-editor__image-dialog {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px;
  border-bottom: 1px solid var(--divider-color);
  background: rgb(var(--color-bg-rgb) / 0.8);
}

.rich-editor__image-input {
  flex: 1;
  min-height: 32px;
  padding: 0 10px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.28);
  border-radius: 8px;
  background: rgb(var(--color-bg-rgb) / 0.72);
  color: inherit;
  font-size: 13px;
}

.rich-editor__image-file-input {
  display: none;
}

.rich-editor__status {
  padding: 6px 10px;
  border-bottom: 1px solid var(--divider-color);
  background: rgb(var(--color-bg-rgb) / 0.65);
  font-size: 12px;
  color: rgb(var(--color-surface-rgb) / 0.74);
}

.rich-editor__status-error {
  color: var(--color-danger);
}

.rich-editor__surface-wrap {
  position: relative;
  transition: background-color 160ms ease;
}

.rich-editor__surface-wrap.is-drag-over {
  background: rgb(var(--color-primary-rgb) / 0.08);
}

.rich-editor__surface-wrap.is-uploading {
  cursor: progress;
}

.rich-editor__surface :deep(img) {
  max-width: 100%;
  height: auto;
  border-radius: 6px;
  margin: 8px 0;
  display: block;
}

.rich-editor__surface {
  width: 100%;
  padding: 12px;
  outline: none;
  line-height: 1.7;
}

.rich-editor__drop-hint {
  position: absolute;
  inset: 12px;
  border: 1px dashed rgb(var(--color-primary-rgb) / 0.5);
  border-radius: 10px;
  background: rgb(var(--color-primary-rgb) / 0.1);
  color: rgb(var(--color-primary-rgb) / 1);
  display: grid;
  place-items: center;
  text-align: center;
  font-size: 12px;
  font-weight: 600;
  padding: 12px;
  pointer-events: none;
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

@media (hover: none) and (pointer: coarse) {
  .rich-editor__tool {
    min-height: 40px;
    min-width: 40px;
  }

  .rich-editor__format select,
  .rich-editor__size select {
    min-height: 40px;
  }
}
</style>

