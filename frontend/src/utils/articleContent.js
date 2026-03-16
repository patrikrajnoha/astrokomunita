const FONT_SIZE_MIN_PX = 10;
const FONT_SIZE_MAX_PX = 72;
const ELEMENT_NODE = 1;
const TEXT_NODE = 3;
const HEADING_TAGS = new Set(["h2", "h3"]);

const ALLOWED_TAGS = new Set([
  "a",
  "br",
  "code",
  "em",
  "h2",
  "h3",
  "img",
  "li",
  "ol",
  "p",
  "span",
  "strong",
  "u",
  "ul",
]);

const FORBIDDEN_TAGS = new Set([
  "audio",
  "embed",
  "iframe",
  "object",
  "script",
  "style",
  "svg",
  "video",
]);

const EXEC_COMMAND_FONT_SIZE_MAP = {
  "xx-small": 10,
  "x-small": 12,
  small: 13,
  medium: 16,
  large: 18,
  "x-large": 24,
  "xx-large": 32,
  "xxx-large": 40,
  smaller: 13,
  larger: 20,
};

const LEGACY_FONT_SIZE_MAP = {
  1: 10,
  2: 13,
  3: 16,
  4: 18,
  5: 24,
  6: 30,
  7: 36,
};

function clampFontSizePx(value) {
  const parsed = Number(value);
  if (!Number.isFinite(parsed)) return null;
  const normalized = Math.round(parsed);
  return Math.min(FONT_SIZE_MAX_PX, Math.max(FONT_SIZE_MIN_PX, normalized));
}

export function escapeHtml(text) {
  return String(text || "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");
}

function escapeAttribute(value) {
  return escapeHtml(value).replace(/`/g, "&#96;");
}

export function slugifyHeading(text) {
  return String(text || "")
    .toLowerCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/[^a-z0-9\s-]/g, "")
    .trim()
    .replace(/\s+/g, "-")
    .slice(0, 80);
}

export function stripHtml(value) {
  const raw = String(value || "");
  if (!raw) return "";

  if (typeof document !== "undefined") {
    const template = document.createElement("template");
    template.innerHTML = raw;
    return String(template.content.textContent || "")
      .replace(/\s+/g, " ")
      .trim();
  }

  return raw
    .replace(/<[^>]*>/g, " ")
    .replace(/\s+/g, " ")
    .trim();
}

export function hasHtmlMarkup(value) {
  return /<\/?[a-z][\s\S]*>/i.test(String(value || ""));
}

export function inlineMarkdown(text) {
  const safe = escapeHtml(text);
  let html = safe;
  html = html.replace(/`([^`]+)`/g, "<code>$1</code>");
  html = html.replace(/\*\*([^*]+)\*\*/g, "<strong>$1</strong>");
  html = html.replace(/\*([^*]+)\*/g, "<em>$1</em>");
  html = html.replace(
    /\[([^\]]+)\]\((https?:\/\/[^)]+)\)/g,
    '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>'
  );
  return html;
}

function uniqueHeadingId(baseId, headingCounts) {
  const safeBase = baseId || "sekcia";
  const current = headingCounts.get(safeBase) || 0;
  const next = current + 1;
  headingCounts.set(safeBase, next);
  if (next === 1) return safeBase;
  return `${safeBase}-${next}`;
}

function sanitizeHref(value) {
  const raw = String(value || "").trim();
  if (!raw) return "";

  if (raw.startsWith("#")) {
    return raw.slice(0, 200);
  }

  if (/^(mailto:|tel:)/i.test(raw)) {
    return raw;
  }

  if (/^\/(?!\/)/.test(raw) || /^\.\.?\//.test(raw)) {
    return raw;
  }

  try {
    const parsed = new URL(raw);
    if (parsed.protocol === "http:" || parsed.protocol === "https:") {
      return parsed.href;
    }
  } catch {
    return "";
  }

  return "";
}

