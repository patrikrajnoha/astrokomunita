import api from "./api";

export type CandidateStatus = "pending" | "approved" | "rejected";
export type CandidateType =
  | "eclipse_lunar"
  | "eclipse_solar"
  | "meteor_shower"
  | "planetary_event"
  | "aurora"
  | "other";

export type CandidateAiMode = "ai" | "template" | "mix";
export type CandidateAiScope = "all" | "missing" | "template";
export type CandidateDescriptionMode =
  | "all"
  | "missing"
  | "template"
  | "ai"
  | "ai_refined"
  | "translated"
  | "manual";

export type CandidateApproveBatchRunStatus =
  | "queued"
  | "running"
  | "completed"
  | "completed_with_failures"
  | "failed";

export type CandidateApproveBatchRun = {
  id: number;
  status: CandidateApproveBatchRunStatus;
  is_terminal: boolean;
  publish_generation_mode: CandidateAiMode;
  total_selected: number;
  processed: number;
  published: number;
  failed: number;
  limit_applied: number | null;
  progress_percent: number;
  started_at: string | null;
  finished_at: string | null;
  error_message: string | null;
  created_at: string | null;
  updated_at: string | null;
};

export type CandidateListItem = {
  id: number;
  source_name: string;
  source_url: string;
  title: string;
  translated_title: string | null;
  translated_description: string | null;
  translation_status: string | null;
  translation_mode: "template" | "translated" | "ai_title" | "ai_description" | "ai_refined" | "manual" | null;
  translation_error: string | null;
  translated_at: string | null;
  status: CandidateStatus;
  raw_type: string | null;
  type: CandidateType;
  canonical_key: string | null;
  confidence_score: string | number | null;
  matched_sources: string[] | null;

  max_at: string | null;
  start_at: string | null;
  end_at: string | null;

  short: string | null;
  description: string | null;

  reviewed_by: string | null;
  reviewed_at: string | null;
  reject_reason: string | null;
  published_event_id: number | null;

  created_at: string;
  updated_at: string;
};

export type LaravelPaginator<T> = {
  current_page: number;
  data: T[];

  first_page_url: string | null;
  from: number | null;
  last_page: number;
  last_page_url: string | null;

  links: Array<{ url: string | null; label: string; active: boolean }>;

  next_page_url: string | null;
  path: string;
  per_page: number;
  prev_page_url: string | null;
  to: number | null;
  total: number;
};

export type CandidateDetail = CandidateListItem & {
  source_uid: string;
  source_hash: string;

  original_title: string | null;
  original_description: string | null;

  visibility: string | null;
  raw_payload: string | null;

  published_event_id: number | null;
};

export type DuplicateCandidatePreviewItem = {
  id: number;
  title: string;
  source_name: string;
  status: string;
  start_at: string | null;
  confidence_score: number | null;
  matched_sources: string[];
};

export type DuplicateGroupPreview = {
  canonical_key: string;
  count: number;
  keeper: DuplicateCandidatePreviewItem;
  duplicates: DuplicateCandidatePreviewItem[];
  hidden_duplicates: number;
};

