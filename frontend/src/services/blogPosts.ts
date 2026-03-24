import api from "./api";
import { normalizeMediaPath, normalizeMediaUrl } from "@/utils/profileMedia";

export type BlogPost = {
  id: number;
  user_id: number;
  title: string;
  slug: string;
  content: string;
  published_at: string | null;
  is_hidden?: boolean;
  cover_image_url?: string | null;
  cover_image_path?: string | null;
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
  tag_sync?: {
    attached_existing: number;
    created_new: number;
    added_total: number;
    selected_total: number;
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
export type AdminBlogPostStatus = BlogPostStatus | "hidden";

export type BlogWidgetArticle = {
  id: number;
  title: string;
  slug: string;
  thumbnail_url: string | null;
  views: number;
  created_at: string;
};

export type BlogWidgetPayload = {
  most_read: BlogWidgetArticle[];
  latest: BlogWidgetArticle[];
  generated_at: string;
};

export type AdminBlogTagSuggestion = {
  id: number;
  name: string;
  reason: string;
};

export type AdminBlogTagSuggestionResponse = {
  status: string;
  tags: AdminBlogTagSuggestion[];
  fallback_used: boolean;
  reason?: string | null;
  last_run?: Record<string, unknown>;
};

export type AdminBlogInlineImageUploadResponse = {
  path: string;
  url: string;
  mime?: string;
  name?: string;
  size?: number;
};

export const blogPosts = {
  async listPublic(params: { page?: number; tag?: string; q?: string }) {
    const res = await api.get<LaravelPaginator<BlogPost>>("/blog-posts", {
      params,
    });
    return normalizeBlogPostPaginator(res.data);
  },

  async widget() {
    const res = await api.get<BlogWidgetPayload>("/articles/widget");
    return res.data;
  },

  async getPublic(slug: string) {
    const res = await api.get<BlogPost>(`/blog-posts/${slug}`);
    return normalizeBlogPost(res.data);
  },

  async getRelated(slug: string) {
    const res = await api.get<BlogPost[]>(`/blog-posts/${slug}/related`);
    return Array.isArray(res.data) ? res.data.map(normalizeBlogPost) : [];
  },

  async listTagsPublic() {
    const res = await api.get<
      Array<{ id: number; name: string; slug: string; published_posts_count: number }>
    >("/blog-tags");
    return res.data;
  },

  async adminList(params: {
    status?: AdminBlogPostStatus;
    page?: number;
    per_page?: number;
    q?: string;
  }) {
    const res = await api.get<LaravelPaginator<BlogPost>>("/admin/blog-posts", {
      params,
    });
    return normalizeBlogPostPaginator(res.data);
  },

  async adminGet(id: number) {
    const res = await api.get<BlogPost>(`/admin/blog-posts/${id}`);
    return normalizeBlogPost(res.data);
  },

  async adminCreate(payload: {
    title: string;
    content: string;
    published_at: string | null;
    cover_image?: File | null;
    tags?: string[];
    tag_ids?: number[];
  }) {
    const res = await api.post<BlogPost>(
      "/admin/blog-posts",
      buildBlogPostPayload(payload)
    );
    return normalizeBlogPost(res.data);
  },

  async adminUpdate(
    id: number,
    payload: {
      title?: string;
      content?: string;
      published_at?: string | null;
      is_hidden?: boolean;
      cover_image?: File | null;
      tags?: string[];
      tag_ids?: number[];
    }
  ) {
    const body = buildBlogPostPayload(payload);
    if (body instanceof FormData) {
      body.append("_method", "PUT");
      const res = await api.post<BlogPost>(`/admin/blog-posts/${id}`, body);
      return normalizeBlogPost(res.data);
    }

    const res = await api.put<BlogPost>(`/admin/blog-posts/${id}`, body);
    return normalizeBlogPost(res.data);
  },

  async adminDelete(id: number) {
    const res = await api.delete(`/admin/blog-posts/${id}`);
    return res.data;
  },

  async adminSuggestTags(
    id: number,
    payload?: {
      mode?: "existing_only" | "allow_new";
    }
  ) {
    const res = await api.post<AdminBlogTagSuggestionResponse>(
      `/admin/blog-posts/${id}/ai/suggest-tags`,
      payload || {},
      {
        meta: { skipErrorToast: true },
      }
    );
    return res.data;
  },

  async adminUploadInlineImage(file: File) {
    const form = new FormData();
    form.append("image", file);

    const res = await api.post<AdminBlogInlineImageUploadResponse>(
      "/admin/blog-posts/images",
      form
    );
    return res.data;
  },
};

function buildBlogPostPayload(payload: {
  title?: string;
  content?: string;
  published_at?: string | null;
  is_hidden?: boolean;
  cover_image?: File | null;
  tags?: string[];
  tag_ids?: number[];
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
    if (payload.is_hidden !== undefined) {
      form.append("is_hidden", payload.is_hidden ? "1" : "0");
    }
    if (payload.tags && payload.tags.length > 0) {
      payload.tags.forEach((tag) => form.append("tags[]", tag));
    }
    if (payload.tag_ids && payload.tag_ids.length > 0) {
      payload.tag_ids.forEach((tagId) => form.append("tag_ids[]", String(tagId)));
    }
    form.append("cover_image", payload.cover_image);
    return form;
  }

  const body: Record<string, string | string[] | number[] | boolean | null> = {};
  if (payload.title !== undefined) body.title = payload.title;
  if (payload.content !== undefined) body.content = payload.content;
  if (payload.published_at !== undefined) body.published_at = payload.published_at;
  if (payload.is_hidden !== undefined) body.is_hidden = payload.is_hidden;
  if (payload.tags !== undefined) {
    body.tags = payload.tags;
  }
  if (payload.tag_ids !== undefined) {
    body.tag_ids = payload.tag_ids;
  }
  return body;
}

function normalizeBlogPost(post: BlogPost): BlogPost {
  if (!post || typeof post !== "object") return post;

  const coverImageUrl =
    normalizeMediaUrl(post.cover_image_url || "") ||
    normalizeMediaPath(post.cover_image_path || "");

  return {
    ...post,
    cover_image_url: coverImageUrl || null,
  };
}

function normalizeBlogPostPaginator(
  payload: LaravelPaginator<BlogPost>
): LaravelPaginator<BlogPost> {
  if (!payload || typeof payload !== "object") return payload;

  return {
    ...payload,
    data: Array.isArray(payload.data) ? payload.data.map(normalizeBlogPost) : [],
  };
}
