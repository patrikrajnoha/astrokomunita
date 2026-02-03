/**
 * Admin table composable - štandardizované tabuľky, filtre, paginácia
 * Používa sa vo všetkých admin views s tabuľkovými dátami
 */

import { ref, computed, watch } from 'vue';
import { PAGINATION, LOADING_STATES } from '@/utils/constants.js';

/**
 * Admin table composable
 * @param {Function} fetchFunction - Funkcia na fetch dát
 * @param {Object} options - Možnosti konfigurácie
 * @returns {Object} Composable API
 */
export function useAdminTable(fetchFunction, options = {}) {
  const {
    defaultPerPage = PAGINATION.DEFAULT_PER_PAGE,
    defaultFilters = {},
    autoFetch = true
  } = options;

  // State
  const loading = ref(false);
  const error = ref(null);
  const data = ref(null);
  
  // Pagination
  const page = ref(PAGINATION.DEFAULT_PAGE);
  const perPage = ref(defaultPerPage);
  
  // Filters
  const filters = ref({ ...defaultFilters });
  const search = ref('');
  
  // Computed
  const loadingState = computed(() => {
    if (loading.value) return LOADING_STATES.LOADING;
    if (error.value) return LOADING_STATES.ERROR;
    if (data.value) return LOADING_STATES.SUCCESS;
    return LOADING_STATES.IDLE;
  });
  
  const pagination = computed(() => {
    if (!data.value?.data) return null;
    
    return {
      currentPage: data.value.current_page || 1,
      lastPage: data.value.last_page || 1,
      perPage: data.value.per_page || defaultPerPage,
      total: data.value.total || 0,
      from: data.value.from || 0,
      to: data.value.to || 0
    };
  });
  
  const hasNextPage = computed(() => {
    return pagination.value ? pagination.value.currentPage < pagination.value.lastPage : false;
  });
  
  const hasPrevPage = computed(() => {
    return pagination.value ? pagination.value.currentPage > 1 : false;
  });
  
  const isEmpty = computed(() => {
    return !loading.value && data.value?.data?.length === 0;
  });
  
  // Methods
  const buildQueryParams = () => {
    const params = {
      page: page.value,
      per_page: perPage.value
    };
    
    // Pridať search
    if (search.value) {
      params.search = search.value;
    }
    
    // Pridať filtre (ignorovať empty hodnoty)
    Object.entries(filters.value).forEach(([key, value]) => {
      if (value !== '' && value !== null && value !== undefined) {
        params[key] = value;
      }
    });
    
    return params;
  };
  
  const fetch = async () => {
    if (!fetchFunction) return;
    
    loading.value = true;
    error.value = null;
    
    try {
      const params = buildQueryParams();
      const response = await fetchFunction(params);
      data.value = response.data;
    } catch (err) {
      console.error('Failed to fetch table data:', err);
      error.value = err.response?.data?.message || 'Failed to load data';
      data.value = null;
    } finally {
      loading.value = false;
    }
  };
  
  const refresh = () => {
    return fetch();
  };
  
  const goToPage = (newPage) => {
    if (newPage >= 1 && (!pagination.value || newPage <= pagination.value.lastPage)) {
      page.value = newPage;
    }
  };
  
  const nextPage = () => {
    if (hasNextPage.value) {
      page.value++;
    }
  };
  
  const prevPage = () => {
    if (hasPrevPage.value) {
      page.value--;
    }
  };
  
  const setPerPage = (newPerPage) => {
    if (newPerPage >= 1 && newPerPage <= PAGINATION.MAX_PER_PAGE) {
      perPage.value = newPerPage;
      page.value = PAGINATION.DEFAULT_PAGE; // Reset page na 1
    }
  };
  
  const setFilter = (key, value) => {
    filters.value[key] = value;
    page.value = PAGINATION.DEFAULT_PAGE; // Reset page na 1
  };
  
  const setFilters = (newFilters) => {
    filters.value = { ...filters.value, ...newFilters };
    page.value = PAGINATION.DEFAULT_PAGE; // Reset page na 1
  };
  
  const clearFilters = () => {
    filters.value = { ...defaultFilters };
    search.value = '';
    page.value = PAGINATION.DEFAULT_PAGE;
  };
  
  const setSearch = (newSearch) => {
    search.value = newSearch;
    page.value = PAGINATION.DEFAULT_PAGE; // Reset page na 1
  };
  
  const reset = () => {
    page.value = PAGINATION.DEFAULT_PAGE;
    perPage.value = defaultPerPage;
    filters.value = { ...defaultFilters };
    search.value = '';
    error.value = null;
    data.value = null;
  };
  
  // Auto fetch na zmeny
  if (autoFetch) {
    watch([page, perPage, filters, search], () => {
      fetch();
    }, { immediate: false });
  }
  
  // Initial fetch
  if (autoFetch) {
    fetch();
  }
  
  return {
    // State
    loading,
    error,
    data,
    loadingState,
    isEmpty,
    
    // Pagination
    page,
    perPage,
    pagination,
    hasNextPage,
    hasPrevPage,
    
    // Filters
    filters,
    search,
    
    // Methods
    fetch,
    refresh,
    goToPage,
    nextPage,
    prevPage,
    setPerPage,
    setFilter,
    setFilters,
    clearFilters,
    setSearch,
    reset,
    buildQueryParams
  };
}