export const eventCandidates = {
  async list(params: {
    status?: CandidateStatus;
    type?: CandidateType;
    description_mode?: CandidateDescriptionMode;
    source?: string;
    source_key?: string;
    run_id?: number;
    year?: number;
    month?: number;
    week?: number;
    date_from?: string;
    date_to?: string;
    q?: string;
    page?: number;
    per_page?: number;
  }) {
    const res = await api.get<LaravelPaginator<CandidateListItem>>(
      "/admin/event-candidates",
      { params }
    );
    return res.data;
  },

  async get(id: number) {
    const res = await api.get<CandidateDetail>(
      `/admin/event-candidates/${id}`
    );
    return res.data;
  },

  async approve(id: number, options?: { mode?: CandidateAiMode }) {
    const payload = options?.mode ? { mode: options.mode } : {};
    const res = await api.post(`/admin/event-candidates/${id}/approve`, payload);
    return res.data;
  },

  async approveBatch(params: {
    status?: CandidateStatus;
    type?: CandidateType;
    description_mode?: CandidateDescriptionMode;
    source?: string;
    source_key?: string;
    run_id?: number;
    q?: string;
    year?: number;
    month?: number;
    week?: number;
    date_from?: string;
    date_to?: string;
    limit?: number;
    mode?: CandidateAiMode;
  }) {
    const res = await api.post(`/admin/event-candidates/approve-batch`, params, {
      timeout: 180000,
      meta: { skipErrorToast: true },
    });
    return res.data as { ok: boolean; published: number; failed: number; total_selected: number; limit_applied: number };
  },

  async approveBatchStart(params: {
    status?: CandidateStatus;
    type?: CandidateType;
    description_mode?: CandidateDescriptionMode;
    source?: string;
    source_key?: string;
    run_id?: number;
    q?: string;
    year?: number;
    month?: number;
    week?: number;
    date_from?: string;
    date_to?: string;
    limit?: number;
    mode?: CandidateAiMode;
  }) {
    const res = await api.post(`/admin/event-candidates/approve-batch/start`, params, {
      timeout: 30000,
      meta: { skipErrorToast: true },
    });

    return res.data as {
      ok: boolean;
      status: "accepted" | "done";
      run: CandidateApproveBatchRun;
    };
  },

  async approveBatchRunStatus(runId: number) {
    const res = await api.get(`/admin/event-candidates/approve-batch/runs/${runId}`, {
      timeout: 30000,
      meta: { skipErrorToast: true },
    });

    return res.data as {
      ok: boolean;
      run: CandidateApproveBatchRun;
    };
  },

  async publishManualBatch(params: {
    status?: string;
    type?: CandidateType;
    q?: string;
    year?: number;
    month?: number;
    limit?: number;
  }) {
    const res = await api.post(`/admin/manual-events/publish-batch`, params);
    return res.data as { ok: boolean; published: number; failed: number; total_selected: number; limit_applied: number };
  },

  async reject(id: number) {
    const res = await api.post(`/admin/event-candidates/${id}/reject`);
    return res.data;
  },

  async retranslate(id: number, options?: { mode?: CandidateAiMode }) {
    const payload = options?.mode ? { mode: options.mode } : {};
    const res = await api.post(`/admin/event-candidates/${id}/retranslate`, payload, {
      timeout: 30000,
      meta: { skipErrorToast: true },
    });
    return res.data;
  },

  async retranslateBatch(params: {
    status?: CandidateStatus;
    type?: CandidateType;
    description_mode?: CandidateDescriptionMode;
    source?: string;
    source_key?: string;
    run_id?: number;
    q?: string;
    year?: number;
    month?: number;
    week?: number;
    date_from?: string;
    date_to?: string;
    limit?: number;
    mode?: CandidateAiMode;
    ai_scope?: CandidateAiScope;
  }) {
    const res = await api.post(`/admin/event-candidates/retranslate-batch`, params, {
      timeout: 180000,
      meta: { skipErrorToast: true },
    });
    return res.data as {
      ok: boolean;
      queued: number;
      failed: number;
      total_selected: number;
      limit_applied: number;
      mode_applied?: CandidateAiMode;
      scope_applied?: CandidateAiScope;
    };
  },

  async updateTranslation(
    id: number,
    payload: { translated_title: string; translated_description?: string | null }
  ) {
    const res = await api.patch(`/admin/event-candidates/${id}/translation`, payload);
    return res.data;
  },

  async duplicatesPreview(params: {
    status?: CandidateStatus;
    type?: CandidateType;
    source?: string;
    source_key?: string;
    run_id?: number;
    q?: string;
    year?: number;
    month?: number;
    week?: number;
    date_from?: string;
    date_to?: string;
    limit_groups?: number;
    per_group?: number;
  }) {
    const res = await api.get('/admin/event-candidates/duplicates/preview', { params });
    return res.data as {
      status: 'ok';
      summary: {
        group_count: number;
        duplicate_candidates: number;
        limit_groups: number;
        per_group: number;
      };
      groups: DuplicateGroupPreview[];
    };
  },

  async cancelTranslationQueue() {
    const res = await api.post('/admin/event-candidates/translation-queue/cancel');
    return res.data as { ok: boolean; deleted_jobs: number };
  },

  async mergeDuplicates(payload: {
    status?: CandidateStatus;
    type?: CandidateType;
    source?: string;
    source_key?: string;
    run_id?: number;
    q?: string;
    year?: number;
    month?: number;
    week?: number;
    date_from?: string;
    date_to?: string;
    limit_groups?: number;
    dry_run?: boolean;
  }) {
    const res = await api.post('/admin/event-candidates/duplicates/merge', payload);
    return res.data as {
      status: 'ok' | 'dry_run';
      dry_run: boolean;
      summary: {
        group_count: number;
        merged_candidates: number;
        limit_groups: number;
      };
      groups: Array<{
        canonical_key: string;
        keeper_id: number;
        duplicate_ids: number[];
        count: number;
      }>;
    };
  },
};
