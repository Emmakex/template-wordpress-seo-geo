( function () {
	'use strict';

	const root = document.getElementById( 'seo-geo-manager-dashboard' );
	const api = window.SeoGeoManagerGrowWorkspace || {};
	if ( ! root || ! window.wp || ! window.wp.apiFetch ) {
		return;
	}

	const state = { preview: null, idempotencyKey: null, created: null };

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
			return `manager-grow-${ window.crypto.randomUUID() }`;
		}
		return `manager-grow-${ Date.now() }-${ Math.random().toString( 16 ).slice( 2 ) }`;
	}

	const section = el( 'section', 'seo-geo-manager-admin__panel seo-geo-manager-grow-workspace' );
	section.dataset.seoGeoGrowWorkspace = '';
	section.innerHTML = `
		<div class="seo-geo-manager-admin__panel-heading">
			<div>
				<p class="seo-geo-manager-admin__eyebrow">Grow · EMMAKE</p>
				<h2>Crear contenido nuevo</h2>
				<p class="description">Crea una página o entrada como borrador mediante Preview y Apply controlados. Después continúa en Content, SEO, Links y Media antes de publicar.</p>
			</div>
			<button type="button" class="button" data-grow-reset>Nuevo borrador</button>
		</div>
		<div class="notice notice-info inline seo-geo-manager-admin__notice" data-grow-status role="status" aria-live="polite"><p>Completa los campos y genera una vista previa antes de crear.</p></div>
		<div class="seo-geo-manager-grow-grid">
			<div class="seo-geo-manager-grow-form">
				<label>Tipo
					<select data-grow-field="type"><option value="page">Página</option><option value="post">Entrada</option></select>
				</label>
				<label>Título<input type="text" maxlength="200" data-grow-field="title" placeholder="Título del nuevo contenido"></label>
				<label>Slug<input type="text" maxlength="200" data-grow-field="slug" placeholder="slug-opcional"></label>
				<label>Extracto<textarea rows="4" data-grow-field="excerpt" placeholder="Resumen breve"></textarea></label>
				<label>Contenido<textarea rows="14" data-grow-field="content" placeholder="Contenido inicial del borrador"></textarea></label>
				<div class="seo-geo-manager-grow-policy">
					<strong>Política de creación</strong>
					<ul>
						<li>Siempre se crea primero como borrador.</li>
						<li>No se publica ni se programa desde este bloque.</li>
						<li>Slug duplicado o permisos insuficientes bloquean Apply.</li>
					</ul>
				</div>
				<div class="seo-geo-manager-build__actions">
					<button type="button" class="button button-secondary" data-grow-preview>Vista previa</button>
					<button type="button" class="button button-primary" data-grow-apply disabled>Crear borrador</button>
				</div>
			</div>
			<div class="seo-geo-manager-grow-preview" data-grow-preview-box>
				<p class="description">La vista previa mostrará exactamente qué recurso se creará.</p>
			</div>
		</div>`;

	const optimization = root.querySelector( '[data-seo-geo-optimization-queue]' );
	if ( optimization ) optimization.insertAdjacentElement( 'afterend', section );
	else root.appendChild( section );

	const status = section.querySelector( '[data-grow-status]' );
	const previewBox = section.querySelector( '[data-grow-preview-box]' );
	const previewButton = section.querySelector( '[data-grow-preview]' );
	const applyButton = section.querySelector( '[data-grow-apply]' );
	const resetButton = section.querySelector( '[data-grow-reset]' );

	function field( key ) {
		return section.querySelector( `[data-grow-field="${ key }"]` );
	}

	function setStatus( message, type = 'info' ) {
		status.className = `notice inline seo-geo-manager-admin__notice notice-${ type }`;
		clear( status );
		status.appendChild( el( 'p', '', message ) );
	}

	function payload() {
		return {
			schema_version: 1,
			type: String( field( 'type' ).value || 'page' ),
			title: String( field( 'title' ).value || '' ).trim(),
			slug: String( field( 'slug' ).value || '' ).trim(),
			excerpt: String( field( 'excerpt' ).value || '' ),
			content: String( field( 'content' ).value || '' ),
			status: 'draft',
		};
	}

	function resetPreview() {
		state.preview = null;
		state.idempotencyKey = null;
		applyButton.disabled = true;
		clear( previewBox );
		previewBox.appendChild( el( 'p', 'description', 'La vista previa mostrará exactamente qué recurso se creará.' ) );
	}

	function resetForm() {
		state.created = null;
		[ 'title', 'slug', 'excerpt', 'content' ].forEach( ( key ) => { field( key ).value = ''; } );
		field( 'type' ).value = 'page';
		resetPreview();
		setStatus( 'Completa los campos y genera una vista previa antes de crear.' );
		field( 'title' ).focus();
	}

	function renderPreview( result ) {
		clear( previewBox );
		previewBox.appendChild( el( 'p', 'seo-geo-manager-admin__eyebrow', 'Vista previa de creación' ) );
		previewBox.appendChild( el( 'h3', '', result.resource?.title || payload().title ) );
		const facts = el( 'dl', 'seo-geo-manager-grow-preview__facts' );
		[ [ 'Tipo', result.resource?.type || payload().type ], [ 'Estado', result.resource?.status || 'draft' ], [ 'Slug', result.resource?.slug || payload().slug || '(WordPress lo generará)' ] ].forEach( ( pair ) => {
			facts.appendChild( el( 'dt', '', pair[ 0 ] ) );
			facts.appendChild( el( 'dd', '', pair[ 1 ] ) );
		} );
		previewBox.appendChild( facts );
		previewBox.appendChild( el( 'p', 'description', 'Apply crea únicamente el borrador. La optimización y publicación continúan en los workspaces existentes.' ) );
	}

	async function preview() {
		const data = payload();
		if ( ! data.title ) {
			setStatus( 'El título es obligatorio.', 'warning' );
			field( 'title' ).focus();
			return;
		}
		previewButton.disabled = true;
		setStatus( 'Preparando vista previa del nuevo borrador…' );
		try {
			const result = await window.wp.apiFetch( {
				path: api.previewPath || '/seo-geo-manager/v1/content/resources/preview',
				method: 'POST',
				data,
			} );
			state.preview = result;
			state.idempotencyKey = idempotencyKey();
			renderPreview( result );
			applyButton.disabled = true !== result.will_create;
			setStatus( 'Vista previa correcta. Revisa el recurso antes de crear el borrador.', 'success' );
		} catch ( error ) {
			resetPreview();
			setStatus( error?.message || 'La vista previa de creación fue rechazada.', 'error' );
		} finally {
			previewButton.disabled = false;
		}
	}

	async function handoff( targetId ) {
		if ( Number( targetId || 0 ) <= 0 ) return;
		try {
			const resource = await window.wp.apiFetch( { path: `${ api.contentPath || '/seo-geo-manager/v1/content' }/${ Number( targetId ) }` } );
			root.dispatchEvent( new CustomEvent( 'seo-geo-manager:content-selected', { detail: { resource } } ) );
			const workspace = root.querySelector( '[data-seo-geo-content-workspace]' );
			if ( workspace ) workspace.scrollIntoView( { behavior: 'smooth', block: 'start' } );
		} catch ( error ) {
			setStatus( `${ error?.message || 'El borrador se creó, pero no se pudo abrir en Content Workspace.' } ID: ${ targetId }.`, 'warning' );
		}
	}

	async function apply() {
		if ( ! state.preview ) return;
		applyButton.disabled = true;
		previewButton.disabled = true;
		setStatus( 'Creando borrador y verificando el recurso…' );
		const data = {
			...payload(),
			idempotency_key: state.idempotencyKey || idempotencyKey(),
			environment_fingerprint: String( state.preview.environment?.fingerprint || '' ),
		};
		state.idempotencyKey = data.idempotency_key;
		try {
			const result = await window.wp.apiFetch( {
				path: api.applyPath || '/seo-geo-manager/v1/content/resources/apply',
				method: 'POST',
				data,
			} );
			state.created = result;
			const targetId = Number( result.target_id || 0 );
			clear( previewBox );
			previewBox.appendChild( el( 'strong', '', `Borrador creado · #${ targetId || '—' }` ) );
			previewBox.appendChild( el( 'p', 'description', result.permalink || 'Recurso creado sin permalink público.' ) );
			if ( result.operation_id ) previewBox.appendChild( el( 'code', '', `Operación ${ result.operation_id }` ) );
			setStatus( `Borrador creado y verificado${ targetId ? ` · #${ targetId }` : '' }. Continuamos con Content/SEO/Links/Media.`, 'success' );
			if ( targetId ) await handoff( targetId );
		} catch ( error ) {
			setStatus( error?.message || 'El Manager rechazó la creación del borrador.', 'error' );
			applyButton.disabled = false;
		} finally {
			previewButton.disabled = false;
		}
	}

	section.querySelectorAll( '[data-grow-field]' ).forEach( ( input ) => {
		input.addEventListener( 'input', resetPreview );
		input.addEventListener( 'change', resetPreview );
	} );
	previewButton.addEventListener( 'click', preview );
	applyButton.addEventListener( 'click', apply );
	resetButton.addEventListener( 'click', resetForm );
}() );