function parseSpanStyle(styleValue) {
  const styleText = String(styleValue || "");
  const declarations = styleText
    .split(";")
    .map((item) => item.trim())
    .filter(Boolean);

  let fontSizePx = null;

  for (const declaration of declarations) {
    const separator = declaration.indexOf(":");
    if (separator === -1) continue;

    const prop = declaration.slice(0, separator).trim().toLowerCase();
    const rawValue = declaration.slice(separator + 1).trim().toLowerCase();

    if (prop === "font-size") {
      const keywordPx = EXEC_COMMAND_FONT_SIZE_MAP[rawValue];
      if (keywordPx) {
        fontSizePx = clampFontSizePx(keywordPx);
        continue;
      }

      const pxMatch = rawValue.match(/^(\d{1,3}(?:\.\d+)?)\s*px$/);
      if (pxMatch) {
        fontSizePx = clampFontSizePx(pxMatch[1]);
      }
    }
  }

  return fontSizePx;
}

function normalizeTagName(tagName) {
  const original = String(tagName || "").toLowerCase();

  if (original === "b") return "strong";
  if (original === "i") return "em";
  if (original === "h1") return "h2";
  if (original === "h4" || original === "h5" || original === "h6") return "h3";
  if (original === "div" || original === "section" || original === "article") return "p";

  return original;
}

function sanitizeChildren(node, context) {
  return Array.from(node.childNodes)
    .map((child) => sanitizeNode(child, context))
    .join("");
}

function sanitizeNode(node, context) {
  if (!node) return "";

  if (node.nodeType === TEXT_NODE) {
    return escapeHtml(node.textContent || "");
  }

  if (node.nodeType !== ELEMENT_NODE) {
    return "";
  }

  const originalTag = String(node.nodeName || "").toLowerCase();
  if (FORBIDDEN_TAGS.has(originalTag)) {
    return "";
  }

  if (originalTag === "font") {
    const size = clampFontSizePx(LEGACY_FONT_SIZE_MAP[Number(node.getAttribute("size") || 0)]);
    const content = sanitizeChildren(node, context);
    if (!content.trim()) return "";
    if (!size) return content;
    return `<span style="font-size:${size}px">${content}</span>`;
  }

  const tag = normalizeTagName(originalTag);
  if (!ALLOWED_TAGS.has(tag)) {
    return sanitizeChildren(node, context);
  }

  if (tag === "br") {
    return "<br />";
  }

  const inner = sanitizeChildren(node, context);

  if (tag === "a") {
    const href = sanitizeHref(node.getAttribute("href"));
    if (!href) return inner;
    const attrs = [`href="${escapeAttribute(href)}"`];
    if (/^https?:\/\//i.test(href)) {
      attrs.push('target="_blank"');
      attrs.push('rel="noopener noreferrer"');
    }
    return `<a ${attrs.join(" ")}>${inner}</a>`;
  }

  if (tag === "img") {
    const src = sanitizeHref(node.getAttribute("src"));
    if (!src) return "";
    const alt = escapeAttribute(node.getAttribute("alt") || "");
    return `<img src="${escapeAttribute(src)}" alt="${alt}" />`;
  }

  if (tag === "span") {
    const styleSize = parseSpanStyle(node.getAttribute("style"));
    if (!styleSize) return inner;
    return `<span style="font-size:${styleSize}px">${inner}</span>`;
  }

  if (HEADING_TAGS.has(tag)) {
    const title = stripHtml(inner);
    if (!title) {
      return "";
    }
    const id = uniqueHeadingId(slugifyHeading(title), context.headingCounts);
    context.toc.push({ id, text: title, type: tag });
    return `<${tag} id="${escapeAttribute(id)}">${inner}</${tag}>`;
  }

  if ((tag === "p" || tag === "li") && !stripHtml(inner)) {
    return "";
  }

  if ((tag === "ul" || tag === "ol") && !stripHtml(inner)) {
    return "";
  }

  return `<${tag}>${inner}</${tag}>`;
}

