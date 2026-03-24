import { computed, ref } from "vue";
import { blogPosts } from "@/services/blogPosts";
import { useToast } from "@/composables/useToast";

/**
 * Manages AI tag suggestion state and actions for the blog post editor.
 *
 * Accepts shared refs from the parent composable as context so it can
 * read and mutate the same reactive state.
 */
export function useAdminBlogPostsAiTags({
  selectedId,
  isEditing,
  titleLength,
  contentPlainText,
  saving,
  formError,
  selectedPostRecord,
  tagsInput,
  selectedPost,
  lastSavedAt,
  posts,
  save,
  load,
  selectPost,
  syncFormSnapshot,
  parseTagsInput,
}) {
  const toast = useToast();

  const aiTagSuggestionsLoading = ref(false);
  const aiTagSuggestionsError = ref("");
  const aiTagSuggestions = ref([]);
  const aiTagFallbackUsed = ref(false);
  const aiTagSuggestionMode = ref("existing_only");
  const aiLoadingPercent = ref(0);
  let _aiLoadingTimer = null;

  function _startLoadingProgress() {
    aiLoadingPercent.value = 0;
    _aiLoadingTimer = setInterval(() => {
      const current = aiLoadingPercent.value;
      if (current < 85) {
        aiLoadingPercent.value = Math.min(85, current + Math.max(1, (85 - current) * 0.1));
      }
    }, 200);
  }

  function _stopLoadingProgress() {
    clearInterval(_aiLoadingTimer);
    _aiLoadingTimer = null;
    aiLoadingPercent.value = 100;
  }

  const hasSelectedAiTagSuggestions = computed(() =>
    aiTagSuggestions.value.some((item) => item.checked)
  );
  const hasAiSuggestionResult = computed(
    () => aiTagSuggestions.value.length > 0 || aiTagSuggestionsError.value !== ""
  );
  const aiSuggestActionLabel = computed(() => {
    if (aiTagSuggestionsLoading.value) return "Navrhujem...";
    return hasAiSuggestionResult.value ? "Navrhnut znova" : "Navrhnut tagy";
  });

  function resetAiTagSuggestions() {
    aiTagSuggestionsLoading.value = false;
    aiTagSuggestionsError.value = "";
    aiTagSuggestions.value = [];
    aiTagFallbackUsed.value = false;
  }

  function setAiTagSuggestionMode(mode) {
    if (mode !== "existing_only" && mode !== "allow_new") return;
    if (aiTagSuggestionMode.value === mode) return;
    aiTagSuggestionMode.value = mode;
    resetAiTagSuggestions();
  }

  async function ensureDraftForAiSuggestions() {
    if (isEditing.value && selectedId.value) return true;

    const hasMinimumContent =
      titleLength.value >= 3 && String(contentPlainText.value || "").trim().length >= 10;

    if (!hasMinimumContent) {
      aiTagSuggestionsError.value =
        "Pre AI tagy doplň aspoň krátky nadpis a obsah (min. 10 znakov).";
      return false;
    }

    await save({ silent: true });

    if (!isEditing.value || !selectedId.value) {
      aiTagSuggestionsError.value =
        "Nepodarilo sa vytvoriť koncept článku pre AI návrh tagov.";
      return false;
    }

    return true;
  }

  async function suggestAiTags() {
    if (aiTagSuggestionsLoading.value) return;

    if (!isEditing.value || !selectedId.value) {
      const ready = await ensureDraftForAiSuggestions();
      if (!ready) return;
    }

    aiTagSuggestionsLoading.value = true;
    aiTagSuggestionsError.value = "";
    aiTagSuggestions.value = [];
    aiTagFallbackUsed.value = false;
    _startLoadingProgress();

    try {
      const response = await blogPosts.adminSuggestTags(selectedId.value, {
        mode: aiTagSuggestionMode.value,
      });
      const items = Array.isArray(response?.tags) ? response.tags : [];

      aiTagSuggestions.value = items
        .slice(0, 5)
        .map((item) => ({
          id: Number(item?.id || 0),
          name: String(item?.name || "").trim(),
          reason: String(item?.reason || "").trim(),
          checked: true,
        }))
        .filter((item) => item.id >= 0 && item.name && item.reason);
      aiTagFallbackUsed.value = Boolean(response?.fallback_used);

      if (aiTagSuggestions.value.length === 0) {
        if (response?.reason === "provider_error") {
          aiTagSuggestionsError.value = "AI je dočasne nedostupné.";
        } else if (response?.reason === "no_existing_tags") {
          aiTagSuggestionsError.value =
            'Zatia\u013E nem\u00E1\u0161 \u017Eiadne tagy. Za\u0161krtni "Navrhova\u0165 aj nov\u00E9 tagy" a sk\u00FAs znova.';
        } else {
          aiTagSuggestionsError.value = "Nenašli sa vhodné tagy.";
        }
      }
    } catch (e) {
      aiTagSuggestionsError.value =
        e?.response?.data?.message ||
        e?.userMessage ||
        e?.message ||
        "Nepodarilo sa navrhnut tagy.";
    } finally {
      _stopLoadingProgress();
      aiTagSuggestionsLoading.value = false;
    }
  }

  async function applySelectedAiTags() {
    const selected = aiTagSuggestions.value
      .filter((item) => item.checked)
      .map((item) => ({
        id: Number(item?.id || 0),
        name: String(item?.name || "").trim(),
      }))
      .filter((item) => item.id >= 0 && item.name);

    if (selected.length === 0) return;
    const selectedExisting = selected.filter((item) => item.id > 0);
    const hasNewTagNames = selected.some((item) => item.id === 0);

    const existingTagIds = Array.isArray(selectedPost.value?.tags)
      ? selectedPost.value.tags
          .map((tag) => Number(tag?.id || 0))
          .filter((id) => id > 0)
      : [];
    const mergedTagIds = Array.from(
      new Set([...existingTagIds, ...selectedExisting.map((item) => item.id)])
    );

    const existingTags = parseTagsInput();
    const normalizedExistingNames = new Set(
      existingTags.map((tag) => String(tag || "").trim().toLowerCase()).filter(Boolean)
    );
    const mergedNames = [...existingTags];

    selected.forEach((item) => {
      const key = item.name.toLowerCase();
      if (!key || normalizedExistingNames.has(key)) return;
      normalizedExistingNames.add(key);
      mergedNames.push(item.name);
    });

    tagsInput.value = mergedNames.join(", ");

    if (!isEditing.value || !selectedId.value) {
      return;
    }

    saving.value = true;
    formError.value = null;

    try {
      const attachedBeforeIds = Array.isArray(selectedPost.value?.tags)
        ? selectedPost.value.tags
            .map((tag) => Number(tag?.id || 0))
            .filter((id) => id > 0)
        : [];

      const payload = hasNewTagNames
        ? { tags: mergedNames }
        : { tag_ids: mergedTagIds };
      const saved = await blogPosts.adminUpdate(selectedId.value, payload);
      if (saved?.id) {
        selectedPostRecord.value = saved;
      }

      const tagSync = saved?.tag_sync || null;
      await load();
      if (saved?.id) {
        const found = posts.value.find((p) => p.id === saved.id);
        if (found) {
          await selectPost(found, true);
        } else {
          syncFormSnapshot();
        }
      } else {
        syncFormSnapshot();
      }
      lastSavedAt.value = new Date();
      if (tagSync && typeof tagSync === "object") {
        const createdNew = Number(tagSync.created_new || 0);
        const attachedExisting = Number(tagSync.attached_existing || 0);
        const addedTotal = Number(tagSync.added_total || 0);
        if (addedTotal <= 0) {
          toast.success("Tagy už boli priradené.");
        } else {
          const parts = [];
          if (attachedExisting > 0) parts.push(`existujúce: ${attachedExisting}`);
          if (createdNew > 0) parts.push(`nové: ${createdNew}`);
          const suffix = parts.length ? ` (${parts.join(", ")})` : "";
          toast.success(`Tagy boli pridané${suffix}.`);
        }
      } else {
        const attachedAfterIds = Array.isArray(saved?.tags)
          ? saved.tags
              .map((tag) => Number(tag?.id || 0))
              .filter((id) => id > 0)
          : attachedBeforeIds;
        const addedCount = Math.max(
          0,
          attachedAfterIds.filter((id) => !attachedBeforeIds.includes(id)).length
        );
        toast.success(addedCount > 0 ? `Tagy boli pridané (${addedCount}).` : "Tagy už boli priradené.");
      }
    } catch (e) {
      formError.value = e?.response?.data?.message || "Nepodarilo sa pridať tagy.";
      toast.error(formError.value);
    } finally {
      saving.value = false;
    }
  }

  return {
    aiTagSuggestionsLoading,
    aiTagSuggestionsError,
    aiTagSuggestions,
    aiTagFallbackUsed,
    aiTagSuggestionMode,
    aiLoadingPercent,
    hasSelectedAiTagSuggestions,
    hasAiSuggestionResult,
    aiSuggestActionLabel,
    resetAiTagSuggestions,
    setAiTagSuggestionMode,
    suggestAiTags,
    applySelectedAiTags,
  };
}

