<template>
  <div class="page">
    <header class="pageHeader">
      <h1>Event Candidates</h1>
      <button class="btn btn-primary" @click="showManualForm = true">
        + Pridať manuálne
      </button>
    </header>

    <!-- Tab navigation -->
    <div class="tabNavigation">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        :class="['tabBtn', { 'tabBtn--active': activeTab === tab.key }]"
        @click="setActiveTab(tab.key)"
      >
        {{ tab.label }}
        <span v-if="tab.count" class="tabCount">{{ tab.count }}</span>
      </button>
    </div>

    <!-- Tab content -->
    <div class="tabContent">
      <!-- Crawled candidates tab -->
      <div v-show="activeTab === 'crawled'" class="tabPane">
        <CandidatesFilters
          v-model:status="crawledFilters.status"
          v-model:type="crawledFilters.type"
          v-model:source="crawledFilters.source"
          v-model:search="crawledFilters.search"
          @filter="crawledTable.refresh"
          @clear="clearCrawledFilters"
        />
        
        <CandidatesTable
          :data="crawledTable.data"
          :loading="crawledTable.loading"
          :error="crawledTable.error"
          :pagination="crawledTable.pagination"
          @row-click="openCandidate"
          @approve="handleApprove"
          @reject="handleReject"
          @publish="handlePublish"
        />
        
        <PaginationBar
          v-if="crawledTable.pagination"
          :pagination="crawledTable.pagination"
          :has-prev-page="crawledTable.hasPrevPage"
          :has-next-page="crawledTable.hasNextPage"
          @prev-page="crawledTable.prevPage"
          @next-page="crawledTable.nextPage"
          @per-page-change="crawledTable.setPerPage"
        />
      </div>

      <!-- Manual candidates tab -->
      <div v-show="activeTab === 'manual'" class="tabPane">
        <CandidatesFilters
          v-model:status="manualFilters.status"
          v-model:type="manualFilters.type"
          v-model:search="manualFilters.search"
          @filter="manualTable.refresh"
          @clear="clearManualFilters"
          :show-source="false"
        />
        
        <CandidatesTable
          :data="manualTable.data"
          :loading="manualTable.loading"
          :error="manualTable.error"
          :pagination="manualTable.pagination"
          :show-source="false"
          @row-click="openCandidate"
          @edit="editManualCandidate"
          @delete="handleDelete"
          @publish="handlePublish"
        />
        
        <PaginationBar
          v-if="manualTable.pagination"
          :pagination="manualTable.pagination"
          :has-prev-page="manualTable.hasPrevPage"
          :has-next-page="manualTable.hasNextPage"
          @prev-page="manualTable.prevPage"
          @next-page="manualTable.nextPage"
          @per-page-change="manualTable.setPerPage"
        />
      </div>

      <!-- Reviewed candidates tab -->
      <div v-show="activeTab === 'reviewed'" class="tabPane">
        <CandidatesFilters
          v-model:status="reviewedFilters.status"
          v-model:type="reviewedFilters.type"
          v-model:search="reviewedFilters.search"
          @filter="reviewedTable.refresh"
          @clear="clearReviewedFilters"
          :show-source="false"
        />
        
        <CandidatesTable
          :data="reviewedTable.data"
          :loading="reviewedTable.loading"
          :error="reviewedTable.error"
          :pagination="reviewedTable.pagination"
          :show-source="false"
          @row-click="openCandidate"
          @publish="handlePublish"
          @unreview="handleUnreview"
        />
        
        <PaginationBar
          v-if="reviewedTable.pagination"
          :pagination="reviewedTable.pagination"
          :has-prev-page="reviewedTable.hasPrevPage"
          :has-next-page="reviewedTable.hasNextPage"
          @prev-page="reviewedTable.prevPage"
          @next-page="reviewedTable.nextPage"
          @per-page-change="reviewedTable.setPerPage"
        />
      </div>

      <!-- Published candidates tab -->
      <div v-show="activeTab === 'published'" class="tabPane">
        <CandidatesFilters
          v-model:type="publishedFilters.type"
          v-model:search="publishedFilters.search"
          @filter="publishedTable.refresh"
          @clear="clearPublishedFilters"
          :show-status="false"
          :show-source="false"
        />
        
        <CandidatesTable
          :data="publishedTable.data"
          :loading="publishedTable.loading"
          :error="publishedTable.error"
          :pagination="publishedTable.pagination"
          :show-status="false"
          :show-source="false"
          :show-actions="false"
          @row-click="openCandidate"
        />
        
        <PaginationBar
          v-if="publishedTable.pagination"
          :pagination="publishedTable.pagination"
          :has-prev-page="publishedTable.hasPrevPage"
          :has-next-page="publishedTable.hasNextPage"
          @prev-page="publishedTable.prevPage"
          @next-page="publishedTable.nextPage"
          @per-page-change="publishedTable.setPerPage"
        />
      </div>
    </div>

    <!-- Manual form modal -->
    <CandidateForm
      v-if="showManualForm"
      :candidate="editingCandidate"
      @close="closeManualForm"
      @save="saveManualCandidate"
    />
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useAdminTable } from '@/composables/useAdminTable.js';
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import { CANDIDATES_TABS, CANDIDATE_STATUS } from '@/utils/constants.js';
import { getCandidates, approveCandidate, rejectCandidate, publishCandidate, updateCandidate, createCandidate, deleteCandidate } from '@/services/api/admin/candidates.js';

