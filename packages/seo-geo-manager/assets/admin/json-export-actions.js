( function () {
	'use strict';

	const root = document.getElementById( 'seo-geo-manager-dashboard' );
	if ( ! root ) {
		return;
	}

	const rawDetails = root.querySelector( '.seo-geo-manager-admin__raw' );
	const rawBox = root.querySelector( '[data-seo-geo-raw]' );
	if ( ! rawDetails || ! rawBox ) {
		return;
	}

	const toolbar = document.createElement( 'div' );
	toolbar.className = 'seo-geo-manager-admin__json-actions';

	const copyButton = document.createElement( 'button' );
	copyButton.type = 'button';
	copyButton.className = 'button';
	copyButton.textContent = 'Copiar JSON';
	copyButton.disabled = true;
	copyButton.setAttribute( 'data-seo-geo-json-action', 'copy' );

	const downloadButton = document.createElement( 'button' );
	downloadButton.type = 'button';
	downloadButton.className = 'button';
	downloadButton.textContent = 'Descargar JSON';
	downloadButton.disabled = true;
	downloadButton.setAttribute( 'data-seo-geo-json-action', 'download' );

	const feedback = document.createElement( 'span' );
	feedback.className = 'seo-geo-manager-admin__json-feedback';
	feedback.setAttribute( 'role', 'status' );
	feedback.setAttribute( 'aria-live', 'polite' );

	toolbar.appendChild( copyButton );
	toolbar.appendChild( downloadButton );
	toolbar.appendChild( feedback );
	rawBox.before( toolbar );

	function jsonText() {
		const value = rawBox.textContent ? rawBox.textContent.trim() : '';
		return '' !== value && '{}' !== value ? value : '';
	}

	function refreshState() {
		const enabled = '' !== jsonText();
		copyButton.disabled = ! enabled;
		downloadButton.disabled = ! enabled;
	}

	function setFeedback( message ) {
		feedback.textContent = message;
		window.setTimeout(
			() => {
				if ( feedback.textContent === message ) {
					feedback.textContent = '';
				}
			},
			2400
		);
	}

	async function copyJson() {
		const value = jsonText();
		if ( ! value ) {
			return;
		}

		try {
			if ( navigator.clipboard && window.isSecureContext ) {
				await navigator.clipboard.writeText( value );
			} else {
				const textarea = document.createElement( 'textarea' );
				textarea.value = value;
				textarea.setAttribute( 'readonly', 'readonly' );
				textarea.style.position = 'fixed';
				textarea.style.opacity = '0';
				document.body.appendChild( textarea );
				textarea.select();
				const copied = document.execCommand( 'copy' );
				textarea.remove();
				if ( ! copied ) {
					throw new Error( 'copy-failed' );
				}
			}
			setFeedback( 'JSON copiado.' );
		} catch ( error ) {
			setFeedback( 'No se pudo copiar el JSON.' );
		}
	}

	function filename() {
		const now = new Date();
		const stamp = now.toISOString().replace( /[:.]/g, '-' );
		return `seo-geo-manager-diagnostico-${ stamp }.json`;
	}

	function downloadJson() {
		const value = jsonText();
		if ( ! value ) {
			return;
		}

		const blob = new Blob( [ value + '\n' ], { type: 'application/json;charset=utf-8' } );
		const url = URL.createObjectURL( blob );
		const link = document.createElement( 'a' );
		link.href = url;
		link.download = filename();
		document.body.appendChild( link );
		link.click();
		link.remove();
		URL.revokeObjectURL( url );
		setFeedback( 'JSON descargado.' );
	}

	copyButton.addEventListener( 'click', copyJson );
	downloadButton.addEventListener( 'click', downloadJson );

	const observer = new MutationObserver( refreshState );
	observer.observe( rawBox, { childList: true, characterData: true, subtree: true } );
	refreshState();
}() );
