(function () {
	'use strict';

	var cfg = window.commercePilotInbox || {};
	var root = document.getElementById('cp-inbox');
	if (!root || !cfg.restUrl) {
		return;
	}

	var LIST_INTERVAL = 15000;
	var THREAD_INTERVAL = 5000;

	var i18n = cfg.i18n || {};
	var listEl = document.getElementById('cp-inbox-items');
	var threadEl = document.getElementById('cp-inbox-thread');
	var filterEl = document.getElementById('cp-inbox-filter');
	var refreshEl = document.getElementById('cp-inbox-refresh');

	var state = {
		conversations: [],
		activeId: '',
		mode: 'ai',
		messages: [],
		lastMessageId: 0,
		busy: false,
		filter: ''
	};

	var timers = { list: null, thread: null };

	function t(key, fallback) {
		return i18n[key] || fallback;
	}

	function restHref(path, query) {
		var url = new URL(cfg.restUrl, window.location.origin);
		var route = url.searchParams.get('rest_route');
		var raw = String(path || '');
		var cleanPath = raw.replace(/^\//, '');
		var inline = '';
		var q = cleanPath.indexOf('?');
		if (q !== -1) {
			inline = cleanPath.slice(q + 1);
			cleanPath = cleanPath.slice(0, q);
		}
		var params = new URLSearchParams(inline);

		if (query) {
			Object.keys(query).forEach(function (key) {
				if (query[key] !== undefined && query[key] !== null && query[key] !== '') {
					params.set(key, String(query[key]));
				}
			});
		}

		if (route !== null) {
			url.searchParams.set('rest_route', route.replace(/\/?$/, '/') + cleanPath);
			params.forEach(function (value, key) {
				url.searchParams.set(key, value);
			});
			return url.toString();
		}

		var href = cfg.restUrl.replace(/\/?$/, '/') + cleanPath;
		var extra = params.toString();
		return extra ? href + (href.indexOf('?') === -1 ? '?' : '&') + extra : href;
	}

	function api(method, path, body, query) {
		return fetch(restHref(path, query), {
			method: method,
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': cfg.nonce
			},
			body: body ? JSON.stringify(body) : undefined
		}).then(function (res) {
			return res.json().catch(function () {
				return {};
			}).then(function (json) {
				if (!res.ok) {
					var message = (json.error && json.error.message) || t('failed', 'Request failed.');
					throw new Error(message);
				}
				return json;
			});
		});
	}

	function el(tag, className, text) {
		var node = document.createElement(tag);
		if (className) {
			node.className = className;
		}
		if (typeof text === 'string') {
			node.textContent = text;
		}
		return node;
	}

	function formatTime(value) {
		if (!value) {
			return '';
		}
		var date = new Date(value);
		if (isNaN(date.getTime())) {
			return '';
		}
		return date.toLocaleString();
	}

	// ----- conversation list -----

	function loadList() {
		return api('GET', 'admin/conversations', null, state.filter ? { mode: state.filter } : null)
			.then(function (json) {
				state.conversations = json.conversations || [];
				paintList();
			})
			.catch(function (error) {
				listEl.innerHTML = '';
				listEl.appendChild(el('li', 'cp-inbox__empty', error.message));
			});
	}

	function paintList() {
		listEl.innerHTML = '';

		if (!state.conversations.length) {
			listEl.appendChild(el('li', 'cp-inbox__empty', t('noConversations', 'No conversations yet.')));
			return;
		}

		state.conversations.forEach(function (conversation) {
			var item = el('li', 'cp-inbox__item');
			if (conversation.id === state.activeId) {
				item.className += ' is-active';
			}

			var button = el('button', 'cp-inbox__item-button');
			button.type = 'button';

			var top = el('span', 'cp-inbox__item-top');
			top.appendChild(el('span', 'cp-inbox__visitor', shortId(conversation.visitor_id)));
			if (conversation.mode === 'human') {
				top.appendChild(el('span', 'cp-badge cp-badge--human', t('human', 'You')));
			}
			// The visitor spoke last, so somebody still owes them a reply.
			if (conversation.last_message && conversation.last_message.role === 'user') {
				top.appendChild(el('span', 'cp-badge cp-badge--waiting', t('waiting', 'Waiting')));
			}
			button.appendChild(top);

			if (conversation.last_message) {
				button.appendChild(el('span', 'cp-inbox__preview', conversation.last_message.preview || ''));
			}
			button.appendChild(el('span', 'cp-inbox__meta', formatTime(conversation.last_message_at)));

			button.addEventListener('click', function () {
				openConversation(conversation.id);
			});

			item.appendChild(button);
			listEl.appendChild(item);
		});
	}

	function shortId(value) {
		if (!value) {
			return t('visitor', 'Visitor');
		}
		return t('visitor', 'Visitor') + ' ' + value.slice(0, 8);
	}

	function activeConversation() {
		for (var i = 0; i < state.conversations.length; i++) {
			if (state.conversations[i].id === state.activeId) {
				return state.conversations[i];
			}
		}
		return null;
	}

	// ----- thread -----

	function openConversation(id) {
		state.activeId = id;
		state.messages = [];
		state.lastMessageId = 0;
		paintList();
		threadEl.innerHTML = '';
		threadEl.appendChild(el('div', 'cp-inbox__placeholder', t('loading', 'Loading…')));

		api('GET', 'admin/conversations/' + encodeURIComponent(id))
			.then(function (json) {
				if (state.activeId !== id) {
					return;
				}
				state.mode = json.mode || 'ai';
				state.messages = json.messages || [];
				trackCursor();
				paintThread();
				startThreadPolling();
			})
			.catch(function (error) {
				threadEl.innerHTML = '';
				threadEl.appendChild(el('div', 'cp-inbox__placeholder', error.message));
			});
	}

	function trackCursor() {
		state.messages.forEach(function (message) {
			if (message.id > state.lastMessageId) {
				state.lastMessageId = message.id;
			}
		});
	}

	function paintThread() {
		var conversation = activeConversation();
		threadEl.innerHTML = '';

		var header = el('div', 'cp-inbox__thread-head');
		var title = el('div');
		title.appendChild(el('strong', '', shortId(conversation ? conversation.visitor_id : '')));
		title.appendChild(el('span', 'cp-inbox__mode', state.mode === 'human'
			? t('modeHuman', 'You are handling this chat. The AI is off.')
			: t('modeAi', 'The AI is answering this chat.')));
		header.appendChild(title);

		var toggle = el('button', 'button button-primary', state.mode === 'human'
			? t('release', 'Give back to AI')
			: t('takeOver', 'Take over'));
		toggle.type = 'button';
		toggle.disabled = state.busy;
		toggle.addEventListener('click', function () {
			setMode(state.mode === 'human' ? 'release' : 'takeover');
		});
		header.appendChild(toggle);
		threadEl.appendChild(header);

		var body = el('div', 'cp-inbox__messages');
		if (!state.messages.length) {
			body.appendChild(el('div', 'cp-inbox__placeholder', t('noMessages', 'No messages yet.')));
		}
		state.messages.forEach(function (message) {
			body.appendChild(renderMessage(message));
		});
		threadEl.appendChild(body);

		threadEl.appendChild(renderComposer());
		body.scrollTop = body.scrollHeight;
	}

	function renderMessage(message) {
		var wrap = el('div', 'cp-msg cp-msg--' + message.role);
		if (message.role === 'assistant') {
			wrap.className += message.author === 'human' ? ' cp-msg--human' : ' cp-msg--ai';
		}

		var label = message.role === 'user'
			? t('visitor', 'Visitor')
			: (message.author === 'human' ? (message.author_name || t('human', 'You')) : t('assistant', 'AI assistant'));

		wrap.appendChild(el('div', 'cp-msg__label', label));
		wrap.appendChild(el('div', 'cp-msg__body', message.content));
		if (message.created_at) {
			wrap.appendChild(el('div', 'cp-msg__time', formatTime(message.created_at)));
		}
		return wrap;
	}

	function renderComposer() {
		var form = el('form', 'cp-inbox__composer');
		var input = document.createElement('textarea');
		input.rows = 2;
		input.placeholder = state.mode === 'human'
			? t('replyPlaceholder', 'Write a reply…')
			: t('takeOverFirst', 'Take over this chat to reply manually.');
		input.disabled = state.mode !== 'human' || state.busy;

		var send = el('button', 'button button-primary', t('send', 'Send'));
		send.type = 'submit';
		send.disabled = input.disabled;

		form.appendChild(input);
		form.appendChild(send);

		form.addEventListener('submit', function (event) {
			event.preventDefault();
			sendReply(input);
		});
		input.addEventListener('keydown', function (event) {
			if (event.key === 'Enter' && !event.shiftKey) {
				event.preventDefault();
				sendReply(input);
			}
		});

		return form;
	}

	function setMode(action) {
		if (!state.activeId || state.busy) {
			return;
		}
		state.busy = true;
		paintThread();

		api('POST', 'admin/conversations/' + encodeURIComponent(state.activeId) + '/' + action)
			.then(function (json) {
				state.mode = json.mode || 'ai';
				var conversation = activeConversation();
				if (conversation) {
					conversation.mode = state.mode;
				}
			})
			.catch(function (error) {
				window.alert(error.message);
			})
			.then(function () {
				state.busy = false;
				paintThread();
				paintList();
			});
	}

	function sendReply(input) {
		var content = (input.value || '').trim();
		if (!content || state.busy || state.mode !== 'human') {
			return;
		}

		state.busy = true;
		input.value = '';
		paintThread();

		api('POST', 'admin/conversations/' + encodeURIComponent(state.activeId) + '/reply', { content: content })
			.then(function (message) {
				appendMessage(message);
			})
			.catch(function (error) {
				window.alert(error.message);
			})
			.then(function () {
				state.busy = false;
				paintThread();
				focusComposer();
			});
	}

	function focusComposer() {
		var field = threadEl.querySelector('.cp-inbox__composer textarea');
		if (field && !field.disabled) {
			field.focus();
		}
	}

	function appendMessage(message) {
		if (!message || !message.id) {
			return;
		}
		for (var i = 0; i < state.messages.length; i++) {
			if (state.messages[i].id === message.id) {
				return;
			}
		}
		state.messages.push(message);
		if (message.id > state.lastMessageId) {
			state.lastMessageId = message.id;
		}
	}

	// ----- polling -----

	function pollThread() {
		if (!state.activeId || document.hidden) {
			return;
		}

		var id = state.activeId;
		api('GET', 'admin/conversations/' + encodeURIComponent(id) + '/messages', null, { after_id: state.lastMessageId })
			.then(function (json) {
				if (state.activeId !== id) {
					return;
				}
				var incoming = json.messages || [];
				if (!incoming.length && json.mode === state.mode) {
					return;
				}
				state.mode = json.mode || state.mode;
				incoming.forEach(appendMessage);
				paintThread();
			})
			.catch(function () {
				// Transient failures are ignored; the next tick retries.
			});
	}

	function startThreadPolling() {
		if (timers.thread) {
			return;
		}
		timers.thread = window.setInterval(pollThread, THREAD_INTERVAL);
	}

	function startListPolling() {
		timers.list = window.setInterval(function () {
			if (!document.hidden) {
				loadList();
			}
		}, LIST_INTERVAL);
	}

	if (filterEl) {
		filterEl.addEventListener('change', function () {
			state.filter = filterEl.value;
			loadList();
		});
	}

	if (refreshEl) {
		refreshEl.addEventListener('click', function () {
			loadList();
			if (state.activeId) {
				pollThread();
			}
		});
	}

	document.addEventListener('visibilitychange', function () {
		if (!document.hidden) {
			loadList();
			pollThread();
		}
	});

	loadList();
	startListPolling();
})();
