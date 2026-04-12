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
  inset: 0;
  background: rgb(6 10 16 / 0.72);
  display: grid;
  place-items: center;
  z-index: 1000;
  padding: 1rem;
}

.modalContent {
  background: #151d28;
  border-radius: 24px;
  border: 0;
  box-shadow: none;
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
  padding: 1.2rem;
  border-bottom: 1px solid rgb(34 46 63 / 0.9);
}

.modalHeader h2 {
  margin: 0;
  font-size: 1.15rem;
  font-weight: 600;
  color: #ffffff;
}

.modalClose {
  width: 2.2rem;
  height: 2.2rem;
  border-radius: 999px;
  border: none;
  box-shadow: none;
  background: #222e3f;
  color: #abb8c9;
  cursor: pointer;
  padding: 0;
  line-height: 1;
  transition: background-color 140ms ease, color 140ms ease;
}

.modalClose:hover {
  background: #1c2736;
  color: #ffffff;
}

.modalBody {
  padding: 1.1rem 1.2rem;
}

.formField {
  margin-bottom: 1rem;
}

.formLabel {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
  color: #abb8c9;
  font-size: 0.875rem;
}

.formInput,
.formTextarea {
  width: 100%;
  padding: 0.72rem 0.78rem;
  border: none;
  border-radius: 14px;
  box-shadow: none;
  background: #1c2736;
  color: #ffffff;
  font-size: 0.875rem;
  transition: background-color 140ms ease;
}

.formTextarea--large {
  min-height: 300px;
  font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
}

.formInput::placeholder,
.formTextarea::placeholder {
  color: rgb(171 184 201 / 0.8);
}

.formInput:focus-visible,
.formTextarea:focus-visible {
  outline: 2px solid #0f73ff;
  outline-offset: 1px;
}

.formError {
  color: #eb2452;
  font-size: 0.75rem;
  margin-top: 0.25rem;
}

.formHint {
  font-size: 0.75rem;
  color: #abb8c9;
  margin-top: 0.25rem;
}

.formTabs {
  display: flex;
  margin-bottom: 1rem;
  border-bottom: 1px solid rgb(34 46 63 / 0.9);
}

.tabBtn {
  min-height: 2.1rem;
  padding: 0.4rem 0.95rem;
  border: none;
  border-radius: 999px;
  box-shadow: none;
  background: transparent;
  color: #abb8c9;
  cursor: pointer;
  transition: background-color 140ms ease, color 140ms ease;
}

.tabBtn:hover {
  background: #1c2736;
  color: #ffffff;
}

.tabBtn--active {
  background: #0f73ff;
  color: #ffffff;
}

.contentPreview {
  border: 0;
  border-radius: 14px;
  padding: 1rem;
  min-height: 300px;
  background: #1c2736;
}

.previewContent {
  line-height: 1.6;
  color: #ffffff;
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
  border-radius: 14px;
}

.coverRemove {
  position: absolute;
  top: -8px;
  right: -8px;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: #eb2452;
  color: #ffffff;
  border: none;
  box-shadow: none;
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
  padding: 1rem 1.2rem 1.2rem;
  border-top: 1px solid rgb(34 46 63 / 0.9);
  background: transparent;
}

.btn {
  min-height: 40px;
  padding: 0.6rem 1rem;
  border: none;
  border-radius: 999px;
  box-shadow: none;
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  transition: background-color 140ms ease, color 140ms ease, opacity 140ms ease;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.btn:disabled {
  opacity: 0.52;
  cursor: not-allowed;
}

.btn-primary {
  background: #0f73ff;
  color: #ffffff;
}

.btn-primary:hover:not(:disabled) {
  background: #0d65e6;
}

.btn-secondary {
  background: #222e3f;
  color: #abb8c9;
}

.btn-secondary:hover:not(:disabled) {
  background: #1c2736;
  color: #ffffff;
}

.btn-outline {
  background: #222e3f;
  color: #abb8c9;
}

.btn-outline:hover:not(:disabled) {
  background: #1c2736;
  color: #ffffff;
}

@media (max-width: 640px) {
  .modalOverlay {
    align-items: end;
    padding: 0.5rem;
  }

  .modalContent {
    max-height: 96vh;
    border-radius: 20px;
  }

  .modalHeader,
  .modalBody,
  .modalFooter {
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
