<template>
  <div class="modalOverlay" @click="$emit('close')">
    <div class="modalContent" @click.stop>
      <div class="modalHeader">
        <h2>{{ isEditing ? 'Upraviť kandidáta' : 'Pridať nového kandidáta' }}</h2>
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
            placeholder="Zadajte názov eventu..."
            required
          />
          <div v-if="errors.title" class="formError">{{ errors.title }}</div>
        </div>
        
        <!-- Description -->
        <div class="formField">
          <label class="formLabel">Popis *</label>
          <textarea
            v-model="form.description"
            class="formTextarea"
            placeholder="Zadajte popis eventu..."
            rows="4"
            required
          ></textarea>
          <div v-if="errors.description" class="formError">{{ errors.description }}</div>
        </div>
        
        <!-- Event Type -->
        <div class="formField">
          <label class="formLabel">Typ eventu *</label>
          <select v-model="form.event_type" class="formSelect" required>
            <option value="meteor_shower">Meteorický dážď</option>
            <option value="eclipse">Zatmenie</option>
            <option value="comet">Kométa</option>
            <option value="planetary">Planetárny úkaz</option>
            <option value="aurora">Polárna žiara</option>
            <option value="other">Iné</option>
          </select>
          <div v-if="errors.event_type" class="formError">{{ errors.event_type }}</div>
        </div>
        
        <!-- Start Date -->
        <div class="formField">
          <label class="formLabel">Začiatok *</label>
          <input
            v-model="form.starts_at"
            type="datetime-local"
            class="formInput"
            required
          />
          <div v-if="errors.starts_at" class="formError">{{ errors.starts_at }}</div>
        </div>
        
        <!-- End Date -->
        <div class="formField">
          <label class="formLabel">Koniec</label>
          <input
            v-model="form.ends_at"
            type="datetime-local"
            class="formInput"
          />
          <div v-if="errors.ends_at" class="formError">{{ errors.ends_at }}</div>
        </div>
        
        <!-- Additional Notes -->
        <div class="formField">
          <label class="formLabel">Poznámky</label>
          <textarea
            v-model="form.notes"
            class="formTextarea"
            placeholder="Interné poznámky..."
            rows="2"
          ></textarea>
        </div>
      </form>
      
      <div class="modalFooter">
        <button type="button" class="btn btn-secondary" @click="$emit('close')">
          Zrušiť
        </button>
        <button
          type="submit"
          form="candidateForm"
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
import { EVENT_TYPES } from '@/utils/constants.js';

const props = defineProps({
  candidate: {
    type: Object,
    default: null
  }
});

const emit = defineEmits(['close', 'save']);

// State
const loading = ref(false);
const errors = ref({});

// Computed
const isEditing = computed(() => !!props.candidate);

// Form data
const form = reactive({
  title: '',
  description: '',
  event_type: EVENT_TYPES.METEOR_SHOWER,
  starts_at: '',
  ends_at: '',
  notes: ''
});

// Initialize form with candidate data
if (props.candidate) {
  form.title = props.candidate.title || '';
  form.description = props.candidate.description || '';
  form.event_type = props.candidate.event_type || EVENT_TYPES.METEOR_SHOWER;
  form.starts_at = toDateTimeLocal(props.candidate.starts_at);
  form.ends_at = toDateTimeLocal(props.candidate.ends_at);
  form.notes = props.candidate.notes || '';
}

// Validation rules
const validationRules = {
  title: commonRules.title,
  description: commonRules.description,
  event_type: [{ type: 'required' }],
  starts_at: [
    { type: 'required' },
    { 
      type: 'date', 
      options: { 
        required: true,
        allowFuture: true,
        allowPast: false
      }
    }
  ],
  ends_at: [
    { 
      type: 'date', 
      options: { 
        required: false,
        allowFuture: true,
        allowPast: false,
        minDate: form.starts_at
      }
    }
  ]
};

// Methods
function validateCandidateForm() {
  errors.value = validateForm(form, validationRules);
  return Object.keys(errors.value).length === 0;
}

async function handleSubmit() {
  if (!validateCandidateForm()) {
    return;
  }
  
  loading.value = true;
  
  try {
    const formData = {
      ...form,
      starts_at: fromDateTimeLocal(form.starts_at),
      ends_at: fromDateTimeLocal(form.ends_at) || null
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
  max-width: 600px;
  max-height: 90vh;
  overflow-y: auto;
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

.formInput, .formTextarea, .formSelect {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: 0.375rem;
  background: var(--color-background);
  color: var(--color-text);
  font-size: 0.875rem;
  transition: border-color 0.2s, box-shadow 0.2s;
}

.formInput:focus, .formTextarea:focus, .formSelect:focus {
  outline: none;
  border-color: var(--color-primary);
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.formTextarea {
  resize: vertical;
  min-height: 100px;
}

.formError {
  color: var(--color-danger);
  font-size: 0.75rem;
  margin-top: 0.25rem;
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
}
</style>
