/* global URLSearchParams */
/**
 * Browser-controlled, serial batches. The server does not run background workers.
 * Closing this tab or pressing Pause stops scheduling new requests.
 */
(function () {
	'use strict';

	function init() {
		const panel = document.querySelector('[data-seo-geo-auto-export]');
		if (!panel) {
			return;
		}

		const run = panel.querySelector('[data-auto-run]');
		const pause = panel.querySelector('[data-auto-pause]');
		const progress = panel.querySelector('[data-auto-progress]');
		const stage = panel.querySelector('[data-auto-stage]');
		const status = panel.querySelector('[data-auto-status]');
		const processed = panel.querySelector('[data-auto-processed]');
		const label = panel.querySelector('[data-auto-label]');
		let state;
		try {
			state = JSON.parse(panel.getAttribute('data-initial-progress') || '{}');
		} catch (error) {
			return;
		}

		let running = false;
		let shouldPause = false;
		let cycles = 0;
		const messages = {
			start: panel.getAttribute('data-msg-start') || '',
			pausing: panel.getAttribute('data-msg-pausing') || '',
			paused: panel.getAttribute('data-msg-paused') || '',
			ready: panel.getAttribute('data-msg-ready') || '',
			blocked: panel.getAttribute('data-msg-blocked') || '',
			network: panel.getAttribute('data-msg-network') || '',
			limit: panel.getAttribute('data-msg-limit') || '',
			running: panel.getAttribute('data-msg-running') || '',
		};

		function update(next) {
			state = next;
			const finished = next.status === 'ready';
			const allowed = next.status === 'running';
			const percent = Math.max(0, Math.min(100, Number(next.percent) || 0));
			progress.value = percent;
			progress.setAttribute('aria-valuenow', String(Math.round(percent)));
			label.textContent = next.label || '';
			stage.textContent = String(next.completed || 0) + ' / ' + String(next.steps || 5);
			const units = next.unit === 'rows' ? (panel.getAttribute('data-msg-rows') || '') : (panel.getAttribute('data-msg-files') || '');
			processed.textContent = String(next.processed || 0)
				+ ((Number(next.total) > 0) ? ' / ' + String(next.total) : '') + ' ' + units;
			run.disabled = running || !allowed;
			pause.disabled = !running;
			if (finished) {
				status.textContent = messages.ready;
			} else if (!allowed) {
				status.textContent = messages.blocked;
			} else if (!running) {
				status.textContent = messages.start;
			}
		}

		function stop(message) {
			running = false;
			shouldPause = false;
			update(state);
			if (message) {
				status.textContent = message;
			}
		}

		async function nextBatch() {
			while (running && !shouldPause) {
				if (++cycles > 1500) {
					stop(messages.limit);
					return;
				}
				const body = new URLSearchParams({
					action: panel.getAttribute('data-action') || '',
					nonce: panel.getAttribute('data-nonce') || '',
					clone_job_id: panel.getAttribute('data-job-id') || '',
					expected_phase: state.phase || ''
				});
				let reply;
				try {
					const response = await fetch(panel.getAttribute('data-endpoint'), {
						method: 'POST',
						credentials: 'same-origin',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
						body: body.toString()
					});
					reply = await response.json();
					if (!response.ok || !reply.success || !reply.data) {
						stop(messages.blocked);
						return;
					}
				} catch (error) {
					stop(messages.network);
					return;
				}
				update(reply.data);
				if (reply.data.status !== 'running') {
					stop(reply.data.status === 'ready' ? messages.ready : messages.blocked);
					return;
				}
				status.textContent = messages.running;
				// Yield to the browser, avoid hammering PHP and keep Pause responsive.
				await new Promise(function (resolve) { window.setTimeout(resolve, 250); });
			}
			stop(messages.paused);
		}

		run.addEventListener('click', function () {
			if (running || state.status !== 'running') {
				return;
			}
			running = true;
			shouldPause = false;
			cycles = 0;
			update(state);
			status.textContent = messages.running;
			nextBatch();
		});

		pause.addEventListener('click', function () {
			if (running) {
				shouldPause = true;
				status.textContent = messages.pausing;
			}
		});

		update(state);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
}());