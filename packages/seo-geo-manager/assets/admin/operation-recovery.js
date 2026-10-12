( function () {
	'use strict';

	const root = document.getElementById( 'seo-geo-manager-dashboard' );
	const api = window.SeoGeoManagerOperationRecovery || {};
	if ( ! root || ! window.wp || ! window.wp.apiFetch ) {
		return;
	}

	const evidence = root.querySelector( '[data-seo-geo-operation-evidence]' );
	if ( ! evidence ) {
		return;
	}

	function el( tag, className, text ) {
		const node = document.createElement( tag );
		if ( className ) {
			node.className = className;
		}
		if ( undefined !== text ) {
			node.textContent = String( text );
		}
		return node;
	}

	function manifestPath( operationId ) {
		const base = api.operationsBase || '/seo-geo-manager/v1/operations';
		return `${ base }/${ encodeURIComponent( operationId ) }/recovery`;
	}

	function environmentPath() {
		return api.environmentPath || '/seo-geo-manager/v1/site/snapshot';
	}

	function clearControls( wrap ) {
		wrap.querySelectorAll( '[data-operation-recovery-controls]' ).forEach( ( node ) => node.remove() );
	}

	function message( controls, text, className = '' ) {
		const node = el( 'span', `seo-geo-manager-operation-recovery__message ${ className }`.trim(), text );
		controls.appendChild( node );
		return node;
	}

	async function verify( manifest, controls ) {
		const descriptor = manifest.recovery?.verify || {};
		if ( true !== descriptor.available || 'GET' !== descriptor.method || ! descriptor.path ) {
			return;
		}

		const button = controls.querySelector( '[data-operation-recovery-verify]' );
		if ( button ) {
			button.disabled = true;
		}
		try {
			const result = await window.wp.apiFetch( { path: String( descriptor.path ) } );
			if ( true !== result.verified ) {
				throw new Error( 'El endpoint específico no confirmó verified=true.' );
			}
			controls.querySelectorAll( '.seo-geo-manager-operation-recovery__message' ).forEach( ( node ) => node.remove() );
			message( controls, 'Verificado ahora contra el estado actual.', 'is-success' );
		} catch ( error ) {
			controls.querySelectorAll( '.seo-geo-manager-operation-recovery__message' ).forEach( ( node ) => node.remove() );
			message( controls, error?.message || 'La verificación específica fue rechazada.', 'is-error' );
		} finally {
			if ( button ) {
				button.disabled = false;
			}
		}
	}

	async function rollback( manifest, controls ) {
		const descriptor = manifest.recovery?.rollback || {};
		const operationId = String( manifest.operation?.operation_id || '' );
		if ( true !== descriptor.available || 'POST' !== descriptor.method || ! descriptor.path || ! operationId ) {
			return;
		}
		if ( ! window.confirm( `Ejecutar el rollback específico y protegido de la operación ${ operationId }?` ) ) {
			return;
		}

		const button = controls.querySelector( '[data-operation-recovery-rollback]' );
		if ( button ) {
			button.disabled = true;
		}
		try {
			const snapshot = await window.wp.apiFetch( { path: environmentPath() } );
			const environmentFingerprint = String( snapshot?.environment?.fingerprint || '' );
			if ( true === descriptor.requires_environment_fingerprint && ! environmentFingerprint ) {
				throw new Error( 'No se pudo obtener el fingerprint del entorno actual.' );
			}
			const result = await window.wp.apiFetch( {
				path: String( descriptor.path ),
				method: 'POST',
				data: { environment_fingerprint: environmentFingerprint },
			} );
			if ( 'rolled-back' !== result.status ) {
				throw new Error( 'El endpoint específico no confirmó status=rolled-back.' );
			}
			controls.querySelectorAll( '.seo-geo-manager-operation-recovery__message' ).forEach( ( node ) => node.remove() );
			message( controls, 'Rollback específico completado y registrado.', 'is-success' );
			const refresh = evidence.querySelector( '[data-operation-evidence-refresh]' );
			if ( refresh instanceof HTMLButtonElement ) {
				refresh.click();
			}
		} catch ( error ) {
			controls.querySelectorAll( '.seo-geo-manager-operation-recovery__message' ).forEach( ( node ) => node.remove() );
			message( controls, error?.message || 'El rollback específico fue rechazado.', 'is-error' );
		} finally {
			if ( button ) {
				button.disabled = false;
			}
		}
	}

	function renderManifest( wrap, manifest ) {
		clearControls( wrap );
		const controls = el( 'div', 'seo-geo-manager-operation-recovery__controls' );
		controls.dataset.operationRecoveryControls = '';
		wrap.appendChild( controls );

		if ( true !== manifest.privacy_safe || 'operation-recovery-manifest' !== manifest.mode ) {
			message( controls, 'El manifiesto de recuperación no confirmó su contrato de privacidad.', 'is-error' );
			return;
		}
		if ( true !== manifest.recovery?.supported ) {
			message( controls, 'Esta operación no tiene recuperación integrada en este slice.', 'is-muted' );
			return;
		}

		const verifyDescriptor = manifest.recovery?.verify || {};
		const rollbackDescriptor = manifest.recovery?.rollback || {};
		if ( true !== verifyDescriptor.available && true !== rollbackDescriptor.available ) {
			message( controls, 'La operación ya no está en estado aplicado; no hay acciones de recuperación disponibles.', 'is-muted' );
			return;
		}

		if ( true === verifyDescriptor.available ) {
			const verifyButton = el( 'button', 'button button-small', 'Verificar ahora' );
			verifyButton.type = 'button';
			verifyButton.dataset.operationRecoveryVerify = '';
			verifyButton.addEventListener( 'click', () => verify( manifest, controls ) );
			controls.appendChild( verifyButton );
		}
		if ( true === rollbackDescriptor.available ) {
			const rollbackButton = el( 'button', 'button button-small', 'Rollback seguro' );
			rollbackButton.type = 'button';
			rollbackButton.dataset.operationRecoveryRollback = '';
			rollbackButton.addEventListener( 'click', () => rollback( manifest, controls ) );
			controls.appendChild( rollbackButton );
		}
		message( controls, 'Acciones delegadas a endpoints específicos del adaptador.', 'is-muted' );
	}

	async function openRecovery( wrap, operationId, button ) {
		button.disabled = true;
		clearControls( wrap );
		const controls = el( 'div', 'seo-geo-manager-operation-recovery__controls' );
		controls.dataset.operationRecoveryControls = '';
		message( controls, 'Comprobando recuperación allowlist…', 'is-muted' );
		wrap.appendChild( controls );
		try {
			const manifest = await window.wp.apiFetch( { path: manifestPath( operationId ) } );
			renderManifest( wrap, manifest );
		} catch ( error ) {
			clearControls( wrap );
			const failed = el( 'div', 'seo-geo-manager-operation-recovery__controls' );
			failed.dataset.operationRecoveryControls = '';
			message( failed, error?.message || 'No se pudo obtener el manifiesto de recuperación.', 'is-error' );
			wrap.appendChild( failed );
		} finally {
			button.disabled = false;
		}
	}

	function enhance() {
		evidence.querySelectorAll( '.seo-geo-manager-operation-evidence__operation' ).forEach( ( wrap ) => {
			if ( wrap.querySelector( '[data-operation-recovery-open]' ) ) {
				return;
			}
			const code = wrap.querySelector( 'code' );
			const operationId = String( code?.textContent || '' ).trim();
			if ( ! /^[a-f0-9-]{36}$/.test( operationId ) ) {
				return;
			}
			const button = el( 'button', 'button button-small', 'Revisar recuperación' );
			button.type = 'button';
			button.dataset.operationRecoveryOpen = '';
			button.addEventListener( 'click', () => openRecovery( wrap, operationId, button ) );
			wrap.appendChild( button );
		} );
	}

	const observer = new MutationObserver( enhance );
	observer.observe( evidence, { childList: true, subtree: true } );
	enhance();
}() );
