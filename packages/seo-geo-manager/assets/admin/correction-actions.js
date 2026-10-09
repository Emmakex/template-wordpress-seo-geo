( function () {
	'use strict';

	const root = document.getElementById( 'seo-geo-manager-dashboard' );
	if ( ! root || ! window.wp || ! window.wp.apiFetch ) return;

	const rawBox = root.querySelector( '[data-seo-geo-raw]' );
	const actionablesBox = root.querySelector( '[data-seo-geo-actionables]' );
	if ( ! rawBox || ! actionablesBox ) return;

	let workspace = null;

	function makeText( tag, value, className ) {
		const node = document.createElement( tag );
		node.textContent = String( value || '' );
		if ( className ) node.className = className;
		return node;
	}

	function readDiagnostic() {
		try {
			return JSON.parse( ( rawBox.textContent || '' ).trim() );
		} catch ( error ) {
			return null;
		}
	}

	function items() {
		const data = readDiagnostic();
		return data && data.actionable_diagnostics && Array.isArray( data.actionable_diagnostics.items )
			? data.actionable_diagnostics.items
			: [];
	}

	function eligible( item ) {
		return item && 'auto-fixable' === item.classification && 'navigation' === item.category && 'environment-link-leakage' === item.code && item.current_url && item.suggested_url;
	}

	function diagnosticIdentity( item, index ) {
		if ( item && item.id ) return String( item.id );
		return [ item?.classification || '', item?.category || '', item?.code || '', item?.current_url || '', item?.suggested_url || '', index ].join( '|' );
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

	function sourceId( source ) {
		return Number( source && ( source.id || source.post_id || source.resource_id ) || 0 );
	}

	function sourceKind( source ) {
		return source && typeof source.kind === 'string' ? source.kind : '';
	}

	function sourceOccurrences( source ) {
		return Math.max( 1, Number( source && source.occurrences || 1 ) );
	}

	function sourceUrls( source, item ) {
		const own = source && Array.isArray( source.urls )
			? source.urls.filter( ( value ) => typeof value === 'string' && value )
			: [];
		const grouped = item && Array.isArray( item.current_urls )
			? item.current_urls.filter( ( value ) => typeof value === 'string' && value )
			: [];
		const urls = own.length ? own : ( grouped.length ? grouped : ( item && item.current_url ? [ item.current_url ] : [] ) );
		return [ ...new Set( urls ) ];
	}

	function decodeHtml( value ) {
		const textarea = document.createElement( 'textarea' );
		textarea.innerHTML = String( value || '' );
		return textarea.value;
	}

	function canonicalUrl( value, base ) {
		try {
			const url = new URL( decodeHtml( value ), base );
			url.hash = '';
			let pathname = url.pathname || '/';
			if ( pathname.length > 1 ) pathname = pathname.replace( /\/+$/, '' );
			return `${ url.protocol.toLowerCase() }//${ url.host.toLowerCase() }${ pathname }${ url.search }`;
		} catch ( error ) {
			return '';
		}
	}

	function escapeHref( value, quote ) {
		let escaped = String( value || '' ).replace( /&/g, '&amp;' );
		if ( '"' === quote ) escaped = escaped.replace( /"/g, '&quot;' );
		if ( "'" === quote ) escaped = escaped.replace( /'/g, '&#039;' );
		return escaped;
	}

	function rewriteAnchors( html, item, resource, source ) {
		const observed = sourceUrls( source, item );
		const targets = new Set( observed.map( ( url ) => canonicalUrl( url, resource.permalink ) ).filter( Boolean ) );
		let matches = 0;
		const next = String( html || '' ).replace( /<a\b[^>]*>/gi, ( tag ) => tag.replace( /(\bhref\s*=\s*)(?:"([^"]*)"|'([^']*)'|([^\s>]+))/i, ( attribute, prefix, doubleQuoted, singleQuoted, unquoted ) => {
			const encodedHref = undefined !== doubleQuoted ? doubleQuoted : ( undefined !== singleQuoted ? singleQuoted : unquoted );
			const canonical = canonicalUrl( decodeHtml( encodedHref ), resource.permalink || window.location.href );
			if ( ! canonical || ! targets.has( canonical ) ) return attribute;
			matches += 1;
			const quote = undefined !== singleQuoted ? "'" : '"';
			return `${ prefix }${ quote }${ escapeHref( item.suggested_url, quote ) }${ quote }`;
		} ) );
		return { value: next, matches };
	}

	function deriveContentChanges( resource, item, source ) {
		const original = String( resource.content || '' );
		const rewritten = rewriteAnchors( original, item, resource, source );
		const changes = {};
		if ( rewritten.value !== original ) changes.content = rewritten.value;
		return {
			changes,
			matches: rewritten.matches,
			expected: sourceOccurrences( source ),
			urls: sourceUrls( source, item ),
		};
	}

	function contentIdempotencyKey( item, id, fingerprint ) {
		const seed = `${ item.id || item.code }-${ id }-${ fingerprint.slice( 0, 20 ) }`;
		return `manager-content-nav-${ seed }`.replace( /[^a-zA-Z0-9._-]/g, '-' ).slice( 0, 128 );
	}

	function menuIdempotencyKey( item, id, fingerprint ) {
		const seed = `${ item.id || item.code }-${ id }-${ fingerprint.slice( 0, 20 ) }`;
		return `manager-menu-nav-${ seed }`.replace( /[^a-zA-Z0-9._-]/g, '-' ).slice( 0, 128 );
	}

	function isPublished( entry ) {
		return 'content' === entry.kind && 'publish' === entry.resource.status;
	}

	function isMenuEntry( entry ) {
		return 'menu' === entry.kind;
	}

	function stillPresent( data, item ) {
		const actionable = data && data.actionable_diagnostics && Array.isArray( data.actionable_diagnostics.items ) ? data.actionable_diagnostics.items : [];
		if ( item && item.id ) return actionable.some( ( candidate ) => candidate && String( candidate.id || '' ) === String( item.id ) );
		return actionable.some( ( candidate ) => candidate && candidate.code === item.code && candidate.suggested_url === item.suggested_url );
	}

	function mergeSource( existing, source ) {
		if ( ! existing ) return Object.assign( {}, source );
		existing.urls = [ ...new Set( sourceUrls( existing, null ).concat( sourceUrls( source, null ) ) ) ];
		existing.occurrences = sourceOccurrences( existing ) + sourceOccurrences( source );
		return existing;
	}

	function accountSources( item ) {
		const sources = Array.isArray( item.sources ) ? item.sources : [];
		const contentSources = new Map();
		const menuSources = new Map();
		const renderedObservations = [];
		const blocked = [];

		sources.forEach( ( source ) => {
			const kind = sourceKind( source );
			const id = sourceId( source );
			if ( 'content' === kind ) {
				if ( id <= 0 ) blocked.push( 'Origen content sin ID editable.' );
				else contentSources.set( id, mergeSource( contentSources.get( id ), source ) );
			}
			if ( 'menu' === kind ) {
				if ( id <= 0 ) blocked.push( 'Origen menú sin ID editable.' );
				else menuSources.set( id, mergeSource( menuSources.get( id ), source ) );
			}
		} );

		sources.forEach( ( source ) => {
			const kind = sourceKind( source );
			if ( 'content' === kind || 'menu' === kind ) return;
			if ( 'rendered' === kind ) {
				renderedObservations.push( sourceId( source ) );
				return;
			}
			blocked.push( `Origen ${ kind || 'desconocido' } #${ sourceId( source ) || '—' } no tiene adaptador de escritura seguro.` );
		} );

		if ( ! contentSources.size && ! menuSources.size && renderedObservations.length ) {
			blocked.push( 'Solo hay observaciones renderizadas; no existe un origen editable determinista para preparar Apply.' );
		}

		return {
			contentSources,
			menuSources,
			renderedObservations: [ ...new Set( renderedObservations ) ],
			blocked,
			sourceCount: sources.length,
		};
	}

	async function refreshIntelligence() {
		const data = await window.wp.apiFetch( { path: '/seo-geo-manager/v1/site/intelligence?include_rendered=1' } );
		window.dispatchEvent( new CustomEvent( 'seo-geo-manager:analysis', { detail: data } ) );
		return data;
	}

	async function resolveContentPreview( id, source, item, snapshot, status ) {
		status.textContent = `Resolviendo recurso content #${ id } y reconciliando sus href reales…`;
		const resource = await window.wp.apiFetch( { path: `/seo-geo-manager/v1/content/${ id }` } );
		const derived = deriveContentChanges( resource, item, source );
		if ( derived.matches !== derived.expected ) {
			throw new Error( `#${ id }: se esperaban ${ derived.expected } enlace(s) y se encontraron ${ derived.matches } href(s) equivalentes dentro de <a>.` );
		}
		if ( ! Object.keys( derived.changes ).length || derived.matches < 1 ) {
			throw new Error( `#${ id }: no se pudo preparar un cambio acotado al atributo href.` );
		}
		const payload = {
			schema_version: 1,
			target: { id, expected_fingerprint: resource.fingerprint },
			changes: derived.changes,
			allow_published_target: false,
		};
		const preview = await window.wp.apiFetch( { path: '/seo-geo-manager/v1/changes/preview', method: 'POST', data: payload } );
		if ( ! preview.has_changes ) throw new Error( `#${ id }: Preview no contiene cambios efectivos.` );
		return { kind: 'content', resource, source, payload, preview, environment: snapshot.environment, matches: derived.matches };
	}

	async function resolveMenuPreview( id, source, item, snapshot, status ) {
		status.textContent = `Resolviendo menú #${ id } con el adaptador de navegación…`;
		const previewPayload = {
			schema_version: 1,
			menu_id: id,
			current_urls: sourceUrls( source, item ),
			expected_occurrences: sourceOccurrences( source ),
			suggested_url: item.suggested_url,
			allow_public_navigation: false,
		};
		const preview = await window.wp.apiFetch( { path: '/seo-geo-manager/v1/navigation/changes/preview', method: 'POST', data: previewPayload } );
		if ( ! preview.has_changes || ! preview.fingerprint ) throw new Error( `Menú #${ id }: Preview de navegación no produjo un cambio verificable.` );
		const payload = Object.assign( {}, previewPayload, { expected_fingerprint: preview.fingerprint } );
		return {
			kind: 'menu',
			menuId: id,
			menuName: preview.menu_name || `Menú #${ id }`,
			source,
			payload,
			preview,
			environment: snapshot.environment,
			matches: Array.isArray( preview.matches ) ? preview.matches.length : sourceOccurrences( source ),
		};
	}

	async function resolvePreviews( item, status ) {
		const accounting = accountSources( item );
		const resolved = [];
		const blocked = accounting.blocked.slice();
		if ( ! accounting.contentSources.size && ! accounting.menuSources.size ) {
			return { resolved, blocked: [ ...new Set( blocked ) ], accounting };
		}

		const snapshot = await window.wp.apiFetch( { path: '/seo-geo-manager/v1/site/snapshot' } );

		for ( const [ id, source ] of accounting.contentSources.entries() ) {
			try {
				resolved.push( await resolveContentPreview( id, source, item, snapshot, status ) );
			} catch ( error ) {
				blocked.push( error && error.message ? error.message : `#${ id }: preview content fallido.` );
			}
		}

		for ( const [ id, source ] of accounting.menuSources.entries() ) {
			try {
				resolved.push( await resolveMenuPreview( id, source, item, snapshot, status ) );
			} catch ( error ) {
				blocked.push( error && error.message ? error.message : `Menú #${ id}: preview fallido.` );
			}
		}

		const preparedContent = resolved.filter( ( entry ) => 'content' === entry.kind ).length;
		const preparedMenus = resolved.filter( ( entry ) => 'menu' === entry.kind ).length;
		if ( preparedContent !== accounting.contentSources.size ) blocked.push( 'No todos los orígenes content quedaron preparados.' );
		if ( preparedMenus !== accounting.menuSources.size ) blocked.push( 'No todos los orígenes de menú quedaron preparados.' );

		return { resolved, blocked: [ ...new Set( blocked ) ], accounting };
	}

	async function preflightEntries( entries ) {
		for ( const entry of entries ) {
			if ( 'content' === entry.kind ) {
				const current = await window.wp.apiFetch( { path: `/seo-geo-manager/v1/content/${ entry.resource.id }` } );
				if ( current.fingerprint !== entry.resource.fingerprint ) throw new Error( `#${ current.id }: cambió después del Preview; vuelve a preparar la corrección.` );
				continue;
			}

			const preview = await window.wp.apiFetch( {
				path: '/seo-geo-manager/v1/navigation/changes/preview',
				method: 'POST',
				data: entry.payload,
			} );
			if ( preview.fingerprint !== entry.payload.expected_fingerprint ) throw new Error( `Menú #${ entry.menuId }: cambió después del Preview; vuelve a preparar la corrección.` );
		}
	}

	async function applyEntry( entry, item, publishedApproved, navigationApproved ) {
		if ( 'menu' === entry.kind ) {
			if ( ! navigationApproved ) throw new Error( `Menú #${ entry.menuId }: la navegación pública no tiene autorización explícita.` );
			const payload = Object.assign( {}, entry.payload, {
				allow_public_navigation: true,
				idempotency_key: menuIdempotencyKey( item, entry.menuId, entry.payload.expected_fingerprint ),
				environment_fingerprint: entry.environment && entry.environment.fingerprint ? entry.environment.fingerprint : '',
			} );
			return window.wp.apiFetch( { path: '/seo-geo-manager/v1/navigation/changes/apply', method: 'POST', data: payload } );
		}

		const current = await window.wp.apiFetch( { path: `/seo-geo-manager/v1/content/${ entry.resource.id }` } );
		if ( current.fingerprint !== entry.resource.fingerprint ) throw new Error( `#${ current.id }: cambió después del Preview; se cancela el grupo.` );
		if ( 'publish' === current.status && ! publishedApproved ) throw new Error( `#${ current.id }: el recurso publicado no tiene autorización explícita.` );
		const payload = Object.assign( {}, entry.payload, {
			allow_published_target: 'publish' === current.status,
			idempotency_key: contentIdempotencyKey( item, current.id, current.fingerprint ),
			environment_fingerprint: entry.environment && entry.environment.fingerprint ? entry.environment.fingerprint : '',
		} );
		return window.wp.apiFetch( { path: '/seo-geo-manager/v1/changes/apply', method: 'POST', data: payload } );
	}

	async function verifyOperation( operation ) {
		if ( 'navigation-menu' === operation.adapter ) {
			const stored = await window.wp.apiFetch( { path: `/seo-geo-manager/v1/navigation/changes/${ operation.operation_id }` } );
			return { stored, ok: 'applied' === stored.status && stored.after_fingerprint === operation.after_fingerprint };
		}
		const stored = await window.wp.apiFetch( { path: `/seo-geo-manager/v1/changes/${ operation.operation_id }` } );
		const current = await window.wp.apiFetch( { path: `/seo-geo-manager/v1/content/${ operation.target_id }` } );
		return { stored, current, ok: 'applied' === stored.status && current.fingerprint === stored.after_fingerprint };
	}

	async function rollbackOperation( operation ) {
		const environment = operation.environment || {};
		if ( 'navigation-menu' === operation.adapter ) {
			return window.wp.apiFetch( {
				path: `/seo-geo-manager/v1/navigation/changes/${ operation.operation_id }/rollback`,
				method: 'POST',
				data: { environment_fingerprint: environment.fingerprint || '' },
			} );
		}
		return window.wp.apiFetch( {
			path: `/seo-geo-manager/v1/changes/${ operation.operation_id }/rollback`,
			method: 'POST',
			data: { environment_fingerprint: environment.fingerprint || '' },
		} );
	}

	async function compensateOperations( operations ) {
		const failures = [];
		for ( const operation of operations.slice().reverse() ) {
			try {
				await rollbackOperation( operation );
			} catch ( error ) {
				failures.push( `${ 'navigation-menu' === operation.adapter ? 'menú' : '#' }${ operation.target_id }: ${ error && error.message ? error.message : 'rollback fallido' }` );
			}
		}
		return failures;
	}

	function addApproval( panel, text ) {
		const label = document.createElement( 'label' );
		label.className = 'seo-geo-manager-correction__published-approval';
		label.hidden = true;
		const input = document.createElement( 'input' );
		input.type = 'checkbox';
		input.checked = false;
		label.appendChild( input );
		label.appendChild( document.createTextNode( ` ${ text }` ) );
		panel.appendChild( label );
		return { label, input };
	}

	function renderPreparation( item ) {
		const panel = ensureWorkspace();
		panel.hidden = false;
		panel.replaceChildren();
		panel.appendChild( makeText( 'p', 'Correction workflow', 'seo-geo-manager-admin__eyebrow' ) );
		panel.appendChild( makeText( 'h2', 'Corrección segura' ) );
		panel.appendChild( makeText( 'p', `${ item.current_url } → ${ item.suggested_url }`, 'description' ) );
		const variants = Array.isArray( item.current_urls ) ? item.current_urls : [];
		if ( variants.length > 1 ) panel.appendChild( makeText( 'p', `${ variants.length } variantes equivalentes detectadas para el mismo destino.`, 'description' ) );

		const status = makeText( 'p', 'Preparando Preview REST y reconciliando orígenes editables…', 'seo-geo-manager-correction__safety' );
		panel.appendChild( status );
		const results = document.createElement( 'div' );
		panel.appendChild( results );

		const publishedApproval = addApproval( panel, 'Autorizo modificar los recursos publicados incluidos en este Preview.' );
		const navigationApproval = addApproval( panel, 'Autorizo modificar los enlaces del menú público incluidos en este Preview.' );

		const actions = document.createElement( 'div' );
		actions.className = 'seo-geo-manager-correction__actions';
		panel.appendChild( actions );
		const close = makeText( 'button', 'Cerrar' );
		close.type = 'button';
		close.className = 'button';
		close.addEventListener( 'click', () => { panel.hidden = true; } );
		actions.appendChild( close );
		const apply = makeText( 'button', 'Aplicar corrección' );
		apply.type = 'button';
		apply.className = 'button button-primary';
		apply.disabled = true;
		actions.appendChild( apply );

		resolvePreviews( item, status ).then( ( result ) => {
			results.replaceChildren();
			results.appendChild( makeText( 'p', `Orígenes: ${ result.accounting.sourceCount } · content: ${ result.accounting.contentSources.size } · menús: ${ result.accounting.menuSources.size } · observaciones renderizadas: ${ result.accounting.renderedObservations.length }`, 'description' ) );
			result.resolved.forEach( ( entry ) => {
				if ( 'menu' === entry.kind ) {
					results.appendChild( makeText( 'p', `✓ ${ entry.menuName } (#${ entry.menuId }) · Preview válido · ${ entry.matches } enlace(s) de menú reconciliado(s)` ) );
					return;
				}
				results.appendChild( makeText( 'p', `✓ #${ entry.resource.id } · Preview válido · content · ${ entry.matches } href(s) reconciliado(s)${ isPublished( entry ) ? ' · publicado' : '' }` ) );
			} );
			result.blocked.forEach( ( reason ) => results.appendChild( makeText( 'p', `⚠ ${ reason }` ) ) );

			const fullySafe = result.resolved.length > 0 && result.blocked.length === 0;
			const hasPublished = result.resolved.some( isPublished );
			const hasNavigation = result.resolved.some( isMenuEntry );
			publishedApproval.label.hidden = ! ( fullySafe && hasPublished );
			navigationApproval.label.hidden = ! ( fullySafe && hasNavigation );

			function syncApply() {
				const publishedOk = ! hasPublished || publishedApproval.input.checked;
				const navigationOk = ! hasNavigation || navigationApproval.input.checked;
				apply.disabled = ! fullySafe || ! publishedOk || ! navigationOk;
				if ( ! fullySafe ) status.textContent = 'Apply bloqueado: todos los orígenes editables deben quedar reconciliados.';
				else if ( ! publishedOk ) status.textContent = 'Preview completo. Hay recursos publicados: falta autorización explícita.';
				else if ( ! navigationOk ) status.textContent = 'Preview completo. Hay navegación pública: falta autorización explícita.';
				else status.textContent = 'Preview completo y autorizado. Apply requiere confirmación final.';
			}

			publishedApproval.input.addEventListener( 'change', syncApply );
			navigationApproval.input.addEventListener( 'change', syncApply );
			syncApply();
			if ( ! fullySafe ) return;

			apply.addEventListener( 'click', async () => {
				const publishedApproved = ! hasPublished || publishedApproval.input.checked;
				const navigationApproved = ! hasNavigation || navigationApproval.input.checked;
				if ( ! publishedApproved || ! navigationApproved ) {
					syncApply();
					return;
				}

				const publishedCount = result.resolved.filter( isPublished ).length;
				const menuCount = result.resolved.filter( isMenuEntry ).length;
				let warning = '';
				if ( publishedCount ) warning += `\n\n${ publishedCount } recurso(s) publicados se modificarán en la web visible.`;
				if ( menuCount ) warning += `\n${ menuCount } menú(s) público(s) se modificarán.`;
				if ( ! window.confirm( `Aplicar ${ result.resolved.length } corrección(es) validadas como un grupo? Si una falla, el Manager intentará revertir las anteriores.${ warning }` ) ) return;

				apply.disabled = true;
				publishedApproval.input.disabled = true;
				navigationApproval.input.disabled = true;
				status.textContent = 'Preflight final de fingerprints y menús…';
				const operations = [];

				try {
					await preflightEntries( result.resolved );
					status.textContent = 'Aplicando grupo con autorización, idempotencia y guard de entorno…';
					for ( const entry of result.resolved ) {
						operations.push( await applyEntry( entry, item, publishedApproved, navigationApproved ) );
					}
					const verifications = [];
					for ( const operation of operations ) verifications.push( await verifyOperation( operation ) );
					if ( ! verifications.every( ( verification ) => verification.ok ) ) throw new Error( 'Verify técnico detectó una discrepancia en el grupo.' );

					status.textContent = 'Verify técnico correcto. Reanalizando Site Intelligence renderizado…';
					const refreshed = await refreshIntelligence();
					status.textContent = stillPresent( refreshed, item )
						? 'Aplicado y verificado técnicamente, pero el diagnóstico sigue presente. Requiere un adaptador adicional; no se marca como cerrado.'
						: `Corrección cerrada: ${ operations.length } operación(es) aplicadas y el diagnóstico ya no aparece.`;

					operations.forEach( ( operation ) => {
						const label = 'navigation-menu' === operation.adapter ? `Rollback menú #${ operation.target_id }` : `Rollback #${ operation.target_id }`;
						const rollback = makeText( 'button', label );
						rollback.type = 'button';
						rollback.className = 'button';
						rollback.addEventListener( 'click', async () => {
							if ( ! window.confirm( `Revertir la operación ${ operation.operation_id }?` ) ) return;
							rollback.disabled = true;
							try {
								await rollbackOperation( operation );
								await refreshIntelligence();
								status.textContent = `${ label } completado y diagnóstico actualizado.`;
							} catch ( error ) {
								rollback.disabled = false;
								status.textContent = error && error.message ? error.message : 'Rollback bloqueado.';
							}
						} );
						actions.appendChild( rollback );
					} );
				} catch ( error ) {
					if ( operations.length ) {
						status.textContent = 'Fallo durante el grupo. Ejecutando rollback compensatorio…';
						const rollbackFailures = await compensateOperations( operations );
						await refreshIntelligence().catch( () => null );
						status.textContent = rollbackFailures.length
							? `El grupo falló y hubo errores al compensar: ${ rollbackFailures.join( ' · ' ) }`
							: `El grupo falló y ${ operations.length } operación(es) previas fueron revertidas automáticamente. ${ error && error.message ? error.message : '' }`;
					} else {
						status.textContent = error && error.message ? error.message : 'Apply bloqueado o fallido antes de escribir.';
					}
					publishedApproval.input.disabled = false;
					navigationApproval.input.disabled = false;
					syncApply();
				}
			} );
		} ).catch( ( error ) => {
			status.textContent = error && error.message ? error.message : 'No se pudo preparar el Preview.';
		} );

		panel.scrollIntoView( { behavior: 'smooth', block: 'start' } );
	}

	function wireButtons() {
		const actionableItems = items();
		const itemMap = new Map();
		actionableItems.forEach( ( item, index ) => itemMap.set( diagnosticIdentity( item, index ), item ) );
		actionablesBox.querySelectorAll( '.seo-geo-manager-admin__check[data-seo-geo-diagnostic-id]' ).forEach( ( row ) => {
			const item = itemMap.get( row.dataset.seoGeoDiagnosticId );
			const existing = row.querySelector( '[data-seo-geo-prepare-correction]' );
			if ( ! eligible( item ) ) {
				if ( existing ) existing.remove();
				return;
			}
			if ( existing ) return;
			const button = makeText( 'button', 'Preparar corrección' );
			button.type = 'button';
			button.className = 'button button-secondary seo-geo-manager-correction__prepare';
			button.setAttribute( 'data-seo-geo-prepare-correction', row.dataset.seoGeoDiagnosticId );
			button.addEventListener( 'click', () => renderPreparation( item ) );
			row.appendChild( button );
		} );
	}

	const observer = new MutationObserver( wireButtons );
	observer.observe( actionablesBox, { childList: true, subtree: true } );
	observer.observe( rawBox, { childList: true, characterData: true, subtree: true } );
	wireButtons();
}() );