// Components
import CandidatesTable from '@/components/admin/tables/CandidatesTable.vue';
import CandidatesFilters from '@/components/admin/filters/CandidatesFilters.vue';
import PaginationBar from '@/components/admin/shared/PaginationBar.vue';
import CandidateForm from '@/components/admin/forms/CandidateForm.vue';

const router = useRouter();
const { confirm, prompt } = useConfirm()
const toast = useToast()

// State
const activeTab = ref(CANDIDATES_TABS.CRAWLED);
const showManualForm = ref(false);
const editingCandidate = ref(null);

// Tab definitions
const tabs = computed(() => [
  { key: CANDIDATES_TABS.CRAWLED, label: 'Crawled', count: crawledTable.data?.total || 0 },
  { key: CANDIDATES_TABS.MANUAL, label: 'Manual', count: manualTable.data?.total || 0 },
  { key: CANDIDATES_TABS.REVIEWED, label: 'Reviewed', count: reviewedTable.data?.total || 0 },
  { key: CANDIDATES_TABS.PUBLISHED, label: 'Published', count: publishedTable.data?.total || 0 }
]);

// Table instances pre každý tab
const crawledTable = useAdminTable(
  (params) => getCandidates({ ...params, source: 'crawled' }),
  { defaultFilters: { status: CANDIDATE_STATUS.PENDING } }
);

const manualTable = useAdminTable(
  (params) => getCandidates({ ...params, source: 'manual' }),
  { defaultFilters: { status: CANDIDATE_STATUS.DRAFT } }
);

const reviewedTable = useAdminTable(
  (params) => getCandidates({ ...params, status: CANDIDATE_STATUS.APPROVED }),
  { defaultFilters: {} }
);

const publishedTable = useAdminTable(
  (params) => getCandidates({ ...params, status: CANDIDATE_STATUS.PUBLISHED }),
  { defaultFilters: {} }
);

// Filters pre každý tab
const crawledFilters = ref({
  status: CANDIDATE_STATUS.PENDING,
  type: '',
  source: '',
  search: ''
});

const manualFilters = ref({
  status: CANDIDATE_STATUS.DRAFT,
  type: '',
  search: ''
});

const reviewedFilters = ref({
  status: CANDIDATE_STATUS.APPROVED,
  type: '',
  search: ''
});

const publishedFilters = ref({
  type: '',
  search: ''
});

// Methods
function setActiveTab(tabKey) {
  activeTab.value = tabKey;
}

function openCandidate(candidate) {
  router.push(`/admin/candidates/${candidate.id}`);
}

function editManualCandidate(candidate) {
  editingCandidate.value = candidate;
  showManualForm.value = true;
}

// Action handlers
async function handleApprove(candidate) {
  try {
    await approveCandidate(candidate.id);
    toast.success('Kandidát schválený');
    crawledTable.refresh();
    manualTable.refresh();
  } catch (err) {
    toast.error(err?.response?.data?.message || 'Schválenie zlyhalo');
  }
}

async function handleReject(candidate) {
  const reason = await prompt({
    title: 'Zamietnuť kandidáta',
    message: 'Dôvod zamietnutia:',
    placeholder: 'Napíš dôvod',
    confirmText: 'Reject',
    cancelText: 'Cancel',
    required: true,
    variant: 'danger',
  });
  if (!reason) return;
  
  try {
    await rejectCandidate(candidate.id, { reason });
    toast.success('Kandidát zamietnutý');
    crawledTable.refresh();
    manualTable.refresh();
  } catch (err) {
    toast.error(err?.response?.data?.message || 'Reject zlyhal');
  }
}

