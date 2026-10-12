( function () {
	'use strict';

	const root = document.getElementById( 'seo-geo-manager-dashboard' );
	const anchor = root ? root.querySelector( '[data-seo-geo-featured-media-workspace]' ) : null;
	const api = window.SeoGeoManagerMediaAltWorkspace || {};
	if ( ! root || ! anchor || ! window.wp || ! window.wp.apiFetch ) {
		return;
	}

	const object = ( value ) => value && typeof value === 'object' && ! Array.isArray( value ) ? value : {};
	const array = ( value ) => Array.isArray( value ) ? value : [];
	const state = {
		media: [],
		selected: null,
		preview: null,
		operation: null,
		idempotencyKey: null,
	};

	const mediaPath = () => api.mediaPath || '/seo-geo-manager/v1/media';

	function el( tag, className, text ) {
		const node = document.createElement( tag );
		if ( className ) {
			node.className = className;
		}
		if ( undefined !== text ) {
			node.textContent = String( text );
		}
		return node;
	}

	function clear( node ) {
		while ( node && node.firstChild ) {
			node.removeChild( node.firstChild );
		}
	}

	function idempotencyKey() {
		if ( window.crypto && typeof window.crypto.randomUUID === 'function' ) {
			return `manager-media-alt-${ window.crypto.randomUUID() }`;
		}
		return `manager-media-alt-${ Date.now() }-${ Math.random().toString( 16 ).slice( 2 ) }`;
	}

	const section = el( 'section', 'seo-geo-manager-admin__panel seo-geo-manager-media-alt-workspace' );
	section.dataset.seoGeoMediaAltWorkspace = '';
	section.innerHTML = `
		<div class="seo-geo-manager-admin__panel-heading">
			<div>
				<p class="seo-geo-manager-admin__eyebrow">Build / Finish · Media SEO</p>
				<h2>ALT de imágenes existentes</h2>
				<p class="description">Edita únicamente el texto alternativo de imágenes ya existentes. No modifica archivos, URLs, EXIF ni contenido.</p>
			</div>
			<button type="button" class="button" data-media-alt-refresh>Recargar</button>
		</div>
		<div class="notice notice-info inline seo-geo-manager-admin__notice" data-media-alt-status role="status" aria-live="polite"><p>Cargando imágenes…</p></div>
		<div class="seo-geo-manager-media-alt-toolbar">
			<label>Buscar imagen<input type="search" data-media-alt-search placeholder="Título, ALT o ID"></label>
		</div>
		<div class="seo-geo-manager-media-alt-layout">
			<div class="seo-geo-manager-media-alt-grid" data-media-alt-grid></div>
			<div class="seo-geo-manager-media-alt-editor" data-media-alt-editor hidden>
				<div class="seo-geo-manager-media-alt-selection" data-media-alt-selection></div>
				<label>Nuevo ALT<textarea rows="4" maxlength="1000" data-media-alt-input></textarea></label>
				<label class="seo-geo-manager-media-alt-approval"><input type="checkbox" data-media-alt-approve> Confirmo el cambio de metadatos de esta imagen pública después de revisar la vista previa.</label>
				<div class="seo-geo-manager-build__actions">
					<button type="button" class="button button-secondary" data-media-alt-preview>Vista previa</button>
					<button type="button" class="button button-primary" data-media-alt-apply disabled>Aplicar y verificar</button>
					<button type="button" class="button" data-media-alt-rollback hidden>Rollback seguro</button>
				</div>
				<div class="seo-geo-manager-build__preview" data-media-alt-preview-box><p class="description">Selecciona una imagen y genera una vista previa.</p></div>
			</div>
		</div>`;
	anchor.insertAdjacentElement( 'afterend', section );

	const status = section.querySelector( '[data-media-alt-status]' );
	const grid = section.querySelector( '[data-media-alt-grid]' );
	const editor = section.querySelector( '[data-media-alt-editor]' );
	const selection = section.querySelector( '[data-media-alt-selection]' );
	const search = section.querySelector( '[data-media-alt-search]' );
	const input = section.querySelector( '[data-media-alt-input]' );
	const approve = section.querySelector( '[data-media-alt-approve]' );
	const previewBtn = section.querySelector( '[data-media-alt-preview]' );
	const applyBtn = section.querySelector( '[data-media-alt-apply]' );
	const rollbackBtn = section.querySelector( '[data-media-alt-rollback]' );
	const previewBox = section.querySelector( '[data-media-alt-preview-box]' );
	const refreshBtn = section.querySelector( '[data-media-alt-refresh]' );

	function setStatus( message, type = 'info' ) {
		status.className = `notice inline seo-geo-manager-admin__notice notice-${ type }`;
		clear( status );
		status.appendChild( el( 'p', '', message ) );
	}

	function locked() {
		if ( ! state.operation ) {
			return false;
		}
		setStatus( `La operación ${ state.operation.id } sigue aplicada. Ejecuta rollback o recarga la página antes de editar otra imagen.`, 'warning' );
		return true;
	}

	function resetPreview() {
		state.preview = null;
		state.idempotencyKey = null;
		applyBtn.disabled = true;
		rollbackBtn.hidden = ! state.operation;
		approve.checked = false;
		clear( previewBox );
		previewBox.appendChild( el( 'p', 'description', state.operation ? `La operación ${ state.operation.id } sigue aplicada y conserva el rollback seguro.` : 'Genera una vista previa antes de aplicar.' ) );
	}

	function image( media ) {
		const img = document.createElement( 'img' );
		img.src = String( media.url || '' );
		img.alt = String( media.alt || media.title || '' );
		img.loading = 'lazy';
		return img;
	}

	function matches( media, query ) {
		if ( ! query ) {
			return true;
		}
		return `${ media.id || '' } ${ media.title || '' } ${ media.alt || '' } ${ media.caption || '' }`.toLowerCase().includes( query.toLowerCase() );
	}

	function renderGrid() {
		clear( grid );
		const items = state.media.filter( ( media ) => matches( media, search.value.trim() ) );
		if ( ! items.length ) {
			grid.appendChild( el( 'p', 'description', 'No hay imágenes que coincidan con la búsqueda.' ) );
			return;
		}
		items.forEach( ( media ) => {
			const card = el( 'article', 'seo-geo-manager-media-alt-card' );
			if ( Number( state.selected?.id || 0 ) === Number( media.id || 0 ) ) {
				card.classList.add( 'is-selected' );
			}
			if ( media.url ) {
				card.appendChild( image( media ) );
			}
			card.appendChild( el( 'strong', '', `${ media.title || '(Sin título)' } · #${ media.id }` ) );
			card.appendChild( el( 'small', '', `ALT: ${ media.alt || '(vacío)' }` ) );
			const button = el( 'button', 'button button-small', state.operation ? 'Rollback pendiente' : 'Editar ALT' );
			button.type = 'button';
			button.disabled = Boolean( state.operation );
			button.addEventListener( 'click', () => selectMedia( media ) );
			card.appendChild( button );
			grid.appendChild( card );
		} );
	}

	function renderSelection() {
		clear( selection );
		if ( ! state.selected ) {
			return;
		}
		if ( state.selected.url ) {
			selection.appendChild( image( state.selected ) );
		}
		selection.appendChild( el( 'strong', '', `${ state.selected.title || '(Sin título)' } · #${ state.selected.id }` ) );
		selection.appendChild( el( 'span', '', `ALT actual: ${ state.selected.alt || '(vacío)' }` ) );
		selection.appendChild( el( 'code', '', String( state.selected.fingerprint || '' ) ) );
	}

	function selectMedia( media ) {
		if ( locked() ) {
			return;
		}
		state.selected = media;
		input.value = String( media.alt || '' );
		editor.hidden = false;
		resetPreview();
		renderSelection();
		renderGrid();
	}

	async function load() {
		if ( state.operation ) {
			locked();
			return;
		}
		setStatus( 'Cargando biblioteca acotada de imágenes…' );
		try {
			const result = await window.wp.apiFetch( { path: mediaPath() } );
			state.media = array( result.items );
			if ( state.selected ) {
				state.selected = state.media.find( ( media ) => Number( media.id ) === Number( state.selected.id ) ) || null;
			}
			renderGrid();
			if ( state.selected ) {
				input.value = String( state.selected.alt || '' );
				renderSelection();
			}
			setStatus( `${ state.media.length } imagen(es) disponibles para revisar ALT.`, 'success' );
		} catch ( error ) {
			setStatus( error?.message || 'No se pudo cargar la biblioteca de imágenes.', 'error' );
		}
	}

	function payload() {
		return {
			schema_version: 1,
			media_id: Number( state.selected?.id || 0 ),
			alt: String( input.value || '' ),
			expected_fingerprint: String( state.selected?.fingerprint || '' ),
			allow_public_media: Boolean( approve.checked ),
		};
	}

	function renderPreview( result ) {
		clear( previewBox );
		previewBox.appendChild( el( 'strong', '', 'Vista previa de ALT' ) );
		previewBox.appendChild( el( 'pre', '', JSON.stringify( {
			media_id: Number( state.selected?.id || 0 ),
			before_alt: result.before_alt ?? state.selected?.alt ?? '',
			after_alt: result.after_alt ?? input.value,
		}, null, 2 ) ) );
	}

	async function preview() {
		if ( state.operation || ! state.selected ) {
			return;
		}
		previewBtn.disabled = true;
		setStatus( 'Generando vista previa de ALT…' );
		try {
			const result = await window.wp.apiFetch( {
				path: `${ mediaPath() }/${ Number( state.selected.id ) }/alt/changes/preview`,
				method: 'POST',
				data: payload(),
			} );
			state.preview = object( result );
			state.idempotencyKey = idempotencyKey();
			renderPreview( state.preview );
			applyBtn.disabled = ! approve.checked || true !== result.has_changes;
			setStatus( 'Vista previa correcta. Solo cambiará el texto ALT de la imagen seleccionada.', 'success' );
		} catch ( error ) {
			state.preview = null;
			state.idempotencyKey = null;
			applyBtn.disabled = true;
			setStatus( error?.message || 'No se pudo generar la vista previa de ALT.', 'error' );
		} finally {
			previewBtn.disabled = false;
		}
	}

	async function apply() {
		if ( state.operation || ! state.preview || ! approve.checked || ! state.selected ) {
			return;
		}
		applyBtn.disabled = true;
		const data = payload();
		data.idempotency_key = state.idempotencyKey || idempotencyKey();
		state.idempotencyKey = data.idempotency_key;
		const environment = object( state.preview.environment );
		if ( environment.fingerprint ) {
			data.environment_fingerprint = environment.fingerprint;
		}
		setStatus( 'Aplicando ALT y verificando readback…' );
		try {
			const result = await window.wp.apiFetch( {
				path: `${ mediaPath() }/${ Number( state.selected.id ) }/alt/changes/apply`,
				method: 'POST',
				data,
			} );
			const operationId = String( result.operation_id || '' );
			if ( ! operationId ) {
				throw new Error( 'Apply no devolvió operation_id.' );
			}

			state.operation = {
				id: operationId,
				environment: object( result.environment ),
				before_fingerprint: String( result.before_fingerprint || '' ),
				before_alt: String( result.before_alt || '' ),
				before_alt_exists: true === result.before_alt_exists,
			};
			previewBtn.disabled = true;
			refreshBtn.disabled = true;
			rollbackBtn.hidden = false;
			renderGrid();

			const verified = await window.wp.apiFetch( {
				path: `${ mediaPath() }/alt/changes/${ encodeURIComponent( operationId ) }/verify`,
			} );
			if ( true !== verified.verified ) {
				throw new Error( 'Verify no confirmó el ALT aplicado.' );
			}

			state.selected = {
				...state.selected,
				alt: String( verified.alt || '' ),
				fingerprint: String( verified.current_fingerprint || state.selected.fingerprint || '' ),
			};
			input.value = state.selected.alt;
			renderSelection();
			renderGrid();
			clear( previewBox );
			previewBox.appendChild( el( 'strong', '', `Aplicado y verificado · Operación ${ operationId }` ) );
			setStatus( `ALT aplicado y verificado. Operación ${ operationId }. El rollback seguro permanece disponible.`, 'success' );
		} catch ( error ) {
			if ( state.operation ) {
				previewBtn.disabled = true;
				refreshBtn.disabled = true;
				rollbackBtn.hidden = false;
				applyBtn.disabled = true;
				renderGrid();
				setStatus( `${ error?.message || 'Verify no pudo confirmar el cambio.' } La operación aplicada conserva rollback seguro.`, 'error' );
				return;
			}
			applyBtn.disabled = ! approve.checked || ! state.preview;
			setStatus( error?.message || 'El Manager rechazó el cambio de ALT.', 'error' );
		}
	}

	async function rollback() {
		if ( ! state.operation || ! state.selected ) {
			return;
		}
		if ( ! window.confirm( `Revertir de forma segura la operación ALT ${ state.operation.id }?` ) ) {
			return;
		}
		rollbackBtn.disabled = true;
		setStatus( 'Ejecutando rollback seguro de ALT…' );
		try {
			const environmentFingerprint = String( object( state.operation.environment ).fingerprint || '' );
			const result = await window.wp.apiFetch( {
				path: `${ mediaPath() }/alt/changes/${ encodeURIComponent( state.operation.id ) }/rollback`,
				method: 'POST',
				data: { environment_fingerprint: environmentFingerprint },
			} );
			if ( 'rolled-back' !== result.status ) {
				throw new Error( 'El rollback no devolvió estado rolled-back.' );
			}
			const readback = await window.wp.apiFetch( { path: `${ mediaPath() }/${ Number( state.selected.id ) }` } );
			const restored = object( readback.media || readback );
			if ( String( restored.fingerprint || '' ) !== String( result.before_fingerprint || '' ) || String( restored.alt || '' ) !== String( result.before_alt || '' ) ) {
				throw new Error( 'El readback posterior al rollback no coincide con el ALT original.' );
			}
			state.selected = restored;
			state.media = state.media.map( ( media ) => Number( media.id ) === Number( restored.id ) ? restored : media );
			state.operation = null;
			refreshBtn.disabled = false;
			rollbackBtn.hidden = true;
			input.value = String( restored.alt || '' );
			resetPreview();
			renderSelection();
			renderGrid();
			setStatus( 'Rollback verificado: se restauró exactamente el ALT original.', 'success' );
		} catch ( error ) {
			setStatus( error?.message || 'El rollback de ALT fue rechazado.', 'error' );
		} finally {
			rollbackBtn.disabled = false;
		}
	}

	search.addEventListener( 'input', renderGrid );
	input.addEventListener( 'input', () => {
		if ( ! state.operation ) {
			resetPreview();
		}
	} );
	approve.addEventListener( 'change', () => {
		applyBtn.disabled = ! state.preview || ! approve.checked || true !== state.preview.has_changes;
	} );
	previewBtn.addEventListener( 'click', preview );
	applyBtn.addEventListener( 'click', apply );
	rollbackBtn.addEventListener( 'click', rollback );
	refreshBtn.addEventListener( 'click', () => {
		if ( ! locked() ) {
			load();
		}
	} );
	load();
}() );