function sanitizeArticleHtmlInternal(value) {
  const raw = String(value || "");
  if (!raw.trim()) {
    return { html: "", toc: [] };
  }

  if (typeof document === "undefined") {
    const fallbackHtml = escapeHtml(raw).replace(/\r?\n/g, "<br />");
    return {
      html: `<p>${fallbackHtml}</p>`,
      toc: [],
    };
  }

  const template = document.createElement("template");
  template.innerHTML = raw;

  const context = {
    headingCounts: new Map(),
    toc: [],
  };

  const html = Array.from(template.content.childNodes)
    .map((node) => sanitizeNode(node, context))
    .join("");

  return {
    html,
    toc: context.toc,
  };
}

export function sanitizeArticleHtml(value) {
  return sanitizeArticleHtmlInternal(value).html;
}

function parseMarkdownBlocks(value) {
  const raw = String(value || "");
  if (!raw.trim()) return [];

  const lines = raw.split(/\r?\n/);
  const blocks = [];
  const headingCounts = new Map();

  let paragraphBuffer = [];
  let listBuffer = [];

  const flushParagraph = () => {
    const paragraphText = paragraphBuffer.join(" ").trim();
    if (paragraphText) {
      blocks.push({
        type: "p",
        html: inlineMarkdown(paragraphText),
      });
    }
    paragraphBuffer = [];
  };

  const flushList = () => {
    if (!listBuffer.length) return;
    blocks.push({
      type: "ul",
      items: listBuffer.map((item) => inlineMarkdown(item)),
    });
    listBuffer = [];
  };

  lines.forEach((line) => {
    const trimmed = line.trim();
    const headingMatch = trimmed.match(/^(#{1,6})\s+(.+)$/);
    const isList = trimmed.startsWith("- ") || trimmed.startsWith("* ");

    if (headingMatch) {
      flushList();
      flushParagraph();
      const rawDepth = Math.max(1, headingMatch[1].length);
      const depth = rawDepth <= 2 ? 2 : 3;
      const text = String(headingMatch[2] || "").trim();
      if (!text) {
        return;
      }
      const headingType = `h${depth}`;
      const id = uniqueHeadingId(slugifyHeading(text), headingCounts);
      blocks.push({
        type: headingType,
        id,
        text,
      });
      return;
    }

    if (!trimmed) {
      flushList();
      flushParagraph();
      return;
    }

    if (isList) {
      flushParagraph();
      listBuffer.push(trimmed.replace(/^[-*]\s+/, ""));
      return;
    }

    paragraphBuffer.push(trimmed);
  });

  flushList();
  flushParagraph();

  return blocks;
}

function renderMarkdownBlocks(blocks) {
  const toc = [];

  const html = blocks
    .map((block) => {
      if (HEADING_TAGS.has(block.type)) {
        const text = String(block.text || "").trim();
        if (!text) return "";
        const id = String(block.id || slugifyHeading(text) || "sekcia");
        toc.push({ id, text, type: block.type });
        return `<${block.type} id="${escapeAttribute(id)}">${escapeHtml(text)}</${block.type}>`;
      }

      if (block.type === "ul") {
        const items = Array.isArray(block.items)
          ? block.items
              .map((item) => `<li>${String(item || "")}</li>`)
              .join("")
          : "";
        if (!items) return "";
        return `<ul>${items}</ul>`;
      }

      if (block.type === "p") {
        if (!stripHtml(block.html)) return "";
        return `<p>${String(block.html || "")}</p>`;
      }

      return "";
    })
    .join("");

  return { html, toc };
}

export function renderArticleContent(value) {
  const raw = String(value || "");
  if (!raw.trim()) {
    return {
      html: "",
      toc: [],
      plainText: "",
    };
  }

  if (hasHtmlMarkup(raw)) {
    const rich = sanitizeArticleHtmlInternal(raw);
    if (rich.html) {
      return {
        html: rich.html,
        toc: rich.toc,
        plainText: stripHtml(rich.html),
      };
    }
  }

  const blocks = parseMarkdownBlocks(raw);
  const rendered = renderMarkdownBlocks(blocks);

  return {
    html: rendered.html,
    toc: rendered.toc,
    plainText: stripHtml(rendered.html),
  };
}
