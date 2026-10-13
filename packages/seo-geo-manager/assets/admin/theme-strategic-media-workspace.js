( function () {
	'use strict';

	const root = document.getElementById( 'seo-geo-manager-dashboard' );
	const anchor = root ? root.querySelector( '[data-seo-geo-build-workspace]' ) : null;
	const api = window.SeoGeoManagerThemeMediaWorkspace || {};
	if ( ! root || ! anchor || ! window.wp || ! window.wp.apiFetch ) {
		return;
	}

	const object = ( value ) => value && typeof value === 'object' && ! Array.isArray( value ) ? value : {};
	const array = ( value ) => Array.isArray( value ) ? value : [];
	const state = {
		models: [],
		model: null,
		slot: null,
		binding: null,
		media: [],
		selectedMediaId: null,
		preview: null,
		operation: null,
	};

	function el( tag, className, value ) {
		const node = document.createElement( tag );
		if ( className ) node.className = className;
		if ( undefined !== value ) node.textContent = String( value );
		return node;
	}

	function clear( node ) {
		while ( node && node.firstChild ) node.removeChild( node.firstChild );
	}

	function idempotencyKey() {
		if ( window.crypto && typeof window.crypto.randomUUID === 'function' ) {
			return `manager-theme-media-${ window.crypto.randomUUID() }`;
		}
		return `manager-theme-media-${ Date.now() }-${ Math.random().toString( 16 ).slice( 2 ) }`;
	}

	function image( media ) {
		const img = document.createElement( 'img' );
		img.src = String( media.url || '' );
		img.alt = String( media.alt || media.title || '' );
		img.loading = 'lazy';
		return img;
	}

	const section = el( 'section', 'seo-geo-manager-admin__panel seo-geo-manager-theme-media-workspace' );
	section.dataset.seoGeoThemeMediaWorkspace = '';
	section.innerHTML = `
		<div class="seo-geo-manager-admin__panel-heading">
			<div>
				<p class="seo-geo-manager-admin__eyebrow">Build / Finish · Theme Media</p>
				<h2>Imágenes estratégicas del Theme</h2>
				<p class="description">Asigna imágenes existentes a slots declarados por el Theme. El layout y el HTML siguen bajo autoridad del Theme.</p>
			</div>
			<button type="button" class="button" data-theme-media-refresh>Recargar</button>
		</div>
		<div class="notice notice-info inline seo-geo-manager-admin__notice" data-theme-media-status role="status" aria-live="polite"><p>Cargando modelos y biblioteca…</p></div>
		<div class="seo-geo-manager-theme-media-layout">
			<div>
				<h3>Modelos y slots de imagen</h3>
				<div class="seo-geo-manager-theme-media-models" data-theme-media-models></div>
				<div class="seo-geo-manager-theme-media-slots" data-theme-media-slots></div>
			</div>
			<div class="seo-geo-manager-theme-media-editor" data-theme-media-editor hidden>
				<div data-theme-media-current></div>
				<label>Buscar imagen<input type="search" data-theme-media-search placeholder="Título, ALT o ID"></label>
				<div class="seo-geo-manager-theme-media-grid" data-theme-media-grid></div>
				<label class="seo-geo-manager-theme-media-published" data-theme-media-published hidden><input type="checkbox" data-theme-media-allow-published> Confirmo el cambio de imagen sobre una página publicada.</label>
				<div class="seo-geo-manager-build__actions">
					<button type="button" class="button button-secondary" data-theme-media-preview>Vista previa</button>
					<button type="button" class="button button-primary" data-theme-media-apply disabled>Aplicar</button>
					<button type="button" class="button" data-theme-media-rollback hidden>Rollback seguro</button>
				</div>
				<div class="seo-geo-manager-build__preview" data-theme-media-preview-box><p class="description">Selecciona una imagen y genera una vista previa.</p></div>
			</div>
		</div>`;
	anchor.insertAdjacentElement( 'afterend', section );

	const status = section.querySelector( '[data-theme-media-status]' );
	const modelsBox = section.querySelector( '[data-theme-media-models]' );
	const slotsBox = section.querySelector( '[data-theme-media-slots]' );
	const editor = section.querySelector( '[data-theme-media-editor]' );
	const currentBox = section.querySelector( '[data-theme-media-current]' );
	const search = section.querySelector( '[data-theme-media-search]' );
	const grid = section.querySelector( '[data-theme-media-grid]' );
	const publishedWrap = section.querySelector( '[data-theme-media-published]' );
	const allowPublished = section.querySelector( '[data-theme-media-allow-published]' );
	const previewButton = section.querySelector( '[data-theme-media-preview]' );
	const applyButton = section.querySelector( '[data-theme-media-apply]' );
	const rollbackButton = section.querySelector( '[data-theme-media-rollback]' );
	const previewBox = section.querySelector( '[data-theme-media-preview-box]' );
	const refreshButton = section.querySelector( '[data-theme-media-refresh]' );

	function setStatus( message, type = 'info' ) {
		status.className = `notice inline seo-geo-manager-admin__notice notice-${ type }`;
		clear( status );
		status.appendChild( el( 'p', '', message ) );
	}

	function locked() {
		if ( ! state.operation ) return false;
		setStatus( `La operación ${ state.operation.id } sigue aplicada. Ejecuta rollback antes de cambiar de slot.`, 'warning' );
		return true;
	}

	function resetPreview() {
		state.preview = null;
		applyButton.disabled = true;
		rollbackButton.hidden = ! state.operation;
		clear( previewBox );
		previewBox.appendChild( el( 'p', 'description', state.operation ? `Operación ${ state.operation.id } aplicada; rollback disponible.` : 'Genera una vista previa antes de aplicar.' ) );
	}

	function bindingPath() {
		const resource = object( state.model?.mapping?.resource );
		const model = object( state.model?.model );
		const slot = object( state.slot );
		return `${ api.bindingBase || '/seo-geo-manager/v1/theme/media-bindings' }/${ Number( resource.id || 0 ) }/${ encodeURIComponent( String( model.model_id || '' ) ) }/${ encodeURIComponent( String( slot.id || '' ) ) }`;
	}

	function renderModels() {
		clear( modelsBox );
		const mapped = state.models.filter( ( model ) => true === model.mapped && Number( object( model.resource ).id || 0 ) > 0 );
		if ( ! mapped.length ) {
			modelsBox.appendChild( el( 'p', 'description', 'No hay modelos Theme mapeados a páginas editables.' ) );
			return;
		}
		mapped.forEach( ( model ) => {
			const button = el( 'button', 'seo-geo-manager-theme-media-model' );
			button.type = 'button';
			button.appendChild( el( 'strong', '', model.page_key || model.model_id || 'Modelo' ) );
			button.appendChild( el( 'span', '', object( model.resource ).title || `#${ object( model.resource ).id || '—' }` ) );
			button.addEventListener( 'click', () => loadModel( model.model_id ) );
			modelsBox.appendChild( button );
		} );
	}

	function renderSlots() {
		clear( slotsBox );
		const slots = array( state.model?.slots ).filter( ( slot ) => 'media' === slot.type );
		if ( ! slots.length ) {
			slotsBox.appendChild( el( 'p', 'description', 'Este modelo no declara slots estratégicos de imagen.' ) );
			editor.hidden = true;
			return;
		}
		slotsBox.appendChild( el( 'h4', '', 'Slots de imagen' ) );
		slots.forEach( ( slot ) => {
			const button = el( 'button', 'seo-geo-manager-theme-media-slot' );
			button.type = 'button';
			button.appendChild( el( 'strong', '', slot.id || 'media' ) );
			button.appendChild( el( 'span', '', true === slot.required ? 'Obligatorio' : 'Opcional' ) );
			button.addEventListener( 'click', () => inspectSlot( slot ) );
			slotsBox.appendChild( button );
		} );
	}

	function renderCurrent() {
		clear( currentBox );
		const binding = object( state.binding?.binding );
		const media = object( state.binding?.media );
		currentBox.appendChild( el( 'p', 'seo-geo-manager-admin__eyebrow', `${ binding.model_id || 'modelo' } · ${ binding.slot_id || 'slot' }` ) );
		currentBox.appendChild( el( 'h3', '', 'Imagen actual' ) );
		if ( media.id ) {
			const wrap = el( 'div', 'seo-geo-manager-theme-media-current' );
			if ( media.url ) wrap.appendChild( image( media ) );
			const copy = el( 'div' );
			copy.appendChild( el( 'strong', '', `${ media.title || 'Imagen' } · #${ media.id }` ) );
			copy.appendChild( el( 'span', '', `ALT: ${ media.alt || '(vacío)' }` ) );
			wrap.appendChild( copy );
			currentBox.appendChild( wrap );
		} else {
			currentBox.appendChild( el( 'p', 'description', 'Este slot todavía no tiene imagen asignada.' ) );
		}
	}

	function matches( media ) {
		const query = search.value.trim().toLowerCase();
		if ( ! query ) return true;
		return `${ media.id || '' } ${ media.title || '' } ${ media.alt || '' }`.toLowerCase().includes( query );
	}

	function renderMediaGrid() {
		clear( grid );
		const slot = object( state.slot );
		const items = state.media.filter( matches );
		if ( true !== slot.required ) {
			const remove = el( 'button', 'seo-geo-manager-theme-media-card is-remove' );
			remove.type = 'button';
			remove.classList.toggle( 'is-selected', 0 === Number( state.selectedMediaId ) );
			remove.appendChild( el( 'strong', '', 'Sin imagen' ) );
			remove.appendChild( el( 'small', '', 'Quitar asignación del slot opcional' ) );
			remove.addEventListener( 'click', () => selectMedia( 0 ) );
			grid.appendChild( remove );
		}
		items.forEach( ( media ) => {
			const card = el( 'button', 'seo-geo-manager-theme-media-card' );
			card.type = 'button';
			card.classList.toggle( 'is-selected', Number( media.id || 0 ) === Number( state.selectedMediaId ) );
			if ( media.url ) card.appendChild( image( media ) );
			card.appendChild( el( 'strong', '', `${ media.title || '(Sin título)' } · #${ media.id }` ) );
			card.appendChild( el( 'small', '', `ALT: ${ media.alt || '(vacío)' }` ) );
			card.addEventListener( 'click', () => selectMedia( Number( media.id || 0 ) ) );
			grid.appendChild( card );
		} );
	}

	function selectMedia( attachmentId ) {
		if ( locked() ) return;
		state.selectedMediaId = Number( attachmentId || 0 );
		resetPreview();
		renderMediaGrid();
	}

	async function loadMedia() {
		const result = await window.wp.apiFetch( { path: api.mediaPath || '/seo-geo-manager/v1/media' } );
		state.media = array( result.items );
	}

	async function loadModels() {
		if ( locked() ) return;
		setStatus( 'Cargando modelos Theme e imágenes…' );
		editor.hidden = true;
		clear( slotsBox );
		try {
			const [ models ] = await Promise.all( [
				window.wp.apiFetch( { path: api.themeModelsPath || '/seo-geo-manager/v1/theme/models' } ),
				loadMedia(),
			] );
			state.models = array( models.models );
			state.model = null;
			state.slot = null;
			state.binding = null;
			renderModels();
			setStatus( `${ state.models.length } modelo(s) Theme · ${ state.media.length } imagen(es) disponibles.`, 'success' );
		} catch ( error ) {
			setStatus( error?.message || 'No se pudieron cargar los medios estratégicos del Theme.', 'error' );
		}
	}

	async function loadModel( modelId ) {
		if ( locked() || ! modelId ) return;
		setStatus( `Cargando modelo ${ modelId }…` );
		try {
			state.model = await window.wp.apiFetch( { path: `${ api.themeModelsPath || '/seo-geo-manager/v1/theme/models' }/${ encodeURIComponent( modelId ) }` } );
			state.slot = null;
			state.binding = null;
			editor.hidden = true;
			renderSlots();
			setStatus( 'Modelo cargado. Selecciona un slot de imagen.', 'success' );
		} catch ( error ) {
			setStatus( error?.message || 'No se pudo cargar el modelo Theme.', 'error' );
		}
	}

	async function inspectSlot( slot, preserveOperation = false ) {
		if ( state.operation && ! preserveOperation ) {
			locked();
			return;
		}
		state.slot = object( slot );
		setStatus( `Inspeccionando slot ${ state.slot.id || 'media' }…` );
		try {
			state.binding = await window.wp.apiFetch( { path: bindingPath() } );
			state.selectedMediaId = Number( object( state.binding.binding ).attachment_id || 0 );
			if ( ! preserveOperation ) state.operation = null;
			resetPreview();
			renderCurrent();
			renderMediaGrid();
			const resource = object( state.binding.resource );
			publishedWrap.hidden = 'publish' !== resource.status;
			allowPublished.checked = false;
			editor.hidden = false;
			setStatus( 'Slot listo. Selecciona una imagen y genera vista previa.', 'success' );
		} catch ( error ) {
			editor.hidden = true;
			setStatus( error?.message || 'No se pudo inspeccionar el slot estratégico.', 'error' );
		}
	}

	function payload() {
		return {
			schema_version: 1,
			attachment_id: Number( state.selectedMediaId || 0 ),
			expected_fingerprint: String( object( state.binding?.binding ).fingerprint || '' ),
			allow_published_target: Boolean( allowPublished.checked ),
		};
	}

	function renderPreview( result ) {
		clear( previewBox );
		previewBox.appendChild( el( 'strong', '', 'Vista previa de imagen estratégica' ) );
		previewBox.appendChild( el( 'pre', '', JSON.stringify( object( result.change ), null, 2 ) ) );
		previewBox.appendChild( el( 'p', 'description', 'Solo cambiará la relación entre el slot Theme y el attachment seleccionado.' ) );
	}

	async function preview() {
		if ( ! state.binding || state.operation ) return;
		const beforeId = Number( object( state.binding.binding ).attachment_id || 0 );
		if ( beforeId === Number( state.selectedMediaId || 0 ) ) {
			setStatus( 'Selecciona una imagen distinta a la asignación actual.', 'info' );
			return;
		}
		previewButton.disabled = true;
		setStatus( 'Generando vista previa de imagen estratégica…' );
		try {
			const result = await window.wp.apiFetch( { path: `${ bindingPath() }/changes/preview`, method: 'POST', data: payload() } );
			state.preview = object( result );
			renderPreview( result );
			const published = 'publish' === object( state.binding.resource ).status;
			applyButton.disabled = true !== result.has_changes || ( published && ! allowPublished.checked );
			setStatus( 'Vista previa correcta. Revisa la asignación antes de aplicar.', 'success' );
		} catch ( error ) {
			resetPreview();
			setStatus( error?.message || 'La vista previa fue rechazada.', 'error' );
		} finally {
			previewButton.disabled = false;
		}
	}

	async function apply() {
		if ( ! state.preview || state.operation ) return;
		const intendedMediaId = Number( state.selectedMediaId || 0 );
		applyButton.disabled = true;
		previewButton.disabled = true;
		setStatus( 'Aplicando imagen estratégica y comprobando readback…' );
		try {
			const data = {
				...payload(),
				attachment_id: intendedMediaId,
				idempotency_key: idempotencyKey(),
				environment_fingerprint: String( object( state.preview.environment ).fingerprint || '' ),
			};
			const result = await window.wp.apiFetch( { path: `${ bindingPath() }/changes/apply`, method: 'POST', data } );
			const operationId = String( result.operation_id || '' );
			if ( ! operationId ) throw new Error( 'Apply no devolvió operation_id.' );
			state.operation = { id: operationId, environment: object( result.environment ) };
			rollbackButton.hidden = false;
			await inspectSlot( state.slot, true );
			const appliedId = Number( object( state.binding.binding ).attachment_id || 0 );
			if ( appliedId !== intendedMediaId ) throw new Error( 'El readback no coincide con la imagen aplicada.' );
			state.selectedMediaId = intendedMediaId;
			state.operation = { id: operationId, environment: object( result.environment ) };
			rollbackButton.hidden = false;
			clear( previewBox );
			previewBox.appendChild( el( 'strong', '', `Aplicado y verificado · Operación ${ operationId }` ) );
			setStatus( `Imagen estratégica aplicada y verificada · Operación ${ operationId }.`, 'success' );
		} catch ( error ) {
			if ( state.operation ) {
				rollbackButton.hidden = false;
				setStatus( `${ error?.message || 'No se pudo confirmar el readback.' } El rollback sigue disponible.`, 'error' );
			} else {
				setStatus( error?.message || 'El Manager rechazó la asignación.', 'error' );
			}
		} finally {
			previewButton.disabled = Boolean( state.operation );
		}
	}

	async function rollback() {
		if ( ! state.operation || ! state.slot ) return;
		if ( ! window.confirm( `Revertir la operación de imagen estratégica ${ state.operation.id }?` ) ) return;
		rollbackButton.disabled = true;
		setStatus( 'Ejecutando rollback de imagen estratégica…' );
		try {
			const result = await window.wp.apiFetch( {
				path: `${ api.bindingBase || '/seo-geo-manager/v1/theme/media-bindings' }/changes/${ encodeURIComponent( state.operation.id ) }/rollback`,
				method: 'POST',
				data: { environment_fingerprint: String( object( state.operation.environment ).fingerprint || '' ) },
			} );
			if ( 'rolled-back' !== result.status ) throw new Error( 'Rollback no confirmó estado rolled-back.' );
			state.operation = null;
			rollbackButton.hidden = true;
			await inspectSlot( state.slot );
			setStatus( 'Rollback verificado: se restauró la asignación estratégica anterior.', 'success' );
		} catch ( error ) {
			setStatus( error?.message || 'El rollback fue rechazado.', 'error' );
		} finally {
			rollbackButton.disabled = false;
		}
	}

	search.addEventListener( 'input', renderMediaGrid );
	allowPublished.addEventListener( 'change', () => {
		const published = 'publish' === object( state.binding?.resource ).status;
		applyButton.disabled = ! state.preview || ( published && ! allowPublished.checked );
	} );
	previewButton.addEventListener( 'click', preview );
	applyButton.addEventListener( 'click', apply );
	rollbackButton.addEventListener( 'click', rollback );
	refreshButton.addEventListener( 'click', loadModels );
	loadModels();
}() );