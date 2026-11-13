/* Progressive, non-destructive layout upgrade for imported About pages. */
(function () {
  'use strict';
  function isElement(node, selector) {
    return node && node.nodeType === 1 && node.matches(selector);
  }
  function enhance() {
    const root = document.querySelector('main .wp-block-post-content');
    if (!root || root.classList.contains('seo-geo-legacy-about-enhanced') ||
        !root.querySelector(':scope > .sgmb-divi-fullwidth-header') ||
        !root.querySelector(':scope > .seo-geo-contact-form-wrap')) {
      return;
    }

    // Preserve native Gutenberg form, URL, headings, links and all text nodes.
    // Group only contiguous legacy blurb siblings, skipping whitespace nodes.
    let cursor = root.firstChild;
    while (cursor) {
      if (!isElement(cursor, '.sgmb-divi-blurb')) {
        cursor = cursor.nextSibling;
        continue;
      }
      const grid = document.createElement('div');
      grid.className = 'seo-geo-legacy-about-card-grid';
      root.insertBefore(grid, cursor);
      while (cursor) {
        const emptyText = cursor.nodeType === 3 && !cursor.nodeValue.trim();
        if (!isElement(cursor, '.sgmb-divi-blurb') && !emptyText) break;
        const next = cursor.nextSibling;
        grid.appendChild(cursor);
        cursor = next;
      }
    }

    // Only the gallery immediately after its authored H2 is collected;
    // the decorative hero figure remains in the original position.
    const heads = [...root.children].filter(el => el.matches('h2'));
    const galleryTitle = heads.find(h => isElement(h.nextElementSibling, 'figure.wp-block-image'));
    if (galleryTitle) {
      let node = galleryTitle.nextElementSibling;
      const gallery = document.createElement('div');
      gallery.className = 'seo-geo-legacy-about-brand-grid';
      root.insertBefore(gallery, node);
      while (node) {
        const emptyText = node.nodeType === 3 && !node.nodeValue.trim();
        if (!isElement(node, 'figure.wp-block-image') && !emptyText) break;
        const next = node.nextSibling;
        gallery.appendChild(node);
        node = next;
      }
    }
    root.classList.add('seo-geo-legacy-about-enhanced');
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', enhance, { once: true });
  } else {
    enhance();
  }
}());
