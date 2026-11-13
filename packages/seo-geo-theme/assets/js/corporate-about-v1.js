/* Progressive, non-destructive layout upgrade for imported About pages. */
(function () {
  'use strict';
  function isElement(node, selector) {
    return node && node.nodeType === 1 && node.matches(selector);
  }
  // Restore contrast for pale transparent logos imported into native blurb
  // cards. The decision is based on local image pixels, never a client name,
  // filename, post ID or remote request. WordPress images are never altered.
  function makeBlurbLogosLegible(root) {
    const cards = root.querySelectorAll('.sgmb-divi-blurb');
    if (cards.length < 3) return;
    for (const card of cards) {
      const figure = card.querySelector('.wp-block-image');
      if (!figure) continue;
      const img = figure.querySelector('img');
      if (!img) {
        if (!figure.textContent.trim()) card.classList.add('seo-geo-legacy-blurb-blank-art');
        continue;
      }
      const inspect = () => {
        if (!img.complete || !img.naturalWidth || !img.naturalHeight) return;
        try {
          const canvas = document.createElement('canvas');
          canvas.width = canvas.height = 36;
          const ctx = canvas.getContext('2d', { willReadFrequently: true });
          if (!ctx) return;
          ctx.drawImage(img, 0, 0, 36, 36);
          const data = ctx.getImageData(0, 0, 36, 36).data;
          let transparent = 0, visible = 0, bright = 0;
          for (let i = 0; i < data.length; i += 4) {
            if (data[i + 3] < 40) { transparent++; continue; }
            visible++;
            if (data[i] > 225 && data[i + 1] > 225 && data[i + 2] > 225) bright++;
          }
          const sample = 36 * 36;
          if (transparent / sample > .2 && visible > 16 && bright / visible > .75) {
            card.classList.add('seo-geo-legacy-blurb-light-art');
          } else if (transparent / sample > .997 && visible < 3) {
            // An entirely transparent media slot is not a useful graphic.
            // Hide only the slot, retaining the authored heading/link/copy.
            card.classList.add('seo-geo-legacy-blurb-blank-art');
          }
        } catch {
          // Cross-origin or unsupported canvas: keep the original asset.
        }
      };
      if (img.complete) inspect();
      else img.addEventListener('load', inspect, { once: true });
    }
  }

  function enhance() {
    const root = document.querySelector('main .wp-block-post-content');
    if (root) makeBlurbLogosLegible(root);
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
    // Imported assets may be white logotypes on a transparent background.
    // Sample only 48x48 image pixels locally; no URL or pixel data is sent.
    // Light transparent logos receive a dark contrast-safe card backdrop.
    const brandGallery = root.querySelector(':scope > .seo-geo-legacy-about-brand-grid');
    if (brandGallery) {
      for (const img of brandGallery.querySelectorAll('figure img')) {
        const inspect = () => {
          if (!img.naturalWidth || !img.naturalHeight) return;
          try {
            const canvas = document.createElement('canvas');
            canvas.width = canvas.height = 48;
            const ctx = canvas.getContext('2d', { willReadFrequently: true });
            if (!ctx) return;
            ctx.drawImage(img, 0, 0, 48, 48);
            const pixels = ctx.getImageData(0, 0, 48, 48).data;
            let transparent = 0, visible = 0, white = 0;
            for (let i = 0; i < pixels.length; i += 4) {
              const a = pixels[i + 3];
              if (a < 40) { transparent++; continue; }
              visible++;
              if (pixels[i] > 225 && pixels[i + 1] > 225 && pixels[i + 2] > 225) white++;
            }
            const total = 48 * 48;
            if (transparent > total * .12 && visible > 15 && white / visible > .72) {
              img.closest('figure')?.classList.add('seo-geo-legacy-about-light-logo');
            }
          } catch {
            // Cross-origin or unsupported canvas: leave the authored image untouched.
          }
        };
        if (img.complete) inspect();
        else img.addEventListener('load', inspect, { once: true });
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
