(function () {
	'use strict';

	var cfg = window.commercePilotMenuBadge || {};
	if (!cfg.restUrl || !cfg.nonce) {
		return;
	}

	var INTERVAL = Math.max(15000, parseInt(cfg.intervalMs, 10) || 30000);

	function restHref(path) {
		var url = new URL(cfg.restUrl, window.location.origin);
		var route = url.searchParams.get('rest_route');
		var cleanPath = String(path || '').replace(/^\//, '');

		if (route !== null) {
			url.searchParams.set('rest_route', route.replace(/\/?$/, '/') + cleanPath);
			return url.toString();
		}

		return cfg.restUrl.replace(/\/?$/, '/') + cleanPath;
	}

	function paint(count) {
		count = Math.max(0, parseInt(count, 10) || 0);
		var menu = document.getElementById('toplevel_page_commercepilot');
		if (!menu) {
			return;
		}

		var targets = [
			menu.querySelector('.wp-menu-name'),
			menu.querySelector('a[href*="page=commercepilot-conversations"]')
		];

		targets.forEach(function (target) {
			if (!target) {
				return;
			}

			var badge = target.querySelector('.awaiting-mod');
			if (count < 1) {
				if (badge) {
					badge.remove();
				}
				return;
			}

			var label = String(count);
			if (!badge) {
				badge = document.createElement('span');
				badge.className = 'awaiting-mod count-' + count;
				var pending = document.createElement('span');
				pending.className = 'pending-count';
				pending.textContent = label;
				badge.appendChild(pending);
				target.appendChild(document.createTextNode(' '));
				target.appendChild(badge);
				return;
			}

			badge.className = 'awaiting-mod count-' + count;
			var pendingCount = badge.querySelector('.pending-count');
			if (pendingCount) {
				pendingCount.textContent = label;
			} else {
				badge.textContent = label;
			}
		});
	}

	function refresh() {
		if (document.hidden) {
			return;
		}

		fetch(restHref('admin/conversations/waiting-count'), {
			method: 'GET',
			credentials: 'same-origin',
			headers: {
				'Accept': 'application/json',
				'X-WP-Nonce': cfg.nonce
			}
		}).then(function (res) {
			return res.json().catch(function () {
				return {};
			}).then(function (json) {
				if (!res.ok) {
					return;
				}
				paint(json.waiting_count);
			});
		}).catch(function () {
			// Leave the last known badge in place on transient failures.
		});
	}

	paint(cfg.waitingCount);
	window.setInterval(refresh, INTERVAL);
	document.addEventListener('visibilitychange', function () {
		if (!document.hidden) {
			refresh();
		}
	});
})();
