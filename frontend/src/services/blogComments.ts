import api from "./api";

export type BlogComment = {
  id: number;
  blog_post_id: number;
  user_id: number;
  content: string;
  created_at: string;
  updated_at: string;
  user?: {
    id: number;
    name: string;
    email: string;
    is_admin: boolean;
  };
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

export const blogComments = {
  async list(slug: string, params?: { page?: number }) {
    const res = await api.get<LaravelPaginator<BlogComment>>(
      `/blog-posts/${slug}/comments`,
      { params }
    );
    return res.data;
  },

  async create(slug: string, payload: { content: string }) {
    const res = await api.post<BlogComment>(
      `/blog-posts/${slug}/comments`,
      payload
    );
    return res.data;
  },

  async remove(slug: string, id: number) {
    const res = await api.delete(`/blog-posts/${slug}/comments/${id}`);
    return res.data;
  },
};
