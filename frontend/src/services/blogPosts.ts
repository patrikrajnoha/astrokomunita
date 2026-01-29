import api from "./api";

export type BlogPost = {
  id: number;
  user_id: number;
  title: string;
  slug: string;
  content: string;
  published_at: string | null;
  cover_image_url?: string | null;
  views?: number;
  tags?: Array<{
    id: number;
    name: string;
    slug: string;
  }>;
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

export type BlogPostStatus = "published" | "draft" | "scheduled";

export const blogPosts = {
  async listPublic(params: { page?: number; tag?: string; q?: string }) {
    const res = await api.get<LaravelPaginator<BlogPost>>("/blog-posts", {
      params,
    });
    return res.data;
  },

  async getPublic(slug: string) {
    const res = await api.get<BlogPost>(`/blog-posts/${slug}`);
    return res.data;
  },

  async getRelated(slug: string) {
    const res = await api.get<BlogPost[]>(`/blog-posts/${slug}/related`);
    return res.data;
  },

  async listTagsPublic() {
    const res = await api.get<
      Array<{ id: number; name: string; slug: string; published_posts_count: number }>
    >("/blog-tags");
    return res.data;
  },

  async adminList(params: {
    status?: BlogPostStatus;
    page?: number;
    per_page?: number;
  }) {
    const res = await api.get<LaravelPaginator<BlogPost>>("/admin/blog-posts", {
      params,
    });
    return res.data;
  },

  async adminGet(id: number) {
    const res = await api.get<BlogPost>(`/admin/blog-posts/${id}`);
    return res.data;
  },

  async adminCreate(payload: {
    title: string;
    content: string;
    published_at: string | null;
    cover_image?: File | null;
    tags?: string[];
  }) {
    const res = await api.post<BlogPost>(
      "/admin/blog-posts",
      buildBlogPostPayload(payload)
    );
    return res.data;
  },

  async adminUpdate(
    id: number,
    payload: {
      title?: string;
      content?: string;
      published_at?: string | null;
      cover_image?: File | null;
      tags?: string[];
    }
  ) {
    const body = buildBlogPostPayload(payload);
    if (body instanceof FormData) {
      body.append("_method", "PUT");
      const res = await api.post<BlogPost>(`/admin/blog-posts/${id}`, body);
      return res.data;
    }

    const res = await api.put<BlogPost>(`/admin/blog-posts/${id}`, body);
    return res.data;
  },

  async adminDelete(id: number) {
    const res = await api.delete(`/admin/blog-posts/${id}`);
    return res.data;
  },
};

function buildBlogPostPayload(payload: {
  title?: string;
  content?: string;
  published_at?: string | null;
  cover_image?: File | null;
  tags?: string[];
}) {
  if (payload.cover_image instanceof File) {
    const form = new FormData();
    if (payload.title !== undefined) {
      form.append("title", payload.title);
    }
    if (payload.content !== undefined) {
      form.append("content", payload.content);
    }
    if (payload.published_at !== undefined && payload.published_at !== null) {
      form.append("published_at", payload.published_at);
    }
    if (payload.tags && payload.tags.length > 0) {
      payload.tags.forEach((tag) => form.append("tags[]", tag));
    }
    form.append("cover_image", payload.cover_image);
    return form;
  }

  const body: Record<string, string | string[] | null> = {};
  if (payload.title !== undefined) body.title = payload.title;
  if (payload.content !== undefined) body.content = payload.content;
  if (payload.published_at !== undefined) body.published_at = payload.published_at;
  if (payload.tags !== undefined) {
    body.tags = payload.tags;
  }
  return body;
}
