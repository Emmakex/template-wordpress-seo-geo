( function () {
	'use strict';

	const root = document.getElementById( 'seo-geo-manager-dashboard' );
	if ( ! root || ! window.wp || ! window.wp.apiFetch ) return;

	const slot = root.querySelector( '[data-seo-geo-finalization-actions]' );
	const fieldSlot = root.querySelector( '[data-seo-geo-field-slot]' );
	const fieldPanel = root.querySelector( '[data-seo-geo-field-gate]' );
	if ( fieldSlot && fieldPanel ) fieldSlot.appendChild( fieldPanel );

	const raw = root.querySelector( '[data-seo-geo-field-raw]' );
	if ( ! slot || ! raw ) return;

	let latestReport = null;

	function text( tag, value, className ) {
		const node = document.createElement( tag );
		node.textContent = String( value ?? '' );
		if ( className ) node.className = className;
		return node;
	}

	function metric( label, value ) {
		const node = document.createElement( 'div' );
		node.className = 'seo-geo-manager-focus__metric';
		node.appendChild( text( 'span', label ) );
		node.appendChild( text( 'strong', value ) );
		return node;
	}

	function rerunButton() {
		const button = text( 'button', 'Volver a comprobar' );
		button.type = 'button';
		button.className = 'button button-secondary';
		button.addEventListener( 'click', () => {
			const run = root.querySelector( '[data-seo-geo-field-run]' );
			if ( run && ! run.disabled ) run.click();
		} );
		return button;
	}

	function renderWaiting() {
		slot.replaceChildren();
		const panel = document.createElement( 'section' );
		panel.className = 'seo-geo-manager-admin__panel seo-geo-manager-focus';
		panel.appendChild( text( 'p', 'Paso 2', 'seo-geo-manager-admin__eyebrow' ) );
		panel.appendChild( text( 'h2', 'Aplicar la arquitectura SEO/GEO final' ) );
		panel.appendChild( text( 'p', 'Primero ejecuta la comprobación superior. El Manager mostrará aquí únicamente la operación segura que corresponda al estado real del sitio.', 'description' ) );
		slot.appendChild( panel );
	}

	function renderBlocked( report, plan, gate ) {
		slot.replaceChildren();
		const panel = document.createElement( 'section' );
		panel.className = 'seo-geo-manager-admin__panel seo-geo-manager-focus is-blocked';
		panel.appendChild( text( 'p', 'Paso 2', 'seo-geo-manager-admin__eyebrow' ) );
		panel.appendChild( text( 'h2', 'Todavía no podemos aplicar el cambio final' ) );
		panel.appendChild( text( 'p', gate.reason || plan.block_reason || 'El estado actual todavía requiere una corrección protegida.', 'seo-geo-manager-focus__message' ) );

		const metrics = document.createElement( 'div' );
		metrics.className = 'seo-geo-manager-focus__metrics';
		metrics.appendChild( metric( 'Target', plan.target_structure || '—' ) );
		metrics.appendChild( metric( 'Slugs pendientes', Number( plan.local_slug_repair_count || 0 ) ) );
		metrics.appendChild( metric( 'Colisiones', Number( plan.collision_count || 0 ) ) );
		metrics.appendChild( metric( '301 previstos', Number( plan.planned_redirects || 0 ) ) );
		panel.appendChild( metrics );

		const actions = document.createElement( 'div' );
		actions.className = 'seo-geo-manager-focus__actions';
		actions.appendChild( rerunButton() );
		panel.appendChild( actions );
		slot.appendChild( panel );
	}

	async function rollback( operation, report, status, actions ) {
		if ( ! window.confirm( 'Se restaurará la estructura anterior y se retirará el runtime 301 creado por esta operación. ¿Continuar?' ) ) return;
		status.textContent = 'Revirtiendo estructura y 301 de forma conjunta…';
		try {
			const result = await window.wp.apiFetch( {
				path: `/seo-geo-manager/v1/permalinks/operations/${ operation.operation_id }/rollback`,
				method: 'POST',
				data: { environment_fingerprint: report.environment?.fingerprint || '' }
			} );
			status.textContent = result.rollback_verified
				? 'Rollback verificado. La estructura anterior y el runtime 301 han sido restaurados.'
				: 'Rollback completado.';
			actions.replaceChildren( rerunButton() );
		} catch ( error ) {
			status.textContent = error && error.message ? error.message : 'No se pudo completar el rollback.';
		}
	}

	async function previewAtomic( report, plan, panel, status, previewButton ) {
		previewButton.disabled = true;
		status.textContent = 'Revalidando el mapa 301 y el estado actual antes de escribir…';
		try {
			const runtime = await window.wp.apiFetch( {
				path: '/seo-geo-manager/v1/permalinks/redirect-runtime/preview',
				method: 'POST',
				data: {
					legacy_base_url: report.options?.legacy_base_url || '',
					authority_fingerprint: plan.authority_fingerprint || ''
				}
			} );

			const preview = panel.querySelector( '[data-seo-geo-final-preview]' );
			preview.replaceChildren();
			preview.hidden = false;
			preview.appendChild( text( 'h3', 'Preview final verificado' ) );
			preview.appendChild( text( 'p', `Estructura objetivo: ${ runtime.expected_structure || plan.target_structure || '—' }` ) );
			preview.appendChild( text( 'p', `Redirecciones 301 de un salto: ${ Number( runtime.redirect_count || 0 ) }` ) );
			preview.appendChild( text( 'p', 'No se ha escrito nada todavía.', 'description' ) );

			if ( ! runtime.safe_to_activate || ! runtime.atomic_apply_available ) {
				status.textContent = runtime.block_reason || 'El preview no permite todavía la operación atómica.';
				previewButton.disabled = false;
				return;
			}

			const apply = text( 'button', 'Aplicar estructura + 301' );
			apply.type = 'button';
			apply.className = 'button button-primary button-hero';
			preview.appendChild( apply );
			status.textContent = 'Preview correcto. La escritura requiere todavía confirmación explícita.';

			apply.addEventListener( 'click', async () => {
				const redirectCount = Number( runtime.redirect_count || plan.planned_redirects || 0 );
				const targetStructure = runtime.expected_structure || plan.target_structure || '';
				if ( ! window.confirm( `Se cambiará la estructura a ${ targetStructure } y se activarán ${ redirectCount } redirecciones 301 verificadas. Si la verificación falla, el Manager revertirá la operación. ¿Continuar?` ) ) return;

				apply.disabled = true;
				previewButton.disabled = true;
				status.textContent = 'Aplicando estructura, activando 301 y verificando la operación…';
				try {
					const operation = await window.wp.apiFetch( {
						path: '/seo-geo-manager/v1/permalinks/redirect-apply',
						method: 'POST',
						data: {
							legacy_base_url: report.options?.legacy_base_url || '',
							authority_fingerprint: plan.authority_fingerprint || '',
							plan_fingerprint: plan.plan_fingerprint || '',
							current_fingerprint: plan.current_fingerprint || '',
							idempotency_key: `permalink-301-${ ( plan.plan_fingerprint || '' ).slice( 0, 48 ) }-${ Date.now() }`,
							confirm_permalink_change: true,
							confirm_redirect_runtime: true,
							environment_fingerprint: report.environment?.fingerprint || ''
						}
					} );

					preview.replaceChildren();
					preview.appendChild( text( 'h3', 'Operación aplicada y verificada' ) );
					preview.appendChild( text( 'p', `Estructura activa: ${ operation.after_structure || targetStructure || '—' }` ) );
					preview.appendChild( text( 'p', `301 activos: ${ Number( operation.planned_redirects || redirectCount ) }` ) );
					preview.appendChild( text( 'p', `Operación: ${ operation.operation_id || '—' }`, 'description' ) );
					status.textContent = operation.redirect_runtime_effective
						? 'Finalización SEO/GEO aplicada y verificada. Ejecuta una última comprobación del Field Gate.'
						: 'La operación terminó, pero el runtime 301 no figura como efectivo. Revisa antes de continuar.';

					const actions = document.createElement( 'div' );
					actions.className = 'seo-geo-manager-focus__actions';
					actions.appendChild( rerunButton() );
					const rollbackButton = text( 'button', 'Revertir operación' );
					rollbackButton.type = 'button';
					rollbackButton.className = 'button button-secondary';
					rollbackButton.addEventListener( 'click', () => rollback( operation, report, status, actions ) );
					actions.appendChild( rollbackButton );
					preview.appendChild( actions );
				} catch ( error ) {
					apply.disabled = false;
					previewButton.disabled = false;
					status.textContent = error && error.message ? error.message : 'No se pudo completar la operación atómica.';
				}
			} );
		} catch ( error ) {
			previewButton.disabled = false;
			status.textContent = error && error.message ? error.message : 'No se pudo validar el preview de estructura + 301.';
		}
	}

	function renderReady( report, plan ) {
		slot.replaceChildren();
		const panel = document.createElement( 'section' );
		panel.className = 'seo-geo-manager-admin__panel seo-geo-manager-focus is-ready';
		panel.appendChild( text( 'p', 'Paso 2', 'seo-geo-manager-admin__eyebrow' ) );
		panel.appendChild( text( 'h2', 'Aplicar estructura + 301' ) );
		panel.appendChild( text( 'p', 'La autoridad histórica ya está verificada. La arquitectura antigua no se conserva como dependencia: solo se preserva su valor SEO mediante redirecciones directas cuando hace falta.', 'seo-geo-manager-focus__message' ) );

		const metrics = document.createElement( 'div' );
		metrics.className = 'seo-geo-manager-focus__metrics';
		metrics.appendChild( metric( 'Estructura final', plan.target_structure || '—' ) );
		metrics.appendChild( metric( 'URLs ya preservadas', Number( plan.path_preservation_count || 0 ) ) );
		metrics.appendChild( metric( '301 necesarias', Number( plan.planned_redirects || 0 ) ) );
		metrics.appendChild( metric( 'Colisiones', Number( plan.collision_count || 0 ) ) );
		panel.appendChild( metrics );

		const preview = document.createElement( 'div' );
		preview.className = 'seo-geo-manager-focus__preview';
		preview.hidden = true;
		preview.setAttribute( 'data-seo-geo-final-preview', '' );
		panel.appendChild( preview );

		const status = text( 'p', 'Todo listo para un último preview protegido antes de escribir.', 'seo-geo-manager-focus__status' );
		status.setAttribute( 'role', 'status' );
		status.setAttribute( 'aria-live', 'polite' );
		panel.appendChild( status );

		const actions = document.createElement( 'div' );
		actions.className = 'seo-geo-manager-focus__actions';
		const previewButton = text( 'button', 'Previsualizar operación final' );
		previewButton.type = 'button';
		previewButton.className = 'button button-primary';
		previewButton.addEventListener( 'click', () => previewAtomic( latestReport, plan, panel, status, previewButton ) );
		actions.appendChild( previewButton );
		panel.appendChild( actions );
		slot.appendChild( panel );
	}

	function renderFromReport( report ) {
		latestReport = report;
		const plan = report?.permalinks?.authoritative_plan || {};
		const gate = report?.field_gate || {};
		const runtime = report?.permalinks?.runtime_preview || {};

		const ready = gate.guarded_write_eligible === true &&
			gate.guarded_write_mode === 'atomic-301' &&
			gate.permalink_plan?.status === 'atomic-ready' &&
			Number( plan.local_slug_repair_count || 0 ) === 0 &&
			Number( plan.collision_count || 0 ) === 0 &&
			plan.safe_structure_candidate === true &&
			plan.requires_redirect_runtime === true &&
			runtime.safe_to_activate === true;

		if ( ready ) renderReady( report, plan );
		else renderBlocked( report, plan, gate );
	}

	function sync() {
		const value = ( raw.textContent || '' ).trim();
		if ( ! value ) {
			renderWaiting();
			return;
		}
		try {
			const report = JSON.parse( value );
			if ( report && report.field_gate ) renderFromReport( report );
			else renderWaiting();
		} catch ( error ) {
			renderWaiting();
		}
	}

	new MutationObserver( sync ).observe( raw, { childList: true, characterData: true, subtree: true } );
	sync();
}() );
