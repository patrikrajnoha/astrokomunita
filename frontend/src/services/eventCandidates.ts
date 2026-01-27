import api from "./api";

export type CandidateStatus = "pending" | "approved" | "rejected";
export type CandidateType =
  | "eclipse_lunar"
  | "eclipse_solar"
  | "meteor_shower"
  | "planetary_event"
  | "other";

export type CandidateListItem = {
  id: number;
  source_name: string;
  source_url: string;
  title: string;
  status: CandidateStatus;
  raw_type: string | null;
  type: CandidateType;

  max_at: string | null;
  start_at: string | null;
  end_at: string | null;

  short: string | null;
  description: string | null;

  reviewed_by: string | null;
  reviewed_at: string | null;
  reject_reason: string | null;

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

  visibility: string | null;
  raw_payload: string | null;

  published_event_id: number | null;
};

export const eventCandidates = {
  async list(params: {
    status?: CandidateStatus;
    type?: CandidateType;
    source?: string;
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

  async approve(id: number) {
    const res = await api.post(`/admin/event-candidates/${id}/approve`);
    return res.data;
  },

  async reject(id: number) {
    const res = await api.post(`/admin/event-candidates/${id}/reject`);
    return res.data;
  },
};
