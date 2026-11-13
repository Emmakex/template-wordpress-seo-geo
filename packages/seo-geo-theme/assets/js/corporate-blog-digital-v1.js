/**
 * Progressive migrated editorial blog presentation.
 *
 * The Gutenberg content remains the source of truth. This module only
 * rearranges already-published cards on the public frontend. It never
 * fetches data, rewrites saved content or creates article URLs.
 */
(function () {
	'use strict';

	const root = document.querySelector('main .wp-block-post-content');
	if ( ! root || root.classList.contains('seo-geo-blog-digital-enhanced') ) {
		return;
	}
	const slider = root.querySelector(':scope > .sgmb-divi-post-slider');
	const sliderList = slider?.querySelector('ul.wp-block-latest-posts');
	const siblingLists = [...root.querySelectorAll(':scope > ul.wp-block-latest-posts')];
	if ( ! sliderList || siblingLists.length !== 4 ) {
		return;
	}
	const firstListItems = [...sliderList.querySelectorAll(':scope > li')];
	if (firstListItems.length < 4) {
		return;
	}
	function articlePath( item ) {
		const link = item.querySelector('.wp-block-latest-posts__post-title[href]');
		if ( ! link ) {
			return '';
		}
		try {
			const url = new URL(link.href, location.href);
			if (url.origin !== location.origin || ! url.pathname.startsWith('/nuevaweb/')) {
				return '';
			}
			return url.pathname;
		} catch {
			return '';
		}
	}

	const allItems = [
		...firstListItems,
		...siblingLists.flatMap(list => [...list.querySelectorAll(':scope > li')])
	];
	const unique = new Map();
	for (const item of allItems) {
		const key = articlePath(item);
		if ( ! key ) {
			return; // Bail out before any changes when source markup is unknown.
		}
		if ( ! unique.has(key) ) {
			unique.set(key, item);
		}
	}
	if (unique.size < 5 || unique.size > 12 || allItems.length <= unique.size) {
		return;
	}
	const featured = firstListItems[0];
	const featuredKey = articlePath(featured);
	const archive = siblingLists[siblingLists.length - 1];
	const remaining = [...unique.entries()].filter(([key]) => key !== featuredKey).map(([,item]) => item);
	if (remaining.length < 4) {
		return;
	}

	// Reparent original cards rather than duplicating content or inventing posts.
	archive.replaceChildren(...remaining);
	sliderList.replaceChildren(featured);
	for (const list of siblingLists.slice(0, -1)) {
		list.remove();
	}
	slider.classList.add('seo-geo-blog-digital-feature');
	archive.classList.add('seo-geo-blog-digital-archive');

	function moveSectionItems(heading, className, predicate) {
		if ( ! heading || heading.parentElement !== root ) {
			return null;
		}
		const items = [];
		let next = heading.nextElementSibling;
		while (next && predicate(next)) {
			items.push(next);
			next = next.nextElementSibling;
		}
		if ( ! items.length ) {
			return null;
		}
		const container = document.createElement('div');
		container.className = className;
		root.insertBefore(container, items[0]);
		for (const item of items) {
			container.appendChild(item);
		}
		return container;
	}

	const heading = [...root.children].filter(node => node.matches('h2'));
	const topicHeader = heading.find(node => node.textContent.trim().startsWith('Busque su categoría favorita'));
	const categoryHeader = heading.find(node => node.textContent.trim().startsWith('Todas las Categorías'));
	const conversionHeader = heading.find(node => node.textContent.trim().startsWith('Manténgase actualizado'));
	const topics = moveSectionItems(topicHeader, 'seo-geo-blog-digital-topic-grid', node => node.matches('h3'));
	const categories = moveSectionItems(categoryHeader, 'seo-geo-blog-digital-category-grid', node => node.matches('p'));
	if (topics?.children.length !== 6 || categories?.children.length !== 12) {
		// The cards have already been reparented. Keep the readable fallback.
		root.classList.add('seo-geo-blog-digital-enhanced');
		return;
	}

	if (conversionHeader && conversionHeader.parentElement === root) {
		const collected = [];
		let next = conversionHeader.nextElementSibling;
		while (next) {
			collected.push(next);
			next = next.nextElementSibling;
		}
		if (collected.length && collected.filter(el => el.matches('h3')).length === 3) {
			const grid = document.createElement('div');
			grid.className = 'seo-geo-blog-digital-conversion-grid';
			root.insertBefore(grid, collected[0]);
			let card = null;
			for (const node of collected) {
				if (node.matches('h3')) {
					card = document.createElement('section');
					card.className = 'seo-geo-blog-digital-conversion-card';
					grid.appendChild(card);
				}
				if (card) {
					card.appendChild(node);
				}
			}
		}
	}

	// A blank legacy graphic creates a large fake editorial section. Hide only
	// an actual near-uniform pale image, determined locally from image pixels.
	const blankFigure = archive.nextElementSibling;
	if (blankFigure?.matches('figure.wp-block-image')) {
		const image = blankFigure.querySelector('img');
		const inspectBlank = () => {
			if ( ! image?.naturalWidth || ! image.naturalHeight ) {
				return;
			}
			try {
				const canvas = document.createElement('canvas');
				canvas.width = canvas.height = 32;
				const context = canvas.getContext('2d', {willReadFrequently:true});
				if ( ! context ) {
					return;
				}
				context.drawImage(image, 0, 0, 32, 32);
				const pixels = context.getImageData(0, 0, 32, 32).data;
				let pale = 0;
				for (let i = 0; i < pixels.length; i += 4) {
					if (pixels[i + 3] < 16 || (pixels[i] > 230 && pixels[i + 1] > 230 && pixels[i + 2] > 230)) {
						pale++;
					}
				}
				// Also suppress a large migrated placeholder whose *only* alt
				// is its autogenerated upload filename, not meaningful text.
				// It is never deleted from WordPress and a newly authored
				// replacement with descriptive alt will remain visible.
				const url = new URL(image.currentSrc || image.src, location.href);
				const filename = url.pathname.split('/').pop().replace(/\.[^.]+$/, '');
				const filenameOnly = filename.length > 15 &&
					image.getAttribute('alt')?.trim() === filename &&
					url.pathname.startsWith('/wp-content/uploads/') &&
					blankFigure.getBoundingClientRect().height > 450;
				if (pale / (32 * 32) > .995 || filenameOnly) {
					blankFigure.classList.add('seo-geo-blog-digital-blank-legacy');
				}
			} catch {
				// Cross-origin images are left visible, without any network call.
			}
		};
		if (image?.complete) {
			inspectBlank();
		} else {
			image?.addEventListener('load', inspectBlank, {once:true});
		}
	}
	// The source mentions newsletter signup without a working form.
	// Do not advertise a nonfunctional subscription flow to visitors.
	const cards = [...root.querySelectorAll('.seo-geo-blog-digital-conversion-card')];
	const newsletter = cards.find(card => /suscr[ií]base a nuestro bolet[ií]n/i.test(
		card.querySelector('h3')?.textContent || ''
	));
	if (newsletter && ! newsletter.querySelector('form,input[type="email"]')) {
		newsletter.classList.add('seo-geo-blog-digital-newsletter-unavailable');
	}
	root.classList.add('seo-geo-blog-digital-enhanced');
}());
