( function () {
	'use strict';

	const root = document.getElementById( 'seo-geo-manager-dashboard' );
	if ( ! root || ! window.wp || ! window.wp.apiFetch ) return;
	const rawBox = root.querySelector( '[data-seo-geo-raw]' );
	const actionablesBox = root.querySelector( '[data-seo-geo-actionables]' );
	if ( ! rawBox || ! actionablesBox ) return;
	let workspace = null;

	function text( tag, value, className ) {
		const node = document.createElement( tag );
		node.textContent = String( value ?? '' );
		if ( className ) node.className = className;
		return node;
	}

	function diagnosticIdentity( item, index ) {
		if ( item && item.id ) return String( item.id );
		return [ item?.classification || '', item?.category || '', item?.code || '', item?.current_url || '', item?.suggested_url || '', index ].join( '|' );
	}

	function items() {
		try {
			const data = JSON.parse( ( rawBox.textContent || '' ).trim() );
			return data && data.actionable_diagnostics && Array.isArray( data.actionable_diagnostics.items ) ? data.actionable_diagnostics.items : [];
		} catch ( error ) {
			return [];
		}
	}

	function ensureWorkspace() {
		if ( workspace && workspace.isConnected ) return workspace;
		workspace = document.createElement( 'section' );
		workspace.className = 'seo-geo-manager-admin__panel seo-geo-manager-correction';
		workspace.hidden = true;
		workspace.setAttribute( 'aria-live', 'polite' );
		( actionablesBox.closest( '.seo-geo-manager-admin__panel' ) || actionablesBox ).after( workspace );
		return workspace;
	}

	function renderFact( label, value ) {
		const row = document.createElement( 'p' );
		row.appendChild( text( 'strong', `${ label }: ` ) );
		row.appendChild( document.createTextNode( String( value ?? '—' ) ) );
		return row;
	}

	function collisionLabel( type ) {
		const labels = {
			'duplicate-old-source': 'URL antigua ambigua',
			'duplicate-new-target': 'destino nuevo duplicado',
			'target-collides-with-existing-public-resource': 'destino ocupado por otro recurso público',
			'invalid-generated-url': 'URL generada no válida',
			'host-change-detected': 'cambio de host no permitido'
		};
		return labels[ type ] || type || 'colisión';
	}

	function renderRedirectSample( plan, body ) {
		const redirects = Array.isArray( plan.redirects ) ? plan.redirects : [];
		if ( ! redirects.length ) return;
		body.appendChild( text( 'h3', 'Muestra del mapa 301 propuesto' ) );
		const list = document.createElement( 'ol' );
		redirects.slice( 0, 20 ).forEach( ( redirect ) => {
			list.appendChild( text( 'li', `#${ redirect.post_id || '—' } · ${ redirect.old_path || redirect.old_url || '—' } → ${ redirect.new_path || redirect.new_url || '—' }` ) );
		} );
		body.appendChild( list );
		if ( redirects.length > 20 ) body.appendChild( text( 'p', `Se muestran 20 de ${ redirects.length } cambios de URL calculados.`, 'description' ) );
	}

	function renderCollisions( plan, body ) {
		const collisions = Array.isArray( plan.collisions ) ? plan.collisions : [];
		if ( ! collisions.length ) return;
		body.appendChild( text( 'h3', 'Colisiones o bloqueos detectados' ) );
		const list = document.createElement( 'ul' );
		collisions.slice( 0, 20 ).forEach( ( collision ) => {
			const target = collision.old_url || collision.new_url || 'sin URL';
			const counterpart = collision.other_post_id ? ` · también post #${ collision.other_post_id }` : '';
			list.appendChild( text( 'li', `${ collisionLabel( collision.type ) } · post #${ collision.post_id || '—' }${ counterpart } · ${ target }` ) );
		} );
		body.appendChild( list );
		if ( collisions.length > 20 ) body.appendChild( text( 'p', `Se muestran 20 de ${ collisions.length } bloqueos detectados.`, 'description' ) );
	}

	function renderLegacySample( authority, container ) {
		const rows = Array.isArray( authority.rows ) ? authority.rows : [];
		if ( ! rows.length ) return;
		container.appendChild( text( 'h4', 'Muestra de URLs históricas recuperadas' ) );
		const list = document.createElement( 'ol' );
		rows.slice( 0, 12 ).forEach( ( row ) => {
			list.appendChild( text( 'li', `#${ row.post_id || '—' } · ${ row.slug || '—' } → ${ row.legacy_path || row.legacy_url || '—' }` ) );
		} );
		container.appendChild( list );
		if ( rows.length > 12 ) container.appendChild( text( 'p', `Se muestran 12 de ${ rows.length } correspondencias históricas.`, 'description' ) );
	}

	function renderLegacyAuthorityControls( plan, status, body, apply ) {
		if ( ! plan.requires_authoritative_legacy_urls ) return;
		const section = document.createElement( 'div' );
		section.className = 'seo-geo-manager-correction__legacy-authority';
		section.appendChild( text( 'h3', 'Recuperar autoridad SEO histórica' ) );
		section.appendChild( text( 'p', 'La estructura corrupta hace que varias entradas compartan la misma URL generada. Indica la URL base del WordPress histórico para recuperar sus enlaces públicos por slug. Esta comprobación es de solo lectura.', 'description' ) );

		const label = document.createElement( 'label' );
		label.appendChild( text( 'strong', 'URL base histórica: ' ) );
		const input = document.createElement( 'input' );
		input.type = 'url';
		input.className = 'regular-text';
		input.placeholder = 'https://dominio.tld/';
		input.value = `${ window.location.origin }/`;
		label.appendChild( input );
		section.appendChild( label );
		section.appendChild( text( 'p', 'Se propone la raíz del mismo host como punto de partida; revísala antes de comprobar. La 0.3.17 no admite todavía fuentes históricas en otro host.', 'description' ) );

		const button = text( 'button', 'Comprobar URLs históricas' );
		button.type = 'button';
		button.className = 'button button-secondary';
		section.appendChild( button );
		const result = document.createElement( 'div' );
		section.appendChild( result );
		body.appendChild( section );

		button.addEventListener( 'click', async () => {
			const legacyBaseUrl = input.value.trim();
			if ( ! legacyBaseUrl ) {
				status.textContent = 'Indica una URL base histórica antes de comprobar.';
				return;
			}
			button.disabled = true;
			result.replaceChildren();
			status.textContent = 'Consultando el WordPress histórico en modo lectura y reconciliando entradas por slug…';
			try {
				const authority = await window.wp.apiFetch( {
					path: '/seo-geo-manager/v1/permalinks/legacy-authority-preview',
					method: 'POST',
					data: { legacy_base_url: legacyBaseUrl }
				} );
				result.appendChild( renderFact( 'Fuente histórica', authority.legacy_base_url || legacyBaseUrl ) );
				result.appendChild( renderFact( 'Entradas actuales escaneadas', authority.current_posts_scanned ?? 0 ) );
				result.appendChild( renderFact( 'Entradas históricas escaneadas', authority.legacy_posts_scanned ?? 0 ) );
				result.appendChild( renderFact( 'Correspondencias exactas por slug', authority.matched_posts ?? 0 ) );
				result.appendChild( renderFact( 'Entradas actuales sin URL histórica', authority.missing_count ?? 0 ) );
				result.appendChild( renderFact( 'Escaneo completo', authority.complete_scan ? 'sí' : 'no' ) );
				result.appendChild( renderFact( 'Estructura histórica inferida', authority.inferred_structure || 'no concluyente' ) );
				result.appendChild( renderFact( 'Autoridad SEO histórica verificada', authority.seo_authority_verified ? 'sí' : 'no' ) );
				result.appendChild( renderFact( 'Fingerprint de autoridad', authority.authority_fingerprint || '—' ) );
				renderLegacySample( authority, result );
				if ( authority.seo_authority_verified ) {
					status.textContent = `Autoridad histórica recuperada: ${ authority.matched_posts } entrada(s) y estructura ${ authority.inferred_structure }. Apply sigue bloqueado hasta integrar esta autoridad en el plan de reparación y probar rollback.`;
				} else {
					status.textContent = authority.block_reason || 'La fuente histórica no permite todavía demostrar una correspondencia completa y unívoca.';
				}
				apply.disabled = true;
			} catch ( error ) {
				status.textContent = error && error.message ? error.message : 'No se pudo comprobar la fuente histórica.';
			} finally {
				button.disabled = false;
			}
		} );
	}

	async function renderRedirectPlan( status, body, planButton, apply ) {
		planButton.disabled = true;
		status.textContent = 'Generando mapa completo de URLs antiguas → nuevas y comprobando colisiones…';
		try {
			const plan = await window.wp.apiFetch( { path: '/seo-geo-manager/v1/permalinks/redirect-plan' } );
			body.appendChild( text( 'hr', '' ) );
			body.appendChild( text( 'h3', 'Plan SEO de redirecciones' ) );
			body.appendChild( renderFact( 'Entradas publicadas escaneadas', plan.published_posts_scanned ?? 0 ) );
			body.appendChild( renderFact( 'Cambios de URL calculados', plan.planned_redirects ?? 0 ) );
			body.appendChild( renderFact( 'URLs sin cambio', plan.skipped_unchanged ?? 0 ) );
			body.appendChild( renderFact( 'Colisiones totales', plan.collision_count ?? 0 ) );
			body.appendChild( renderFact( 'URLs antiguas ambiguas', plan.ambiguous_old_source_count ?? 0 ) );
			body.appendChild( renderFact( 'Necesita URLs históricas autoritativas', plan.requires_authoritative_legacy_urls ? 'sí' : 'no' ) );
			body.appendChild( renderFact( 'Escaneo completo', plan.complete_scan ? 'sí' : 'no' ) );
			body.appendChild( renderFact( 'Fingerprint del plan', plan.plan_fingerprint || '—' ) );
			renderCollisions( plan, body );
			renderRedirectSample( plan, body );
			renderLegacyAuthorityControls( plan, status, body, apply );

			if ( plan.safe_to_apply ) {
				status.textContent = 'Plan completo, con origen único por entrada y sin colisiones. Apply continúa bloqueado hasta implementar el runtime 301, la aprobación explícita y el rollback de estructura.';
			} else if ( plan.requires_authoritative_legacy_urls ) {
				status.textContent = plan.block_reason || 'La URL antigua es compartida por varias entradas; no se puede construir un 301 exacto por entrada desde la estructura corrupta.';
			} else {
				status.textContent = plan.block_reason || 'El plan no es todavía seguro: hay colisiones, URLs no reconciliadas o el escaneo quedó incompleto.';
			}
			apply.disabled = true;
			apply.title = plan.requires_authoritative_legacy_urls
				? 'Apply bloqueado: primero hay que recuperar URLs históricas autoritativas o definir una política segura específica para este clon.'
				: 'Apply permanece bloqueado hasta implementar y validar el runtime 301 y el rollback de la estructura.';
		} catch ( error ) {
			planButton.disabled = false;
			status.textContent = error && error.message ? error.message : 'No se pudo generar el plan SEO de redirecciones.';
		}
	}

	async function renderPreview() {
		const panel = ensureWorkspace();
		panel.hidden = false;
		panel.replaceChildren();
		panel.appendChild( text( 'p', 'Permalink workflow', 'seo-geo-manager-admin__eyebrow' ) );
		panel.appendChild( text( 'h2', 'Revisión segura de estructura de enlaces permanentes' ) );
		const status = text( 'p', 'Inspeccionando permalink_structure sin escribir cambios…', 'seo-geo-manager-correction__safety' );
		panel.appendChild( status );
		const body = document.createElement( 'div' );
		panel.appendChild( body );
		const actions = document.createElement( 'div' );
		actions.className = 'seo-geo-manager-correction__actions';
		const close = text( 'button', 'Cerrar' );
		close.type = 'button';
		close.className = 'button';
		close.addEventListener( 'click', () => { panel.hidden = true; } );
		actions.appendChild( close );
		const planButton = text( 'button', 'Generar plan SEO de redirecciones' );
		planButton.type = 'button';
		planButton.className = 'button button-secondary';
		planButton.disabled = true;
		actions.appendChild( planButton );
		const apply = text( 'button', 'Aplicar reparación' );
		apply.type = 'button';
		apply.className = 'button button-primary';
		apply.disabled = true;
		apply.title = 'Apply permanece bloqueado hasta generar y validar un plan de redirecciones SEO.';
		actions.appendChild( apply );
		panel.appendChild( actions );

		try {
			const preview = await window.wp.apiFetch( { path: '/seo-geo-manager/v1/permalinks/preview' } );
			body.replaceChildren();
			body.appendChild( renderFact( 'Estructura actual', preview.current_structure || '(vacía)' ) );
			body.appendChild( renderFact( 'Recuperación sintáctica', preview.proposed_structure || '(sin propuesta)' ) );
			body.appendChild( renderFact( 'Autoridad SEO histórica verificada', preview.seo_authority_verified ? 'sí' : 'no' ) );
			body.appendChild( renderFact( 'Tokens restaurados', Array.isArray( preview.restored_tokens ) && preview.restored_tokens.length ? preview.restored_tokens.join( ', ' ) : 'ninguno' ) );
			body.appendChild( renderFact( 'Entradas publicadas potencialmente afectadas', preview.published_posts ?? 0 ) );
			body.appendChild( renderFact( 'Fingerprint', preview.current_fingerprint || '—' ) );
			if ( Array.isArray( preview.malformed_fragments ) && preview.malformed_fragments.length ) {
				body.appendChild( renderFact( 'Fragmentos todavía no reconocidos', preview.malformed_fragments.join( ', ' ) ) );
			}
			if ( preview.safe_candidate ) {
				planButton.disabled = false;
				status.textContent = preview.redirect_plan_required
					? 'La sintaxis de los tokens se puede recuperar de forma determinista, pero todavía no es autoridad SEO. Genera el mapa completo para comprobar orígenes, destinos y necesidad de URLs históricas.'
					: 'La sintaxis de los tokens se puede recuperar de forma determinista. No hay entradas publicadas que requieran redirección.';
				planButton.addEventListener( 'click', () => renderRedirectPlan( status, body, planButton, apply ), { once: true } );
			} else {
				status.textContent = 'No existe todavía una reparación sintáctica determinista completa. Se mantiene en revisión manual y no se escribe nada.';
			}
		} catch ( error ) {
			status.textContent = error && error.message ? error.message : 'No se pudo preparar la revisión de permalinks.';
		}
		panel.scrollIntoView( { behavior: 'smooth', block: 'start' } );
	}

	function wireButtons() {
		const map = new Map();
		items().forEach( ( item, index ) => map.set( diagnosticIdentity( item, index ), item ) );
		actionablesBox.querySelectorAll( '.seo-geo-manager-admin__check[data-seo-geo-diagnostic-id]' ).forEach( ( row ) => {
			const item = map.get( row.dataset.seoGeoDiagnosticId );
			const existing = row.querySelector( '[data-seo-geo-permalink-preview]' );
			if ( ! item || 'malformed-permalink-template' !== item.code ) {
				if ( existing ) existing.remove();
				return;
			}
			if ( existing ) return;
			const button = text( 'button', 'Preparar revisión segura' );
			button.type = 'button';
			button.className = 'button button-secondary';
			button.setAttribute( 'data-seo-geo-permalink-preview', row.dataset.seoGeoDiagnosticId );
			button.addEventListener( 'click', renderPreview );
			row.appendChild( button );
		} );
	}

	const observer = new MutationObserver( wireButtons );
	observer.observe( actionablesBox, { childList: true, subtree: true } );
	observer.observe( rawBox, { childList: true, characterData: true, subtree: true } );
	wireButtons();
}() );
