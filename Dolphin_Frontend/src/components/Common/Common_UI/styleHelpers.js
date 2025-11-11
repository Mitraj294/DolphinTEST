// Small helpers for building style objects used by Common_UI components.
// Accepts numeric or string widths and normalizes to a Vue style object.

function normalizeSize(value) {
  if (value == null) return null;
  if (typeof value === 'number') return `${value}px`;
  if (typeof value === 'string' && value.trim() !== '') return value.trim();
  return null;
}

export function getWidthStyle(width) {
  const w = normalizeSize(width);
  if (!w) return null;
  return {
    width: w,
    minWidth: w,
    maxWidth: w,
  };
}

// Parse a column descriptor (used by TableHeader). Supports properties:
// - width (number|string)
// - minWidth (number|string)
// - style (string) e.g. 'padding-left: 12px; color: red;'
export function parseColumnStyle(col = {}) {
  const style = {};

  if (col.width) {
    style.width = normalizeSize(col.width);
  }

  if (col.minWidth) {
    style.minWidth = normalizeSize(col.minWidth);
  }

  if (col.style && typeof col.style === 'string') {
    const cssRules = col.style.split(';').filter((r) => r.trim());
    for (const rule of cssRules) {
      const [property, value] = rule.split(':').map((s) => s?.trim());
      if (!property || !value) continue;
      // convert kebab-case to camelCase
      const camel = property.replaceAll(/-([a-z])/g, (_, ch) => ch.toUpperCase());
      style[camel] = value;
    }
  }

  return Object.keys(style).length > 0 ? style : null;
}

export default {
  getWidthStyle,
  parseColumnStyle,
};
