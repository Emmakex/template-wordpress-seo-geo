( function () {
	'use strict';

	const root = document.getElementById( 'seo-geo-manager-dashboard' );
	const api = window.SeoGeoManagerOptimizationQueue || {};
	if ( ! root || ! window.wp || ! window.wp.apiFetch ) {
		return;
	}

	const object = ( value ) => value && typeof value === 'object' && ! Array.isArray( value ) ? value : {};
	const array = ( value ) => Array.isArray( value ) ? value : [];
	const state = { items: [], loading: false };

	function el( tag, className, value ) {
		const node = document.createElement( tag );
		if ( className ) node.className = className;
		if ( undefined !== value ) node.textContent = String( value );
		return node;
	}

	function clear( node ) {
		while ( node && node.firstChild ) node.removeChild( node.firstChild );
	}

	function severityRank( value ) {
		return { blocker: 0, warning: 1, info: 2 }[ value ] ?? 3;
	}

	function classificationRank( value ) {
		return { 'auto-fixable': 0, hydrate: 1, 'evidence-required': 2, review: 3 }[ value ] ?? 4;
	}

	function severityLabel( value ) {
		return { blocker: 'Bloqueo', warning: 'Aviso', info: 'Info' }[ value ] || value || 'Info';
	}

	function classificationLabel( value ) {
		return {
			'auto-fixable': 'Acción acotada',
			hydrate: 'Completar Theme',
			'evidence-required': 'Evidencia requerida',
			review: 'Revisión',
		}[ value ] || value || 'Revisión';
	}

	function categoryLabel( value ) {
		return {
			content: 'Contenido',
			navigation: 'Enlaces / navegación',
			permalinks: 'Permalinks',
			media: 'Medios',
			seo: 'SEO',
		}[ value ] || value || 'General';
	}

	function sourceResourceId( item ) {
		const sources = array( item.sources );
		for ( const source of sources ) {
			const candidate = Number( object( source ).id || 0 );
			const kind = String( object( source ).kind || '' );
			if ( candidate > 0 && ! kind.includes( 'menu' ) ) return candidate;
		}
		return Number( item.resource_id || 0 );
	}

	function sourceLooksLikeMenu( item ) {
		return array( item.sources ).some( ( source ) => String( object( source ).kind || '' ).includes( 'menu' ) );
	}

	function normalizeDiagnostics( data ) {
		const diagnostics = object( data.actionable_diagnostics );
		return array( diagnostics.items ).map( ( item, index ) => ( {
			...object( item ),
			queue_id: String( item.id || `${ item.code || 'diagnostic' }-${ index }` ),
			origin: 'diagnostics',
		} ) );
	}

	function mediaItems( data ) {
		const media = object( data.media_intelligence );
		return array( media.missing_alt_sample ).map( ( attachment ) => {
			const item = object( attachment );
			return {
				queue_id: `media-alt-${ Number( item.id || 0 ) }`,
				category: 'media',
				code: 'image-missing-alt',
				severity: 'warning',
				classification: 'auto-fixable',
				title: `${ item.title || 'Imagen' } no tiene texto ALT.`,
				media_id: Number( item.id || 0 ),
				current_url: String( item.url || '' ),
				occurrences: 1,
				next_action: 'edit-existing-image-alt',
				origin: 'media-intelligence',
			};
		} );
	}

	function seoItems( data ) {
		const seo = object( data.seo_authority );
		if ( true === seo.conflict ) {
			return [ {
				queue_id: 'seo-authority-conflict',
				category: 'seo',
				code: 'seo-authority-conflict',
				severity: 'blocker',
				classification: 'review',
				title: 'Hay más de una autoridad SEO activa y el Manager no debe escribir hasta resolverla.',
				next_action: String( seo.next_action || 'review-seo-authority' ),
				occurrences: 1,
				origin: 'seo-authority',
			} ];
		}
		if ( true !== seo.authority_resolved || true !== seo.write_adapter_ready ) {
			return [ {
				queue_id: 'seo-authority-not-ready',
				category: 'seo',
				code: 'seo-authority-not-ready',
				severity: 'warning',
				classification: 'review',
				title: 'La autoridad SEO todavía no está lista para escritura segura.',
				next_action: String( seo.next_action || 'establish-output-authority-before-seo-mutation' ),
				occurrences: 1,
				origin: 'seo-authority',
			} ];
		}
		return [];
	}

	function orphanItems( data ) {
		const links = object( data.links );
		return array( links.orphan_page_candidates ).slice( 0, 20 ).map( ( candidate, index ) => {
			const item = object( candidate );
			return {
				queue_id: `orphan-${ Number( item.id || 0 ) || index }`,
				category: 'navigation',
				code: 'orphan-page-candidate',
				severity: 'warning',
				classification: 'review',
				title: `${ item.title || item.permalink || 'Página' } parece no recibir enlaces internos inventariados.`,
				resource_id: Number( item.id || 0 ),
				current_url: String( item.permalink || '' ),
				occurrences: 1,
				next_action: 'review-internal-linking',
				origin: 'site-links',
			};
		} );
	}

	function buildQueue( data ) {
		const merged = [ ...normalizeDiagnostics( data ), ...mediaItems( data ), ...seoItems( data ), ...orphanItems( data ) ];
		const seen = new Set();
		state.items = merged.filter( ( item ) => {
			const key = String( item.queue_id || `${ item.code }-${ item.resource_id || item.media_id || '' }` );
			if ( seen.has( key ) ) return false;
			seen.add( key );
			return true;
		} ).sort( ( left, right ) => {
			const severity = severityRank( left.severity ) - severityRank( right.severity );
			if ( severity ) return severity;
			const classification = classificationRank( left.classification ) - classificationRank( right.classification );
			if ( classification ) return classification;
			return String( left.title || left.code || '' ).localeCompare( String( right.title || right.code || '' ) );
		} );
	}

	const section = el( 'section', 'seo-geo-manager-admin__panel seo-geo-manager-optimization-queue' );
	section.dataset.seoGeoOptimizationQueue = '';
	section.innerHTML = `
		<div class="seo-geo-manager-admin__panel-heading">
			<div>
				<p class="seo-geo-manager-admin__eyebrow">Optimize · EMMAKE</p>
				<h2>Cola de optimización</h2>
				<p class="description">Prioriza mejoras detectadas y abre el workspace seguro que corresponde. La cola no aplica cambios automáticamente.</p>
			</div>
			<button type="button" class="button button-primary" data-optimization-refresh>Actualizar cola</button>
		</div>
		<div class="notice notice-info inline seo-geo-manager-admin__notice" data-optimization-status role="status" aria-live="polite"><p>Cargando prioridades…</p></div>
		<div class="seo-geo-manager-optimization-summary" data-optimization-summary></div>
		<div class="seo-geo-manager-optimization-filters">
			<label>Categoría<select data-optimization-category><option value="">Todas</option></select></label>
			<label>Severidad<select data-optimization-severity><option value="">Todas</option><option value="blocker">Bloqueo</option><option value="warning">Aviso</option><option value="info">Info</option></select></label>
			<label>Acción<select data-optimization-classification><option value="">Todas</option><option value="auto-fixable">Acción acotada</option><option value="hydrate">Completar Theme</option><option value="evidence-required">Evidencia requerida</option><option value="review">Revisión</option></select></label>
		</div>
		<div class="seo-geo-manager-optimization-list" data-optimization-list></div>`;

	const diagnosticsAnchor = root.querySelector( '[data-seo-geo-actionables]' );
	const insertion = diagnosticsAnchor ? diagnosticsAnchor.closest( 'section' ) : null;
	if ( insertion ) insertion.insertAdjacentElement( 'afterend', section );
	else root.appendChild( section );

	const status = section.querySelector( '[data-optimization-status]' );
	const summary = section.querySelector( '[data-optimization-summary]' );
	const list = section.querySelector( '[data-optimization-list]' );
	const categoryFilter = section.querySelector( '[data-optimization-category]' );
	const severityFilter = section.querySelector( '[data-optimization-severity]' );
	const classificationFilter = section.querySelector( '[data-optimization-classification]' );
	const refresh = section.querySelector( '[data-optimization-refresh]' );

	function setStatus( message, type = 'info' ) {
		status.className = `notice inline seo-geo-manager-admin__notice notice-${ type }`;
		clear( status );
		status.appendChild( el( 'p', '', message ) );
	}

	function metric( label, value ) {
		const card = el( 'div', 'seo-geo-manager-optimization-summary__metric' );
		card.appendChild( el( 'span', '', label ) );
		card.appendChild( el( 'strong', '', value ) );
		return card;
	}

	function renderSummary() {
		clear( summary );
		summary.appendChild( metric( 'Pendientes', state.items.length ) );
		summary.appendChild( metric( 'Bloqueos', state.items.filter( ( item ) => 'blocker' === item.severity ).length ) );
		summary.appendChild( metric( 'Acciones acotadas', state.items.filter( ( item ) => 'auto-fixable' === item.classification ).length ) );
		summary.appendChild( metric( 'Revisión', state.items.filter( ( item ) => 'review' === item.classification ).length ) );
	}

	function renderCategoryOptions() {
		const selected = categoryFilter.value;
		while ( categoryFilter.options.length > 1 ) categoryFilter.remove( 1 );
		[ ...new Set( state.items.map( ( item ) => String( item.category || 'general' ) ) ) ].sort().forEach( ( category ) => {
			const option = document.createElement( 'option' );
			option.value = category;
			option.textContent = categoryLabel( category );
			categoryFilter.appendChild( option );
		} );
		if ( [ ...categoryFilter.options ].some( ( option ) => option.value === selected ) ) categoryFilter.value = selected;
	}

	function filteredItems() {
		return state.items.filter( ( item ) => {
			if ( categoryFilter.value && categoryFilter.value !== String( item.category || '' ) ) return false;
			if ( severityFilter.value && severityFilter.value !== String( item.severity || '' ) ) return false;
			if ( classificationFilter.value && classificationFilter.value !== String( item.classification || '' ) ) return false;
			return true;
		} );
	}

	function focus( selector ) {
		const target = root.querySelector( selector );
		if ( ! target ) return false;
		target.scrollIntoView( { behavior: 'smooth', block: 'start' } );
		target.classList.add( 'is-optimization-focus' );
		window.setTimeout( () => target.classList.remove( 'is-optimization-focus' ), 2400 );
		return true;
	}

	async function dispatchResource( resourceId, selector ) {
		const id = Number( resourceId || 0 );
		if ( id <= 0 ) return false;
		try {
			const resource = await window.wp.apiFetch( { path: `${ api.contentPath || '/seo-geo-manager/v1/content' }/${ id }` } );
			root.dispatchEvent( new CustomEvent( 'seo-geo-manager:content-selected', { detail: { resource } } ) );
			window.setTimeout( () => focus( selector ), 180 );
			return true;
		} catch ( error ) {
			setStatus( error?.message || `No se pudo abrir el recurso #${ id }.`, 'error' );
			return false;
		}
	}

	function openThemeModel( item ) {
		const modelId = String( item.model_id || '' );
		if ( modelId ) {
			const card = root.querySelector( `[data-seo-geo-build-models] [data-model-id="${ CSS.escape( modelId ) }"]` );
			if ( card ) {
				card.click();
				window.setTimeout( () => focus( '[data-seo-geo-build-workspace]' ), 80 );
				return true;
			}
		}
		return focus( '[data-seo-geo-build-workspace]' );
	}

	function openMediaAlt( item ) {
		const mediaId = Number( item.media_id || 0 );
		if ( mediaId <= 0 || ! focus( '[data-seo-geo-media-alt-workspace]' ) ) return false;
		const workspace = root.querySelector( '[data-seo-geo-media-alt-workspace]' );
		const searchInput = workspace?.querySelector( '[data-media-alt-search]' );
		if ( searchInput ) {
			searchInput.value = String( mediaId );
			searchInput.dispatchEvent( new Event( 'input', { bubbles: true } ) );
			window.setTimeout( () => {
				const button = workspace.querySelector( '.seo-geo-manager-media-alt-card .button' );
				if ( button && ! button.disabled ) button.click();
			}, 120 );
		}
		return true;
	}

	async function openTool( item ) {
		setStatus( `Abriendo herramienta para: ${ item.title || item.code || 'prioridad' }…` );
		if ( item.media_id ) {
			openMediaAlt( item );
			setStatus( 'Media ALT abierto con la imagen detectada.', 'success' );
			return;
		}
		if ( item.model_id || String( item.next_action || '' ).includes( 'theme' ) || String( item.next_action || '' ).includes( 'structured' ) ) {
			openThemeModel( item );
			setStatus( 'Editor Theme abierto para continuar la corrección.', 'success' );
			return;
		}
		if ( sourceLooksLikeMenu( item ) || 'navigation' === item.next_action ) {
			focus( '[data-seo-geo-navigation-workspace]' );
			setStatus( 'Navigation Workspace abierto.', 'success' );
			return;
		}
		if ( 'seo' === item.category ) {
			focus( '[data-seo-geo-content-workspace]' );
			setStatus( 'Selecciona el recurso a optimizar; SEO Authority se abrirá con esa selección.', 'info' );
			return;
		}
		const sourceId = sourceResourceId( item );
		if ( sourceId > 0 && ( 'navigation' === item.category || String( item.code || '' ).includes( 'link' ) || String( item.next_action || '' ).includes( 'link' ) ) ) {
			if ( await dispatchResource( sourceId, '[data-seo-geo-contextual-links-workspace]' ) ) {
				setStatus( `Recurso #${ sourceId } enviado al Contextual Links Workspace.`, 'success' );
			}
			return;
		}
		if ( Number( item.resource_id || 0 ) > 0 ) {
			if ( await dispatchResource( item.resource_id, '[data-seo-geo-content-workspace]' ) ) {
				setStatus( `Recurso #${ item.resource_id } cargado para revisión.`, 'success' );
			}
			return;
		}
		focus( '[data-seo-geo-actionables]' );
		setStatus( 'Esta prioridad necesita revisión de evidencia antes de abrir una mutación.', 'info' );
	}

	function renderList() {
		clear( list );
		const items = filteredItems();
		if ( ! items.length ) {
			list.appendChild( el( 'p', 'description', state.items.length ? 'No hay prioridades con los filtros actuales.' : 'No hay prioridades detectadas dentro del alcance actual.' ) );
			return;
		}
		items.forEach( ( item, index ) => {
			const row = el( 'article', `seo-geo-manager-optimization-item is-${ item.severity || 'info' }` );
			row.dataset.optimizationId = String( item.queue_id || index );
			const heading = el( 'div', 'seo-geo-manager-optimization-item__heading' );
			const copy = el( 'div' );
			copy.appendChild( el( 'strong', '', item.title || item.code || 'Prioridad' ) );
			copy.appendChild( el( 'small', '', `${ categoryLabel( item.category ) } · ${ severityLabel( item.severity ) } · ${ classificationLabel( item.classification ) }` ) );
			heading.appendChild( copy );
			const action = el( 'button', 'button button-secondary', 'Abrir herramienta' );
			action.type = 'button';
			action.addEventListener( 'click', () => openTool( item ) );
			heading.appendChild( action );
			row.appendChild( heading );
			if ( item.current_url ) row.appendChild( el( 'code', '', item.current_url ) );
			if ( item.suggested_url ) row.appendChild( el( 'p', 'description', `Destino propuesto: ${ item.suggested_url }` ) );
			if ( item.next_action ) row.appendChild( el( 'p', 'description', `Siguiente acción: ${ item.next_action }` ) );
			const occurrences = Number( item.occurrences || 1 );
			if ( occurrences > 1 ) row.appendChild( el( 'span', 'seo-geo-manager-optimization-item__count', `${ occurrences } detecciones agrupadas` ) );
			list.appendChild( row );
		} );
	}

	function render() {
		renderSummary();
		renderCategoryOptions();
		renderList();
	}

	async function loadQueue() {
		if ( state.loading ) return;
		state.loading = true;
		refresh.disabled = true;
		setStatus( 'Analizando oportunidades y preparando la cola…' );
		try {
			const data = await window.wp.apiFetch( { path: `${ api.intelligencePath || '/seo-geo-manager/v1/site/intelligence' }?include_rendered=1` } );
			buildQueue( object( data ) );
			render();
			const blockers = state.items.filter( ( item ) => 'blocker' === item.severity ).length;
			setStatus( blockers > 0 ? `${ state.items.length } prioridad(es), con ${ blockers } bloqueo(s) primero.` : `${ state.items.length } prioridad(es) listas para trabajar.`, blockers > 0 ? 'warning' : 'success' );
		} catch ( error ) {
			state.items = [];
			render();
			setStatus( error?.message || 'No se pudo cargar la cola de optimización.', 'error' );
		} finally {
			state.loading = false;
			refresh.disabled = false;
		}
	}

	[ categoryFilter, severityFilter, classificationFilter ].forEach( ( input ) => input.addEventListener( 'change', renderList ) );
	refresh.addEventListener( 'click', loadQueue );
	loadQueue();
}() );
