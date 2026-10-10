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
	const actionablesBox = root.querySelector( '[data-seo-geo-actionables]' );
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

	function diagnosticIdentity( item, index ) {
		if ( item && item.id ) {
			return String( item.id );
		}
		return [ item?.classification || '', item?.category || '', item?.code || '', item?.current_url || '', item?.suggested_url || '', index ].join( '|' );
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
		const labels = { pass: 'Correcto', warning: 'Aviso', blocker: 'Bloqueo' };
		return labels[ value ] || String( value || '—' );
	}

	function classificationLabel( value ) {
		const labels = { 'auto-fixable': 'Autocorregible', hydrate: 'Hidratar', 'evidence-required': 'Evidencia requerida', review: 'Revisar' };
		return labels[ value ] || String( value || 'Revisar' );
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
				details.appendChild( text( 'pre', JSON.stringify( check.evidence, null, 2 ) ) );
				row.appendChild( details );
			}
			checksBox.appendChild( row );
		} );
	}

	function renderActionables( data ) {
		if ( ! actionablesBox ) return;
		clear( actionablesBox );
		const diagnostics = object( data.actionable_diagnostics );
		const summary = object( diagnostics.summary );
		const items = array( diagnostics.items );
		if ( ! items.length ) {
			actionablesBox.appendChild( text( 'p', 'No se detectaron causas raíz accionables dentro del alcance actual.', 'description' ) );
			return;
		}
		const facts = document.createElement( 'ul' );
		facts.className = 'seo-geo-manager-admin__facts';
		[
			[ 'Problemas únicos', summary.unique_issues ?? items.length ],
			[ 'Detecciones agrupadas', summary.raw_occurrences ?? items.length ],
			[ 'Autocorregibles', summary.auto_fixable ?? 0 ],
			[ 'Revisión manual', summary.review_required ?? 0 ],
			[ 'Evidencia requerida', summary.evidence_required ?? 0 ],
		].forEach( ( [ label, value ] ) => {
			const fact = document.createElement( 'li' );
			fact.appendChild( text( 'span', label ) );
			fact.appendChild( text( 'strong', value ) );
			facts.appendChild( fact );
		} );
		actionablesBox.appendChild( facts );
		const list = document.createElement( 'div' );
		list.className = 'seo-geo-manager-admin__checks';
		items.slice( 0, 20 ).forEach( ( item, index ) => {
			const row = document.createElement( 'article' );
			row.className = `seo-geo-manager-admin__check is-${ item.severity || 'warning' }`;
			row.dataset.seoGeoDiagnosticId = diagnosticIdentity( item, index );
			const heading = document.createElement( 'div' );
			heading.className = 'seo-geo-manager-admin__check-heading';
			heading.appendChild( text( 'strong', item.title || item.code || 'Diagnóstico' ) );
			heading.appendChild( text( 'span', classificationLabel( item.classification ), 'seo-geo-manager-admin__badge' ) );
			row.appendChild( heading );
			const occurrences = Number( item.occurrences || 1 );
			row.appendChild( text( 'p', `${ item.category || 'diagnóstico' } · ${ occurrences } detección(es)` ) );
			if ( item.current_url ) row.appendChild( text( 'code', item.current_url ) );
			if ( item.suggested_url ) row.appendChild( text( 'p', `Destino propuesto: ${ item.suggested_url }` ) );
			if ( item.next_action ) row.appendChild( text( 'p', `Siguiente acción: ${ item.next_action }`, 'description' ) );
			const sources = array( item.sources );
			if ( sources.length ) {
				const details = document.createElement( 'details' );
				details.appendChild( text( 'summary', `Ver orígenes (${ sources.length })` ) );
				const sourceList = document.createElement( 'ul' );
				sources.forEach( ( source ) => {
					const value = source.permalink || `${ source.kind || 'origen' } #${ source.id || '—' }`;
					sourceList.appendChild( text( 'li', value ) );
				} );
				details.appendChild( sourceList );
				row.appendChild( details );
			}
			list.appendChild( row );
		} );
		actionablesBox.appendChild( list );
	}

	function renderStructure( data ) {
		clear( structureBox );
		const contract = object( data.theme_contract );
		const summary = object( contract.summary );
		const pages = array( contract.pages );
		if ( contract.applicable !== true ) {
			structureBox.appendChild( text( 'p', 'No hay un contrato de preset SEO/GEO activo. El análisis genérico sigue disponible.' ) );
			return;
		}
		const unhydrated = pages.filter( ( page ) => {
			const model = object( page && page.model );
			return array( model.required_slots ).length > 0 && array( model.present_required_slots ).length === 0 && array( model.missing_required_slots ).length > 0;
		} ).length;
		const list = document.createElement( 'ul' );
		list.className = 'seo-geo-manager-admin__facts';
		[
			[ 'Preset', contract.preset || '—' ],
			[ 'Páginas esperadas', summary.expected_pages ?? 0 ],
			[ 'Páginas resueltas', summary.resolved_pages ?? 0 ],
			[ 'Páginas ausentes', summary.missing_pages ?? 0 ],
			[ 'Modelos pendientes de hidratar', unhydrated ],
			[ 'Slots detectados como ausentes', summary.missing_required_slots ?? 0 ],
		].forEach( ( [ label, value ] ) => {
			const item = document.createElement( 'li' );
			item.appendChild( text( 'span', label ) );
			item.appendChild( text( 'strong', value ) );
			list.appendChild( item );
		} );
		structureBox.appendChild( list );
		const missing = pages.filter( ( page ) => page && page.resolved !== true );
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
		const orphans = array( links.orphan_page_candidates );
		const highLeakage = leakage.filter( ( item ) => item && item.confidence === 'high' );
		const list = document.createElement( 'ul' );
		list.className = 'seo-geo-manager-admin__facts';
		[
			[ 'Fugas de entorno con confianza alta', highLeakage.length ],
			[ 'Rutas internas no resueltas', unresolved.length ],
			[ 'Páginas huérfanas candidatas', orphans.length ],
		].forEach( ( [ label, value ] ) => {
			const item = document.createElement( 'li' );
			item.appendChild( text( 'span', label ) );
			item.appendChild( text( 'strong', value ) );
			list.appendChild( item );
		} );
		navigationBox.appendChild( list );
		if ( highLeakage.length ) {
			navigationBox.appendChild( text( 'h3', 'Fugas de entorno detectadas' ) );
			const pending = document.createElement( 'ul' );
			highLeakage.slice( 0, 10 ).forEach( ( item ) => {
				const source = object( item.source );
				const sourceLabel = source.permalink || `${ source.kind || 'origen' } #${ source.id || '—' }`;
				const target = item.absolute_url || item.href || 'Destino';
				pending.appendChild( text( 'li', `${ sourceLabel } → ${ target }` ) );
			} );
			navigationBox.appendChild( pending );
		}
	}

	function render( data ) {
		renderMetrics( data );
		renderChecks( data );
		renderActionables( data );
		renderStructure( data );
		renderNavigation( data );
		if ( rawBox ) rawBox.textContent = JSON.stringify( data, null, 2 );
		const readiness = object( data.build_finish_readiness );
		const blockerCount = Number( readiness.blockers || 0 );
		const warningCount = Number( readiness.warnings || 0 );
		if ( blockerCount > 0 ) setStatus( `Análisis completado: ${ blockerCount } bloqueo(s) y ${ warningCount } aviso(s).`, 'warning' );
		else if ( warningCount > 0 ) setStatus( `Análisis completado sin bloqueos: ${ warningCount } aviso(s) por revisar.`, 'info' );
		else setStatus( 'Análisis completado: no se detectaron bloqueos ni avisos dentro del alcance actual.', 'success' );
	}

	async function analyze() {
		if ( ! analyzeButton ) return;
		analyzeButton.disabled = true;
		analyzeButton.setAttribute( 'aria-busy', 'true' );
		setStatus( config.labels?.analyzing || 'Analizando el sitio…', 'info' );
		try {
			const includeRendered = renderedToggle && renderedToggle.checked ? '1' : '0';
			const data = await window.wp.apiFetch( { path: `${ config.intelligencePath || '/seo-geo-manager/v1/site/intelligence' }?include_rendered=${ includeRendered }` } );
			render( object( data ) );
		} catch ( error ) {
			setStatus( error && error.message ? error.message : ( config.labels?.error || 'No se pudo completar el análisis.' ), 'error' );
		} finally {
			analyzeButton.disabled = false;
			analyzeButton.removeAttribute( 'aria-busy' );
		}
	}

	analyzeButton?.addEventListener( 'click', analyze );
	window.addEventListener( 'seo-geo-manager:analysis', ( event ) => {
		if ( event && event.detail ) render( object( event.detail ) );
	} );
}() );
