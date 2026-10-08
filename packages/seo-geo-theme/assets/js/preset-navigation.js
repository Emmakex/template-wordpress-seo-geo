/**
 * Mobile preset navigation interactions for strategic Theme surfaces.
 *
 * @package SeoGeoTheme
 */

'use strict';

const seoGeoMobileNavigationSelector = '.seo-geo-preset-navigation__mobile';

/**
 * Close one mobile navigation details element.
 *
 * @param {HTMLDetailsElement} details      Navigation details element.
 * @param {boolean}            restoreFocus Whether focus should return to summary.
 * @return {void}
 */
function seoGeoCloseNavigation( details, restoreFocus ) {
	let summary;

	if ( ! ( details instanceof HTMLDetailsElement ) || ! details.open ) {
		return;
	}

	details.open = false;

	if ( restoreFocus ) {
		summary = details.querySelector( 'summary' );
		if ( summary instanceof HTMLElement ) {
			summary.focus();
		}
	}
}

document.addEventListener(
	'click',
	function( event ) {
		const target = event.target;
		const summary = target instanceof Element ? target.closest( seoGeoMobileNavigationSelector + ' > summary' ) : null;
		let details;
		let link;

		if ( summary instanceof HTMLElement ) {
			details = summary.parentElement;
			if ( details instanceof HTMLDetailsElement ) {
				event.preventDefault();
				details.open = ! details.open;
			}
			return;
		}

		link = target instanceof Element ? target.closest( seoGeoMobileNavigationSelector + ' a' ) : null;
		if ( link instanceof HTMLAnchorElement ) {
			details = link.closest( seoGeoMobileNavigationSelector );
			if ( details instanceof HTMLDetailsElement ) {
				seoGeoCloseNavigation( details, false );
			}
		}
	}
);

document.addEventListener(
	'pointerdown',
	function( event ) {
		const target = event.target;

		if ( ! ( target instanceof Node ) ) {
			return;
		}

		document.querySelectorAll( seoGeoMobileNavigationSelector + '[open]' ).forEach(
			function( details ) {
				if ( details instanceof HTMLDetailsElement && ! details.contains( target ) ) {
					seoGeoCloseNavigation( details, false );
				}
			}
		);
	}
);

document.addEventListener(
	'keydown',
	function( event ) {
		if ( event.key !== 'Escape' ) {
			return;
		}

		document.querySelectorAll( seoGeoMobileNavigationSelector + '[open]' ).forEach(
			function( details ) {
				if ( details instanceof HTMLDetailsElement ) {
					seoGeoCloseNavigation( details, true );
				}
			}
		);
	}
);
