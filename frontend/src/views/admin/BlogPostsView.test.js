import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import BlogPostsView from "@/views/admin/BlogPostsView.vue";

const adminListMock = vi.hoisted(() => vi.fn());
const adminCreateMock = vi.hoisted(() => vi.fn());
const adminUpdateMock = vi.hoisted(() => vi.fn());
const adminDeleteMock = vi.hoisted(() => vi.fn());
const adminSuggestTagsMock = vi.hoisted(() => vi.fn());
const confirmMock = vi.hoisted(() => vi.fn());
const toastSuccessMock = vi.hoisted(() => vi.fn());
const toastErrorMock = vi.hoisted(() => vi.fn());

vi.mock("@/services/blogPosts", () => ({
  blogPosts: {
    adminList: (...args) => adminListMock(...args),
    adminCreate: (...args) => adminCreateMock(...args),
    adminUpdate: (...args) => adminUpdateMock(...args),
    adminDelete: (...args) => adminDeleteMock(...args),
    adminSuggestTags: (...args) => adminSuggestTagsMock(...args),
  },
}));

vi.mock("@/composables/useConfirm", () => ({
  useConfirm: () => ({
    confirm: (...args) => confirmMock(...args),
  }),
}));

vi.mock("@/composables/useToast", () => ({
  useToast: () => ({
    success: (...args) => toastSuccessMock(...args),
    error: (...args) => toastErrorMock(...args),
  }),
}));

function flush() {
  return new Promise((resolve) => setTimeout(resolve, 0));
}

describe("BlogPostsView AI tag suggestions", () => {
  beforeEach(() => {
    vi.clearAllMocks();

    adminListMock.mockResolvedValue({
      current_page: 1,
      data: [
        {
          id: 7,
          title: "Mars observacny plan",
          content: "Obsah clanku o planetach a pozorovani oblohy.",
          published_at: "2026-03-01T10:00:00Z",
          cover_image_url: null,
          tags: [{ id: 1, name: "Mesiac", slug: "mesiac" }],
          user: { id: 2, name: "Admin", email: "admin@example.com", is_admin: true },
        },
      ],
      first_page_url: null,
      from: 1,
      last_page: 1,
      last_page_url: null,
      links: [],
      next_page_url: null,
      path: "/api/admin/blog-posts",
      per_page: 10,
      prev_page_url: null,
      to: 1,
      total: 1,
    });

    adminSuggestTagsMock.mockResolvedValue({
      status: "success",
      fallback_used: false,
      tags: [
        { id: 11, name: "Mars", reason: "Clanok rozobera pozorovanie planety Mars." },
        { id: 12, name: "Planety", reason: "Hlavna tema su planety a ich viditelnost." },
      ],
      last_run: {
        feature_name: "blog_tag_suggestions",
        status: "success",
      },
    });

    adminUpdateMock.mockResolvedValue({
      id: 7,
    });

    adminCreateMock.mockResolvedValue({ id: 7 });
    adminDeleteMock.mockResolvedValue({});
    confirmMock.mockResolvedValue(true);
  });

  it("click on Navrhnut tagy renders 1-5 AI tag suggestions", async () => {
    const wrapper = mount(BlogPostsView);
    await flush();
    await flush();

    await wrapper.find("button.post-card").trigger("click");
    await flush();

    await wrapper
      .findAll("button")
      .find((button) => button.text() === "Meta")
      .trigger("click");
    await flush();

    const suggestButton = wrapper
      .findAll("button")
      .find((button) => button.text().toLowerCase().includes("navrhnut tagy"));
    expect(suggestButton).toBeTruthy();

    await suggestButton.trigger("click");
    await flush();
    await flush();

    expect(adminSuggestTagsMock).toHaveBeenCalledWith(7);
    expect(wrapper.findAll(".ai-tag-option").length).toBeGreaterThanOrEqual(1);
    expect(wrapper.findAll(".ai-tag-option").length).toBeLessThanOrEqual(5);
    expect(wrapper.text()).toContain("Mars");
    expect(wrapper.text()).toContain("Planety");
  });

  it('click on "Pridat vybrane" calls existing attach flow via adminUpdate', async () => {
    const wrapper = mount(BlogPostsView);
    await flush();
    await flush();

    await wrapper.find("button.post-card").trigger("click");
    await flush();

    await wrapper
      .findAll("button")
      .find((button) => button.text() === "Meta")
      .trigger("click");
    await flush();

    const suggestButton = wrapper
      .findAll("button")
      .find((button) => button.text().toLowerCase().includes("navrhnut tagy"));
    await suggestButton.trigger("click");
    await flush();
    await flush();

    const applyButton = wrapper
      .findAll("button")
      .find((button) => button.text().toLowerCase().includes("pridat vybrane"));
    expect(applyButton).toBeTruthy();

    await applyButton.trigger("click");
    await flush();
    await flush();

    expect(adminUpdateMock).toHaveBeenCalledTimes(1);
    expect(adminUpdateMock).toHaveBeenCalledWith(7, {
      tag_ids: [1, 11, 12],
    });
  });
});
