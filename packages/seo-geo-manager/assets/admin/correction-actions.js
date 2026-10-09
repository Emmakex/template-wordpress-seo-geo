( function () {
	'use strict';

	const root = document.getElementById( 'seo-geo-manager-dashboard' );
	if ( ! root || ! window.wp || ! window.wp.apiFetch ) return;
	const rawBox = root.querySelector( '[data-seo-geo-raw]' );
	const actionablesBox = root.querySelector( '[data-seo-geo-actionables]' );
	if ( ! rawBox || ! actionablesBox ) return;
	let workspace = null;

	function makeText( tag, value, className ) { const node = document.createElement( tag ); node.textContent = String( value || '' ); if ( className ) node.className = className; return node; }
	function readDiagnostic() { try { return JSON.parse( ( rawBox.textContent || '' ).trim() ); } catch ( error ) { return null; } }
	function items() { const data = readDiagnostic(); return data && data.actionable_diagnostics && Array.isArray( data.actionable_diagnostics.items ) ? data.actionable_diagnostics.items : []; }
	function eligible( item ) { return item && 'auto-fixable' === item.classification && 'navigation' === item.category && 'environment-link-leakage' === item.code && item.current_url && item.suggested_url; }
	function diagnosticIdentity( item, index ) { if ( item && item.id ) return String( item.id ); return [ item?.classification || '', item?.category || '', item?.code || '', item?.current_url || '', item?.suggested_url || '', index ].join( '|' ); }
	function ensureWorkspace() { if ( workspace && workspace.isConnected ) return workspace; workspace = document.createElement( 'section' ); workspace.className = 'seo-geo-manager-admin__panel seo-geo-manager-correction'; workspace.hidden = true; workspace.setAttribute( 'aria-live', 'polite' ); ( actionablesBox.closest( '.seo-geo-manager-admin__panel' ) || actionablesBox ).after( workspace ); return workspace; }
	function sourceId( source ) { return Number( source && ( source.id || source.post_id || source.resource_id ) || 0 ); }
	function sourceKind( source ) { return source && typeof source.kind === 'string' ? source.kind : ''; }
	function exactCount( value, needle ) { if ( typeof value !== 'string' || ! needle ) return 0; return value.split( needle ).length - 1; }
	function replaceBounded( value, before, after ) { return typeof value === 'string' && value.includes( before ) ? value.split( before ).join( after ) : null; }
	function deriveChanges( resource, item ) {
		const changes = {}; let matches = 0;
		[ 'content', 'excerpt' ].forEach( ( field ) => {
			matches += exactCount( resource[ field ], item.current_url );
			const next = replaceBounded( resource[ field ], item.current_url, item.suggested_url );
			if ( null !== next && next !== resource[ field ] ) changes[ field ] = next;
		} );
		return { changes, matches };
	}
	function idempotencyKey( item, id, fingerprint ) { const seed = `${ item.id || item.code }-${ id }-${ fingerprint.slice( 0, 20 ) }`; return `manager-nav-${ seed }`.replace( /[^a-zA-Z0-9._-]/g, '-' ).slice( 0, 128 ); }
	function isPublished( entry ) { return 'publish' === entry.resource.status; }
	function stillPresent( data, item ) { const actionable = data && data.actionable_diagnostics && Array.isArray( data.actionable_diagnostics.items ) ? data.actionable_diagnostics.items : []; return actionable.some( ( candidate ) => candidate && candidate.code === item.code && candidate.current_url === item.current_url && candidate.suggested_url === item.suggested_url ); }

	function accountSources( item ) {
		const sources = Array.isArray( item.sources ) ? item.sources : [];
		const contentIds = new Set(); const blocked = []; const coveredRendered = [];
		sources.forEach( ( source ) => { if ( 'content' === sourceKind( source ) && sourceId( source ) > 0 ) contentIds.add( sourceId( source ) ); } );
		sources.forEach( ( source ) => {
			const kind = sourceKind( source ); const id = sourceId( source );
			if ( 'content' === kind ) { if ( id <= 0 ) blocked.push( 'Origen content sin ID editable.' ); return; }
			if ( 'rendered' === kind ) {
				if ( id > 0 && contentIds.has( id ) ) { coveredRendered.push( id ); return; }
				blocked.push( `Origen rendered #${ id || '—' } no está respaldado por un origen content editable; requiere adaptador Theme/render.` ); return;
			}
			if ( 'menu' === kind ) { blocked.push( `Origen menú #${ id || '—' } requiere el adaptador de navegación antes de Apply.` ); return; }
			blocked.push( `Origen ${ kind || 'desconocido' } #${ id || '—' } no tiene adaptador de escritura seguro.` );
		} );
		return { contentIds: [ ...contentIds ], blocked, coveredRendered: [ ...new Set( coveredRendered ) ], sourceCount: sources.length };
	}

	async function refreshIntelligence() {
		const data = await window.wp.apiFetch( { path: '/seo-geo-manager/v1/site/intelligence?include_rendered=1' } );
		window.dispatchEvent( new CustomEvent( 'seo-geo-manager:analysis', { detail: data } ) );
		return data;
	}

	async function resolvePreviews( item, status ) {
		const accounting = accountSources( item );
		const resolved = [], blocked = accounting.blocked.slice();
		if ( ! accounting.contentIds.length ) return { resolved, blocked: blocked.concat( [ 'El diagnóstico no expone orígenes content editables.' ] ), accounting };
		const snapshot = await window.wp.apiFetch( { path: '/seo-geo-manager/v1/site/snapshot' } );
		for ( const id of accounting.contentIds ) {
			try {
				status.textContent = `Resolviendo recurso content #${ id }…`;
				const resource = await window.wp.apiFetch( { path: `/seo-geo-manager/v1/content/${ id }` } );
				const derived = deriveChanges( resource, item );
				if ( ! Object.keys( derived.changes ).length || derived.matches < 1 ) { blocked.push( `#${ id }: el enlace no está en content/excerpt; el origen no puede considerarse resuelto.` ); continue; }
				const payload = { schema_version: 1, target: { id, expected_fingerprint: resource.fingerprint }, changes: derived.changes, allow_published_target: false };
				const preview = await window.wp.apiFetch( { path: '/seo-geo-manager/v1/changes/preview', method: 'POST', data: payload } );
				if ( ! preview.has_changes ) { blocked.push( `#${ id }: Preview no contiene cambios efectivos.` ); continue; }
				resolved.push( { resource, payload, preview, environment: snapshot.environment, matches: derived.matches } );
			} catch ( error ) { blocked.push( `#${ id }: ${ error && error.message ? error.message : 'preview fallido' }` ); }
		}
		if ( resolved.length !== accounting.contentIds.length ) blocked.push( 'No todos los orígenes content quedaron preparados.' );
		return { resolved, blocked: [ ...new Set( blocked ) ], accounting };
	}

	async function preflightEntries( entries ) {
		for ( const entry of entries ) {
			const current = await window.wp.apiFetch( { path: `/seo-geo-manager/v1/content/${ entry.resource.id }` } );
			if ( current.fingerprint !== entry.resource.fingerprint ) throw new Error( `#${ current.id }: cambió después del Preview; vuelve a preparar la corrección.` );
		}
	}

	async function applyEntry( entry, item, publishedApproved ) {
		const current = await window.wp.apiFetch( { path: `/seo-geo-manager/v1/content/${ entry.resource.id }` } );
		if ( current.fingerprint !== entry.resource.fingerprint ) throw new Error( `#${ current.id }: cambió después del Preview; se cancela el grupo.` );
		if ( 'publish' === current.status && ! publishedApproved ) throw new Error( `#${ current.id }: el recurso publicado no tiene autorización explícita.` );
		const payload = Object.assign( {}, entry.payload, {
			allow_published_target: 'publish' === current.status,
			idempotency_key: idempotencyKey( item, current.id, current.fingerprint ),
			environment_fingerprint: entry.environment && entry.environment.fingerprint ? entry.environment.fingerprint : '',
		} );
		return window.wp.apiFetch( { path: '/seo-geo-manager/v1/changes/apply', method: 'POST', data: payload } );
	}
	async function verifyOperation( operation ) { const stored = await window.wp.apiFetch( { path: `/seo-geo-manager/v1/changes/${ operation.operation_id }` } ); const current = await window.wp.apiFetch( { path: `/seo-geo-manager/v1/content/${ operation.target_id }` } ); return { stored, current, ok: 'applied' === stored.status && current.fingerprint === stored.after_fingerprint }; }
	async function rollbackOperation( operation ) { const environment = operation.environment || {}; return window.wp.apiFetch( { path: `/seo-geo-manager/v1/changes/${ operation.operation_id }/rollback`, method: 'POST', data: { environment_fingerprint: environment.fingerprint || '' } } ); }
	async function compensateOperations( operations ) {
		const failures = [];
		for ( const operation of operations.slice().reverse() ) {
			try { await rollbackOperation( operation ); } catch ( error ) { failures.push( `#${ operation.target_id }: ${ error && error.message ? error.message : 'rollback fallido' }` ); }
		}
		return failures;
	}

	function renderPreparation( item ) {
		const panel = ensureWorkspace(); panel.hidden = false; panel.replaceChildren();
		panel.appendChild( makeText( 'p', 'Correction workflow', 'seo-geo-manager-admin__eyebrow' ) ); panel.appendChild( makeText( 'h2', 'Corrección segura' ) ); panel.appendChild( makeText( 'p', `${ item.current_url } → ${ item.suggested_url }`, 'description' ) );
		const status = makeText( 'p', 'Preparando Preview REST y contabilizando orígenes…', 'seo-geo-manager-correction__safety' ); panel.appendChild( status );
		const results = document.createElement( 'div' ); panel.appendChild( results );
		const approval = document.createElement( 'label' ); approval.className = 'seo-geo-manager-correction__published-approval'; approval.hidden = true;
		const approvalInput = document.createElement( 'input' ); approvalInput.type = 'checkbox'; approvalInput.checked = false; approval.appendChild( approvalInput ); approval.appendChild( document.createTextNode( ' Autorizo modificar los recursos publicados incluidos en este Preview.' ) ); panel.appendChild( approval );
		const actions = document.createElement( 'div' ); actions.className = 'seo-geo-manager-correction__actions'; panel.appendChild( actions );
		const close = makeText( 'button', 'Cerrar' ); close.type = 'button'; close.className = 'button'; close.addEventListener( 'click', () => { panel.hidden = true; } ); actions.appendChild( close );
		const apply = makeText( 'button', 'Aplicar corrección' ); apply.type = 'button'; apply.className = 'button button-primary'; apply.disabled = true; actions.appendChild( apply );

		resolvePreviews( item, status ).then( ( result ) => {
			results.replaceChildren();
			results.appendChild( makeText( 'p', `Orígenes: ${ result.accounting.sourceCount } · content editables: ${ result.accounting.contentIds.length } · rendered cubiertos: ${ result.accounting.coveredRendered.length }`, 'description' ) );
			result.resolved.forEach( ( entry ) => results.appendChild( makeText( 'p', `✓ #${ entry.resource.id } · Preview válido · ${ Object.keys( entry.payload.changes ).join( ', ' ) } · ${ entry.matches } coincidencia(s)${ isPublished( entry ) ? ' · publicado' : '' }` ) ) );
			result.blocked.forEach( ( reason ) => results.appendChild( makeText( 'p', `⚠ ${ reason }` ) ) );
			const fullySafe = result.resolved.length > 0 && result.blocked.length === 0; const hasPublished = result.resolved.some( isPublished );
			approval.hidden = ! ( fullySafe && hasPublished );
			function syncApply() { apply.disabled = ! fullySafe || ( hasPublished && ! approvalInput.checked ); status.textContent = ! fullySafe ? 'Apply bloqueado: todos los orígenes deben quedar contabilizados y resueltos.' : hasPublished && ! approvalInput.checked ? 'Preview completo. Hay recursos publicados: falta autorización explícita.' : 'Preview completo, orígenes contabilizados y autorizado. Apply requiere confirmación final.'; }
			approvalInput.addEventListener( 'change', syncApply ); syncApply(); if ( ! fullySafe ) return;
			apply.addEventListener( 'click', async () => {
				const publishedApproved = ! hasPublished || approvalInput.checked; if ( ! publishedApproved ) { syncApply(); return; }
				const publishedCount = result.resolved.filter( isPublished ).length; const warning = publishedCount ? `\n\n${ publishedCount } recurso(s) están publicados y se modificarán en la web visible.` : '';
				if ( ! window.confirm( `Aplicar ${ result.resolved.length } corrección(es) validadas como un grupo? Si una falla, el Manager intentará revertir las anteriores.${ warning }` ) ) return;
				apply.disabled = true; approvalInput.disabled = true; status.textContent = 'Preflight final de fingerprints…'; const operations = [];
				try {
					await preflightEntries( result.resolved );
					status.textContent = 'Aplicando grupo con autorización, fingerprint, idempotencia y guard de entorno…';
					for ( const entry of result.resolved ) operations.push( await applyEntry( entry, item, publishedApproved ) );
					const verifications = []; for ( const operation of operations ) verifications.push( await verifyOperation( operation ) ); const technicalVerified = verifications.every( ( verification ) => verification.ok );
					if ( ! technicalVerified ) throw new Error( 'Verify técnico detectó una discrepancia en el grupo.' );
					status.textContent = 'Verify técnico correcto. Reanalizando Site Intelligence renderizado…';
					const refreshed = await refreshIntelligence();
					status.textContent = stillPresent( refreshed, item ) ? 'Aplicado y verificado técnicamente, pero el diagnóstico sigue presente. Requiere un adaptador adicional; no se marca como cerrado.' : `Corrección cerrada: ${ operations.length } operación(es) aplicadas y el diagnóstico ya no aparece.`;
					operations.forEach( ( operation ) => {
						const rollback = makeText( 'button', `Rollback #${ operation.target_id }` ); rollback.type = 'button'; rollback.className = 'button';
						rollback.addEventListener( 'click', async () => { if ( ! window.confirm( `Revertir la operación ${ operation.operation_id }?` ) ) return; rollback.disabled = true; try { await rollbackOperation( operation ); await refreshIntelligence(); status.textContent = `Rollback completado para #${ operation.target_id } y diagnóstico actualizado.`; } catch ( error ) { rollback.disabled = false; status.textContent = error.message || 'Rollback bloqueado.'; } } ); actions.appendChild( rollback );
					} );
				} catch ( error ) {
					if ( operations.length ) {
						status.textContent = 'Fallo durante el grupo. Ejecutando rollback compensatorio…';
						const rollbackFailures = await compensateOperations( operations );
						await refreshIntelligence().catch( () => null );
						status.textContent = rollbackFailures.length ? `El grupo falló y hubo errores al compensar: ${ rollbackFailures.join( ' · ' ) }` : `El grupo falló y ${ operations.length } operación(es) previas fueron revertidas automáticamente. ${ error && error.message ? error.message : '' }`;
					} else {
						status.textContent = error && error.message ? error.message : 'Apply bloqueado o fallido antes de escribir.';
					}
					approvalInput.disabled = false; apply.disabled = hasPublished && ! approvalInput.checked;
				}
			} );
		} ).catch( ( error ) => { status.textContent = error && error.message ? error.message : 'No se pudo preparar el Preview.'; } );
		panel.scrollIntoView( { behavior: 'smooth', block: 'start' } );
	}

	function wireButtons() {
		const actionableItems = items(); const itemMap = new Map(); actionableItems.forEach( ( item, index ) => itemMap.set( diagnosticIdentity( item, index ), item ) );
		actionablesBox.querySelectorAll( '.seo-geo-manager-admin__check[data-seo-geo-diagnostic-id]' ).forEach( ( row ) => {
			const item = itemMap.get( row.dataset.seoGeoDiagnosticId ); const existing = row.querySelector( '[data-seo-geo-prepare-correction]' );
			if ( ! eligible( item ) ) { if ( existing ) existing.remove(); return; } if ( existing ) return;
			const button = makeText( 'button', 'Preparar corrección' ); button.type = 'button'; button.className = 'button button-secondary seo-geo-manager-correction__prepare'; button.setAttribute( 'data-seo-geo-prepare-correction', row.dataset.seoGeoDiagnosticId ); button.addEventListener( 'click', () => renderPreparation( item ) ); row.appendChild( button );
		} );
	}
	const observer = new MutationObserver( wireButtons ); observer.observe( actionablesBox, { childList: true, subtree: true } ); observer.observe( rawBox, { childList: true, characterData: true, subtree: true } ); wireButtons();
}() );
