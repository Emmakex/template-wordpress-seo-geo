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
			'duplicate-historical-source': 'ruta histórica duplicada',
			'duplicate-authoritative-target': 'destino histórico duplicado',
			'target-collides-with-existing-public-resource': 'destino ocupado por otro recurso público',
			'invalid-generated-url': 'URL generada no válida',
			'invalid-logical-path': 'ruta lógica no válida',
			'local-post-changed-since-authority-scan': 'entrada local cambió durante la comprobación',
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
			list.appendChild( text( 'li', `#${ redirect.post_id || '—' } · ${ redirect.old_path || redirect.old_url || redirect.source_path || '—' } → ${ redirect.new_path || redirect.new_url || redirect.target_path || '—' }` ) );
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
			const target = collision.old_url || collision.new_url || collision.source_path || collision.target_path || 'sin URL';
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

	function renderAuthoritativeSample( plan, container ) {
		const rows = Array.isArray( plan.rows ) ? plan.rows : [];
		if ( ! rows.length ) return;
		container.appendChild( text( 'h4', 'Comprobación de rutas SEO después del cambio' ) );
		const list = document.createElement( 'ol' );
		rows.slice( 0, 12 ).forEach( ( row ) => {
			const state = row.path_preserved ? 'preservada' : 'requiere 301';
			list.appendChild( text( 'li', `#${ row.post_id || '—' } · ${ row.historical_path || '—' } → ${ row.target_logical_path || '—' } · ${ state }` ) );
		} );
		container.appendChild( list );
		if ( rows.length > 12 ) container.appendChild( text( 'p', `Se muestran 12 de ${ rows.length } rutas comparadas.`, 'description' ) );
	}

	function wireRollback( operation, plan, result, status ) {
		const rollback = text( 'button', 'Revertir esta reparación' );
		rollback.type = 'button';
		rollback.className = 'button button-secondary';
		result.appendChild( rollback );
		rollback.addEventListener( 'click', async () => {
			const rollbackApproved = window.confirm( 'Se restaurará exactamente la estructura anterior. Si esta operación instaló un mapa 301, también se retirará de forma atómica. ¿Revertir esta operación?' );
			if ( ! rollbackApproved ) return;
			rollback.disabled = true;
			status.textContent = 'Restaurando la estructura anterior y verificando rollback…';
			try {
				const rolledBack = await window.wp.apiFetch( {
					path: `/seo-geo-manager/v1/permalinks/operations/${ operation.operation_id }/rollback`,
					method: 'POST',
					data: { environment_fingerprint: plan.environment?.fingerprint || '' }
				} );
				status.textContent = `Rollback verificado. Estructura restaurada: ${ rolledBack.before_structure || 'anterior' }.${ rolledBack.redirect_runtime_removed ? ' Runtime 301 retirado.' : '' }`;
			} catch ( error ) {
				rollback.disabled = false;
				status.textContent = error && error.message ? error.message : 'No se pudo completar el rollback.';
			}
		} );
	}

	async function wireAuthoritativeApply( plan, authority, legacyBaseUrl, result, status, apply ) {
		apply.onclick = null;
		apply.textContent = 'Aplicar reparación';
		let atomic = false;
		let runtimePreview = null;

		if ( plan.requires_redirect_runtime && plan.safe_structure_candidate ) {
			status.textContent = 'Validando el runtime 301 atómico contra el plan autoritativo actual…';
			try {
				runtimePreview = await window.wp.apiFetch( {
					path: '/seo-geo-manager/v1/permalinks/redirect-runtime/preview',
					method: 'POST',
					data: {
						legacy_base_url: legacyBaseUrl,
						authority_fingerprint: authority.authority_fingerprint || ''
					}
				} );
				result.appendChild( renderFact( 'Runtime 301 atómico disponible', runtimePreview.atomic_apply_available ? 'sí' : 'no' ) );
				result.appendChild( renderFact( '301 validados para activación', runtimePreview.redirect_count ?? 0 ) );
				result.appendChild( renderFact( 'Runtime 301 activo actualmente', runtimePreview.active_runtime?.active ? 'sí' : 'no' ) );
				atomic = Boolean( runtimePreview.atomic_apply_available );
			} catch ( error ) {
				apply.disabled = true;
				apply.title = 'No se pudo validar el runtime 301 atómico.';
				status.textContent = error && error.message ? error.message : 'No se pudo validar el runtime 301 atómico.';
				return { available: false, atomic: false };
			}
		}

		const available = Boolean( plan.apply_available || atomic );
		if ( ! available ) {
			apply.disabled = true;
			apply.title = plan.requires_redirect_runtime
				? ( runtimePreview?.block_reason || 'Apply bloqueado: el runtime 301 atómico no supera todavía sus guardas.' )
				: 'Apply bloqueado por el plan autoritativo.';
			return { available: false, atomic: false };
		}

		apply.disabled = false;
		apply.textContent = atomic ? 'Aplicar estructura + 301' : 'Aplicar reparación';
		apply.title = atomic
			? 'Aplicar estructura histórica y mapa 301 como una sola operación verificada y reversible.'
			: 'Aplicar la estructura histórica verificada con comprobación posterior y rollback disponible.';

		apply.onclick = async () => {
			const confirmation = atomic
				? `Se cambiará permalink_structure a ${ plan.authoritative_structure } y se activarán ${ plan.planned_redirects } redirección(es) 301 verificadas. El Manager armará el mapa primero, aplicará la estructura, verificará ambos estados y revertirá todo si algo falla. ¿Continuar?`
				: `Se cambiará permalink_structure a ${ plan.authoritative_structure } y se regenerarán las reglas de reescritura. El Manager verificará todas las rutas y registrará rollback. ¿Continuar?`;
			if ( ! window.confirm( confirmation ) ) return;
			apply.disabled = true;
			status.textContent = atomic
				? 'Armando mapa 301, aplicando estructura y verificando la operación atómica…'
				: 'Aplicando estructura autoritativa, regenerando reglas y verificando rutas SEO…';
			try {
				const operation = await window.wp.apiFetch( {
					path: atomic ? '/seo-geo-manager/v1/permalinks/redirect-apply' : '/seo-geo-manager/v1/permalinks/apply',
					method: 'POST',
					data: {
						legacy_base_url: legacyBaseUrl,
						authority_fingerprint: authority.authority_fingerprint || '',
						plan_fingerprint: plan.plan_fingerprint || '',
						current_fingerprint: plan.current_fingerprint || '',
						idempotency_key: `${ atomic ? 'permalink-301' : 'permalink' }-${ ( plan.plan_fingerprint || '' ).slice( 0, 64 ) }`,
						confirm_permalink_change: true,
						confirm_redirect_runtime: atomic,
						environment_fingerprint: plan.environment?.fingerprint || ''
					}
				} );
				result.appendChild( text( 'h4', atomic ? 'Estructura y 301 aplicados y verificados' : 'Cambio aplicado y verificado' ) );
				result.appendChild( renderFact( 'Operación', operation.operation_id || '—' ) );
				result.appendChild( renderFact( 'Estructura anterior', operation.before_structure || '—' ) );
				result.appendChild( renderFact( 'Estructura activa', operation.after_structure || '—' ) );
				result.appendChild( renderFact( '301 activos', operation.planned_redirects ?? 0 ) );
				if ( atomic ) {
					result.appendChild( renderFact( 'Runtime 301 efectivo', operation.redirect_runtime_effective ? 'sí' : 'no' ) );
					result.appendChild( renderFact( 'Fingerprint runtime', operation.redirect_runtime_fingerprint || '—' ) );
				} else {
					result.appendChild( renderFact( 'Rutas históricas preservadas', operation.path_preservation_count ?? 0 ) );
				}
				status.textContent = atomic
					? 'Operación atómica verificada: estructura y mapa 301 están activos y el rollback conjunto queda protegido contra estado obsoleto.'
					: 'Estructura aplicada y verificada. El Manager conserva una operación reversible y bloquea rollback si detecta cambios posteriores.';
				wireRollback( operation, plan, result, status );
			} catch ( error ) {
				apply.disabled = false;
				status.textContent = error && error.message ? error.message : 'No se pudo aplicar la reparación de permalinks.';
			}
		};

		return { available: true, atomic };
	}

	async function renderAuthoritativePlan( authority, legacyBaseUrl, result, status, apply ) {
		status.textContent = 'Revalidando la autoridad histórica y comparando las rutas SEO sin contar el prefijo temporal del clon…';
		const plan = await window.wp.apiFetch( {
			path: '/seo-geo-manager/v1/permalinks/authoritative-plan',
			method: 'POST',
			data: {
				legacy_base_url: legacyBaseUrl,
				authority_fingerprint: authority.authority_fingerprint || ''
			}
		} );

		result.appendChild( text( 'h4', 'Plan SEO basado en autoridad histórica' ) );
		result.appendChild( renderFact( 'Estructura histórica autoritativa', plan.authoritative_structure || '—' ) );
		result.appendChild( renderFact( 'Entradas reconciliadas', plan.matched_posts ?? 0 ) );
		result.appendChild( renderFact( 'Rutas SEO preservadas exactamente', plan.path_preservation_count ?? 0 ) );
		result.appendChild( renderFact( 'Redirecciones 301 realmente necesarias', plan.planned_redirects ?? 0 ) );
		result.appendChild( renderFact( 'Modo de preservación SEO', plan.seo_preservation_mode || 'bloqueado' ) );
		result.appendChild( renderFact( 'Colisiones', plan.collision_count ?? 0 ) );
		result.appendChild( renderFact( 'Escaneo completo', plan.complete_scan ? 'sí' : 'no' ) );
		result.appendChild( renderFact( 'Candidato seguro para cambio de estructura', plan.safe_structure_candidate ? 'sí' : 'no' ) );
		result.appendChild( renderFact( 'Apply directo sin 301', plan.apply_available ? 'sí' : 'no' ) );
		result.appendChild( renderFact( 'Fingerprint del plan', plan.plan_fingerprint || '—' ) );
		renderCollisions( plan, result );
		renderAuthoritativeSample( plan, result );
		renderRedirectSample( plan, result );
		const applyMode = await wireAuthoritativeApply( plan, authority, legacyBaseUrl, result, status, apply );

		if ( plan.apply_available ) {
			status.textContent = `Plan autoritativo cerrado: ${ plan.path_preservation_count } ruta(s) histórica(s) se conservan exactamente. No hacen falta 301 y Apply está disponible con verificación y rollback.`;
		} else if ( applyMode.atomic ) {
			status.textContent = `Plan autoritativo cerrado: ${ plan.planned_redirects } ruta(s) requieren 301 y el runtime atómico ha sido validado. Apply conjunto está disponible con rollback de estructura + redirecciones.`;
		} else if ( plan.safe_structure_candidate ) {
			status.textContent = `Plan autoritativo cerrado, pero ${ plan.planned_redirects } ruta(s) necesitan 301 y el runtime atómico no está disponible para este estado.`;
		} else {
			status.textContent = plan.block_reason || 'El plan autoritativo todavía contiene bloqueos y no permite avanzar a Apply.';
		}
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
		section.appendChild( text( 'p', 'Se propone la raíz del mismo host como punto de partida; revísala antes de comprobar. La recuperación histórica permanece limitada al mismo host.', 'description' ) );

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
					await renderAuthoritativePlan( authority, legacyBaseUrl, result, status, apply );
				} else {
					status.textContent = authority.block_reason || 'La fuente histórica no permite todavía demostrar una correspondencia completa y unívoca.';
					apply.disabled = true;
				}
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
				status.textContent = 'Plan sintáctico completo y sin colisiones. Si la URL antigua es ambigua, recupera primero la autoridad histórica; Apply solo se habilita desde un plan autoritativo verificado.';
			} else if ( plan.requires_authoritative_legacy_urls ) {
				status.textContent = plan.block_reason || 'La URL antigua es compartida por varias entradas; no se puede construir un 301 exacto por entrada desde la estructura corrupta.';
			} else {
				status.textContent = plan.block_reason || 'El plan no es todavía seguro: hay colisiones, URLs no reconciliadas o el escaneo quedó incompleto.';
			}
			if ( ! plan.requires_authoritative_legacy_urls ) {
				apply.disabled = true;
				apply.title = 'Apply requiere autoridad histórica verificada o un plan que no cambie ninguna URL SEO.';
			}
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
		apply.title = 'Apply requiere primero un plan SEO autoritativo y verificable.';
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
