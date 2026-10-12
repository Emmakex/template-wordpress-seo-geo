( function () {
	'use strict';

	const root = document.getElementById( 'seo-geo-manager-dashboard' );
	const contentWorkspace = root ? root.querySelector( '[data-seo-geo-content-workspace]' ) : null;
	const api = window.SeoGeoManagerSeoAuthorityWorkspace || {};
	if ( ! root || ! contentWorkspace || ! window.wp || ! window.wp.apiFetch ) {
		return;
	}

	const object = ( value ) => value && typeof value === 'object' && ! Array.isArray( value ) ? value : {};
	const array = ( value ) => Array.isArray( value ) ? value : [];
	const state = { resource: null, inspection: null, preview: null, operation: null };

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
			return `manager-seo-${ window.crypto.randomUUID() }`;
		}
		return `manager-seo-${ Date.now() }-${ Math.random().toString( 16 ).slice( 2 ) }`;
	}

	const section = el( 'section', 'seo-geo-manager-admin__panel seo-geo-manager-seo-workspace' );
	section.dataset.seoGeoSeoAuthorityWorkspace = '';
	section.innerHTML = `
		<div class="seo-geo-manager-admin__panel-heading">
			<div>
				<p class="seo-geo-manager-admin__eyebrow">Build / Finish · SEO/GEO</p>
				<h2>Autoridad SEO</h2>
				<p class="description">El Manager resuelve automáticamente la única autoridad SEO escribible. No se selecciona proveedor manualmente.</p>
			</div>
			<button type="button" class="button" data-seo-refresh disabled>Recargar SEO</button>
		</div>
		<div class="notice notice-info inline seo-geo-manager-admin__notice" data-seo-status role="status" aria-live="polite"><p>Selecciona una página o entrada en el bloque anterior.</p></div>
		<div class="seo-geo-manager-seo-authority" data-seo-authority hidden></div>
		<div class="seo-geo-manager-seo-editor" data-seo-editor hidden>
			<div data-seo-fields></div>
			<label class="seo-geo-manager-seo-published" data-seo-published hidden><input type="checkbox" data-seo-allow-published> Permitir cambios SEO sobre el recurso publicado.</label>
			<div class="seo-geo-manager-build__actions">
				<button type="button" class="button button-secondary" data-seo-preview>Vista previa SEO</button>
				<button type="button" class="button button-primary" data-seo-apply disabled>Aplicar y verificar</button>
				<button type="button" class="button" data-seo-rollback hidden>Rollback seguro</button>
			</div>
			<div class="seo-geo-manager-build__preview" data-seo-preview-box><p class="description">Haz una vista previa antes de aplicar cambios.</p></div>
		</div>`;
	contentWorkspace.insertAdjacentElement( 'afterend', section );

	const statusBox = section.querySelector( '[data-seo-status]' );
	const authorityBox = section.querySelector( '[data-seo-authority]' );
	const editor = section.querySelector( '[data-seo-editor]' );
	const fieldsBox = section.querySelector( '[data-seo-fields]' );
	const refreshButton = section.querySelector( '[data-seo-refresh]' );
	const previewButton = section.querySelector( '[data-seo-preview]' );
	const applyButton = section.querySelector( '[data-seo-apply]' );
	const rollbackButton = section.querySelector( '[data-seo-rollback]' );
	const previewBox = section.querySelector( '[data-seo-preview-box]' );
	const publishedWrap = section.querySelector( '[data-seo-published]' );
	const allowPublished = section.querySelector( '[data-seo-allow-published]' );

	function setStatus( message, type = 'info' ) {
		statusBox.className = `notice inline seo-geo-manager-admin__notice notice-${ type }`;
		clear( statusBox );
		statusBox.appendChild( el( 'p', '', message ) );
	}

	function resetPreview() {
		state.preview = null;
		applyButton.disabled = true;
		rollbackButton.hidden = true;
		state.operation = null;
		clear( previewBox );
		previewBox.appendChild( el( 'p', 'description', 'Haz una vista previa antes de aplicar cambios.' ) );
	}

	function provider() {
		return String( object( state.inspection?.seo_authority ).provider || '' );
	}

	function authorityPath( resourceId ) {
		return `${ api.contentPath || '/seo-geo-manager/v1/content' }/${ Number( resourceId ) }/seo/authority`;
	}

	function addTextField( key, label, value, type = 'text', rows = 0 ) {
		const wrapper = el( 'label', 'seo-geo-manager-seo-field' );
		wrapper.appendChild( el( 'span', '', label ) );
		const input = rows > 0 ? el( 'textarea' ) : el( 'input' );
		if ( rows > 0 ) input.rows = rows;
		if ( rows <= 0 ) input.type = type;
		input.value = null === value || undefined === value ? '' : String( value );
		input.dataset.seoField = key;
		input.addEventListener( 'input', resetPreview );
		wrapper.appendChild( input );
		fieldsBox.appendChild( wrapper );
	}

	function addSelectField( key, label, value, options ) {
		const wrapper = el( 'label', 'seo-geo-manager-seo-field' );
		wrapper.appendChild( el( 'span', '', label ) );
		const select = el( 'select' );
		select.dataset.seoField = key;
		options.forEach( ( option ) => {
			const node = el( 'option', '', option.label );
			node.value = option.value;
			select.appendChild( node );
		} );
		select.value = null === value || undefined === value ? '' : String( value );
		select.addEventListener( 'change', resetPreview );
		wrapper.appendChild( select );
		fieldsBox.appendChild( wrapper );
	}

	function addCheckboxField( key, label, value ) {
		const wrapper = el( 'label', 'seo-geo-manager-seo-check' );
		const input = el( 'input' );
		input.type = 'checkbox';
		input.checked = true === value;
		input.dataset.seoField = key;
		input.addEventListener( 'change', resetPreview );
		wrapper.appendChild( input );
		wrapper.appendChild( el( 'span', '', label ) );
		fieldsBox.appendChild( wrapper );
	}

	function renderAuthority() {
		clear( authorityBox );
		const authority = object( state.inspection?.seo_authority );
		const resource = object( state.inspection?.resource );
		const writable = array( authority.writable_signals );
		authorityBox.hidden = false;

		const heading = el( 'div', 'seo-geo-manager-seo-authority__heading' );
		heading.appendChild( el( 'strong', '', resource.title || state.resource?.title || 'Recurso SEO' ) );
		heading.appendChild( el( 'span', 'seo-geo-manager-admin__badge', authority.provider || authority.authority_id || 'autoridad resuelta' ) );
		authorityBox.appendChild( heading );
		authorityBox.appendChild( el( 'p', 'description', `Autoridad: ${ authority.authority_id || '—' } · Adapter: ${ authority.adapter || '—' }` ) );
		authorityBox.appendChild( el( 'p', 'description', `Señales escribibles: ${ writable.length ? writable.join( ', ' ) : 'ninguna' }` ) );
	}

	function renderFields() {
		clear( fieldsBox );
		const values = object( state.inspection?.values );
		if ( 'theme-native' === provider() ) {
			addTextField( 'title', 'Título SEO', values.title );
			addTextField( 'description', 'Meta description', values.description, 'text', 4 );
			addTextField( 'canonical', 'Canonical', values.canonical, 'url' );
			addSelectField(
				'indexability',
				'Indexabilidad',
				values.indexability,
				[
					{ value: '', label: 'Heredar / automático' },
					{ value: 'indexable', label: 'Indexable' },
					{ value: 'noindex-follow', label: 'Noindex, follow' },
					{ value: 'noindex-nofollow', label: 'Noindex, nofollow' },
				]
			);
			return;
		}

		if ( 'yoast' === provider() ) {
			addTextField( 'canonical', 'Canonical', values.canonical, 'url' );
			addSelectField(
				'noindex',
				'Indexación',
				null === values.noindex || undefined === values.noindex ? '' : ( true === values.noindex ? 'true' : 'false' ),
				[
					{ value: '', label: 'Heredar de Yoast' },
					{ value: 'false', label: 'Permitir indexación' },
					{ value: 'true', label: 'Noindex' },
				]
			);
			addCheckboxField( 'nofollow', 'Nofollow', true === values.nofollow );
			addTextField( 'schema_page_type', 'Schema page type', values.schema_page_type );
			addTextField( 'schema_article_type', 'Schema article type', values.schema_article_type );
			return;
		}

		throw new Error( 'La autoridad SEO resuelta no tiene una interfaz aceptada.' );
	}

	function desiredValues() {
		if ( 'theme-native' === provider() ) {
			const values = {};
			[ 'title', 'description', 'canonical', 'indexability' ].forEach( ( key ) => {
				const value = section.querySelector( `[data-seo-field="${ key }"]` )?.value ?? '';
				values[ key ] = '' === value ? null : value;
			} );
			return values;
		}

		if ( 'yoast' === provider() ) {
			const noindex = section.querySelector( '[data-seo-field="noindex"]' )?.value ?? '';
			return {
				canonical: ( section.querySelector( '[data-seo-field="canonical"]' )?.value || '' ) || null,
				noindex: '' === noindex ? null : 'true' === noindex,
				nofollow: Boolean( section.querySelector( '[data-seo-field="nofollow"]' )?.checked ),
				schema_page_type: ( section.querySelector( '[data-seo-field="schema_page_type"]' )?.value || '' ) || null,
				schema_article_type: ( section.querySelector( '[data-seo-field="schema_article_type"]' )?.value || '' ) || null,
			};
		}

		return {};
	}

	function payload() {
		return {
			values: desiredValues(),
			expected_fingerprint: String( state.inspection?.fingerprint || '' ),
			allow_published_target: Boolean( allowPublished.checked ),
		};
	}

	function renderPreview( result ) {
		clear( previewBox );
		const change = object( result.change );
		previewBox.appendChild( el( 'strong', '', 'Vista previa SEO' ) );
		previewBox.appendChild( el( 'pre', '', JSON.stringify( { before: change.before, after: change.after }, null, 2 ) ) );
	}

	async function inspect( resource ) {
		state.resource = object( resource );
		state.inspection = null;
		state.preview = null;
		state.operation = null;
		refreshButton.disabled = false;
		editor.hidden = true;
		authorityBox.hidden = true;
		setStatus( `Resolviendo autoridad SEO para #${ Number( state.resource.id || 0 ) }…` );
		try {
			const result = await window.wp.apiFetch( { path: authorityPath( state.resource.id ) } );
			state.inspection = object( result );
			renderAuthority();
			renderFields();
			publishedWrap.hidden = 'publish' !== String( state.resource.status || object( result.resource ).status || '' );
			allowPublished.checked = false;
			resetPreview();
			editor.hidden = false;
			setStatus( 'Autoridad SEO resuelta. Edita los campos y revisa Vista previa antes de aplicar.', 'success' );
		} catch ( error ) {
			clear( authorityBox );
			authorityBox.hidden = false;
			authorityBox.appendChild( el( 'p', 'description', 'El Manager ha bloqueado la edición porque no existe una única autoridad SEO escribible y aceptada.' ) );
			setStatus( error?.message || 'No se pudo resolver una autoridad SEO segura.', 'error' );
		}
	}

	async function preview() {
		if ( ! state.resource || ! state.inspection ) return;
		previewButton.disabled = true;
		setStatus( 'Generando vista previa SEO segura…' );
		try {
			const result = await window.wp.apiFetch( {
				path: `${ authorityPath( state.resource.id ) }/changes/preview`,
				method: 'POST',
				data: payload(),
			} );
			state.preview = object( result );
			renderPreview( result );
			applyButton.disabled = true !== result.has_changes;
			setStatus( 'Vista previa SEO correcta. Revisa el estado antes de aplicar.', 'success' );
		} catch ( error ) {
			state.preview = null;
			applyButton.disabled = true;
			setStatus( error?.message || 'La vista previa SEO ha sido rechazada.', 'error' );
		} finally {
			previewButton.disabled = false;
		}
	}

	async function apply() {
		if ( ! state.preview || ! state.resource ) return;
		applyButton.disabled = true;
		previewButton.disabled = true;
		setStatus( 'Aplicando SEO y verificando readback…' );
		try {
			const environment = object( state.preview.environment );
			const applyPayload = {
				...payload(),
				idempotency_key: idempotencyKey(),
				environment_fingerprint: String( environment.fingerprint || '' ),
			};
			const result = await window.wp.apiFetch( {
				path: `${ authorityPath( state.resource.id ) }/changes/apply`,
				method: 'POST',
				data: applyPayload,
			} );
			const operationId = String( result.operation_id || '' );
			if ( ! operationId ) throw new Error( 'Apply SEO no devolvió operation_id.' );
			const verified = await window.wp.apiFetch( {
				path: `${ api.contentPath || '/seo-geo-manager/v1/content' }/seo/authority/changes/${ encodeURIComponent( operationId ) }/verify`,
			} );
			if ( true !== verified.verified ) throw new Error( 'La verificación SEO no confirmó el estado aplicado.' );
			state.operation = { id: operationId, environment: object( result.environment ) };
			rollbackButton.hidden = false;
			clear( previewBox );
			previewBox.appendChild( el( 'strong', '', `Aplicado y verificado · Operación ${ operationId }` ) );
			previewBox.appendChild( el( 'p', 'description', `Fingerprint verificado: ${ verified.current_fingerprint || '—' }` ) );
			setStatus( `SEO aplicado y verificado. Operación ${ operationId }.`, 'success' );
			const refreshed = await window.wp.apiFetch( { path: authorityPath( state.resource.id ) } );
			state.inspection = object( refreshed );
			renderAuthority();
			renderFields();
		} catch ( error ) {
			setStatus( error?.message || 'El Manager rechazó la aplicación SEO.', 'error' );
		} finally {
			previewButton.disabled = false;
			applyButton.disabled = true;
		}
	}

	async function rollback() {
		if ( ! state.operation || ! state.resource ) return;
		if ( ! window.confirm( `Revertir de forma segura la operación SEO ${ state.operation.id }?` ) ) return;
		rollbackButton.disabled = true;
		setStatus( 'Ejecutando rollback SEO seguro…' );
		try {
			const environmentFingerprint = String( object( state.operation.environment ).fingerprint || '' );
			const result = await window.wp.apiFetch( {
				path: `${ api.contentPath || '/seo-geo-manager/v1/content' }/seo/authority/changes/${ encodeURIComponent( state.operation.id ) }/rollback`,
				method: 'POST',
				data: { environment_fingerprint: environmentFingerprint },
			} );
			if ( 'rolled-back' !== result.status ) throw new Error( 'El rollback SEO no confirmó estado rolled-back.' );
			setStatus( `Rollback SEO verificado · Operación ${ state.operation.id }.`, 'success' );
			state.operation = null;
			rollbackButton.hidden = true;
			await inspect( state.resource );
		} catch ( error ) {
			setStatus( error?.message || 'El rollback SEO fue rechazado.', 'error' );
		} finally {
			rollbackButton.disabled = false;
		}
	}

	root.addEventListener( 'seo-geo-manager:content-selected', ( event ) => {
		const resource = object( event.detail ).resource;
		if ( resource && resource.id ) inspect( resource );
	} );
	refreshButton.addEventListener( 'click', () => { if ( state.resource ) inspect( state.resource ); } );
	allowPublished.addEventListener( 'change', resetPreview );
	previewButton.addEventListener( 'click', preview );
	applyButton.addEventListener( 'click', apply );
	rollbackButton.addEventListener( 'click', rollback );
}() );
