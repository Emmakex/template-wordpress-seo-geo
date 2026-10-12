( function () {
	'use strict';

	const root = document.getElementById( 'seo-geo-manager-dashboard' );
	const contextual = root ? root.querySelector( '[data-seo-geo-contextual-links-workspace]' ) : null;
	const seoWorkspace = root ? root.querySelector( '[data-seo-geo-seo-authority-workspace]' ) : null;
	const contentWorkspace = root ? root.querySelector( '[data-seo-geo-content-workspace]' ) : null;
	const anchor = contextual || seoWorkspace || contentWorkspace;
	const api = window.SeoGeoManagerNavigationWorkspace || {};
	if ( ! root || ! anchor || ! window.wp || ! window.wp.apiFetch ) {
		return;
	}

	const object = ( value ) => value && typeof value === 'object' && ! Array.isArray( value ) ? value : {};
	const array = ( value ) => Array.isArray( value ) ? value : [];
	const state = {
		menus: [],
		menu: null,
		item: null,
		targets: [],
		preview: null,
		operation: null,
	};

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
			return `manager-navigation-${ window.crypto.randomUUID() }`;
		}
		return `manager-navigation-${ Date.now() }-${ Math.random().toString( 16 ).slice( 2 ) }`;
	}

	function navigationPath() {
		return api.navigationPath || '/seo-geo-manager/v1/navigation';
	}

	const section = el( 'section', 'seo-geo-manager-admin__panel seo-geo-manager-navigation-workspace' );
	section.dataset.seoGeoNavigationWorkspace = '';
	section.innerHTML = `
		<div class="seo-geo-manager-admin__panel-heading">
			<div>
				<p class="seo-geo-manager-admin__eyebrow">Build / Finish · Navigation</p>
				<h2>Navegación</h2>
				<p class="description">Inspecciona los menús de WordPress y corrige destinos de enlaces personalizados sin editar estructura, etiquetas ni elementos respaldados por objetos.</p>
			</div>
			<button type="button" class="button" data-navigation-refresh>Recargar</button>
		</div>
		<div class="notice notice-info inline seo-geo-manager-admin__notice" data-navigation-status role="status" aria-live="polite"><p>Cargando menús…</p></div>
		<div class="seo-geo-manager-navigation-toolbar">
			<label>Menú
				<select data-navigation-menu><option value="">Selecciona un menú</option></select>
			</label>
		</div>
		<div class="seo-geo-manager-navigation-summary" data-navigation-summary hidden></div>
		<div class="seo-geo-manager-navigation-layout" data-navigation-layout hidden>
			<div>
				<h3>Elementos del menú</h3>
				<div class="seo-geo-manager-navigation-list" data-navigation-list></div>
			</div>
			<div class="seo-geo-manager-navigation-editor" data-navigation-editor hidden>
				<p class="seo-geo-manager-admin__eyebrow">Corrección acotada</p>
				<h3 data-navigation-item-title>Elemento</h3>
				<div class="seo-geo-manager-navigation-current" data-navigation-current></div>
				<label>Buscar destino publicado
					<input type="search" data-navigation-target-search placeholder="Título de página o entrada">
				</label>
				<label>Destino
					<select data-navigation-target><option value="">Selecciona un recurso publicado</option></select>
				</label>
				<label class="seo-geo-manager-navigation-approval"><input type="checkbox" data-navigation-approve> Confirmo la modificación de navegación pública tras revisar la vista previa.</label>
				<div class="seo-geo-manager-build__actions">
					<button type="button" class="button button-secondary" data-navigation-preview>Vista previa</button>
					<button type="button" class="button button-primary" data-navigation-apply disabled>Aplicar y verificar</button>
					<button type="button" class="button" data-navigation-rollback hidden>Rollback seguro</button>
				</div>
				<div class="seo-geo-manager-build__preview" data-navigation-preview-box><p class="description">Selecciona un destino publicado y ejecuta Vista previa.</p></div>
			</div>
		</div>`;
	anchor.insertAdjacentElement( 'afterend', section );

	const statusBox = section.querySelector( '[data-navigation-status]' );
	const menuSelect = section.querySelector( '[data-navigation-menu]' );
	const summaryBox = section.querySelector( '[data-navigation-summary]' );
	const layout = section.querySelector( '[data-navigation-layout]' );
	const list = section.querySelector( '[data-navigation-list]' );
	const editor = section.querySelector( '[data-navigation-editor]' );
	const currentBox = section.querySelector( '[data-navigation-current]' );
	const targetSearch = section.querySelector( '[data-navigation-target-search]' );
	const targetSelect = section.querySelector( '[data-navigation-target]' );
	const approve = section.querySelector( '[data-navigation-approve]' );
	const previewButton = section.querySelector( '[data-navigation-preview]' );
	const applyButton = section.querySelector( '[data-navigation-apply]' );
	const rollbackButton = section.querySelector( '[data-navigation-rollback]' );
	const previewBox = section.querySelector( '[data-navigation-preview-box]' );
	const refreshButton = section.querySelector( '[data-navigation-refresh]' );

	function setStatus( message, type = 'info' ) {
		statusBox.className = `notice inline seo-geo-manager-admin__notice notice-${ type }`;
		clear( statusBox );
		statusBox.appendChild( el( 'p', '', message ) );
	}

	function resetMutation() {
		state.preview = null;
		state.operation = null;
		applyButton.disabled = true;
		rollbackButton.hidden = true;
		approve.checked = false;
		clear( previewBox );
		previewBox.appendChild( el( 'p', 'description', 'Selecciona un destino publicado y ejecuta Vista previa.' ) );
	}

	function renderMenus() {
		const selected = menuSelect.value;
		clear( menuSelect );
		const empty = el( 'option', '', 'Selecciona un menú' );
		empty.value = '';
		menuSelect.appendChild( empty );
		state.menus.forEach( ( menu ) => {
			const locations = array( menu.locations );
			const suffix = locations.length ? ` · ${ locations.join( ', ' ) }` : '';
			const option = el( 'option', '', `${ menu.name || `Menú #${ menu.id }` }${ suffix }` );
			option.value = String( menu.id || 0 );
			menuSelect.appendChild( option );
		} );
		if ( state.menus.some( ( menu ) => String( menu.id ) === selected ) ) menuSelect.value = selected;
	}

	async function loadMenus() {
		setStatus( 'Cargando menús de WordPress…' );
		try {
			const result = await window.wp.apiFetch( { path: navigationPath() } );
			state.menus = array( result.items );
			renderMenus();
			if ( ! state.menus.length ) {
				layout.hidden = true;
				setStatus( 'No hay menús clásicos de WordPress disponibles para inspección.', 'info' );
				return;
			}
			setStatus( `${ state.menus.length } menú(s) disponible(s). Selecciona uno para inspeccionarlo.`, 'success' );
		} catch ( error ) {
			setStatus( error?.message || 'No se pudieron cargar los menús.', 'error' );
		}
	}

	function renderSummary() {
		clear( summaryBox );
		if ( ! state.menu ) {
			summaryBox.hidden = true;
			return;
		}
		summaryBox.hidden = false;
		summaryBox.appendChild( el( 'strong', '', `${ state.menu.menu_name || 'Menú' } · ${ Number( state.menu.item_count || 0 ) } elemento(s)` ) );
		const locations = array( state.menu.locations );
		if ( locations.length ) summaryBox.appendChild( el( 'span', 'seo-geo-manager-admin__badge', locations.join( ', ' ) ) );
		summaryBox.appendChild( el( 'code', '', `fingerprint ${ String( state.menu.fingerprint || '' ).slice( 0, 12 ) }…` ) );
	}

	function urlOccurrenceCount( url ) {
		return array( state.menu?.items ).filter( ( item ) => 'custom' === String( item.type || '' ) && String( item.url || '' ) === String( url || '' ) ).length;
	}

	function renderItems() {
		clear( list );
		const items = array( state.menu?.items );
		if ( ! items.length ) {
			list.appendChild( el( 'p', 'description', 'Este menú no contiene elementos.' ) );
			return;
		}
		items.forEach( ( item ) => {
			const card = el( 'article', 'seo-geo-manager-navigation-item' );
			const heading = el( 'div', 'seo-geo-manager-navigation-item__heading' );
			heading.appendChild( el( 'strong', '', item.title || '(Sin título)' ) );
			heading.appendChild( el( 'span', 'seo-geo-manager-admin__badge', 'custom' === item.type ? 'Enlace personalizado' : `${ item.type || 'objeto' }:${ item.object || '—' }` ) );
			card.appendChild( heading );
			card.appendChild( el( 'code', '', item.url || '—' ) );
			card.appendChild( el( 'small', '', `#${ item.id } · orden ${ item.order || 0 }${ item.parent ? ` · padre #${ item.parent }` : '' }` ) );
			const button = el( 'button', 'button button-small', 'Corregir destino' );
			button.type = 'button';
			button.disabled = 'custom' !== String( item.type || '' );
			button.title = button.disabled ? 'Los elementos respaldados por objetos se conservan y requieren revisión manual.' : '';
			button.addEventListener( 'click', () => selectItem( item ) );
			card.appendChild( button );
			list.appendChild( card );
		} );
	}

	async function inspectMenu() {
		const menuId = Number( menuSelect.value || 0 );
		state.menu = null;
		state.item = null;
		editor.hidden = true;
		layout.hidden = true;
		if ( ! menuId ) return;
		setStatus( `Inspeccionando menú #${ menuId }…` );
		try {
			const result = await window.wp.apiFetch( { path: `${ navigationPath() }/${ menuId }` } );
			state.menu = object( result );
			renderSummary();
			renderItems();
			layout.hidden = false;
			setStatus( `${ Number( state.menu.item_count || 0 ) } elemento(s) inspeccionados. Solo los enlaces personalizados pueden corregirse automáticamente.`, 'success' );
		} catch ( error ) {
			setStatus( error?.message || 'No se pudo inspeccionar el menú.', 'error' );
		}
	}

	async function loadTargets() {
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
			state.targets = [ ...array( pages.items ), ...array( posts.items ) ].filter( ( item ) => 'publish' === item.status );
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

	function selectItem( item ) {
		state.item = object( item );
		resetMutation();
		section.querySelector( '[data-navigation-item-title]' ).textContent = item.title || '(Sin título)';
		clear( currentBox );
		currentBox.appendChild( el( 'strong', '', 'Destino actual' ) );
		currentBox.appendChild( el( 'code', '', item.url || '—' ) );
		const count = urlOccurrenceCount( item.url );
		if ( 1 < count ) currentBox.appendChild( el( 'span', 'seo-geo-manager-admin__badge', `${ count } ocurrencias exactas` ) );
		targetSearch.value = '';
		targetSelect.value = '';
		editor.hidden = false;
		loadTargets();
	}

	function mutationPayload() {
		return {
			schema_version: 1,
			menu_id: Number( state.menu?.menu_id || 0 ),
			expected_fingerprint: String( state.menu?.fingerprint || '' ),
			current_urls: [ String( state.item?.url || '' ) ],
			expected_occurrences: Math.max( 1, urlOccurrenceCount( state.item?.url ) ),
			target_resource_id: Number( targetSelect.value || 0 ),
		};
	}

	function renderPreview( result ) {
		clear( previewBox );
		previewBox.appendChild( el( 'strong', '', 'Vista previa de navegación' ) );
		previewBox.appendChild( el( 'pre', '', JSON.stringify( {
			menu: result.menu_name,
			changes: array( result.matches ).map( ( match ) => ( {
				item_id: match.id,
				title: match.title,
				before_url: match.before_url,
				after_url: match.after_url,
			} ) ),
			target_resource_id: Number( targetSelect.value || 0 ),
		}, null, 2 ) ) );
	}

	async function preview() {
		if ( ! state.menu || ! state.item || ! Number( targetSelect.value || 0 ) ) {
			setStatus( 'Selecciona un elemento y un destino publicado antes de generar la vista previa.', 'info' );
			return;
		}
		previewButton.disabled = true;
		setStatus( 'Generando vista previa de navegación…' );
		try {
			const result = await window.wp.apiFetch( { path: `${ navigationPath() }/changes/preview`, method: 'POST', data: mutationPayload() } );
			state.preview = object( result );
			renderPreview( state.preview );
			applyButton.disabled = ! approve.checked || true !== result.has_changes;
			setStatus( 'Vista previa correcta. El Manager cambiará únicamente la URL de los enlaces personalizados coincidentes.', 'success' );
		} catch ( error ) {
			state.preview = null;
			applyButton.disabled = true;
			setStatus( error?.message || 'No se pudo generar la vista previa.', 'error' );
		} finally {
			previewButton.disabled = false;
		}
	}

	async function apply() {
		if ( ! state.preview || ! approve.checked ) {
			setStatus( 'Debes revisar la vista previa y confirmar la modificación de navegación pública.', 'info' );
			return;
		}
		applyButton.disabled = true;
		setStatus( 'Aplicando cambio y verificando fingerprint…' );
		const payload = mutationPayload();
		payload.allow_public_navigation = true;
		payload.idempotency_key = idempotencyKey();
		const environment = object( state.preview.environment );
		if ( environment.fingerprint ) payload.environment_fingerprint = environment.fingerprint;
		try {
			const result = await window.wp.apiFetch( { path: `${ navigationPath() }/changes/apply`, method: 'POST', data: payload } );
			state.operation = object( result );
			const operationId = String( result.operation_id || '' );
			if ( ! operationId ) throw new Error( 'Apply no devolvió operation_id.' );
			const verified = await window.wp.apiFetch( { path: `${ navigationPath() }/changes/${ operationId }/verify` } );
			if ( true !== verified.verified ) throw new Error( 'La verificación de navegación no confirmó el cambio.' );
			if ( state.menu && verified.current_fingerprint ) {
				state.menu.fingerprint = String( verified.current_fingerprint );
				renderSummary();
			}
			rollbackButton.hidden = false;
			setStatus( `Navegación aplicada y verificada. Operación ${ operationId }. El rollback seguro permanece disponible.`, 'success' );
		} catch ( error ) {
			setStatus( error?.message || 'No se pudo aplicar/verificar el cambio de navegación.', 'error' );
		}
	}

	async function rollback() {
		const operationId = String( state.operation?.operation_id || '' );
		if ( ! operationId ) return;
		rollbackButton.disabled = true;
		setStatus( 'Ejecutando rollback seguro…' );
		const environment = object( state.operation.environment || state.preview?.environment );
		const data = {};
		if ( environment.fingerprint ) data.environment_fingerprint = environment.fingerprint;
		try {
			const result = await window.wp.apiFetch( { path: `${ navigationPath() }/changes/${ operationId }/rollback`, method: 'POST', data } );
			if ( 'rolled-back' !== result.status ) throw new Error( 'El rollback no devolvió estado rolled-back.' );
			const expectedBeforeFingerprint = String( result.before_fingerprint || '' );
			if ( ! expectedBeforeFingerprint ) throw new Error( 'El rollback no devolvió el fingerprint original.' );
			await inspectMenu();
			if ( state.menu?.fingerprint !== expectedBeforeFingerprint ) {
				throw new Error( 'El rollback finalizó pero el fingerprint original no fue restaurado.' );
			}
			state.operation = null;
			rollbackButton.hidden = true;
			setStatus( 'Rollback verificado: se restauró el fingerprint original del menú.', 'success' );
		} catch ( error ) {
			setStatus( error?.message || 'No se pudo completar el rollback seguro.', 'error' );
		} finally {
			rollbackButton.disabled = false;
		}
	}

	menuSelect.addEventListener( 'change', inspectMenu );
	refreshButton.addEventListener( 'click', async () => {
		await loadMenus();
		if ( menuSelect.value ) await inspectMenu();
	} );
	previewButton.addEventListener( 'click', preview );
	applyButton.addEventListener( 'click', apply );
	rollbackButton.addEventListener( 'click', rollback );
	targetSelect.addEventListener( 'change', resetMutation );
	approve.addEventListener( 'change', () => {
		applyButton.disabled = ! state.preview || ! approve.checked || true !== state.preview.has_changes;
	} );
	let searchTimer = null;
	targetSearch.addEventListener( 'input', () => {
		window.clearTimeout( searchTimer );
		searchTimer = window.setTimeout( loadTargets, 250 );
	} );

	loadMenus();
}() );