async function handlePublish(candidate) {
  try {
    await publishCandidate(candidate.id);
    toast.success('Kandidát publikovaný ako event');
    crawledTable.refresh();
    manualTable.refresh();
    reviewedTable.refresh();
  } catch (err) {
    toast.error(err?.response?.data?.message || 'Publikovanie zlyhalo');
  }
}

async function handleDelete(candidate) {
  const ok = await confirm({
    title: 'Vymazať kandidáta',
    message: `Naozaj chcete vymazať kandidáta "${candidate.title}"?`,
    confirmText: 'Delete',
    cancelText: 'Cancel',
    variant: 'danger',
  });
  if (!ok) {
    return;
  }
  
  try {
    await deleteCandidate(candidate.id);
    toast.success('Kandidát vymazaný');
    manualTable.refresh();
  } catch (err) {
    toast.error(err?.response?.data?.message || 'Mazanie zlyhalo');
  }
}

async function handleUnreview() {
  // Implement unreview logic
  toast.success('Schválenie zrušené');
  reviewedTable.refresh();
}

async function saveManualCandidate(candidateData) {
  try {
    if (editingCandidate.value) {
      await updateCandidate(editingCandidate.value.id, candidateData);
      toast.success('Kandidát aktualizovaný');
    } else {
      await createCandidate(candidateData);
      toast.success('Kandidát vytvorený');
    }
    
    closeManualForm();
    manualTable.refresh();
  } catch (err) {
    toast.error(err?.response?.data?.message || 'Uloženie zlyhalo');
  }
}

function closeManualForm() {
  showManualForm.value = false;
  editingCandidate.value = null;
}

// Filter methods
function clearCrawledFilters() {
  crawledFilters.value = {
    status: CANDIDATE_STATUS.PENDING,
    type: '',
    source: '',
    search: ''
  };
  crawledTable.setFilters(crawledFilters.value);
}

function clearManualFilters() {
  manualFilters.value = {
    status: CANDIDATE_STATUS.DRAFT,
    type: '',
    search: ''
  };
  manualTable.setFilters(manualFilters.value);
}

function clearReviewedFilters() {
  reviewedFilters.value = {
    status: CANDIDATE_STATUS.APPROVED,
    type: '',
    search: ''
  };
  reviewedTable.setFilters(reviewedFilters.value);
}

function clearPublishedFilters() {
  publishedFilters.value = { 
    type: '',
    search: ''
  };
  publishedTable.setFilters(publishedFilters.value);
}

// Watch filters a aktualizuj tabuľky
watch(crawledFilters, (newFilters) => {
  crawledTable.setFilters(newFilters);
}, { deep: true });

watch(manualFilters, (newFilters) => {
  manualTable.setFilters(newFilters);
}, { deep: true });

watch(reviewedFilters, (newFilters) => {
  reviewedTable.setFilters(newFilters);
}, { deep: true });

watch(publishedFilters, (newFilters) => {
  publishedTable.setFilters(newFilters);
}, { deep: true });
</script>

<style scoped>
.pageHeader {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
}

.pageHeader h1 {
  margin: 0;
  font-size: 1.875rem;
  font-weight: 700;
  color: var(--color-text);
}

.tabNavigation {
  display: flex;
  border-bottom: 1px solid var(--color-border);
  margin-bottom: 2rem;
}

.tabBtn {
  padding: 0.75rem 1.5rem;
  border: none;
  background: none;
  color: var(--color-text-secondary);
  cursor: pointer;
  border-bottom: 2px solid transparent;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.tabBtn:hover {
  color: var(--color-text);
  background: var(--color-background-hover);
}

.tabBtn--active {
  color: var(--color-primary);
  border-bottom-color: var(--color-primary);
}

.tabCount {
  background: var(--color-background-secondary);
  color: var(--color-text-secondary);
  padding: 0.125rem 0.5rem;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 500;
}

.tabBtn--active .tabCount {
  background: var(--color-primary);
  color: white;
}

.tabContent {
  min-height: 400px;
}

.tabPane {
  animation: fadeIn 0.2s ease-in-out;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

@media (max-width: 768px) {
  .pageHeader {
    flex-direction: column;
    align-items: stretch;
    gap: 1rem;
  }
  
  .tabNavigation {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }
  
  .tabBtn {
    flex-shrink: 0;
    padding: 0.75rem 1rem;
    font-size: 0.875rem;
  }
}
</style>
