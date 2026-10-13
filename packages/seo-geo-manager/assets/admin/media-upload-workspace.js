( function () {
	'use strict';

	const root = document.getElementById( 'seo-geo-manager-dashboard' );
	const anchor = root ? root.querySelector( '[data-seo-geo-theme-media-workspace]' ) : null;
	const api = window.SeoGeoManagerMediaUploadWorkspace || {};
	if ( ! root || ! anchor || ! window.wp || ! window.wp.apiFetch ) {
		return;
	}

	const object = ( value ) => value && typeof value === 'object' && ! Array.isArray( value ) ? value : {};
	const state = { file: null, preview: null, idempotencyKey: null, created: null };

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
			return `manager-media-upload-${ window.crypto.randomUUID() }`;
		}
		return `manager-media-upload-${ Date.now() }-${ Math.random().toString( 16 ).slice( 2 ) }`;
	}

	function formatBytes( value ) {
		const bytes = Number( value || 0 );
		if ( bytes < 1024 ) return `${ bytes } B`;
		if ( bytes < 1048576 ) return `${ ( bytes / 1024 ).toFixed( 1 ) } KB`;
		return `${ ( bytes / 1048576 ).toFixed( 2 ) } MB`;
	}

	const section = el( 'section', 'seo-geo-manager-admin__panel seo-geo-manager-media-upload-workspace' );
	section.dataset.seoGeoMediaUploadWorkspace = '';
	section.innerHTML = `
		<div class="seo-geo-manager-admin__panel-heading">
			<div>
				<p class="seo-geo-manager-admin__eyebrow">Build / Finish · Media Library</p>
				<h2>Subir imagen nueva</h2>
				<p class="description">Carga directamente una imagen revisada a WordPress. Preview calcula identidad, MIME, dimensiones y límites antes de crear el attachment.</p>
			</div>
			<button type="button" class="button" data-media-upload-reset>Nueva subida</button>
		</div>
		<div class="notice notice-info inline seo-geo-manager-admin__notice" data-media-upload-status role="status" aria-live="polite"><p>Selecciona una imagen para preparar la subida.</p></div>
		<div class="seo-geo-manager-media-upload-layout">
			<div class="seo-geo-manager-media-upload-form">
				<label>Archivo de imagen<input type="file" accept="image/*" data-media-upload-file></label>
				<div class="seo-geo-manager-media-upload-local-preview" data-media-upload-local-preview hidden></div>
				<label>Título<input type="text" maxlength="500" data-media-upload-title placeholder="Nombre descriptivo de la imagen"></label>
				<label>ALT<input type="text" maxlength="1000" data-media-upload-alt placeholder="Describe la imagen para accesibilidad y SEO"></label>
				<label>Caption<textarea rows="3" maxlength="4000" data-media-upload-caption></textarea></label>
				<label>Descripción<textarea rows="4" maxlength="12000" data-media-upload-description></textarea></label>
				<label>Adjuntar a recurso ID <input type="number" min="0" step="1" data-media-upload-parent value="0"></label>
				<label class="seo-geo-manager-media-upload-approval"><input type="checkbox" data-media-upload-approve> Confirmo que esta imagen y sus metadatos son los correctos y autorizo crear el attachment público.</label>
				<div class="seo-geo-manager-build__actions">
					<button type="button" class="button button-secondary" data-media-upload-preview>Vista previa</button>
					<button type="button" class="button button-primary" data-media-upload-apply disabled>Subir imagen</button>
				</div>
			</div>
			<div class="seo-geo-manager-media-upload-preview" data-media-upload-preview-box>
				<p class="description">Preview no persiste el archivo. Apply reenviará los mismos bytes y comprobará su SHA-256.</p>
			</div>
		</div>`;
	anchor.insertAdjacentElement( 'afterend', section );

	const status = section.querySelector( '[data-media-upload-status]' );
	const fileInput = section.querySelector( '[data-media-upload-file]' );
	const titleInput = section.querySelector( '[data-media-upload-title]' );
	const altInput = section.querySelector( '[data-media-upload-alt]' );
	const captionInput = section.querySelector( '[data-media-upload-caption]' );
	const descriptionInput = section.querySelector( '[data-media-upload-description]' );
	const parentInput = section.querySelector( '[data-media-upload-parent]' );
	const approve = section.querySelector( '[data-media-upload-approve]' );
	const localPreview = section.querySelector( '[data-media-upload-local-preview]' );
	const previewBox = section.querySelector( '[data-media-upload-preview-box]' );
	const previewButton = section.querySelector( '[data-media-upload-preview]' );
	const applyButton = section.querySelector( '[data-media-upload-apply]' );
	const resetButton = section.querySelector( '[data-media-upload-reset]' );

	function setStatus( message, type = 'info' ) {
		status.className = `notice inline seo-geo-manager-admin__notice notice-${ type }`;
		clear( status );
		status.appendChild( el( 'p', '', message ) );
	}

	function metadata() {
		return {
			schema_version: '1',
			title: String( titleInput.value || '' ).trim(),
			alt: String( altInput.value || '' ).trim(),
			caption: String( captionInput.value || '' ),
			description: String( descriptionInput.value || '' ),
			parent_id: String( Math.max( 0, Number( parentInput.value || 0 ) ) ),
		};
	}

	function formData( extra = {} ) {
		const form = new FormData();
		const values = { ...metadata(), ...extra };
		Object.entries( values ).forEach( ( [ key, value ] ) => form.append( key, String( value ?? '' ) ) );
		if ( state.file ) form.append( 'file', state.file, state.file.name );
		return form;
	}

	function resetPreview() {
		state.preview = null;
		state.idempotencyKey = null;
		applyButton.disabled = true;
		approve.checked = false;
		clear( previewBox );
		previewBox.appendChild( el( 'p', 'description', 'Preview no persiste el archivo. Apply reenviará los mismos bytes y comprobará su SHA-256.' ) );
	}

	function renderLocalFile() {
		clear( localPreview );
		if ( ! state.file ) {
			localPreview.hidden = true;
			return;
		}
		localPreview.hidden = false;
		const img = document.createElement( 'img' );
		img.src = URL.createObjectURL( state.file );
		img.alt = '';
		img.addEventListener( 'load', () => URL.revokeObjectURL( img.src ), { once: true } );
		localPreview.appendChild( img );
		const copy = el( 'div' );
		copy.appendChild( el( 'strong', '', state.file.name ) );
		copy.appendChild( el( 'span', '', `${ state.file.type || 'tipo por validar' } · ${ formatBytes( state.file.size ) }` ) );
		localPreview.appendChild( copy );
	}

	function renderPreview( result ) {
		clear( previewBox );
		const resource = object( result.resource );
		const file = object( resource.file );
		const dimensions = object( file.dimensions );
		previewBox.appendChild( el( 'p', 'seo-geo-manager-admin__eyebrow', 'Vista previa validada' ) );
		previewBox.appendChild( el( 'h3', '', resource.title || metadata().title ) );
		const facts = el( 'dl', 'seo-geo-manager-media-upload-preview__facts' );
		[
			[ 'Archivo', file.name || state.file?.name || '—' ],
			[ 'MIME', file.mime_type || '—' ],
			[ 'Peso', formatBytes( file.bytes ) ],
			[ 'Dimensiones', `${ Number( dimensions.width || 0 ) } × ${ Number( dimensions.height || 0 ) }` ],
			[ 'SHA-256', file.sha256 || '—' ],
			[ 'ALT', null === resource.alt ? '(no proporcionado)' : resource.alt ],
		].forEach( ( [ label, value ] ) => {
			facts.appendChild( el( 'dt', '', label ) );
			facts.appendChild( el( 'dd', '', value ) );
		} );
		previewBox.appendChild( facts );
		const policy = object( result.policy );
		previewBox.appendChild( el( 'p', 'description', `Límite efectivo: ${ formatBytes( policy.max_upload_bytes ) }. No se descargan URLs remotas ni se reemplazan archivos existentes.` ) );
	}

	async function preview() {
		if ( ! state.file ) {
			setStatus( 'Selecciona un archivo de imagen.', 'warning' );
			fileInput.focus();
			return;
		}
		if ( ! metadata().title ) {
			setStatus( 'El título de la imagen es obligatorio.', 'warning' );
			titleInput.focus();
			return;
		}
		previewButton.disabled = true;
		setStatus( 'Validando archivo y calculando identidad…' );
		try {
			const result = await window.wp.apiFetch( {
				path: api.previewPath || '/seo-geo-manager/v1/media/uploads/preview',
				method: 'POST',
				body: formData(),
			} );
			state.preview = object( result );
			state.idempotencyKey = idempotencyKey();
			renderPreview( result );
			applyButton.disabled = ! approve.checked || true !== result.will_create;
			setStatus( 'Archivo validado. Confirma la creación del attachment para continuar.', 'success' );
		} catch ( error ) {
			resetPreview();
			setStatus( error?.message || 'La imagen no superó la vista previa.', 'error' );
		} finally {
			previewButton.disabled = false;
		}
	}

	function createdActions( attachmentId ) {
		const actions = el( 'div', 'seo-geo-manager-build__actions' );
		const themeButton = el( 'button', 'button button-primary', 'Usar en Theme Media' );
		themeButton.type = 'button';
		themeButton.addEventListener( 'click', () => {
			const themeWorkspace = root.querySelector( '[data-seo-geo-theme-media-workspace]' );
			const refresh = themeWorkspace?.querySelector( '[data-theme-media-refresh]' );
			if ( refresh && ! refresh.disabled ) refresh.click();
			if ( themeWorkspace ) themeWorkspace.scrollIntoView( { behavior: 'smooth', block: 'start' } );
			window.setTimeout( () => {
				const search = themeWorkspace?.querySelector( '[data-theme-media-search]' );
				if ( search ) {
					search.value = String( attachmentId );
					search.dispatchEvent( new Event( 'input', { bubbles: true } ) );
				}
			}, 250 );
		} );
		actions.appendChild( themeButton );

		const altButton = el( 'button', 'button', 'Revisar ALT' );
		altButton.type = 'button';
		altButton.addEventListener( 'click', () => {
			const altWorkspace = root.querySelector( '[data-seo-geo-media-alt-workspace]' );
			const search = altWorkspace?.querySelector( '[data-media-alt-search]' );
			if ( search ) {
				search.value = String( attachmentId );
				search.dispatchEvent( new Event( 'input', { bubbles: true } ) );
			}
			if ( altWorkspace ) altWorkspace.scrollIntoView( { behavior: 'smooth', block: 'start' } );
		} );
		actions.appendChild( altButton );
		return actions;
	}

	async function apply() {
		if ( ! state.preview || ! state.file || ! approve.checked ) return;
		const resource = object( state.preview.resource );
		const previewFile = object( resource.file );
		const environment = object( state.preview.environment );
		const key = state.idempotencyKey || idempotencyKey();
		state.idempotencyKey = key;
		applyButton.disabled = true;
		previewButton.disabled = true;
		setStatus( 'Subiendo los bytes validados y verificando el attachment…' );
		try {
			const result = await window.wp.apiFetch( {
				path: api.applyPath || '/seo-geo-manager/v1/media/uploads/apply',
				method: 'POST',
				body: formData( {
					idempotency_key: key,
					expected_file_sha256: String( previewFile.sha256 || '' ),
					allow_public_media: '1',
					environment_fingerprint: String( environment.fingerprint || '' ),
				} ),
			} );
			state.created = object( result );
			const attachmentId = Number( result.target_id || 0 );
			clear( previewBox );
			previewBox.appendChild( el( 'strong', '', `Imagen creada · attachment #${ attachmentId || '—' }` ) );
			const createdFile = object( result.file );
			if ( createdFile.url ) {
				const img = document.createElement( 'img' );
				img.className = 'seo-geo-manager-media-upload-created-image';
				img.src = createdFile.url;
				img.alt = altInput.value || titleInput.value || '';
				previewBox.appendChild( img );
			}
			previewBox.appendChild( el( 'code', '', result.operation_id ? `Operación ${ result.operation_id }` : 'Operación registrada' ) );
			if ( attachmentId ) previewBox.appendChild( createdActions( attachmentId ) );
			setStatus( `Imagen subida y verificada${ attachmentId ? ` · #${ attachmentId }` : '' }. Ya puede asignarse al Theme.`, 'success' );
		} catch ( error ) {
			applyButton.disabled = false;
			setStatus( error?.message || 'El Manager rechazó la subida de la imagen.', 'error' );
		} finally {
			previewButton.disabled = false;
		}
	}

	function reset() {
		state.file = null;
		state.created = null;
		fileInput.value = '';
		titleInput.value = '';
		altInput.value = '';
		captionInput.value = '';
		descriptionInput.value = '';
		parentInput.value = '0';
		resetPreview();
		renderLocalFile();
		setStatus( 'Selecciona una imagen para preparar la subida.' );
	}

	fileInput.addEventListener( 'change', () => {
		state.file = fileInput.files && fileInput.files[ 0 ] ? fileInput.files[ 0 ] : null;
		if ( state.file && ! titleInput.value.trim() ) {
			titleInput.value = state.file.name.replace( /\.[^.]+$/, '' ).replace( /[-_]+/g, ' ' ).trim();
		}
		resetPreview();
		renderLocalFile();
	} );
	[ titleInput, altInput, captionInput, descriptionInput, parentInput ].forEach( ( input ) => input.addEventListener( 'input', resetPreview ) );
	approve.addEventListener( 'change', () => { applyButton.disabled = ! state.preview || ! approve.checked || true !== state.preview.will_create; } );
	previewButton.addEventListener( 'click', preview );
	applyButton.addEventListener( 'click', apply );
	resetButton.addEventListener( 'click', reset );
}() );
