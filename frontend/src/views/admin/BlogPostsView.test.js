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
          views: 321,
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

    await wrapper.find("button.article-card").trigger("click");
    await flush();
    await wrapper.findAll("button").find((b) => b.text() === "Nastavenia").trigger("click");
    await flush();

    const suggestButton = wrapper
      .findAll("button")
      .find((button) => button.text().toLowerCase().includes("navrhn"));
    expect(suggestButton).toBeTruthy();

    await suggestButton.trigger("click");
    await flush();
    await flush();

    expect(adminSuggestTagsMock).toHaveBeenCalledWith(7, {
      mode: "existing_only",
    });
    expect(wrapper.findAll(".ai-tag-item").length).toBeGreaterThanOrEqual(1);
    expect(wrapper.findAll(".ai-tag-item").length).toBeLessThanOrEqual(5);
    expect(wrapper.text()).toContain("Mars");
    expect(wrapper.text()).toContain("Planety");
  });

  it("can suggest AI tags from new article flow by auto-creating draft", async () => {
    const wrapper = mount(BlogPostsView);
    await flush();
    await flush();

    await wrapper.find("button.article-card").trigger("click");
    await flush();

    await wrapper.findAll("button").find((b) => b.text() === "Nastavenia").trigger("click");
    await flush();

    const suggestButton = wrapper
      .findAll("button")
      .find((button) => button.text().toLowerCase().includes("navrhn"));
    expect(suggestButton).toBeTruthy();

    await suggestButton.trigger("click");
    await flush();
    await flush();

    expect(adminSuggestTagsMock).toHaveBeenCalledWith(7, {
      mode: "existing_only",
    });
    expect(wrapper.text()).toContain("Mars");
    expect(wrapper.text()).toContain("Planety");
  });

  it('click on "Pridat vybrane" calls existing attach flow via adminUpdate', async () => {
    const wrapper = mount(BlogPostsView);
    await flush();
    await flush();

    await wrapper.find("button.article-card").trigger("click");
    await flush();
    await wrapper.findAll("button").find((b) => b.text() === "Nastavenia").trigger("click");
    await flush();

    const suggestButton = wrapper
      .findAll("button")
      .find((button) => button.text().toLowerCase().includes("navrhn"));
    await suggestButton.trigger("click");
    await flush();
    await flush();

    const applyButton = wrapper
      .findAll("button")
      .find((button) => button.text().toLowerCase().includes("pridať vybrané"));
    expect(applyButton).toBeTruthy();

    await applyButton.trigger("click");
    await flush();
    await flush();

    expect(adminUpdateMock).toHaveBeenCalledTimes(1);
    expect(adminUpdateMock).toHaveBeenCalledWith(7, {
      tag_ids: [1, 11, 12],
    });
  });

  it('click on "Pridat vybrane" with id=0 suggestions sends tag names', async () => {
    adminSuggestTagsMock.mockResolvedValueOnce({
      status: "success",
      fallback_used: false,
      tags: [
        { id: 0, name: "Mars", reason: "Hlavna tema je planeta Mars." },
        { id: 0, name: "Planety", reason: "Text sa venuje viacerym planetam." },
      ],
    });

    const wrapper = mount(BlogPostsView);
    await flush();
    await flush();

    await wrapper.find("button.article-card").trigger("click");
    await flush();
    await wrapper.findAll("button").find((b) => b.text() === "Nastavenia").trigger("click");
    await flush();

    const modeButton = wrapper
      .findAll("button")
      .find((button) => button.text().toLowerCase().includes("aj nové"));
    await modeButton.trigger("click");
    await flush();

    const suggestButton = wrapper
      .findAll("button")
      .find((button) => button.text().toLowerCase().includes("navrhn"));
    await suggestButton.trigger("click");
    await flush();
    await flush();

    expect(adminSuggestTagsMock).toHaveBeenCalledWith(7, {
      mode: "allow_new",
    });

    const applyButton = wrapper
      .findAll("button")
      .find((button) => button.text().toLowerCase().includes("pridať vybrané"));
    expect(applyButton).toBeTruthy();

    await applyButton.trigger("click");
    await flush();
    await flush();

    expect(adminUpdateMock).toHaveBeenCalledTimes(1);
    expect(adminUpdateMock).toHaveBeenCalledWith(7, {
      tags: ["Mesiac", "Mars", "Planety"],
    });
  });

  it('renders "Novy tag" badge for id=0 AI suggestions', async () => {
    adminSuggestTagsMock.mockResolvedValueOnce({
      status: "success",
      fallback_used: false,
      tags: [{ id: 0, name: "Mars", reason: "Hlavna tema je planeta Mars." }],
    });

    const wrapper = mount(BlogPostsView);
    await flush();
    await flush();

    await wrapper.find("button.article-card").trigger("click");
    await flush();
    await wrapper.findAll("button").find((b) => b.text() === "Nastavenia").trigger("click");
    await flush();

    const suggestButton = wrapper
      .findAll("button")
      .find((button) => button.text().toLowerCase().includes("navrhn"));
    await suggestButton.trigger("click");
    await flush();
    await flush();

    expect(wrapper.find(".new-badge").exists()).toBe(true);
    expect(wrapper.text()).toContain("Nový");
  });

  it("runs full AI flow: suggest, apply, persist and show sync feedback", async () => {
    adminListMock
      .mockResolvedValueOnce({
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
      })
      .mockResolvedValue({
        current_page: 1,
        data: [
          {
            id: 7,
            title: "Mars observacny plan",
            content: "Obsah clanku o planetach a pozorovani oblohy.",
            published_at: "2026-03-01T10:00:00Z",
            cover_image_url: null,
            tags: [
              { id: 1, name: "Mesiac", slug: "mesiac" },
              { id: 11, name: "Mars", slug: "mars" },
              { id: 30, name: "Planety", slug: "planety" },
            ],
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

    adminSuggestTagsMock.mockResolvedValueOnce({
      status: "success",
      fallback_used: false,
      tags: [
        { id: 11, name: "Mars", reason: "Tema clanku je Mars." },
        { id: 0, name: "Planety", reason: "Obsah porovnava planety." },
      ],
    });

    adminUpdateMock.mockResolvedValueOnce({
      id: 7,
      tags: [
        { id: 1, name: "Mesiac", slug: "mesiac" },
        { id: 11, name: "Mars", slug: "mars" },
        { id: 30, name: "Planety", slug: "planety" },
      ],
      tag_sync: {
        attached_existing: 1,
        created_new: 1,
        added_total: 2,
        selected_total: 3,
      },
    });

    const wrapper = mount(BlogPostsView);
    await flush();
    await flush();

    await wrapper.find("button.article-card").trigger("click");
    await flush();
    await wrapper.findAll("button").find((b) => b.text() === "Nastavenia").trigger("click");
    await flush();

    const allowNewButton = wrapper
      .findAll("button")
      .find((button) => button.text().toLowerCase().includes("aj nové"));
    await allowNewButton.trigger("click");
    await flush();

    const suggestButton = wrapper
      .findAll("button")
      .find((button) => button.text().toLowerCase().includes("navrhn"));
    await suggestButton.trigger("click");
    await flush();
    await flush();

    expect(adminSuggestTagsMock).toHaveBeenCalledWith(7, {
      mode: "allow_new",
    });
    expect(wrapper.text()).toContain("Mars");
    expect(wrapper.text()).toContain("Planety");

    const applyButton = wrapper
      .findAll("button")
      .find((button) => button.text().toLowerCase().includes("pridať vybrané"));
    await applyButton.trigger("click");
    await flush();
    await flush();

    expect(adminUpdateMock).toHaveBeenCalledWith(7, {
      tags: ["Mesiac", "Mars", "Planety"],
    });
    expect(toastSuccessMock).toHaveBeenCalledWith(
      "Tagy boli pridané (existujúce: 1, nové: 1)."
    );

    const tagsInput = wrapper.find('input[placeholder*="planéty"]');
    expect(tagsInput.exists()).toBe(true);
    expect(tagsInput.element.value).toContain("Planety");
  });

  it('renders "Novy clanok" action in both header and empty editor state', async () => {
    const wrapper = mount(BlogPostsView);
    await flush();
    await flush();

    const createButtons = wrapper
      .findAll("button")
      .filter((button) => button.text().trim() === "+ Nový článok");

    expect(createButtons.length).toBeGreaterThanOrEqual(1);
    expect(wrapper.text()).toContain("Vyber článok alebo vytvor nový");
  });

  it("renders engagement stats on article card", async () => {
    const wrapper = mount(BlogPostsView);
    await flush();
    await flush();

    const firstCard = wrapper.find("button.article-card");
    expect(firstCard.exists()).toBe(true);
    expect(firstCard.findAll(".stat-chip")).toHaveLength(2);
    expect(firstCard.text()).toContain("321");
  });

  it("opens article creation as popup modal", async () => {
    const wrapper = mount(BlogPostsView);
    await flush();
    await flush();

    const openButton = wrapper.find(".blog-header__actions .btn-primary");
    expect(openButton.exists()).toBe(true);

    await wrapper.find("button.article-card").trigger("click");
    await flush();

    expect(wrapper.find(".blog-layout.is-editing").exists()).toBe(true);

    const closeButton = wrapper
      .findAll("button")
      .find((button) => button.text().includes("Späť"));
    expect(closeButton).toBeTruthy();
    await closeButton.trigger("click");
    await flush();

    expect(wrapper.find(".blog-layout.is-editing").exists()).toBe(false);
  });

  it("opens selected article as popup modal", async () => {
    const wrapper = mount(BlogPostsView);
    await flush();
    await flush();

    await wrapper.find("button.article-card").trigger("click");
    await flush();

    expect(wrapper.find(".blog-layout.is-editing").exists()).toBe(true);

    const closeButton = wrapper
      .findAll("button")
      .find((button) => button.text().includes("Späť"));
    expect(closeButton).toBeTruthy();
    await closeButton.trigger("click");
    await flush();

    expect(wrapper.find(".blog-layout.is-editing").exists()).toBe(false);
    expect(wrapper.text()).toContain("Vyber článok alebo vytvor nový");
  });

  it("removes Focus action and shows direct article actions in editor", async () => {
    const wrapper = mount(BlogPostsView);
    await flush();
    await flush();

    await wrapper.find("button.article-card").trigger("click");
    await flush();

    const actionLabels = wrapper
      .findAll(".editor-bar__right button")
      .map((button) => button.text().trim());

    expect(actionLabels).not.toContain("Focus");
    expect(actionLabels).toContain("Uložiť");
    expect(actionLabels).toContain("Zrušiť publikovanie");
    expect(actionLabels).not.toContain("Vymazať článok");
  });

  it('click on "Nepublikovat" updates article back to draft', async () => {
    const wrapper = mount(BlogPostsView);
    await flush();
    await flush();

    await wrapper.find("button.article-card").trigger("click");
    await flush();

    const unpublishButton = wrapper
      .findAll("button")
      .find((button) => button.text().toLowerCase().includes("zrušiť publikovanie"));

    expect(unpublishButton).toBeTruthy();
    await unpublishButton.trigger("click");
    await flush();
    await flush();

    expect(adminUpdateMock).toHaveBeenCalledWith(7, {
      published_at: null,
    });
    expect(toastSuccessMock).toHaveBeenCalledWith("Článok bol stiahnutý z publikácie.");
  });

  it("prevents publish for incomplete draft and shows clear reason", async () => {
    adminListMock.mockResolvedValueOnce({
      current_page: 1,
      data: [
        {
          id: 9,
          title: "",
          content: "",
          published_at: null,
          views: 0,
          cover_image_url: null,
          tags: [],
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

    adminUpdateMock.mockResolvedValue({ id: 9 });

    const wrapper = mount(BlogPostsView);
    await flush();
    await flush();

    await wrapper.find("button.article-card").trigger("click");
    await flush();

    const publishButton = wrapper
      .findAll("button")
      .find((button) => button.text().toLowerCase() === "publikovať");

    expect(publishButton).toBeTruthy();
    expect(publishButton.attributes("disabled")).toBeDefined();
    expect(wrapper.text().toLowerCase()).toContain("pred publikovaním");
  });
});
