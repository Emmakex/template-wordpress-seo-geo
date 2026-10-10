( function () {
	'use strict';

	const root = document.getElementById( 'seo-geo-manager-dashboard' );
	if ( ! root || ! window.wp || ! window.wp.apiFetch ) return;

	const raw = root.querySelector( '[data-seo-geo-field-raw]' );
	if ( ! raw ) return;

	let panel = null;
	let latestReport = null;

	function text( tag, value, className ) {
		const node = document.createElement( tag );
		node.textContent = String( value ?? '' );
		if ( className ) node.className = className;
		return node;
	}

	function fact( label, value ) {
		const row = document.createElement( 'p' );
		row.appendChild( text( 'strong', `${ label }: ` ) );
		row.appendChild( document.createTextNode( String( value ?? '—' ) ) );
		return row;
	}

	function ensurePanel() {
		if ( panel && panel.isConnected ) return panel;
		panel = document.createElement( 'section' );
		panel.className = 'seo-geo-manager-admin__panel seo-geo-manager-slug-repair';
		panel.hidden = true;
		panel.setAttribute( 'aria-live', 'polite' );
		const fieldGate = root.querySelector( '[data-seo-geo-field-gate]' );
		if ( fieldGate ) fieldGate.after( panel );
		else root.appendChild( panel );
		return panel;
	}

	function renderRepairRows( repairs, container ) {
		const list = document.createElement( 'ol' );
		repairs.forEach( ( repair ) => {
			list.appendChild( text( 'li', `#${ repair.post_id || '—' } · ${ repair.before_slug || '—' } → ${ repair.target_slug || repair.historical_slug || '—' }` ) );
		} );
		container.appendChild( list );
	}

	async function rollbackOperation( operation, report, status, actions ) {
		if ( ! window.confirm( 'Se restaurarán exactamente los slugs locales dañados que existían antes de esta reparación. ¿Continuar con el rollback?' ) ) return;
		status.textContent = 'Restaurando slugs anteriores y verificando rollback…';
		try {
			const rolledBack = await window.wp.apiFetch( {
				path: `/seo-geo-manager/v1/permalinks/operations/${ operation.operation_id }/rollback`,
				method: 'POST',
				data: { environment_fingerprint: report.environment?.fingerprint || '' }
			} );
			status.textContent = rolledBack.rollback_verified
				? 'Rollback verificado. Los slugs dañados previos fueron restaurados exactamente.'
				: 'Rollback completado.';
			actions.replaceChildren();
		} catch ( error ) {
			status.textContent = error && error.message ? error.message : 'No se pudo completar el rollback de slugs.';
		}
	}

	async function previewRepair( report, container, status, previewButton ) {
		const plan = report.permalinks?.authoritative_plan || {};
		const legacyBaseUrl = report.options?.legacy_base_url || '';
		previewButton.disabled = true;
		status.textContent = 'Revalidando autoridad, estado local y unicidad de los slugs…';
		try {
			const preview = await window.wp.apiFetch( {
				path: '/seo-geo-manager/v1/permalinks/slug-repair/preview',
				method: 'POST',
				data: {
					legacy_base_url: legacyBaseUrl,
					authority_fingerprint: plan.authority_fingerprint || '',
					plan_fingerprint: plan.plan_fingerprint || ''
				}
			} );

			container.replaceChildren();
			container.appendChild( fact( 'Reparaciones verificadas', preview.repair_count ?? 0 ) );
			container.appendChild( fact( 'Target SEO/GEO', preview.target_structure || '—' ) );
			container.appendChild( fact( 'Permalink structure se modificará', 'no' ) );
			container.appendChild( fact( 'Runtime 301 se modificará', 'no' ) );
			container.appendChild( fact( 'Fingerprint de reparación', preview.repair_fingerprint || '—' ) );
			const repairs = Array.isArray( preview.repairs ) ? preview.repairs : [];
			renderRepairRows( repairs, container );

			if ( ! preview.safe_to_apply ) {
				status.textContent = 'La reparación protegida no supera todavía todas las guardas. No se ha escrito nada.';
				previewButton.disabled = false;
				return;
			}

			const actions = document.createElement( 'div' );
			actions.className = 'seo-geo-manager-admin__actions';
			const apply = text( 'button', `Aplicar reparación de ${ preview.repair_count } slug(s)` );
			apply.type = 'button';
			apply.className = 'button button-primary';
			actions.appendChild( apply );
			container.appendChild( actions );
			status.textContent = 'Preview verificado. La escritura sigue pendiente de confirmación explícita.';

			apply.addEventListener( 'click', async () => {
				if ( ! window.confirm( `Se repararán ${ preview.repair_count } post_name dañados usando exclusivamente la autoridad histórica verificada. No se cambiará permalink_structure ni se activarán 301 en esta operación. ¿Continuar?` ) ) return;
				apply.disabled = true;
				status.textContent = 'Aplicando slugs verificados y comprobando el plan resultante…';
				try {
					const operation = await window.wp.apiFetch( {
						path: '/seo-geo-manager/v1/permalinks/slug-repair/apply',
						method: 'POST',
						data: {
							legacy_base_url: legacyBaseUrl,
							authority_fingerprint: preview.authority_fingerprint || '',
							plan_fingerprint: preview.plan_fingerprint || '',
							repair_fingerprint: preview.repair_fingerprint || '',
							idempotency_key: `slug-repair-${ ( preview.repair_fingerprint || '' ).slice( 0, 64 ) }-${ Date.now() }`,
							confirm_slug_repair: true,
							environment_fingerprint: report.environment?.fingerprint || ''
						}
					} );

					container.appendChild( text( 'h4', 'Reparación aplicada y verificada' ) );
					container.appendChild( fact( 'Operación', operation.operation_id || '—' ) );
					container.appendChild( fact( 'Slugs reparados', operation.repair_count ?? 0 ) );
					container.appendChild( fact( 'Nuevo target', operation.next_target_structure || '—' ) );
					container.appendChild( fact( '301 previstos tras la reparación', operation.next_planned_redirects ?? 0 ) );
					status.textContent = 'Slugs reparados. La estructura y los 301 siguen sin tocarse; ejecuta un Field Gate nuevo antes de cualquier operación de permalinks.';
					actions.replaceChildren();

					const rollback = text( 'button', 'Revertir reparación de slugs' );
					rollback.type = 'button';
					rollback.className = 'button button-secondary';
					rollback.addEventListener( 'click', () => rollbackOperation( operation, report, status, actions ) );
					actions.appendChild( rollback );

					const rerun = text( 'button', 'Volver a ejecutar Field Gate' );
					rerun.type = 'button';
					rerun.className = 'button button-primary';
					rerun.addEventListener( 'click', () => {
						const run = root.querySelector( '[data-seo-geo-field-run]' );
						if ( run && ! run.disabled ) run.click();
					} );
					actions.appendChild( rerun );
				} catch ( error ) {
					apply.disabled = false;
					status.textContent = error && error.message ? error.message : 'No se pudo completar la reparación protegida de slugs.';
				}
			} );
		} catch ( error ) {
			previewButton.disabled = false;
			status.textContent = error && error.message ? error.message : 'No se pudo previsualizar la reparación protegida de slugs.';
		}
	}

	function renderFromReport( report ) {
		latestReport = report;
		const target = ensurePanel();
		const plan = report?.permalinks?.authoritative_plan || {};
		const repairs = Array.isArray( plan.local_slug_repairs ) ? plan.local_slug_repairs : [];
		if ( ! repairs.length ) {
			target.hidden = true;
			target.replaceChildren();
			return;
		}

		target.hidden = false;
		target.replaceChildren();
		target.appendChild( text( 'p', 'Protected migration repair', 'seo-geo-manager-admin__eyebrow' ) );
		target.appendChild( text( 'h2', 'Reparar slugs dañados antes de permalinks' ) );
		target.appendChild( text( 'p', `El Field Gate ha aislado ${ repairs.length } post_name dañados. Esta operación solo repara esos slugs desde la autoridad histórica verificada; no cambia la estructura ni activa redirecciones.`, 'description' ) );
		target.appendChild( fact( 'Target previsto', plan.target_structure || '—' ) );
		target.appendChild( fact( 'Slugs pendientes', repairs.length ) );

		const result = document.createElement( 'div' );
		target.appendChild( result );
		const status = text( 'p', 'Ejecuta primero el preview protegido.', 'seo-geo-manager-correction__safety' );
		target.appendChild( status );
		const previewButton = text( 'button', 'Previsualizar reparación de slugs' );
		previewButton.type = 'button';
		previewButton.className = 'button button-secondary';
		previewButton.addEventListener( 'click', () => previewRepair( latestReport, result, status, previewButton ) );
		target.appendChild( previewButton );
	}

	function syncFromRaw() {
		const value = ( raw.textContent || '' ).trim();
		if ( ! value ) {
			const target = ensurePanel();
			target.hidden = true;
			return;
		}
		try {
			const report = JSON.parse( value );
			if ( report && report.field_gate ) renderFromReport( report );
		} catch ( error ) {
			const target = ensurePanel();
			target.hidden = true;
		}
	}

	new MutationObserver( syncFromRaw ).observe( raw, { childList: true, characterData: true, subtree: true } );
	syncFromRaw();
}() );
