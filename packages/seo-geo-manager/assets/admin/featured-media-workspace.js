( function () {
	'use strict';

	const root = document.getElementById( 'seo-geo-manager-dashboard' );
	const navigation = root ? root.querySelector( '[data-seo-geo-navigation-workspace]' ) : null;
	const contextual = root ? root.querySelector( '[data-seo-geo-contextual-links-workspace]' ) : null;
	const contentWorkspace = root ? root.querySelector( '[data-seo-geo-content-workspace]' ) : null;
	const anchor = navigation || contextual || contentWorkspace;
	const api = window.SeoGeoManagerFeaturedMediaWorkspace || {};
	if ( ! root || ! anchor || ! window.wp || ! window.wp.apiFetch ) {
		return;
	}

	const object = ( value ) => value && typeof value === 'object' && ! Array.isArray( value ) ? value : {};
	const array = ( value ) => Array.isArray( value ) ? value : [];
	const state = {
		resource: null,
		current: null,
		media: [],
		selected: null,
		action: 'replace',
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

	function contentPath() {
		return api.contentPath || '/seo-geo-manager/v1/content';
	}

	function mediaPath() {
		return api.mediaPath || '/seo-geo-manager/v1/media';
	}

	function idempotencyKey() {
		if ( window.crypto && typeof window.crypto.randomUUID === 'function' ) {
			return `manager-featured-media-${ window.crypto.randomUUID() }`;
		}
		return `manager-featured-media-${ Date.now() }-${ Math.random().toString( 16 ).slice( 2 ) }`;
	}

	const section = el( 'section', 'seo-geo-manager-admin__panel seo-geo-manager-featured-media-workspace' );
	section.dataset.seoGeoFeaturedMediaWorkspace = '';
	section.innerHTML = `
		<div class="seo-geo-manager-admin__panel-heading">
			<div>
				<p class="seo-geo-manager-admin__eyebrow">Build / Finish · Media</p>
				<h2>Imagen destacada</h2>
				<p class="description">Revisa y cambia únicamente la relación de imagen destacada de la página o entrada seleccionada. No modifica binarios, contenido ni URLs externas.</p>
			</div>
			<button type="button" class="button" data-featured-media-refresh disabled>Recargar</button>
		</div>
		<div class="notice notice-info inline seo-geo-manager-admin__notice" data-featured-media-status role="status" aria-live="polite"><p>Selecciona una página o entrada en el bloque de contenido.</p></div>
		<div class="seo-geo-manager-featured-media-summary" data-featured-media-summary hidden></div>
		<div class="seo-geo-manager-featured-media-layout" data-featured-media-layout hidden>
			<div class="seo-geo-manager-featured-media-current">
				<h3>Estado actual</h3>
				<div data-featured-media-current></div>
			</div>
			<div class="seo-geo-manager-featured-media-editor">
				<div class="seo-geo-manager-featured-media-toolbar">
					<label>Buscar en la biblioteca acotada
						<input type="search" data-featured-media-search placeholder="Título, ALT o ID">
					</label>
					<button type="button" class="button" data-featured-media-remove disabled>Quitar imagen destacada</button>
				</div>
				<div class="seo-geo-manager-featured-media-selection" data-featured-media-selection><p class="description">Selecciona una imagen existente o elige quitar la imagen destacada.</p></div>
				<div class="seo-geo-manager-featured-media-grid" data-featured-media-grid></div>
				<label class="seo-geo-manager-featured-media-approval" data-featured-media-approval hidden><input type="checkbox" data-featured-media-allow-published> Confirmo el cambio sobre una página/entrada publicada después de revisar la vista previa.</label>
				<div class="seo-geo-manager-build__actions">
					<button type="button" class="button button-secondary" data-featured-media-preview disabled>Vista previa</button>
					<button type="button" class="button button-primary" data-featured-media-apply disabled>Aplicar y verificar</button>
					<button type="button" class="button" data-featured-media-rollback hidden>Rollback seguro</button>
				</div>
				<div class="seo-geo-manager-build__preview" data-featured-media-preview-box><p class="description">La mutación queda bloqueada hasta generar una vista previa.</p></div>
			</div>
		</div>`;
	anchor.insertAdjacentElement( 'afterend', section );

	const statusBox = section.querySelector( '[data-featured-media-status]' );
	const summaryBox = section.querySelector( '[data-featured-media-summary]' );
	const layout = section.querySelector( '[data-featured-media-layout]' );
	const currentBox = section.querySelector( '[data-featured-media-current]' );
	const searchInput = section.querySelector( '[data-featured-media-search]' );
	const grid = section.querySelector( '[data-featured-media-grid]' );
	const selectionBox = section.querySelector( '[data-featured-media-selection]' );
	const approvalWrap = section.querySelector( '[data-featured-media-approval]' );
	const allowPublished = section.querySelector( '[data-featured-media-allow-published]' );
	const removeButton = section.querySelector( '[data-featured-media-remove]' );
	const previewButton = section.querySelector( '[data-featured-media-preview]' );
	const applyButton = section.querySelector( '[data-featured-media-apply]' );
	const rollbackButton = section.querySelector( '[data-featured-media-rollback]' );
	const previewBox = section.querySelector( '[data-featured-media-preview-box]' );
	const refreshButton = section.querySelector( '[data-featured-media-refresh]' );

	function setStatus( message, type = 'info' ) {
		statusBox.className = `notice inline seo-geo-manager-admin__notice notice-${ type }`;
		clear( statusBox );
		statusBox.appendChild( el( 'p', '', message ) );
	}

	function mutationLocked() {
		if ( ! state.operation ) return false;
		setStatus( `La operación ${ state.operation.id } sigue aplicada. Ejecuta rollback o conserva el cambio antes de iniciar otra modificación de imagen destacada.`, 'warning' );
		return true;
	}

	function resetMutation() {
		state.preview = null;
		previewButton.disabled = Boolean( state.operation ) || ! state.resource || ( 'replace' === state.action && ! state.selected ) || ( 'remove' === state.action && ! Number( object( state.current?.resource ).featured_media_id || 0 ) );
		applyButton.disabled = true;
		rollbackButton.hidden = ! state.operation;
		allowPublished.checked = false;
		clear( previewBox );
		previewBox.appendChild( el( 'p', 'description', state.operation ? `La operación ${ state.operation.id } está aplicada y conserva disponible el rollback seguro.` : 'La mutación queda bloqueada hasta generar una vista previa.' ) );
	}

	function imageNode( media ) {
		const image = document.createElement( 'img' );
		image.src = String( media.url || '' );
		image.alt = String( media.alt || media.title || '' );
		image.loading = 'lazy';
		return image;
	}

	function renderSummary() {
		clear( summaryBox );
		if ( ! state.resource ) {
			summaryBox.hidden = true;
			return;
		}
		summaryBox.hidden = false;
		summaryBox.appendChild( el( 'strong', '', `${ state.resource.title || '(Sin título)' } · ${ state.resource.type || state.resource.post_type || 'contenido' } #${ state.resource.id }` ) );
		summaryBox.appendChild( el( 'span', 'seo-geo-manager-admin__badge', state.resource.status || '—' ) );
	}

	function renderCurrent() {
		clear( currentBox );
		const payload = object( state.current );
		const resource = object( payload.resource );
		const media = object( payload.media );
		if ( ! media.id ) {
			currentBox.appendChild( el( 'p', 'description', 'Este recurso no tiene imagen destacada.' ) );
			removeButton.disabled = true;
		} else {
			if ( media.url ) currentBox.appendChild( imageNode( media ) );
			currentBox.appendChild( el( 'strong', '', `${ media.title || '(Sin título)' } · #${ media.id }` ) );
			currentBox.appendChild( el( 'span', '', `ALT: ${ media.alt || '(vacío)' }` ) );
			currentBox.appendChild( el( 'code', '', String( media.fingerprint || '' ) ) );
			removeButton.disabled = Boolean( state.operation );
		}
		if ( resource.fingerprint ) currentBox.appendChild( el( 'small', '', `Fingerprint de relación: ${ resource.fingerprint }` ) );
	}

	function renderSelection() {
		clear( selectionBox );
		if ( 'remove' === state.action ) {
			selectionBox.appendChild( el( 'strong', '', 'Acción seleccionada: quitar imagen destacada' ) );
			selectionBox.appendChild( el( 'p', 'description', 'El archivo de la biblioteca no se elimina; solo se quita la relación de imagen destacada.' ) );
			return;
		}
		if ( ! state.selected ) {
			selectionBox.appendChild( el( 'p', 'description', state.operation ? `Operación ${ state.operation.id } aplicada. Resuélvela antes de elegir otra imagen.` : 'Selecciona una imagen existente o elige quitar la imagen destacada.' ) );
			return;
		}
		selectionBox.appendChild( el( 'strong', '', `${ state.selected.title || '(Sin título)' } · #${ state.selected.id }` ) );
		selectionBox.appendChild( el( 'span', '', `ALT: ${ state.selected.alt || '(vacío)' }` ) );
		const dimensions = object( state.selected.dimensions );
		selectionBox.appendChild( el( 'small', '', `${ dimensions.width || 0 }×${ dimensions.height || 0 } · ${ state.selected.mime_type || 'image' }` ) );
	}

	function matchesSearch( media, query ) {
		if ( ! query ) return true;
		const haystack = `${ media.id || '' } ${ media.title || '' } ${ media.alt || '' } ${ media.caption || '' }`.toLowerCase();
		return haystack.includes( query.toLowerCase() );
	}

	function renderMedia() {
		clear( grid );
		const query = searchInput.value.trim();
		const items = state.media.filter( ( media ) => matchesSearch( media, query ) );
		if ( ! items.length ) {
			grid.appendChild( el( 'p', 'description', 'No hay imágenes que coincidan con la búsqueda.' ) );
			return;
		}
		items.forEach( ( media ) => {
			const card = el( 'article', 'seo-geo-manager-featured-media-card' );
			if ( Number( state.selected?.id || 0 ) === Number( media.id || 0 ) && 'replace' === state.action ) card.classList.add( 'is-selected' );
			if ( media.url ) card.appendChild( imageNode( media ) );
			card.appendChild( el( 'strong', '', media.title || '(Sin título)' ) );
			card.appendChild( el( 'small', '', `#${ media.id } · ALT: ${ media.alt || '(vacío)' }` ) );
			const dimensions = object( media.dimensions );
			card.appendChild( el( 'small', '', `${ dimensions.width || 0 }×${ dimensions.height || 0 }` ) );
			const choose = el( 'button', 'button button-small', state.operation ? 'Rollback pendiente' : 'Usar como destacada' );
			choose.type = 'button';
			choose.disabled = Boolean( state.operation );
			choose.addEventListener( 'click', () => {
				if ( mutationLocked() ) return;
				state.action = 'replace';
				state.selected = media;
				resetMutation();
				renderSelection();
				renderMedia();
			} );
			card.appendChild( choose );
			grid.appendChild( card );
		} );
	}

	async function loadMediaLibrary() {
		try {
			const result = await window.wp.apiFetch( { path: mediaPath() } );
			state.media = array( result.items );
			renderMedia();
		} catch ( error ) {
			setStatus( error?.message || 'No se pudo cargar la biblioteca acotada de imágenes.', 'error' );
		}
	}

	async function inspectCurrent() {
		if ( ! state.resource?.id ) return;
		if ( state.operation ) {
			mutationLocked();
			return;
		}
		setStatus( `Inspeccionando imagen destacada del recurso #${ state.resource.id }…` );
		try {
			const result = await window.wp.apiFetch( { path: `${ contentPath() }/${ Number( state.resource.id ) }/featured-media` } );
			state.current = object( result );
			state.selected = null;
			state.action = 'replace';
			state.operation = null;
			resetMutation();
			renderSummary();
			renderCurrent();
			renderSelection();
			renderMedia();
			layout.hidden = false;
			refreshButton.disabled = false;
			approvalWrap.hidden = 'publish' !== String( object( state.current.resource ).status || state.resource.status || '' );
			setStatus( 'Estado de imagen destacada inspeccionado. Selecciona una imagen existente o quita la relación actual.', 'success' );
		} catch ( error ) {
			layout.hidden = true;
			setStatus( error?.message || 'No se pudo inspeccionar la imagen destacada.', 'error' );
		}
	}

	function payload() {
		const resource = object( state.current?.resource );
		return {
			schema_version: 1,
			resource_id: Number( state.resource?.id || resource.id || 0 ),
			media_action: state.action,
			media_id: 'replace' === state.action ? Number( state.selected?.id || 0 ) : 0,
			expected_fingerprint: String( resource.fingerprint || '' ),
			allow_published_target: Boolean( allowPublished.checked ),
		};
	}

	function renderPreview( result ) {
		clear( previewBox );
		const change = object( result.change );
		const media = object( result.media );
		previewBox.appendChild( el( 'strong', '', 'Vista previa de relación destacada' ) );
		previewBox.appendChild( el( 'pre', '', JSON.stringify( {
			resource_id: Number( state.resource?.id || 0 ),
			action: state.action,
			before_media_id: Number( change.before_media_id || 0 ),
			after_media_id: Number( change.after_media_id || 0 ),
			selected_image: media.id ? { id: media.id, title: media.title, mime_type: media.mime_type } : null,
		}, null, 2 ) ) );
	}

	async function preview() {
		if ( state.operation || ! state.resource || ( 'replace' === state.action && ! state.selected ) ) return;
		previewButton.disabled = true;
		setStatus( 'Generando vista previa de la relación de imagen destacada…' );
		try {
			const result = await window.wp.apiFetch( {
				path: `${ contentPath() }/${ Number( state.resource.id ) }/featured-media/changes/preview`,
				method: 'POST',
				data: payload(),
			} );
			state.preview = object( result );
			renderPreview( state.preview );
			applyButton.disabled = true !== result.has_changes || ( ! approvalWrap.hidden && ! allowPublished.checked );
			setStatus( 'Vista previa correcta. No se modificará ningún binario ni el contenido de la página/entrada.', 'success' );
		} catch ( error ) {
			state.preview = null;
			applyButton.disabled = true;
			setStatus( error?.message || 'No se pudo generar la vista previa de imagen destacada.', 'error' );
		} finally {
			previewButton.disabled = false;
		}
	}

	async function apply() {
		if ( state.operation || ! state.preview ) return;
		if ( ! approvalWrap.hidden && ! allowPublished.checked ) {
			setStatus( 'Confirma el cambio sobre el recurso publicado después de revisar la vista previa.', 'info' );
			return;
		}
		applyButton.disabled = true;
		const data = payload();
		data.idempotency_key = idempotencyKey();
		const environment = object( state.preview.environment );
		if ( environment.fingerprint ) data.environment_fingerprint = environment.fingerprint;
		setStatus( 'Aplicando la relación y ejecutando readback exacto…' );
		try {
			const result = await window.wp.apiFetch( {
				path: `${ contentPath() }/${ Number( state.resource.id ) }/featured-media/changes/apply`,
				method: 'POST',
				data,
			} );
			const operationId = String( result.operation_id || '' );
			if ( ! operationId ) throw new Error( 'Apply no devolvió operation_id.' );
			const verified = await window.wp.apiFetch( { path: `${ contentPath() }/featured-media/changes/${ encodeURIComponent( operationId ) }/verify` } );
			if ( true !== verified.verified ) throw new Error( 'El readback no confirmó la relación de imagen destacada.' );
			state.operation = {
				id: operationId,
				environment: object( result.environment ),
				before_fingerprint: String( result.before_fingerprint || '' ),
				before_media_id: Number( result.before_media_id || 0 ),
			};
			const current = object( state.current );
			const resource = object( current.resource );
			resource.featured_media_id = Number( verified.featured_media_id || 0 );
			resource.fingerprint = String( verified.current_fingerprint || resource.fingerprint || '' );
			current.resource = resource;
			current.media = 'replace' === state.action ? object( state.selected ) : null;
			state.current = current;
			refreshButton.disabled = true;
			rollbackButton.hidden = false;
			previewButton.disabled = true;
			removeButton.disabled = true;
			renderCurrent();
			renderMedia();
			clear( previewBox );
			previewBox.appendChild( el( 'strong', '', `Aplicado y verificado · Operación ${ operationId }` ) );
			previewBox.appendChild( el( 'p', 'description', `Imagen destacada verificada: ${ Number( verified.featured_media_id || 0 ) ? `#${ Number( verified.featured_media_id ) }` : 'ninguna' }` ) );
			setStatus( `Imagen destacada aplicada y verificada. Operación ${ operationId }. El rollback seguro permanece disponible.`, 'success' );
		} catch ( error ) {
			setStatus( error?.message || 'El Manager rechazó el cambio de imagen destacada.', 'error' );
		}
	}

	async function rollback() {
		if ( ! state.operation || ! state.resource ) return;
		if ( ! window.confirm( `Revertir de forma segura la operación de imagen destacada ${ state.operation.id }?` ) ) return;
		rollbackButton.disabled = true;
		setStatus( 'Ejecutando rollback seguro de imagen destacada…' );
		try {
			const environmentFingerprint = String( object( state.operation.environment ).fingerprint || '' );
			const result = await window.wp.apiFetch( {
				path: `${ contentPath() }/featured-media/changes/${ encodeURIComponent( state.operation.id ) }/rollback`,
				method: 'POST',
				data: { environment_fingerprint: environmentFingerprint },
			} );
			if ( 'rolled-back' !== result.status ) throw new Error( 'El rollback no confirmó estado rolled-back.' );
			const readback = await window.wp.apiFetch( { path: `${ contentPath() }/${ Number( state.resource.id ) }/featured-media` } );
			const restored = object( readback.resource );
			if ( Number( restored.featured_media_id || 0 ) !== Number( result.before_media_id || 0 ) || String( restored.fingerprint || '' ) !== String( result.before_fingerprint || '' ) ) {
				throw new Error( 'El readback posterior al rollback no coincide con la relación original.' );
			}
			state.current = object( readback );
			state.operation = null;
			state.selected = null;
			state.action = 'replace';
			refreshButton.disabled = false;
			rollbackButton.hidden = true;
			resetMutation();
			renderCurrent();
			renderSelection();
			renderMedia();
			setStatus( 'Rollback verificado: se restauró exactamente la relación original de imagen destacada.', 'success' );
		} catch ( error ) {
			setStatus( error?.message || 'El rollback de imagen destacada fue rechazado.', 'error' );
		} finally {
			rollbackButton.disabled = false;
		}
	}

	root.addEventListener( 'seo-geo-manager:content-selected', ( event ) => {
		const resource = object( event.detail ).resource;
		if ( ! resource?.id ) return;
		if ( state.operation ) {
			mutationLocked();
			return;
		}
		state.resource = resource;
		inspectCurrent();
	} );
	refreshButton.addEventListener( 'click', () => {
		if ( mutationLocked() ) return;
		inspectCurrent();
	} );
	searchInput.addEventListener( 'input', renderMedia );
	removeButton.addEventListener( 'click', () => {
		if ( mutationLocked() ) return;
		state.action = 'remove';
		state.selected = null;
		resetMutation();
		renderSelection();
		renderMedia();
	} );
	allowPublished.addEventListener( 'change', () => {
		if ( state.preview ) applyButton.disabled = ! allowPublished.checked;
	} );
	previewButton.addEventListener( 'click', preview );
	applyButton.addEventListener( 'click', apply );
	rollbackButton.addEventListener( 'click', rollback );

	loadMediaLibrary();
}() );
