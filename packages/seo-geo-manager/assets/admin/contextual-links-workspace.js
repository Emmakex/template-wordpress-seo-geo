( function () {
	'use strict';

	const root = document.getElementById( 'seo-geo-manager-dashboard' );
	const seoWorkspace = root ? root.querySelector( '[data-seo-geo-seo-authority-workspace]' ) : null;
	const contentWorkspace = root ? root.querySelector( '[data-seo-geo-content-workspace]' ) : null;
	const anchorWorkspace = seoWorkspace || contentWorkspace;
	const api = window.SeoGeoManagerContextualLinksWorkspace || {};
	if ( ! root || ! anchorWorkspace || ! window.wp || ! window.wp.apiFetch ) {
		return;
	}

	const object = ( value ) => value && typeof value === 'object' && ! Array.isArray( value ) ? value : {};
	const array = ( value ) => Array.isArray( value ) ? value : [];
	const state = { source: null, discovery: null, edge: null, targets: [], preview: null, operation: null };

	function el( tag, className, text ) {
		const node = document.createElement( tag );
		if ( className ) node.className = className;
		if ( undefined !== text ) node.textContent = String( text );
		return node;
	}

	function clear( node ) {
		while ( node && node.firstChild ) node.removeChild( node.firstChild );
	}

	function idempotencyKey() {
		if ( window.crypto && typeof window.crypto.randomUUID === 'function' ) {
			return `manager-contextual-${ window.crypto.randomUUID() }`;
		}
		return `manager-contextual-${ Date.now() }-${ Math.random().toString( 16 ).slice( 2 ) }`;
	}

	const section = el( 'section', 'seo-geo-manager-admin__panel seo-geo-manager-contextual-workspace' );
	section.dataset.seoGeoContextualLinksWorkspace = '';
	section.innerHTML = `
		<div class="seo-geo-manager-admin__panel-heading">
			<div>
				<p class="seo-geo-manager-admin__eyebrow">Build / Finish · Linking</p>
				<h2>Enlaces contextuales</h2>
				<p class="description">Inspecciona enlaces de la página seleccionada y corrige únicamente su destino hacia recursos publicados del WordPress actual.</p>
			</div>
			<button type="button" class="button" data-contextual-refresh disabled>Recargar enlaces</button>
		</div>
		<div class="notice notice-info inline seo-geo-manager-admin__notice" data-contextual-status role="status" aria-live="polite"><p>Selecciona una página o entrada en el bloque de contenido.</p></div>
		<div class="seo-geo-manager-contextual-summary" data-contextual-summary hidden></div>
		<div class="seo-geo-manager-contextual-layout" data-contextual-layout hidden>
			<div>
				<h3>Enlaces detectados</h3>
				<div class="seo-geo-manager-contextual-list" data-contextual-list></div>
			</div>
			<div class="seo-geo-manager-contextual-editor" data-contextual-editor hidden>
				<p class="seo-geo-manager-admin__eyebrow">Corrección acotada</p>
				<h3 data-contextual-edge-title>Enlace</h3>
				<div class="seo-geo-manager-contextual-current" data-contextual-current></div>
				<label class="seo-geo-manager-contextual-search">Buscar destino publicado
					<input type="search" data-contextual-target-search placeholder="Título de página o entrada">
				</label>
				<label>Destino
					<select data-contextual-target><option value="">Selecciona un recurso publicado</option></select>
				</label>
				<label class="seo-geo-manager-contextual-published" data-contextual-published hidden><input type="checkbox" data-contextual-allow-published> Permitir corrección en la página/entrada publicada.</label>
				<div class="seo-geo-manager-build__actions">
					<button type="button" class="button button-secondary" data-contextual-preview>Vista previa</button>
					<button type="button" class="button button-primary" data-contextual-apply disabled>Aplicar y verificar</button>
					<button type="button" class="button" data-contextual-rollback hidden>Rollback seguro</button>
				</div>
				<div class="seo-geo-manager-build__preview" data-contextual-preview-box><p class="description">Selecciona un destino y ejecuta Vista previa.</p></div>
			</div>
		</div>`;
	anchorWorkspace.insertAdjacentElement( 'afterend', section );

	const statusBox = section.querySelector( '[data-contextual-status]' );
	const summaryBox = section.querySelector( '[data-contextual-summary]' );
	const layout = section.querySelector( '[data-contextual-layout]' );
	const list = section.querySelector( '[data-contextual-list]' );
	const editor = section.querySelector( '[data-contextual-editor]' );
	const currentBox = section.querySelector( '[data-contextual-current]' );
	const targetSearch = section.querySelector( '[data-contextual-target-search]' );
	const targetSelect = section.querySelector( '[data-contextual-target]' );
	const publishedWrap = section.querySelector( '[data-contextual-published]' );
	const allowPublished = section.querySelector( '[data-contextual-allow-published]' );
	const previewButton = section.querySelector( '[data-contextual-preview]' );
	const applyButton = section.querySelector( '[data-contextual-apply]' );
	const rollbackButton = section.querySelector( '[data-contextual-rollback]' );
	const previewBox = section.querySelector( '[data-contextual-preview-box]' );
	const refreshButton = section.querySelector( '[data-contextual-refresh]' );

	function setStatus( message, type = 'info' ) {
		statusBox.className = `notice inline seo-geo-manager-admin__notice notice-${ type }`;
		clear( statusBox );
		statusBox.appendChild( el( 'p', '', message ) );
	}

	function resetPreview() {
		state.preview = null;
		state.operation = null;
		applyButton.disabled = true;
		rollbackButton.hidden = true;
		clear( previewBox );
		previewBox.appendChild( el( 'p', 'description', 'Selecciona un destino y ejecuta Vista previa.' ) );
	}

	function linksPath() {
		return api.linksPath || '/seo-geo-manager/v1/links/contextual';
	}

	function classificationLabel( value ) {
		const labels = {
			'internal-current': 'Interno actual',
			'internal-nonpublic': 'Interno no público',
			'internal-unresolved': 'Interno sin resolver',
			'environment-leakage-candidate': 'Posible fuga de entorno',
			external: 'Externo',
			system: 'Sistema',
			invalid: 'Inválido',
		};
		return labels[ value ] || value || 'Sin clasificar';
	}

	function renderSummary( payload ) {
		clear( summaryBox );
		summaryBox.hidden = false;
		const leakage = array( payload.items ).filter( ( item ) => 'environment-leakage-candidate' === item.classification ).length;
		summaryBox.appendChild( el( 'strong', '', `${ Number( payload.link_count || 0 ) } enlace(s) · ${ Number( payload.resource_count || 0 ) } recurso(s) inspeccionados` ) );
		if ( leakage ) summaryBox.appendChild( el( 'span', 'seo-geo-manager-contextual-warning', `${ leakage } posible(s) fuga(s) de entorno` ) );
		if ( payload.truncated ) summaryBox.appendChild( el( 'span', 'seo-geo-manager-admin__badge', 'Resultado acotado' ) );
	}

	function renderLinks( payload ) {
		clear( list );
		const items = array( payload.items );
		if ( ! items.length ) {
			list.appendChild( el( 'p', 'description', 'No se han detectado enlaces contextuales en este recurso.' ) );
			return;
		}

		items.forEach( ( item ) => {
			const card = el( 'article', 'seo-geo-manager-contextual-item' );
			if ( 'environment-leakage-candidate' === item.classification ) card.classList.add( 'is-leakage' );
			const heading = el( 'div', 'seo-geo-manager-contextual-item__heading' );
			heading.appendChild( el( 'strong', '', item.anchor_text || '(Enlace sin texto)' ) );
			heading.appendChild( el( 'span', 'seo-geo-manager-admin__badge', classificationLabel( item.classification ) ) );
			card.appendChild( heading );
			card.appendChild( el( 'code', '', item.href || '—' ) );
			const target = object( item.target );
			if ( target.id ) card.appendChild( el( 'small', '', `Destino #${ target.id } · ${ target.status || '—' }` ) );
			if ( 'environment-leakage-candidate' === item.classification ) {
				card.appendChild( el( 'p', 'seo-geo-manager-contextual-warning', 'El href parece apuntar a otro entorno, pero el Manager no lo reescribirá automáticamente.' ) );
			}

			const button = el( 'button', 'button button-small', 'Corregir destino' );
			button.type = 'button';
			button.disabled = [ 'system', 'invalid' ].includes( item.classification );
			button.addEventListener( 'click', () => selectEdge( item ) );
			card.appendChild( button );
			list.appendChild( card );
		} );
	}

	async function loadTargets() {
		if ( ! state.source ) return;
		const query = targetSearch.value.trim();
		const makePath = ( type ) => {
			const params = new URLSearchParams( { type, status: 'publish', per_page: '100', page: '1' } );
			if ( query ) params.set( 'search', query );
			return `${ api.contentPath || '/seo-geo-manager/v1/content' }?${ params.toString() }`;
		};
		try {
			const [ pages, posts ] = await Promise.all( [
				window.wp.apiFetch( { path: makePath( 'page' ) } ),
				window.wp.apiFetch( { path: makePath( 'post' ) } ),
			] );
			state.targets = [ ...array( pages.items ), ...array( posts.items ) ].filter( ( item ) => Number( item.id || 0 ) !== Number( state.source.id || 0 ) && 'publish' === item.status );
			renderTargets();
		} catch ( error ) {
			setStatus( error?.message || 'No se pudieron cargar destinos publicados.', 'error' );
		}
	}

	function renderTargets() {
		const selected = targetSelect.value;
		clear( targetSelect );
		const empty = el( 'option', '', 'Selecciona un recurso publicado' );
		empty.value = '';
		targetSelect.appendChild( empty );
		state.targets.forEach( ( item ) => {
			const option = el( 'option', '', `${ item.title || '(Sin título)' } · ${ item.type || 'contenido' } #${ item.id }` );
			option.value = String( item.id );
			targetSelect.appendChild( option );
		} );
		if ( state.targets.some( ( item ) => String( item.id ) === selected ) ) targetSelect.value = selected;
	}

	function selectEdge( edge ) {
		state.edge = object( edge );
		resetPreview();
		section.querySelector( '[data-contextual-edge-title]' ).textContent = edge.anchor_text || '(Enlace sin texto)';
		clear( currentBox );
		currentBox.appendChild( el( 'strong', '', 'Href actual' ) );
		currentBox.appendChild( el( 'code', '', edge.href || '—' ) );
		currentBox.appendChild( el( 'span', 'seo-geo-manager-admin__badge', classificationLabel( edge.classification ) ) );
		const target = object( edge.target );
		if ( target.id ) currentBox.appendChild( el( 'small', '', `Destino actual #${ target.id} · ${ target.permalink || '' }` ) );
		publishedWrap.hidden = 'publish' !== String( object( edge.source ).status || state.source?.status || '' );
		allowPublished.checked = false;
		targetSearch.value = '';
		editor.hidden = false;
		loadTargets();
	}

	function payload() {
		const source = object( state.edge?.source );
		return {
			schema_version: 1,
			source_id: Number( state.source?.id || source.id || 0 ),
			edge_id: String( state.edge?.edge_id || '' ),
			expected_fingerprint: String( source.fingerprint || '' ),
			target_resource_id: Number( targetSelect.value || 0 ),
			allow_published_target: Boolean( allowPublished.checked ),
		};
	}

	function renderPreview( result ) {
		clear( previewBox );
		const edge = object( result.edge );
		const target = object( result.target );
		previewBox.appendChild( el( 'strong', '', 'Vista previa de href' ) );
		previewBox.appendChild( el( 'pre', '', JSON.stringify( {
			anchor_text: edge.anchor_text,
			before_href: edge.before_href,
			after_href: edge.after_href,
			target_resource_id: target.id || payload().target_resource_id,
		}, null, 2 ) ) );
	}

	async function discover() {
		if ( ! state.source?.id ) return;
		state.edge = null;
		state.preview = null;
		state.operation = null;
		editor.hidden = true;
		refreshButton.disabled = false;
		setStatus( `Inspeccionando enlaces del recurso #${ state.source.id }…` );
		try {
			const result = await window.wp.apiFetch( { path: `${ linksPath() }?source_id=${ Number( state.source.id ) }` } );
			state.discovery = object( result );
			renderSummary( state.discovery );
			renderLinks( state.discovery );
			layout.hidden = false;
			setStatus( `${ Number( state.discovery.link_count || 0 ) } enlace(s) contextuales inspeccionados sin devolver el contenido bruto.`, 'success' );
		} catch ( error ) {
			layout.hidden = true;
			setStatus( error?.message || 'No se pudieron inspeccionar los enlaces contextuales.', 'error' );
		}
	}

	async function preview() {
		if ( ! state.edge || ! Number( targetSelect.value || 0 ) ) {
			setStatus( 'Selecciona un destino publicado antes de generar la vista previa.', 'info' );
			return;
		}
		previewButton.disabled = true;
		setStatus( 'Generando vista previa del cambio de href…' );
		try {
			const result = await window.wp.apiFetch( { path: `${ linksPath() }/changes/preview`, method: 'POST', data: payload() } );
			state.preview = object( result );
			renderPreview( state.preview );
			applyButton.disabled = true !== result.has_changes;
			setStatus( true === result.has_changes ? 'Vista previa correcta. El texto del enlace y el contenido restante no se modificarán.' : 'El enlace ya apunta al destino seleccionado.', true === result.has_changes ? 'success' : 'info' );
		} catch ( error ) {
			state.preview = null;
			applyButton.disabled = true;
			setStatus( error?.message || 'La vista previa del enlace fue rechazada.', 'error' );
		} finally {
			previewButton.disabled = false;
		}
	}

	async function apply() {
		if ( ! state.preview || ! state.source ) return;
		applyButton.disabled = true;
		previewButton.disabled = true;
		setStatus( 'Aplicando href y verificando readback exacto…' );
		try {
			const environment = object( state.preview.environment );
			const applyPayload = {
				...payload(),
				idempotency_key: idempotencyKey(),
				environment_fingerprint: String( environment.fingerprint || '' ),
			};
			const result = await window.wp.apiFetch( { path: `${ linksPath() }/changes/apply`, method: 'POST', data: applyPayload } );
			const operationId = String( result.operation_id || '' );
			if ( ! operationId ) throw new Error( 'Apply contextual no devolvió operation_id.' );
			const verified = await window.wp.apiFetch( { path: `${ linksPath() }/changes/${ encodeURIComponent( operationId ) }/verify` } );
			if ( true !== verified.verified ) throw new Error( 'La verificación contextual no confirmó el estado aplicado.' );
			state.operation = {
				id: operationId,
				environment: object( result.environment ),
				before_href: String( result.before_href || object( state.preview.edge ).before_href || '' ),
				ordinal: Number( result.ordinal ?? object( state.preview.edge ).ordinal ?? state.edge.ordinal ?? -1 ),
			};
			rollbackButton.hidden = false;
			clear( previewBox );
			previewBox.appendChild( el( 'strong', '', `Aplicado y verificado · Operación ${ operationId }` ) );
			previewBox.appendChild( el( 'p', 'description', `Href verificado: ${ verified.href || '—' } · destino #${ verified.target_resource_id || '—' }` ) );
			setStatus( `Enlace aplicado y verificado. Operación ${ operationId }.`, 'success' );
			await refreshDiscoveryPreservingOperation();
		} catch ( error ) {
			setStatus( error?.message || 'El Manager rechazó la corrección contextual.', 'error' );
		} finally {
			previewButton.disabled = false;
			applyButton.disabled = true;
		}
	}

	async function refreshDiscoveryPreservingOperation() {
		const operation = state.operation;
		try {
			const result = await window.wp.apiFetch( { path: `${ linksPath() }?source_id=${ Number( state.source.id ) }` } );
			state.discovery = object( result );
			renderSummary( state.discovery );
			renderLinks( state.discovery );
		} finally {
			state.operation = operation;
			rollbackButton.hidden = ! operation;
		}
	}

	async function rollback() {
		if ( ! state.operation || ! state.source ) return;
		if ( ! window.confirm( `Revertir de forma segura la operación contextual ${ state.operation.id }?` ) ) return;
		rollbackButton.disabled = true;
		setStatus( 'Ejecutando rollback contextual seguro…' );
		try {
			const environmentFingerprint = String( object( state.operation.environment ).fingerprint || '' );
			const result = await window.wp.apiFetch( {
				path: `${ linksPath() }/changes/${ encodeURIComponent( state.operation.id ) }/rollback`,
				method: 'POST',
				data: { environment_fingerprint: environmentFingerprint },
			} );
			if ( 'rolled-back' !== result.status ) throw new Error( 'El rollback contextual no confirmó estado rolled-back.' );
			const readback = await window.wp.apiFetch( { path: `${ linksPath() }?source_id=${ Number( state.source.id ) }` } );
			const restored = array( readback.items ).find( ( item ) => Number( item.ordinal ?? -1 ) === Number( state.operation.ordinal ) );
			if ( ! restored || String( restored.href || '' ) !== state.operation.before_href ) {
				throw new Error( 'El readback posterior al rollback no coincide con el href original.' );
			}
			setStatus( `Rollback contextual verificado · Operación ${ state.operation.id }.`, 'success' );
			state.operation = null;
			rollbackButton.hidden = true;
			state.discovery = object( readback );
			renderSummary( state.discovery );
			renderLinks( state.discovery );
			editor.hidden = true;
		} catch ( error ) {
			setStatus( error?.message || 'El rollback contextual fue rechazado.', 'error' );
		} finally {
			rollbackButton.disabled = false;
		}
	}

	root.addEventListener( 'seo-geo-manager:content-selected', ( event ) => {
		const resource = object( event.detail ).resource;
		if ( ! resource?.id ) return;
		state.source = resource;
		discover();
	} );
	refreshButton.addEventListener( 'click', discover );
	targetSearch.addEventListener( 'input', () => { window.clearTimeout( targetSearch._seoGeoTimer ); targetSearch._seoGeoTimer = window.setTimeout( loadTargets, 250 ); } );
	targetSelect.addEventListener( 'change', resetPreview );
	allowPublished.addEventListener( 'change', resetPreview );
	previewButton.addEventListener( 'click', preview );
	applyButton.addEventListener( 'click', apply );
	rollbackButton.addEventListener( 'click', rollback );
}() );
