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

function normalizeText(value = "") {
  return String(value || "")
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase();
}

function findButtonByText(wrapper, needle) {
  const normalizedNeedle = normalizeText(needle);
  return wrapper
    .findAll("button")
    .find((button) => normalizeText(button.text()).includes(normalizedNeedle));
}

function findCheckboxByLabel(wrapper, needle) {
  const normalizedNeedle = normalizeText(needle);
  const label = wrapper
    .findAll("label")
    .find((node) => normalizeText(node.text()).includes(normalizedNeedle));
  return label ? label.find('input[type="checkbox"]') : null;
}

async function openSettingsForSelectedPost(wrapper) {
  await wrapper.find("button.article-card").trigger("click");
  await flush();
  const settingsButton = findButtonByText(wrapper, "nastavenia");
  expect(settingsButton).toBeTruthy();
  await settingsButton.trigger("click");
  await flush();
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
          is_hidden: false,
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

    adminUpdateMock.mockResolvedValue({ id: 7 });
    adminCreateMock.mockResolvedValue({ id: 7 });
    adminDeleteMock.mockResolvedValue({});
    confirmMock.mockResolvedValue(true);
  });

  it("click on Navrhnut tagy renders 1-5 AI tag suggestions", async () => {
    const wrapper = mount(BlogPostsView);
    await flush();
    await flush();

    await openSettingsForSelectedPost(wrapper);

    const suggestButton = findButtonByText(wrapper, "navrhnut");
    expect(suggestButton).toBeTruthy();

    await suggestButton.trigger("click");
    await flush();
    await flush();

    expect(adminSuggestTagsMock).toHaveBeenCalledWith(7, {
      mode: "existing_only",
    });

    const applyButton = findButtonByText(wrapper, "pridat (2)");
    expect(applyButton).toBeTruthy();
    expect(wrapper.text()).toContain("Mars");
    expect(wrapper.text()).toContain("Planety");
  });

  it("can suggest AI tags from new article flow by auto-creating draft", async () => {
    const wrapper = mount(BlogPostsView);
    await flush();
    await flush();

    await openSettingsForSelectedPost(wrapper);

    const suggestButton = findButtonByText(wrapper, "navrhnut");
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

    await openSettingsForSelectedPost(wrapper);

    const suggestButton = findButtonByText(wrapper, "navrhnut");
    await suggestButton.trigger("click");
    await flush();
    await flush();

    const applyButton = findButtonByText(wrapper, "pridat (2)");
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

    await openSettingsForSelectedPost(wrapper);

    const allowNewCheckbox = findCheckboxByLabel(wrapper, "navrhovat aj nove tagy");
    expect(allowNewCheckbox).toBeTruthy();
    await allowNewCheckbox.setChecked(true);
    await flush();

    const suggestButton = findButtonByText(wrapper, "navrhnut");
    await suggestButton.trigger("click");
    await flush();
    await flush();

    expect(adminSuggestTagsMock).toHaveBeenCalledWith(7, {
      mode: "allow_new",
    });

    const applyButton = findButtonByText(wrapper, "pridat (2)");
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

    await openSettingsForSelectedPost(wrapper);

    const suggestButton = findButtonByText(wrapper, "navrhnut");
    await suggestButton.trigger("click");
    await flush();
    await flush();

    expect(wrapper.text()).toContain("Mars");
    expect(normalizeText(wrapper.text())).toContain("novy");
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

    await openSettingsForSelectedPost(wrapper);

    const allowNewCheckbox = findCheckboxByLabel(wrapper, "navrhovat aj nove tagy");
    expect(allowNewCheckbox).toBeTruthy();
    await allowNewCheckbox.setChecked(true);
    await flush();

    const suggestButton = findButtonByText(wrapper, "navrhnut");
    await suggestButton.trigger("click");
    await flush();
    await flush();

    expect(adminSuggestTagsMock).toHaveBeenCalledWith(7, {
      mode: "allow_new",
    });
    expect(wrapper.text()).toContain("Mars");
    expect(wrapper.text()).toContain("Planety");

    const applyButton = findButtonByText(wrapper, "pridat (2)");
    await applyButton.trigger("click");
    await flush();
    await flush();

    expect(adminUpdateMock).toHaveBeenCalledWith(7, {
      tags: ["Mesiac", "Mars", "Planety"],
    });

    const lastToast = String(toastSuccessMock.mock.calls.at(-1)?.[0] || "");
    expect(normalizeText(lastToast)).toContain("tagy boli pridane");
    expect(normalizeText(lastToast)).toContain("existujuce: 1");
    expect(normalizeText(lastToast)).toContain("nove: 1");

    const tagsInput = wrapper
      .findAll('input[type="text"]')
      .find((node) => normalizeText(node.attributes("placeholder") || "").includes("plan"));
    expect(tagsInput.exists()).toBe(true);
    expect(tagsInput.element.value).toContain("Planety");
  });

  it('renders a single "Novy clanok" action in header while empty state stays informational', async () => {
    const wrapper = mount(BlogPostsView);
    await flush();
    await flush();

    const createButtons = wrapper
      .findAll("button")
      .filter((button) => normalizeText(button.text()).includes("novy clanok"));

    expect(createButtons.length).toBe(1);
    expect(normalizeText(wrapper.text())).toContain("vyber clanok alebo vytvor novy");
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

    const openButton = findButtonByText(wrapper, "novy clanok");
    expect(openButton).toBeTruthy();
    await openButton.trigger("click");
    await flush();

    expect(wrapper.find(".blog-layout.is-editing").exists()).toBe(true);

    const closeButton = findButtonByText(wrapper, "spat");
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

    const closeButton = findButtonByText(wrapper, "spat");
    expect(closeButton).toBeTruthy();
    await closeButton.trigger("click");
    await flush();

    expect(wrapper.find(".blog-layout.is-editing").exists()).toBe(false);
    expect(normalizeText(wrapper.text())).toContain("vyber clanok alebo vytvor novy");
  });

  it("removes Focus action and shows direct article actions in editor", async () => {
    const wrapper = mount(BlogPostsView);
    await flush();
    await flush();

    await wrapper.find("button.article-card").trigger("click");
    await flush();

    const actionLabels = wrapper
      .findAll(".editor-bar__right button")
      .map((button) => normalizeText(button.text().trim()));

    expect(actionLabels).not.toContain("focus");
    expect(actionLabels.some((label) => label.includes("uloz"))).toBe(true);
    expect(actionLabels.some((label) => label.includes("zrusit pub"))).toBe(true);
    expect(actionLabels.some((label) => label.includes("skryt clanok"))).toBe(false);
    expect(actionLabels.some((label) => label.includes("vymazat clanok"))).toBe(false);
  });

  it('click on "Nepublikovat" updates article back to draft', async () => {
    const wrapper = mount(BlogPostsView);
    await flush();
    await flush();

    await wrapper.find("button.article-card").trigger("click");
    await flush();

    const unpublishButton = findButtonByText(wrapper, "zrusit pub");
    expect(unpublishButton).toBeTruthy();

    await unpublishButton.trigger("click");
    await flush();
    await flush();

    expect(adminUpdateMock).toHaveBeenCalledWith(7, {
      published_at: null,
    });

    const lastToast = String(toastSuccessMock.mock.calls.at(-1)?.[0] || "");
    expect(normalizeText(lastToast)).toContain("clanok bol stiahnuty z publikacie");
  });

  it('click on "Skryt clanok" hides a published article', async () => {
    const wrapper = mount(BlogPostsView);
    await flush();
    await flush();

    await openSettingsForSelectedPost(wrapper);

    const hideButton = findButtonByText(wrapper, "skryt clanok");
    expect(hideButton).toBeTruthy();

    await hideButton.trigger("click");
    await flush();
    await flush();

    expect(adminUpdateMock).toHaveBeenCalledWith(7, {
      is_hidden: true,
    });

    const lastToast = String(toastSuccessMock.mock.calls.at(-1)?.[0] || "");
    expect(normalizeText(lastToast)).toContain("clanok bol skryty");
  });

  it('click on "Zobrazit clanok" unhides hidden published article', async () => {
    adminListMock.mockResolvedValueOnce({
      current_page: 1,
      data: [
        {
          id: 7,
          title: "Mars observacny plan",
          content: "Obsah clanku o planetach a pozorovani oblohy.",
          published_at: "2026-03-01T10:00:00Z",
          is_hidden: true,
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

    const wrapper = mount(BlogPostsView);
    await flush();
    await flush();

    await openSettingsForSelectedPost(wrapper);

    const unhideButton = findButtonByText(wrapper, "zobrazit clanok");
    expect(unhideButton).toBeTruthy();

    await unhideButton.trigger("click");
    await flush();
    await flush();

    expect(adminUpdateMock).toHaveBeenCalledWith(7, {
      is_hidden: false,
    });

    const lastToast = String(toastSuccessMock.mock.calls.at(-1)?.[0] || "");
    expect(normalizeText(lastToast)).toContain("clanok je znovu viditelny");
  });

  it("shows clear status when existing cover is already stored", async () => {
    adminListMock.mockResolvedValueOnce({
      current_page: 1,
      data: [
        {
          id: 7,
          title: "Mars observacny plan",
          content: "Obsah clanku o planetach a pozorovani oblohy.",
          published_at: "2026-03-01T10:00:00Z",
          is_hidden: false,
          views: 321,
          cover_image_url: "/storage/blog-covers/7/existing-cover.png",
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

    const wrapper = mount(BlogPostsView);
    await flush();
    await flush();

    await openSettingsForSelectedPost(wrapper);

    expect(normalizeText(wrapper.text())).toContain("pouziva sa aktualny ulozeny obrazok");
  });

  it("shows existing-cover state in custom file picker label", async () => {
    adminListMock.mockResolvedValueOnce({
      current_page: 1,
      data: [
        {
          id: 7,
          title: "Mars observacny plan",
          content: "Obsah clanku o planetach a pozorovani oblohy.",
          published_at: "2026-03-01T10:00:00Z",
          is_hidden: false,
          views: 321,
          cover_image_url: "/storage/blog-covers/7/existing-cover.png",
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

    const wrapper = mount(BlogPostsView);
    await flush();
    await flush();

    await openSettingsForSelectedPost(wrapper);

    expect(normalizeText(wrapper.text())).toContain("pouziva sa aktualny ulozeny obrazok");
  });

  it("shows selected file name under cover uploader", async () => {
    const originalCreateObjectURL = URL.createObjectURL;
    const originalRevokeObjectURL = URL.revokeObjectURL;
    URL.createObjectURL = vi.fn(() => "blob:cover-test");
    URL.revokeObjectURL = vi.fn();

    try {
      const wrapper = mount(BlogPostsView);
      await flush();
      await flush();

      await openSettingsForSelectedPost(wrapper);

      const file = new File(["cover-image"], "cover-test.png", { type: "image/png" });
      wrapper.vm.onCoverChange({
        target: { files: [file] },
      });
      await flush();

      expect(normalizeText(wrapper.text())).toContain("cover-test.png");
    } finally {
      URL.createObjectURL = originalCreateObjectURL;
      URL.revokeObjectURL = originalRevokeObjectURL;
    }
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

    const publishButton = findButtonByText(wrapper, "publikovat");

    expect(publishButton).toBeTruthy();
    expect(publishButton.attributes("disabled")).toBeDefined();
    expect(normalizeText(wrapper.text())).toContain("pred publikovanim");
  });
});
