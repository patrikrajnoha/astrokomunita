import { describe, expect, it, beforeEach, afterEach, vi } from "vitest";
import { mount } from "@vue/test-utils";
import ArticleRichEditor from "./ArticleRichEditor.vue";

function flush() {
  return new Promise((resolve) => setTimeout(resolve, 0));
}

const originalExecCommand = document.execCommand;

function setExecCommandMock(mockFn) {
  Object.defineProperty(document, "execCommand", {
    configurable: true,
    writable: true,
    value: mockFn,
  });
}

describe("ArticleRichEditor", () => {
  beforeEach(() => {
    setExecCommandMock(vi.fn(() => true));
  });

  afterEach(() => {
    setExecCommandMock(originalExecCommand);
  });

  it('changes heading from "Nadpis 2" to "Nadpis 3" using formatBlock with "h3"', async () => {
    let wrapper = null;
    const execMock = vi.fn((command, _showUi, value) => {
      if (command !== "formatBlock") return true;

      const nextTag = String(value || "").toLowerCase();
      if (nextTag !== "h3") return true;

      const editor = wrapper?.find(".rich-editor__surface").element;
      const heading = editor?.querySelector("h2");
      if (!heading) return true;

      const heading3 = document.createElement("h3");
      heading3.innerHTML = heading.innerHTML;
      heading.replaceWith(heading3);
      return true;
    });
    setExecCommandMock(execMock);

    wrapper = mount(ArticleRichEditor, {
      props: {
        modelValue: "<h2>Galaxia Andromeda</h2>",
      },
    });
    await flush();

    const editor = wrapper.find(".rich-editor__surface").element;
    const heading = editor.querySelector("h2");
    expect(heading).toBeTruthy();

    const selection = window.getSelection();
    const range = document.createRange();
    range.setStart(heading.firstChild, 3);
    range.collapse(true);
    selection.removeAllRanges();
    selection.addRange(range);
    document.dispatchEvent(new Event("selectionchange"));
    await flush();

    const formatSelect = wrapper.find(".rich-editor__format select");
    await formatSelect.setValue("h3");
    await flush();

    const formatCommandCalls = execMock.mock.calls.filter(
      (call) => call[0] === "formatBlock"
    );
    expect(formatCommandCalls.some((call) => call[2] === "h3")).toBe(true);

    const modelUpdates = wrapper.emitted("update:modelValue") || [];
    const latestModel = modelUpdates.at(-1)?.[0] || "";
    expect(latestModel).toContain("Galaxia Andromeda</h3>");
    expect(latestModel).toContain("<h3");
    expect(latestModel).not.toContain("<h2");
  });

});
