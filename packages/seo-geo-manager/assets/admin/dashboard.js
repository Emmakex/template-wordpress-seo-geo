( function () {
	'use strict';

	const config = window.SeoGeoManagerDashboard || {};
	const root = document.getElementById( 'seo-geo-manager-dashboard' );
	if ( ! root || ! window.wp || ! window.wp.apiFetch ) {
		return;
	}

	const analyzeButton = root.querySelector( '[data-seo-geo-action="analyze"]' );
	const renderedToggle = root.querySelector( '[data-seo-geo-rendered]' );
	const statusBox = root.querySelector( '[data-seo-geo-status]' );
	const checksBox = root.querySelector( '[data-seo-geo-checks]' );
	const structureBox = root.querySelector( '[data-seo-geo-structure]' );
	const navigationBox = root.querySelector( '[data-seo-geo-navigation]' );
	const rawBox = root.querySelector( '[data-seo-geo-raw]' );

	const metric = ( key ) => root.querySelector( `[data-seo-geo-metric="${ key }"]` );
	const object = ( value ) => value && typeof value === 'object' && ! Array.isArray( value ) ? value : {};
	const array = ( value ) => Array.isArray( value ) ? value : [];

	function clear( node ) {
		while ( node && node.firstChild ) {
			node.removeChild( node.firstChild );
		}
	}

	function text( tag, value, className ) {
		const node = document.createElement( tag );
		node.textContent = String( value ?? '' );
		if ( className ) {
			node.className = className;
		}
		return node;
	}

	function setStatus( message, type = 'info' ) {
		if ( ! statusBox ) {
			return;
		}
		statusBox.className = `notice inline seo-geo-manager-admin__notice notice-${ type }`;
		clear( statusBox );
		statusBox.appendChild( text( 'p', message ) );
	}

	function statusLabel( value ) {
		const labels = {
			pass: 'Correcto',
			warning: 'Aviso',
			blocker: 'Bloqueo',
		};
		return labels[ value ] || String( value || '—' );
	}

	function renderMetrics( data ) {
		const readiness = object( data.build_finish_readiness );
		const seo = object( data.seo_authority );
		const authority = object( seo.authority );

		metric( 'overall' ).textContent = statusLabel( readiness.overall );
		metric( 'blockers' ).textContent = String( readiness.blockers ?? 0 );
		metric( 'warnings' ).textContent = String( readiness.warnings ?? 0 );
		metric( 'seo' ).textContent = authority.label || seo.state || 'No resuelta';
	}

	function renderChecks( data ) {
		clear( checksBox );
		const checks = array( object( data.build_finish_readiness ).checks );
		if ( ! checks.length ) {
			checksBox.appendChild( text( 'p', 'No se recibieron comprobaciones de readiness.', 'description' ) );
			return;
		}

		checks.forEach( ( check ) => {
			const row = document.createElement( 'article' );
			row.className = `seo-geo-manager-admin__check is-${ check.status || 'unknown' }`;

			const heading = document.createElement( 'div' );
			heading.className = 'seo-geo-manager-admin__check-heading';
			heading.appendChild( text( 'strong', check.category || 'Comprobación' ) );
			heading.appendChild( text( 'span', statusLabel( check.status ), 'seo-geo-manager-admin__badge' ) );
			row.appendChild( heading );
			row.appendChild( text( 'p', check.message || '' ) );

			if ( check.evidence && Object.keys( object( check.evidence ) ).length ) {
				const details = document.createElement( 'details' );
				details.appendChild( text( 'summary', 'Ver evidencia' ) );
				const pre = text( 'pre', JSON.stringify( check.evidence, null, 2 ) );
				details.appendChild( pre );
				row.appendChild( details );
			}

			checksBox.appendChild( row );
		} );
	}

	function renderStructure( data ) {
		clear( structureBox );
		const contract = object( data.theme_contract );
		const summary = object( contract.summary );

		if ( contract.applicable !== true ) {
			structureBox.appendChild( text( 'p', 'No hay un contrato de preset SEO/GEO activo. El análisis genérico sigue disponible.' ) );
			return;
		}

		const list = document.createElement( 'ul' );
		list.className = 'seo-geo-manager-admin__facts';
		[
			[ 'Preset', contract.preset || '—' ],
			[ 'Páginas esperadas', summary.expected_pages ?? 0 ],
			[ 'Páginas resueltas', summary.resolved_pages ?? 0 ],
			[ 'Páginas ausentes', summary.missing_pages ?? 0 ],
			[ 'Modelos incompletos', summary.incomplete_model_pages ?? 0 ],
			[ 'Slots requeridos ausentes', summary.missing_required_slots ?? 0 ],
		].forEach( ( [ label, value ] ) => {
			const item = document.createElement( 'li' );
			item.appendChild( text( 'span', label ) );
			item.appendChild( text( 'strong', value ) );
			list.appendChild( item );
		} );
		structureBox.appendChild( list );

		const missing = array( contract.pages ).filter( ( page ) => page && page.resolved !== true );
		if ( missing.length ) {
			structureBox.appendChild( text( 'h3', 'Páginas pendientes' ) );
			const pending = document.createElement( 'ul' );
			missing.forEach( ( page ) => pending.appendChild( text( 'li', page.expected_title || page.key || 'Página' ) ) );
			structureBox.appendChild( pending );
		}
	}

	function renderNavigation( data ) {
		clear( navigationBox );
		const links = object( data.links );
		const leakage = array( links.environment_leakage_candidates );
		const unresolved = array( links.unresolved_internal_path_candidates );
		const orphans = array( links.published_orphan_page_candidates );

		const list = document.createElement( 'ul' );
		list.className = 'seo-geo-manager-admin__facts';
		[
			[ 'Fugas al dominio/ruta anterior', leakage.length ],
			[ 'Rutas internas no resueltas', unresolved.length ],
			[ 'Páginas huérfanas candidatas', orphans.length ],
		].forEach( ( [ label, value ] ) => {
			const item = document.createElement( 'li' );
			item.appendChild( text( 'span', label ) );
			item.appendChild( text( 'strong', value ) );
			list.appendChild( item );
		} );
		navigationBox.appendChild( list );

		if ( leakage.length ) {
			navigationBox.appendChild( text( 'h3', 'Fugas detectadas' ) );
			const pending = document.createElement( 'ul' );
			leakage.slice( 0, 10 ).forEach( ( item ) => {
				const source = item.source_url || item.source || 'Origen';
				const target = item.url || item.target_url || item.href || 'Destino';
				pending.appendChild( text( 'li', `${ source } → ${ target }` ) );
			} );
			navigationBox.appendChild( pending );
		}
	}

	function render( data ) {
		renderMetrics( data );
		renderChecks( data );
		renderStructure( data );
		renderNavigation( data );
		if ( rawBox ) {
			rawBox.textContent = JSON.stringify( data, null, 2 );
		}

		const readiness = object( data.build_finish_readiness );
		const blockerCount = Number( readiness.blockers || 0 );
		const warningCount = Number( readiness.warnings || 0 );
		if ( blockerCount > 0 ) {
			setStatus( `Análisis completado: ${ blockerCount } bloqueo(s) y ${ warningCount } aviso(s).`, 'warning' );
		} else if ( warningCount > 0 ) {
			setStatus( `Análisis completado sin bloqueos: ${ warningCount } aviso(s) por revisar.`, 'info' );
		} else {
			setStatus( 'Análisis completado: no se detectaron bloqueos ni avisos dentro del alcance actual.', 'success' );
		}
	}

	async function analyze() {
		if ( ! analyzeButton ) {
			return;
		}

		analyzeButton.disabled = true;
		analyzeButton.setAttribute( 'aria-busy', 'true' );
		setStatus( config.labels?.analyzing || 'Analizando el sitio…', 'info' );

		try {
			const includeRendered = renderedToggle && renderedToggle.checked ? '1' : '0';
			const data = await window.wp.apiFetch( {
				path: `${ config.intelligencePath || '/seo-geo-manager/v1/site/intelligence' }?include_rendered=${ includeRendered }`,
			} );
			render( object( data ) );
		} catch ( error ) {
			const message = error && error.message ? error.message : ( config.labels?.error || 'No se pudo completar el análisis.' );
			setStatus( message, 'error' );
		} finally {
			analyzeButton.disabled = false;
			analyzeButton.removeAttribute( 'aria-busy' );
		}
	}

	analyzeButton?.addEventListener( 'click', analyze );
}() );
