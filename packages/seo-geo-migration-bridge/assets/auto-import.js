/* global URLSearchParams */
/**
 * Runs a single verified import batch per request; a visible confirmation
 * separates staging from live replacement. No background processing.
 */
(function () {
	'use strict';

	function init() {
		const panel = document.querySelector('[data-seo-geo-auto-import]');
		if (!panel) {
			return;
		}

		const start = panel.querySelector('[data-import-run]');
		const pause = panel.querySelector('[data-import-pause]');
		const approve = panel.querySelector('[data-import-confirm]');
		const review = panel.querySelector('[data-import-review]');
		const checked = panel.querySelector('[data-import-ack]');
		const progress = panel.querySelector('[data-import-progress]');
		const label = panel.querySelector('[data-import-label]');
		const status = panel.querySelector('[data-import-status]');
		const count = panel.querySelector('[data-import-count]');
		let state;

		try {
			state = JSON.parse(panel.getAttribute('data-initial-progress') || '{}');
		} catch (error) {
			status.textContent = panel.getAttribute('data-msg-error') || '';
			return;
		}

		let running = false;
		let pauseRequested = false;
		let cycles = 0;

		function show(next) {
			state = next;
			const isReview = next.status === 'review';
			const finished = next.status === 'ready';
			const canStart = next.status === 'running';
			progress.value = Math.max(0, Math.min(100, Number(next.percent) || 0));
			label.textContent = next.label || next.phase || '';
			count.textContent = String(next.processed || 0) + ((Number(next.total) > 0) ? ' / ' + next.total : '');
			start.disabled = running || !canStart;
			pause.disabled = !running;
			review.hidden = !isReview;
			approve.disabled = running || !isReview || !checked.checked;
			if (finished) {
				status.textContent = panel.getAttribute('data-msg-complete') || '';
				progress.value = 100;
			} else if (isReview) {
				status.textContent = panel.getAttribute('data-msg-review') || '';
			} else if (next.status === 'blocked') {
				status.textContent = panel.getAttribute('data-msg-error') || '';
			} else if (!running) {
				status.textContent = panel.getAttribute('data-msg-start') || '';
			}
		}

		async function request(isConfirmation) {
			const params = new URLSearchParams({
				action: panel.getAttribute('data-action') || '',
				nonce: panel.getAttribute('data-nonce') || '',
				clone_job_id: panel.getAttribute('data-job-id') || '',
				expected_phase: state.phase || '',
			});
			if (isConfirmation) {
				params.set('confirm_override', '1');
			}

			const response = await fetch(panel.getAttribute('data-endpoint'), {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
				body: params.toString(),
			});
			const reply = await response.json();
			if (!response.ok || !reply.success || !reply.data) {
				throw new Error((reply.data && reply.data.code) || 'import-batch-failed');
			}
			return reply.data;
		}

		function stop(message) {
			running = false;
			pauseRequested = false;
			show(state);
			if (message) {
				status.textContent = message;
			}
		}

		async function cycle() {
			while (running && !pauseRequested) {
				if (++cycles > 1500) {
					stop(panel.getAttribute('data-msg-limit') || '');
					return;
				}
				let next;
				try {
					next = await request(false);
				} catch (error) {
					stop((panel.getAttribute('data-msg-error') || '') + ' (' + error.message + ')');
					return;
				}
				show(next);
				if (next.status !== 'running') {
					stop('');
					return;
				}
				status.textContent = panel.getAttribute('data-msg-running') || '';
				// Sequential requests already apply backpressure; avoid idle time
				// between verified checkpoints on low-latency hosts.
				await new Promise(function (resolve) { window.setTimeout(resolve, 40); });
			}
			stop(panel.getAttribute('data-msg-paused') || '');
		}

		function run() {
			if (running || state.status !== 'running') {
				return;
			}
			running = true;
			pauseRequested = false;
			cycles = 0;
			show(state);
			status.textContent = panel.getAttribute('data-msg-running') || '';
			cycle();
		}

		start.addEventListener('click', run);
		pause.addEventListener('click', function () {
			if (running) {
				pauseRequested = true;
			}
		});
		checked.addEventListener('change', function () {
			approve.disabled = running || state.status !== 'review' || !checked.checked;
		});
		approve.addEventListener('click', async function () {
			if (running || state.status !== 'review' || !checked.checked) {
				return;
			}
			approve.disabled = true;
			status.textContent = panel.getAttribute('data-msg-running') || '';
			try {
				show(await request(true));
				if (state.status === 'running') {
					run();
				}
			} catch (error) {
				show(state);
				status.textContent = (panel.getAttribute('data-msg-error') || '') + ' (' + error.message + ')';
			}
		});

		show(state);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
}());
