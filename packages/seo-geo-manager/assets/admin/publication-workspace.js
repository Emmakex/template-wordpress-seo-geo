( function () {
	'use strict';

	const root = document.getElementById( 'seo-geo-manager-dashboard' );
	const anchor = root ? root.querySelector( '[data-seo-geo-content-workspace]' ) : null;
	const api = window.SeoGeoManagerPublicationWorkspace || {};
	if ( ! root || ! anchor || ! window.wp || ! window.wp.apiFetch ) {
		return;
	}

	const object = ( value ) => value && typeof value === 'object' && ! Array.isArray( value ) ? value : {};
	const state = { resource: null, preview: null, idempotencyKey: null };

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
			return `manager-publication-${ window.crypto.randomUUID() }`;
		}
		return `manager-publication-${ Date.now() }-${ Math.random().toString( 16 ).slice( 2 ) }`;
	}

	const section = el( 'section', 'seo-geo-manager-admin__panel seo-geo-manager-publication-workspace' );
	section.dataset.seoGeoPublicationWorkspace = '';
	section.innerHTML = `
		<div class="seo-geo-manager-admin__panel-heading">
			<div>
				<p class="seo-geo-manager-admin__eyebrow">Grow · Publication</p>
				<h2>Publicar o programar</h2>
				<p class="description">Transición explícita del recurso seleccionado. Preview y fingerprint son obligatorios antes de publicar o programar.</p>
			</div>
		</div>
		<div class="notice notice-info inline seo-geo-manager-admin__notice" data-publication-status role="status" aria-live="polite"><p>Selecciona una página o entrada en Content Workspace.</p></div>
		<div class="seo-geo-manager-publication-body" data-publication-body hidden>
			<div class="seo-geo-manager-publication-resource" data-publication-resource></div>
			<div class="seo-geo-manager-publication-controls">
				<label>Acción
					<select data-publication-mode>
						<option value="publish">Publicar ahora</option>
						<option value="future">Programar</option>
					</select>
				</label>
				<label data-publication-schedule-wrap hidden>Fecha y hora local
					<input type="datetime-local" data-publication-schedule>
					<small>Se convertirá a UTC antes de enviar el Preview.</small>
				</label>
				<label class="seo-geo-manager-publication-approval"><input type="checkbox" data-publication-approve> Confirmo que he revisado contenido, SEO, enlaces y medios y autorizo esta transición pública.</label>
			</div>
			<div class="seo-geo-manager-build__actions">
				<button type="button" class="button button-secondary" data-publication-preview>Vista previa</button>
				<button type="button" class="button button-primary" data-publication-apply disabled>Confirmar publicación</button>
			</div>
			<div class="seo-geo-manager-build__preview" data-publication-preview-box><p class="description">Genera una vista previa de la transición antes de aplicar.</p></div>
		</div>`;
	anchor.insertAdjacentElement( 'afterend', section );

	const status = section.querySelector( '[data-publication-status]' );
	const body = section.querySelector( '[data-publication-body]' );
	const resourceBox = section.querySelector( '[data-publication-resource]' );
	const mode = section.querySelector( '[data-publication-mode]' );
	const scheduleWrap = section.querySelector( '[data-publication-schedule-wrap]' );
	const scheduleInput = section.querySelector( '[data-publication-schedule]' );
	const approve = section.querySelector( '[data-publication-approve]' );
	const previewButton = section.querySelector( '[data-publication-preview]' );
	const applyButton = section.querySelector( '[data-publication-apply]' );
	const previewBox = section.querySelector( '[data-publication-preview-box]' );

	function setStatus( message, type = 'info' ) {
		status.className = `notice inline seo-geo-manager-admin__notice notice-${ type }`;
		clear( status );
		status.appendChild( el( 'p', '', message ) );
	}

	function resetPreview() {
		state.preview = null;
		state.idempotencyKey = null;
		applyButton.disabled = true;
		approve.checked = false;
		clear( previewBox );
		previewBox.appendChild( el( 'p', 'description', 'Genera una vista previa de la transición antes de aplicar.' ) );
	}

	function scheduledAtGmt() {
		if ( 'future' !== mode.value ) return '';
		const value = String( scheduleInput.value || '' );
		if ( ! value ) return '';
		const date = new Date( value );
		return Number.isNaN( date.getTime() ) ? '' : date.toISOString();
	}

	function payload() {
		return {
			schema_version: 1,
			target: {
				id: Number( state.resource?.id || 0 ),
				expected_fingerprint: String( state.resource?.fingerprint || '' ),
			},
			status: String( mode.value || 'publish' ),
			scheduled_at_gmt: scheduledAtGmt(),
		};
	}

	function renderResource() {
		clear( resourceBox );
		const resource = object( state.resource );
		resourceBox.appendChild( el( 'strong', '', resource.title || '(Sin título)' ) );
		resourceBox.appendChild( el( 'span', '', `${ resource.type || 'contenido' } · ${ resource.status || '—' } · #${ resource.id || '—' }` ) );
		if ( resource.permalink ) resourceBox.appendChild( el( 'code', '', resource.permalink ) );
	}

	function setResource( resource ) {
		state.resource = object( resource );
		resetPreview();
		renderResource();
		body.hidden = false;
		if ( 'future' === state.resource.status ) mode.value = 'future';
		else mode.value = 'publish';
		scheduleWrap.hidden = 'future' !== mode.value;
		if ( 'publish' === state.resource.status ) {
			setStatus( 'Este recurso ya está publicado. Puedes seleccionarlo para edición, pero no necesita otra transición de publicación.', 'info' );
			previewButton.disabled = true;
		} else {
			previewButton.disabled = false;
			setStatus( 'Recurso listo. Elige publicar ahora o programar y genera Preview.', 'success' );
		}
	}

	function renderPreview( result ) {
		clear( previewBox );
		const transition = object( result.transition );
		previewBox.appendChild( el( 'strong', '', 'Vista previa de publicación' ) );
		const facts = el( 'dl', 'seo-geo-manager-publication-preview__facts' );
		[
			[ 'Estado actual', transition.from_status || state.resource?.status || '—' ],
			[ 'Estado objetivo', transition.to_status || mode.value ],
			[ 'Programado UTC', transition.scheduled_at_gmt || 'No' ],
		].forEach( ( [ label, value ] ) => {
			facts.appendChild( el( 'dt', '', label ) );
			facts.appendChild( el( 'dd', '', value ) );
		} );
		previewBox.appendChild( facts );
		previewBox.appendChild( el( 'p', 'description', 'Apply cambiará únicamente el estado/fecha de publicación del recurso inspeccionado.' ) );
	}

	async function preview() {
		if ( ! state.resource ) return;
		if ( 'publish' === state.resource.status ) {
			setStatus( 'El recurso ya está publicado.', 'info' );
			return;
		}
		if ( 'future' === mode.value && ! scheduledAtGmt() ) {
			setStatus( 'Indica una fecha/hora válida para programar.', 'warning' );
			scheduleInput.focus();
			return;
		}
		previewButton.disabled = true;
		setStatus( 'Calculando transición de publicación…' );
		try {
			const result = await window.wp.apiFetch( {
				path: `${ api.contentPath || '/seo-geo-manager/v1/content' }/${ Number( state.resource.id ) }/publication/preview`,
				method: 'POST',
				data: payload(),
			} );
			state.preview = object( result );
			state.idempotencyKey = idempotencyKey();
			renderPreview( result );
			applyButton.disabled = ! approve.checked || true !== result.has_changes;
			setStatus( true === result.has_changes ? 'Vista previa correcta. Confirma la transición pública para aplicar.' : 'El recurso ya coincide con ese estado.', true === result.has_changes ? 'success' : 'info' );
		} catch ( error ) {
			resetPreview();
			setStatus( error?.message || 'La vista previa de publicación fue rechazada.', 'error' );
		} finally {
			previewButton.disabled = false;
		}
	}

	async function apply() {
		if ( ! state.resource || ! state.preview || ! approve.checked ) return;
		applyButton.disabled = true;
		previewButton.disabled = true;
		setStatus( 'Aplicando transición de publicación…' );
		const data = {
			...payload(),
			idempotency_key: state.idempotencyKey || idempotencyKey(),
			environment_fingerprint: String( object( state.preview.environment ).fingerprint || '' ),
		};
		state.idempotencyKey = data.idempotency_key;
		try {
			const result = await window.wp.apiFetch( {
				path: `${ api.contentPath || '/seo-geo-manager/v1/content' }/${ Number( state.resource.id ) }/publication/apply`,
				method: 'POST',
				data,
			} );
			const refreshed = await window.wp.apiFetch( { path: `${ api.contentPath || '/seo-geo-manager/v1/content' }/${ Number( state.resource.id ) }` } );
			state.resource = object( refreshed );
			root.dispatchEvent( new CustomEvent( 'seo-geo-manager:content-selected', { detail: { resource: state.resource } } ) );
			clear( previewBox );
			previewBox.appendChild( el( 'strong', '', `${ 'future' === result.status ? 'Programado' : 'Publicado' } · Operación ${ result.operation_id || 'registrada' }` ) );
			if ( result.permalink ) previewBox.appendChild( el( 'code', '', result.permalink ) );
			renderResource();
			setStatus( 'Transición aplicada y verificada. El recurso se ha refrescado en los workspaces dependientes.', 'success' );
			state.preview = null;
			approve.checked = false;
			applyButton.disabled = true;
			previewButton.disabled = 'publish' === state.resource.status;
		} catch ( error ) {
			applyButton.disabled = false;
			setStatus( error?.message || 'El Manager rechazó la transición de publicación.', 'error' );
		} finally {
			previewButton.disabled = 'publish' === state.resource?.status;
		}
	}

	root.addEventListener( 'seo-geo-manager:content-selected', ( event ) => {
		const resource = object( event.detail ).resource;
		if ( resource && resource.id ) setResource( resource );
	} );
	mode.addEventListener( 'change', () => {
		scheduleWrap.hidden = 'future' !== mode.value;
		resetPreview();
	} );
	scheduleInput.addEventListener( 'input', resetPreview );
	approve.addEventListener( 'change', () => { applyButton.disabled = ! state.preview || ! approve.checked || true !== state.preview.has_changes; } );
	previewButton.addEventListener( 'click', preview );
	applyButton.addEventListener( 'click', apply );
}() );
