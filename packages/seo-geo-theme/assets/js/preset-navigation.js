(() => {
	'use strict';

	const mobileNavigationSelector = '.seo-geo-preset-navigation__mobile';

	const closeNavigation = (details, restoreFocus = false) => {
		if (!(details instanceof HTMLDetailsElement) || !details.open) {
			return;
		}

		details.open = false;

		if (restoreFocus) {
			const summary = details.querySelector('summary');
			if (summary instanceof HTMLElement) {
				summary.focus();
			}
		}
	};

	document.addEventListener('click', (event) => {
		const target = event.target;
		if (!(target instanceof Element)) {
			return;
		}

		const summary = target.closest(`${mobileNavigationSelector} > summary`);
		if (summary instanceof HTMLElement) {
			const details = summary.parentElement;
			if (details instanceof HTMLDetailsElement) {
				event.preventDefault();
				details.open = !details.open;
			}
			return;
		}

		const link = target.closest(`${mobileNavigationSelector} a`);
		if (link instanceof HTMLAnchorElement) {
			const details = link.closest(mobileNavigationSelector);
			if (details instanceof HTMLDetailsElement) {
				closeNavigation(details);
			}
		}
	});

	document.addEventListener('pointerdown', (event) => {
		const target = event.target;
		if (!(target instanceof Node)) {
			return;
		}

		document.querySelectorAll(`${mobileNavigationSelector}[open]`).forEach((details) => {
			if (details instanceof HTMLDetailsElement && !details.contains(target)) {
				closeNavigation(details);
			}
		});
	});

	document.addEventListener('keydown', (event) => {
		if (event.key !== 'Escape') {
			return;
		}

		document.querySelectorAll(`${mobileNavigationSelector}[open]`).forEach((details) => {
			if (details instanceof HTMLDetailsElement) {
				closeNavigation(details, true);
			}
		});
	});
})();
