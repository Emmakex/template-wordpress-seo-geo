( function () {
	'use strict';

	const root = document.getElementById( 'seo-geo-manager-dashboard' );
	const themeWorkspace = root ? root.querySelector( '[data-seo-geo-build-workspace]' ) : null;
	const api = window.SeoGeoManagerContentWorkspace || {};
	if ( ! root || ! themeWorkspace || ! window.wp || ! window.wp.apiFetch ) {
		return;
	}

	const object = ( value ) => value && typeof value === 'object' && ! Array.isArray( value ) ? value : {};
	const state = { current: null, preview: null, type: 'page' };

	function el( tag, className, text ) {
		const node = document.createElement( tag );
		if ( className ) node.className = className;
		if ( undefined !== text ) node.textContent = String( text );
		return node;
	}

	function clear( node ) {
		while ( node.firstChild ) node.removeChild( node.firstChild );
	}

	function idempotencyKey() {
		if ( window.crypto && typeof window.crypto.randomUUID === 'function' ) {
			return `manager-content-${ window.crypto.randomUUID() }`;
		}
		return `manager-content-${ Date.now() }-${ Math.random().toString( 16 ).slice( 2 ) }`;
	}

	const section = el( 'section', 'seo-geo-manager-admin__panel seo-geo-manager-content-workspace' );
	section.dataset.seoGeoContentWorkspace = '';
	section.innerHTML = `
		<div class="seo-geo-manager-admin__panel-heading">
			<div>
				<p class="seo-geo-manager-admin__eyebrow">Build / Finish · Content</p>
				<h2>Páginas y entradas</h2>
				<p class="description">Edita contenido WordPress normal mediante cambios acotados, previsualizados, verificados y reversibles.</p>
			</div>
			<button type="button" class="button" data-content-refresh>Recargar</button>
		</div>
		<div class="seo-geo-manager-content-toolbar">
			<label>Tipo
				<select data-content-type><option value="page">Páginas</option><option value="post">Entradas</option></select>
			</label>
			<label>Buscar
				<input type="search" data-content-search placeholder="Título o contenido">
			</label>
			<button type="button" class="button" data-content-search-button>Buscar</button>
		</div>
		<div class="notice notice-info inline seo-geo-manager-admin__notice" data-content-status role="status" aria-live="polite"><p>Cargando contenido…</p></div>
		<div class="seo-geo-manager-content-layout">
			<div><h3>Contenido</h3><div class="seo-geo-manager-content-list" data-content-list></div></div>
			<div class="seo-geo-manager-content-editor" data-content-editor hidden>
				<div class="seo-geo-manager-content-editor__heading"><div><p class="seo-geo-manager-admin__eyebrow">Editor WordPress</p><h3 data-content-title>Contenido</h3><p class="description" data-content-meta>—</p></div><a class="button" data-content-open target="_blank" rel="noopener noreferrer" hidden>Abrir URL</a></div>
				<label>Título<input type="text" data-content-field="title"></label>
				<label>Slug<input type="text" data-content-field="slug"></label>
				<label>Extracto<textarea rows="3" data-content-field="excerpt"></textarea></label>
				<label>Contenido<textarea rows="14" data-content-field="content"></textarea></label>
				<label>Estado<select data-content-field="status"><option value="draft">Borrador</option><option value="pending">Pendiente</option><option value="private">Privado</option><option value="publish">Publicado</option></select></label>
				<label class="seo-geo-manager-content-published" data-content-published hidden><input type="checkbox" data-content-allow-published> Permitir cambios sobre el recurso publicado.</label>
				<div class="seo-geo-manager-build__actions"><button type="button" class="button button-secondary" data-content-preview>Vista previa</button><button type="button" class="button button-primary" data-content-apply disabled>Aplicar y verificar</button></div>
				<div class="seo-geo-manager-build__preview" data-content-preview-box><p class="description">Haz una vista previa antes de aplicar cambios.</p></div>
			</div>
		</div>`;
	themeWorkspace.insertAdjacentElement( 'afterend', section );

	const list = section.querySelector( '[data-content-list]' );
	const editor = section.querySelector( '[data-content-editor]' );
	const status = section.querySelector( '[data-content-status]' );
	const typeSelect = section.querySelector( '[data-content-type]' );
	const searchInput = section.querySelector( '[data-content-search]' );
	const previewButton = section.querySelector( '[data-content-preview]' );
	const applyButton = section.querySelector( '[data-content-apply]' );
	const previewBox = section.querySelector( '[data-content-preview-box]' );
	const allowPublished = section.querySelector( '[data-content-allow-published]' );
	const publishedWrap = section.querySelector( '[data-content-published]' );

	function setStatus( message, type = 'info' ) {
		status.className = `notice inline seo-geo-manager-admin__notice notice-${ type }`;
		status.innerHTML = '';
		status.appendChild( el( 'p', '', message ) );
	}

	function resetPreview() {
		state.preview = null;
		applyButton.disabled = true;
		previewBox.innerHTML = '<p class="description">Haz una vista previa antes de aplicar cambios.</p>';
	}

	function currentValues() {
		const values = {};
		section.querySelectorAll( '[data-content-field]' ).forEach( ( field ) => { values[ field.dataset.contentField ] = field.value; } );
		return values;
	}

	function collectChanges() {
		if ( ! state.current ) return {};
		const current = currentValues();
		const changes = {};
		[ 'title', 'slug', 'excerpt', 'content', 'status' ].forEach( ( key ) => {
			if ( String( state.current[ key ] ?? '' ) !== String( current[ key ] ?? '' ) ) changes[ key ] = current[ key ];
		} );
		return changes;
	}

	function basePayload() {
		return {
			schema_version: 1,
			target: { id: Number( state.current?.id || 0 ), expected_fingerprint: String( state.current?.fingerprint || '' ) },
			changes: collectChanges(),
			allow_published_target: Boolean( allowPublished.checked ),
			verify_rendered: true,
		};
	}

	function renderDiff( result ) {
		clear( previewBox );
		const diff = object( result.diff );
		const keys = Object.keys( diff );
		if ( ! keys.length ) {
			previewBox.appendChild( el( 'p', 'description', 'La vista previa no contiene cambios.' ) );
			return;
		}
		previewBox.appendChild( el( 'strong', '', `Vista previa: ${ keys.length } campo(s)` ) );
		const wrap = el( 'div', 'seo-geo-manager-build-diff' );
		keys.forEach( ( key ) => {
			const item = el( 'article' );
			item.appendChild( el( 'strong', '', key ) );
			item.appendChild( el( 'pre', '', JSON.stringify( diff[ key ], null, 2 ) ) );
			wrap.appendChild( item );
		} );
		previewBox.appendChild( wrap );
	}

	function renderList( payload ) {
		clear( list );
		const items = Array.isArray( payload.items ) ? payload.items : [];
		if ( ! items.length ) {
			list.appendChild( el( 'p', 'description', 'No hay resultados.' ) );
			return;
		}
		items.forEach( ( item ) => {
			const button = el( 'button', 'seo-geo-manager-content-item' );
			button.type = 'button';
			button.appendChild( el( 'strong', '', item.title || '(Sin título)' ) );
			button.appendChild( el( 'span', '', `${ item.status || '—' } · /${ item.slug || '' }` ) );
			button.appendChild( el( 'small', '', `#${ item.id } · ${ item.modified || '' }` ) );
			button.addEventListener( 'click', () => loadItem( item.id ) );
			list.appendChild( button );
		} );
	}

	function renderItem( item ) {
		state.current = object( item );
		resetPreview();
		section.querySelector( '[data-content-title]' ).textContent = item.title || '(Sin título)';
		section.querySelector( '[data-content-meta]' ).textContent = `${ item.type || 'contenido' } · ${ item.status || '—' } · #${ item.id || '—' }`;
		[ 'title', 'slug', 'excerpt', 'content', 'status' ].forEach( ( key ) => {
			const field = section.querySelector( `[data-content-field="${ key }"]` );
			if ( field ) field.value = String( item[ key ] ?? '' );
		} );
		const open = section.querySelector( '[data-content-open]' );
		if ( item.permalink ) { open.href = item.permalink; open.hidden = false; } else { open.hidden = true; }
		publishedWrap.hidden = 'publish' !== item.status;
		allowPublished.checked = false;
		editor.hidden = false;
	}

	async function loadList() {
		state.type = typeSelect.value;
		setStatus( 'Cargando contenido…' );
		editor.hidden = true;
		const params = new URLSearchParams( { type: state.type, per_page: '100', page: '1', status: 'any' } );
		if ( searchInput.value.trim() ) params.set( 'search', searchInput.value.trim() );
		try {
			const data = await window.wp.apiFetch( { path: `${ api.contentPath || '/seo-geo-manager/v1/content' }?${ params.toString() }` } );
			renderList( object( data ) );
			setStatus( `${ Number( data.total || 0 ) } recurso(s) encontrados.`, 'success' );
		} catch ( error ) {
			clear( list );
			list.appendChild( el( 'p', 'description', error?.message || 'No se pudo cargar el contenido.' ) );
			setStatus( error?.message || 'No se pudo cargar el contenido.', 'error' );
		}
	}

	async function loadItem( id ) {
		setStatus( `Cargando recurso #${ id }…` );
		try {
			const item = await window.wp.apiFetch( { path: `${ api.contentPath || '/seo-geo-manager/v1/content' }/${ Number( id ) }` } );
			renderItem( item );
			setStatus( 'Recurso listo. Edita y ejecuta Vista previa antes de aplicar.', 'success' );
		} catch ( error ) {
			setStatus( error?.message || 'No se pudo abrir el recurso.', 'error' );
		}
	}

	async function preview() {
		if ( ! state.current ) return;
		const payload = basePayload();
		if ( ! Object.keys( payload.changes ).length ) { setStatus( 'No has modificado ningún campo.' ); return; }
		previewButton.disabled = true;
		setStatus( 'Generando vista previa segura…' );
		try {
			const result = await window.wp.apiFetch( { path: api.previewPath || '/seo-geo-manager/v1/changes/preview', method: 'POST', data: payload } );
			state.preview = { payload, result: object( result ) };
			renderDiff( result );
			applyButton.disabled = true !== result.has_changes;
			setStatus( true === result.has_changes ? 'Vista previa correcta. Revisa el diff antes de aplicar.' : 'No hay cambios efectivos.', true === result.has_changes ? 'success' : 'info' );
		} catch ( error ) {
			resetPreview();
			setStatus( error?.message || 'La vista previa ha sido rechazada.', 'error' );
		} finally { previewButton.disabled = false; }
	}

	async function apply() {
		if ( ! state.preview ) return;
		applyButton.disabled = true;
		previewButton.disabled = true;
		setStatus( 'Aplicando y verificando…' );
		try {
			const environment = object( state.preview.result.environment );
			const payload = { ...state.preview.payload, idempotency_key: idempotencyKey(), environment_fingerprint: String( environment.fingerprint || '' ) };
			const result = await window.wp.apiFetch( { path: api.applyPath || '/seo-geo-manager/v1/changes/apply', method: 'POST', data: payload } );
			const operation = result.operation_id ? ` · Operación ${ result.operation_id }` : '';
			setStatus( `Cambio aplicado y verificado${ operation }.`, 'success' );
			await loadItem( state.current.id );
		} catch ( error ) {
			setStatus( error?.message || 'El Manager rechazó la aplicación.', 'error' );
		} finally { previewButton.disabled = false; applyButton.disabled = true; }
	}

	section.querySelectorAll( '[data-content-field]' ).forEach( ( field ) => field.addEventListener( 'input', resetPreview ) );
	allowPublished.addEventListener( 'change', resetPreview );
	typeSelect.addEventListener( 'change', loadList );
	section.querySelector( '[data-content-refresh]' ).addEventListener( 'click', loadList );
	section.querySelector( '[data-content-search-button]' ).addEventListener( 'click', loadList );
	searchInput.addEventListener( 'keydown', ( event ) => { if ( 'Enter' === event.key ) { event.preventDefault(); loadList(); } } );
	previewButton.addEventListener( 'click', preview );
	applyButton.addEventListener( 'click', apply );
	loadList();
}() );
