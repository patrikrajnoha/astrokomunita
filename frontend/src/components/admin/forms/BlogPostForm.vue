<template>
  <div class="modalOverlay" @click="$emit('close')">
    <div class="modalContent modalContent--large">
      <div class="modalHeader">
        <h2>{{ isEditing ? 'Upraviť článok' : 'Nový článok' }}</h2>
        <button class="modalClose" @click="$emit('close')">&times;</button>
      </div>
      
      <form @submit.prevent="handleSubmit" class="modalBody">
        <!-- Title -->
        <div class="formField">
          <label class="formLabel">Názov *</label>
          <input
            v-model="form.title"
            type="text"
            class="formInput"
            placeholder="Zadajte názov článku..."
            required
          />
          <div v-if="errors.title" class="formError">{{ errors.title }}</div>
        </div>
        
        <!-- Excerpt -->
        <div class="formField">
          <label class="formLabel">Perex</label>
          <textarea
            v-model="form.excerpt"
            class="formTextarea"
            placeholder="Krátky popis článku..."
            rows="2"
          ></textarea>
        </div>
        
        <!-- Content tabs -->
        <div class="formTabs">
          <button
            type="button"
            :class="['tabBtn', { 'tabBtn--active': activeTab === 'content' }]"
            @click="activeTab = 'content'"
          >
            Obsah
          </button>
          <button
            type="button"
            :class="['tabBtn', { 'tabBtn--active': activeTab === 'preview' }]"
            @click="activeTab = 'preview'"
          >
            Náhľad
          </button>
        </div>
        
        <!-- Content/Preview -->
        <div class="formField">
          <div v-if="activeTab === 'content'" class="contentEditor">
            <textarea
              v-model="form.content"
              class="formTextarea formTextarea--large"
              placeholder="Napíšte obsah článku..."
              rows="15"
              required
            ></textarea>
          </div>
          
          <div v-else class="contentPreview">
            <div class="previewContent" v-html="previewContent"></div>
          </div>
          
          <div v-if="errors.content" class="formError">{{ errors.content }}</div>
        </div>
        
        <!-- Cover image -->
        <div class="formField">
          <label class="formLabel">Cover obrázok</label>
          <div class="coverUpload">
            <div v-if="coverPreview" class="coverPreview">
              <img :src="coverPreview" alt="Cover preview" class="coverImage" />
              <button type="button" class="coverRemove" @click="removeCover">
                &times;
              </button>
            </div>
            <input
              ref="coverInput"
              type="file"
              accept="image/*"
              @change="onCoverChange"
              class="coverInput"
            />
            <button type="button" class="btn btn-outline" @click="$refs.coverInput.click()">
              Vybrať obrázok
            </button>
          </div>
        </div>
        
        <!-- Published at -->
        <div class="formField">
          <label class="formLabel">Dátum publikácie</label>
          <input
            v-model="form.published_at"
            type="datetime-local"
            class="formInput"
          />
          <div class="formHint">
            Ponechajte prázdne pre okamžité publikovanie
          </div>
        </div>
        
        <!-- Tags -->
        <div class="formField">
          <label class="formLabel">Tagy</label>
          <input
            v-model="tagsInput"
            type="text"
            class="formInput"
            placeholder="tag1, tag2, tag3..."
          />
          <div class="formHint">
            Oddelte tagy čiarkou
          </div>
        </div>
      </form>
      
      <div class="modalFooter">
        <button type="button" class="btn btn-secondary" @click="$emit('close')">
          Zrušiť
        </button>
        <button
          type="submit"
          form="blogPostForm"
          class="btn btn-primary"
          :disabled="loading"
          @click="handleSubmit"
        >
          {{ loading ? 'Ukladám...' : (isEditing ? 'Uložiť' : 'Vytvoriť') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, reactive } from 'vue';
import { toDateTimeLocal, fromDateTimeLocal } from '@/utils/dateUtils.js';
import { validateForm, commonRules } from '@/utils/validationUtils.js';
import { inlineMarkdown } from '@/utils/textUtils.js';

const props = defineProps({
  post: {
    type: Object,
    default: null
  }
});

const emit = defineEmits(['close', 'save']);

// State
const loading = ref(false);
const errors = ref({});
const activeTab = ref('content');
const coverPreview = ref('');
const tagsInput = ref('');

// Computed
const isEditing = computed(() => !!props.post);
const previewContent = computed(() => inlineMarkdown(form.content || ''));

// Form data
const form = reactive({
  title: '',
  excerpt: '',
  content: '',
  published_at: '',
  cover_image: null
});

// Initialize form with post data
if (props.post) {
  form.title = props.post.title || '';
  form.excerpt = props.post.excerpt || '';
  form.content = props.post.content || '';
  form.published_at = toDateTimeLocal(props.post.published_at);
  coverPreview.value = props.post.cover_image_url || '';
  tagsInput.value = (props.post.tags || []).map(t => t.name).join(', ');
}

// Validation rules
const validationRules = {
  title: commonRules.title,
  content: commonRules.description
};

// Methods
function validateBlogForm() {
  errors.value = validateForm(form, validationRules);
  return Object.keys(errors.value).length === 0;
}

function onCoverChange(event) {
  const file = event.target.files[0];
  if (file) {
    form.cover_image = file;
    coverPreview.value = URL.createObjectURL(file);
  }
}

function removeCover() {
  form.cover_image = null;
  coverPreview.value = '';
  if (props.post?.cover_image_url) {
    // Tu by bola logika pre odstránie existujúceho coveru
  }
}

async function handleSubmit() {
  if (!validateBlogForm()) {
    return;
  }
  
  loading.value = true;
  
  try {
    const formData = {
      ...form,
      published_at: fromDateTimeLocal(form.published_at) || null,
      tags: tagsInput.value.split(',').map(tag => tag.trim()).filter(Boolean)
    };
    
    emit('save', formData);
  } catch (error) {
    console.error('Form submission error:', error);
  } finally {
    loading.value = false;
  }
}
</script>

<style scoped>
.modalOverlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 1rem;
}

