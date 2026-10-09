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
	function replaceBounded( value, before, after ) { return typeof value === 'string' && value.includes( before ) ? value.split( before ).join( after ) : null; }
	function deriveChanges( resource, item ) { const changes = {}; [ 'content', 'excerpt' ].forEach( ( field ) => { const next = replaceBounded( resource[ field ], item.current_url, item.suggested_url ); if ( null !== next && next !== resource[ field ] ) changes[ field ] = next; } ); return changes; }
	function idempotencyKey( item, id, fingerprint ) { const seed = `${ item.id || item.code }-${ id }-${ fingerprint.slice( 0, 20 ) }`; return `manager-nav-${ seed }`.replace( /[^a-zA-Z0-9._-]/g, '-' ).slice( 0, 128 ); }
	function isPublished( entry ) { return 'publish' === entry.resource.status; }
	function stillPresent( data, item ) {
		const actionable = data && data.actionable_diagnostics && Array.isArray( data.actionable_diagnostics.items ) ? data.actionable_diagnostics.items : [];
		return actionable.some( ( candidate ) => candidate && candidate.code === item.code && candidate.current_url === item.current_url && candidate.suggested_url === item.suggested_url );
	}

	async function refreshIntelligence() {
		const data = await window.wp.apiFetch( { path: '/seo-geo-manager/v1/site/intelligence?include_rendered=0' } );
		window.dispatchEvent( new CustomEvent( 'seo-geo-manager:analysis', { detail: data } ) );
		return data;
	}

	async function resolvePreviews( item, status ) {
		const sourceIds = [ ...new Set( ( Array.isArray( item.sources ) ? item.sources : [] ).map( sourceId ).filter( ( id ) => id > 0 ) ) ];
		const resolved = [], blocked = [];
		if ( ! sourceIds.length ) return { resolved, blocked: [ 'El diagnóstico no expone IDs editables.' ] };
		const snapshot = await window.wp.apiFetch( { path: '/seo-geo-manager/v1/site/snapshot' } );
		for ( const id of sourceIds ) {
			try {
				status.textContent = `Resolviendo recurso #${ id }…`;
				const resource = await window.wp.apiFetch( { path: `/seo-geo-manager/v1/content/${ id }` } );
				const changes = deriveChanges( resource, item );
				if ( ! Object.keys( changes ).length ) { blocked.push( `#${ id }: el enlace no está en content/excerpt; requiere adaptador Theme/menú/metadatos.` ); continue; }
				const payload = { schema_version: 1, target: { id, expected_fingerprint: resource.fingerprint }, changes, allow_published_target: false };
				const preview = await window.wp.apiFetch( { path: '/seo-geo-manager/v1/changes/preview', method: 'POST', data: payload } );
				if ( ! preview.has_changes ) { blocked.push( `#${ id }: Preview no contiene cambios efectivos.` ); continue; }
				resolved.push( { resource, payload, preview, environment: snapshot.environment } );
			} catch ( error ) { blocked.push( `#${ id }: ${ error && error.message ? error.message : 'preview fallido' }` ); }
		}
		return { resolved, blocked };
	}

	async function applyEntry( entry, item, publishedApproved ) {
		const current = await window.wp.apiFetch( { path: `/seo-geo-manager/v1/content/${ entry.resource.id }` } );
		if ( current.fingerprint !== entry.resource.fingerprint ) throw new Error( `#${ current.id }: cambió después del Preview; vuelve a preparar la corrección.` );
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

	function renderPreparation( item ) {
		const panel = ensureWorkspace(); panel.hidden = false; panel.replaceChildren();
		panel.appendChild( makeText( 'p', 'Correction workflow', 'seo-geo-manager-admin__eyebrow' ) ); panel.appendChild( makeText( 'h2', 'Corrección segura' ) ); panel.appendChild( makeText( 'p', `${ item.current_url } → ${ item.suggested_url }`, 'description' ) );
		const status = makeText( 'p', 'Preparando Preview REST…', 'seo-geo-manager-correction__safety' ); panel.appendChild( status );
		const results = document.createElement( 'div' ); panel.appendChild( results );
		const approval = document.createElement( 'label' ); approval.className = 'seo-geo-manager-correction__published-approval'; approval.hidden = true;
		const approvalInput = document.createElement( 'input' ); approvalInput.type = 'checkbox'; approvalInput.checked = false; approval.appendChild( approvalInput ); approval.appendChild( document.createTextNode( ' Autorizo modificar los recursos publicados incluidos en este Preview.' ) ); panel.appendChild( approval );
		const actions = document.createElement( 'div' ); actions.className = 'seo-geo-manager-correction__actions'; panel.appendChild( actions );
		const close = makeText( 'button', 'Cerrar' ); close.type = 'button'; close.className = 'button'; close.addEventListener( 'click', () => { panel.hidden = true; } ); actions.appendChild( close );
		const apply = makeText( 'button', 'Aplicar corrección' ); apply.type = 'button'; apply.className = 'button button-primary'; apply.disabled = true; actions.appendChild( apply );

		resolvePreviews( item, status ).then( ( result ) => {
			results.replaceChildren(); result.resolved.forEach( ( entry ) => results.appendChild( makeText( 'p', `✓ #${ entry.resource.id } · Preview válido · ${ Object.keys( entry.payload.changes ).join( ', ' ) }${ isPublished( entry ) ? ' · publicado' : '' }` ) ) ); result.blocked.forEach( ( reason ) => results.appendChild( makeText( 'p', `⚠ ${ reason }` ) ) );
			const fullySafe = result.resolved.length > 0 && result.blocked.length === 0; const hasPublished = result.resolved.some( isPublished );
			approval.hidden = ! ( fullySafe && hasPublished );
			function syncApply() { apply.disabled = ! fullySafe || ( hasPublished && ! approvalInput.checked ); status.textContent = ! fullySafe ? 'Apply bloqueado: el grupo no está resuelto al 100 %.' : hasPublished && ! approvalInput.checked ? 'Preview completo. Hay recursos publicados: falta autorización explícita.' : 'Preview completo y autorizado. Apply requiere confirmación final.'; }
			approvalInput.addEventListener( 'change', syncApply ); syncApply(); if ( ! fullySafe ) return;
			apply.addEventListener( 'click', async () => {
				const publishedApproved = ! hasPublished || approvalInput.checked; if ( ! publishedApproved ) { syncApply(); return; }
				const publishedCount = result.resolved.filter( isPublished ).length; const warning = publishedCount ? `\n\n${ publishedCount } recurso(s) están publicados y se modificarán en la web visible.` : '';
				if ( ! window.confirm( `Aplicar ${ result.resolved.length } corrección(es) validadas? Se crearán revisiones y operaciones reversibles.${ warning }` ) ) return;
				apply.disabled = true; approvalInput.disabled = true; status.textContent = 'Aplicando con autorización, fingerprint, idempotencia y guard de entorno…'; const operations = [];
				try {
					for ( const entry of result.resolved ) operations.push( await applyEntry( entry, item, publishedApproved ) );
					const verifications = []; for ( const operation of operations ) verifications.push( await verifyOperation( operation ) ); const technicalVerified = verifications.every( ( verification ) => verification.ok );
					if ( ! technicalVerified ) {
						status.textContent = 'Aplicado, pero Verify técnico detectó una discrepancia. No continúes sin revisar.';
					} else {
						status.textContent = 'Verify técnico correcto. Reanalizando Site Intelligence…';
						const refreshed = await refreshIntelligence();
						status.textContent = stillPresent( refreshed, item ) ? 'Aplicado y verificado técnicamente, pero el diagnóstico sigue presente. Requiere revisar otros orígenes.' : `Corrección cerrada: ${ operations.length } operación(es) aplicadas y el diagnóstico ya no aparece.`;
					}
					operations.forEach( ( operation ) => {
						const rollback = makeText( 'button', `Rollback #${ operation.target_id }` ); rollback.type = 'button'; rollback.className = 'button';
						rollback.addEventListener( 'click', async () => {
							if ( ! window.confirm( `Revertir la operación ${ operation.operation_id }?` ) ) return;
							rollback.disabled = true;
							try { await rollbackOperation( operation ); await refreshIntelligence(); status.textContent = `Rollback completado para #${ operation.target_id } y diagnóstico actualizado.`; } catch ( error ) { rollback.disabled = false; status.textContent = error.message || 'Rollback bloqueado.'; }
						} ); actions.appendChild( rollback );
					} );
				} catch ( error ) { approvalInput.disabled = false; apply.disabled = hasPublished && ! approvalInput.checked; status.textContent = error && error.message ? error.message : 'Apply bloqueado o fallido.'; }
			} );
		} ).catch( ( error ) => { status.textContent = error && error.message ? error.message : 'No se pudo preparar el Preview.'; } );
		panel.scrollIntoView( { behavior: 'smooth', block: 'start' } );
	}

	function wireButtons() {
		const actionableItems = items();
		const itemMap = new Map();
		actionableItems.forEach( ( item, index ) => itemMap.set( diagnosticIdentity( item, index ), item ) );
		actionablesBox.querySelectorAll( '.seo-geo-manager-admin__check[data-seo-geo-diagnostic-id]' ).forEach( ( row ) => {
			const item = itemMap.get( row.dataset.seoGeoDiagnosticId );
			const existing = row.querySelector( '[data-seo-geo-prepare-correction]' );
			if ( ! eligible( item ) ) { if ( existing ) existing.remove(); return; }
			if ( existing ) return;
			const button = makeText( 'button', 'Preparar corrección' ); button.type = 'button'; button.className = 'button button-secondary seo-geo-manager-correction__prepare'; button.setAttribute( 'data-seo-geo-prepare-correction', row.dataset.seoGeoDiagnosticId ); button.addEventListener( 'click', () => renderPreparation( item ) ); row.appendChild( button );
		} );
	}
	const observer = new MutationObserver( wireButtons ); observer.observe( actionablesBox, { childList: true, subtree: true } ); observer.observe( rawBox, { childList: true, characterData: true, subtree: true } ); wireButtons();
}() );