.modalContent {
  background: var(--color-background);
  border-radius: 0.5rem;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
  width: 100%;
  max-height: 90vh;
  overflow-y: auto;
}

.modalContent--large {
  max-width: 900px;
}

.modalHeader {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem;
  border-bottom: 1px solid var(--color-border);
}

.modalHeader h2 {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--color-text);
}

.modalClose {
  background: none;
  border: none;
  font-size: 1.5rem;
  color: var(--color-text-secondary);
  cursor: pointer;
  padding: 0.25rem;
  line-height: 1;
}

.modalClose:hover {
  color: var(--color-text);
}

.modalBody {
  padding: 1.5rem;
}

.formField {
  margin-bottom: 1.5rem;
}

.formLabel {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
  color: var(--color-text);
  font-size: 0.875rem;
}

.formInput, .formTextarea {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: 0.375rem;
  background: var(--color-background);
  color: var(--color-text);
  font-size: 0.875rem;
  transition: border-color 0.2s, box-shadow 0.2s;
}

.formTextarea--large {
  min-height: 300px;
  font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
}

.formInput:focus, .formTextarea:focus {
  outline: none;
  border-color: var(--color-primary);
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.formError {
  color: var(--color-danger);
  font-size: 0.75rem;
  margin-top: 0.25rem;
}

.formHint {
  font-size: 0.75rem;
  color: var(--color-text-secondary);
  margin-top: 0.25rem;
}

.formTabs {
  display: flex;
  margin-bottom: 1rem;
  border-bottom: 1px solid var(--color-border);
}

.tabBtn {
  padding: 0.75rem 1.5rem;
  border: none;
  background: none;
  color: var(--color-text-secondary);
  cursor: pointer;
  border-bottom: 2px solid transparent;
  transition: all 0.2s;
}

.tabBtn:hover {
  color: var(--color-text);
}

.tabBtn--active {
  color: var(--color-primary);
  border-bottom-color: var(--color-primary);
}

.contentPreview {
  border: 1px solid var(--color-border);
  border-radius: 0.375rem;
  padding: 1rem;
  min-height: 300px;
  background: var(--color-background-secondary);
}

.previewContent {
  line-height: 1.6;
}

.coverUpload {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.coverPreview {
  position: relative;
  width: 100px;
  height: 100px;
}

.coverImage {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 0.375rem;
}

.coverRemove {
  position: absolute;
  top: -8px;
  right: -8px;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: var(--color-danger);
  color: white;
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1rem;
}

.coverInput {
  display: none;
}

.modalFooter {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  padding: 1.5rem;
  border-top: 1px solid var(--color-border);
  background: var(--color-background-secondary);
}

.btn {
  padding: 0.75rem 1.5rem;
  border: 1px solid var(--color-border);
  border-radius: 0.375rem;
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-primary {
  background: var(--color-primary);
  color: white;
  border-color: var(--color-primary);
}

.btn-primary:hover:not(:disabled) {
  background: var(--color-primary-hover);
  border-color: var(--color-primary-hover);
}

.btn-secondary {
  background: var(--color-background);
  color: var(--color-text);
  border-color: var(--color-border);
}

.btn-secondary:hover {
  background: var(--color-background-hover);
}

.btn-outline {
  background: transparent;
  color: var(--color-primary);
  border-color: var(--color-primary);
}

.btn-outline:hover {
  background: var(--color-primary);
  color: white;
}

@media (max-width: 640px) {
  .modalContent {
    margin: 0;
    max-height: 100vh;
    border-radius: 0;
  }
  
  .modalHeader, .modalBody, .modalFooter {
    padding: 1rem;
  }
  
  .modalFooter {
    flex-direction: column;
  }
  
  .btn {
    width: 100%;
    justify-content: center;
  }
  
  .coverUpload {
    flex-direction: column;
    align-items: stretch;
  }
}
</style>
