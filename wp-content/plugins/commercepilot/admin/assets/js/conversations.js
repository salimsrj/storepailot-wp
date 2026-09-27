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
		filter: '',
		pickerOpen: false,
		pickerQuery: '',
		pickerResults: [],
		pickerSelected: {},
		pickerSearching: false
	};

	var timers = { list: null, thread: null, search: null };
	var MAX_SHARED_PRODUCTS = 5;

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
		closePicker(true);
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
		var agentMode = !!cfg.agentMode;
		threadEl.innerHTML = '';

		var header = el('div', 'cp-inbox__thread-head');
		var title = el('div');
		title.appendChild(el('strong', '', shortId(conversation ? conversation.visitor_id : '')));

		var modeLabel;
		if (!agentMode) {
			modeLabel = t('modeDirect', 'Direct messaging. Reply to the visitor here.');
		} else if (state.mode === 'human') {
			modeLabel = t('modeHuman', 'You are handling this chat. The AI is off.');
		} else {
			modeLabel = t('modeAi', 'The AI is answering this chat.');
		}
		title.appendChild(el('span', 'cp-inbox__mode', modeLabel));
		header.appendChild(title);

		if (agentMode) {
			var toggle = el('button', 'button button-primary', state.mode === 'human'
				? t('release', 'Give back to AI')
				: t('takeOver', 'Take over'));
			toggle.type = 'button';
			toggle.disabled = state.busy;
			toggle.addEventListener('click', function () {
				setMode(state.mode === 'human' ? 'release' : 'takeover');
			});
			header.appendChild(toggle);
		}

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
		if (message.content) {
			wrap.appendChild(el('div', 'cp-msg__body', message.content));
		}
		if (message.products && message.products.length) {
			wrap.appendChild(renderMessageProducts(message.products));
		}
		if (message.created_at) {
			wrap.appendChild(el('div', 'cp-msg__time', formatTime(message.created_at)));
		}
		return wrap;
	}

	function renderMessageProducts(products) {
		var list = el('div', 'cp-msg__products');
		products.forEach(function (product) {
			var card = el('div', 'cp-msg__product');
			if (product.image) {
				var img = document.createElement('img');
				img.src = product.image;
				img.alt = '';
				card.appendChild(img);
			}
			var meta = el('div', 'cp-msg__product-meta');
			meta.appendChild(el('div', 'cp-msg__product-name', product.name || ''));
			var price = product.price || '';
			if (price && product.currency) {
				price = product.currency + ' ' + price;
			}
			if (price) {
				meta.appendChild(el('div', 'cp-msg__product-price', price));
			}
			card.appendChild(meta);
			list.appendChild(card);
		});
		return list;
	}

	function renderComposer() {
		var agentMode = !!cfg.agentMode;
		var canReply = !agentMode || state.mode === 'human';
		var form = el('form', 'cp-inbox__composer');
		var input = document.createElement('textarea');
		input.rows = 2;
		input.placeholder = canReply
			? t('replyPlaceholder', 'Write a reply…')
			: t('takeOverFirst', 'Take over this chat to reply manually.');
		input.disabled = !canReply || state.busy;

		var actions = el('div', 'cp-inbox__composer-actions');

		var share = el('button', 'button', t('shareProduct', 'Share product'));
		share.type = 'button';
		share.disabled = !canReply || state.busy;
		share.addEventListener('click', function () {
			if (!canReply || state.busy) {
				return;
			}
			if (state.pickerOpen) {
				closePicker(true);
				paintThread();
				return;
			}
			state.pickerOpen = true;
			state.pickerQuery = '';
			state.pickerResults = [];
			state.pickerSelected = {};
			paintThread();
			searchProducts('');
		});

		var send = el('button', 'button button-primary', t('send', 'Send'));
		send.type = 'submit';
		send.disabled = input.disabled;

		actions.appendChild(share);
		actions.appendChild(send);

		form.appendChild(input);
		if (state.pickerOpen && canReply) {
			form.appendChild(renderProductPicker(input));
		}
		form.appendChild(actions);

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

	function renderProductPicker(input) {
		var picker = el('div', 'cp-inbox__picker');

		var search = document.createElement('input');
		search.type = 'search';
		search.className = 'cp-inbox__picker-search';
		search.placeholder = t('searchProducts', 'Search products…');
		search.value = state.pickerQuery;
		search.addEventListener('input', function () {
			state.pickerQuery = search.value;
			scheduleProductSearch(search.value);
		});

		var results = el('ul', 'cp-inbox__picker-results');
		if (state.pickerSearching && !state.pickerResults.length) {
			results.appendChild(el('li', 'cp-inbox__placeholder', t('loading', 'Loading…')));
		} else if (!state.pickerResults.length) {
			results.appendChild(el('li', 'cp-inbox__placeholder', t('noProducts', 'No products found.')));
		} else {
			state.pickerResults.forEach(function (product) {
				var item = el('li', 'cp-inbox__picker-item');
				if (state.pickerSelected[product.id]) {
					item.className += ' is-selected';
				}
				if (product.image) {
					var img = document.createElement('img');
					img.src = product.image;
					img.alt = '';
					item.appendChild(img);
				}
				var meta = el('div', 'cp-inbox__picker-meta');
				meta.appendChild(el('span', 'cp-inbox__picker-name', product.name || ''));
				var price = product.price || '';
				if (price && product.currency) {
					price = product.currency + ' ' + price;
				}
				if (price) {
					meta.appendChild(el('span', 'cp-inbox__picker-price', price));
				}
				item.appendChild(meta);
				item.addEventListener('click', function () {
					toggleProductSelection(product);
					paintThread();
					var field = threadEl.querySelector('.cp-inbox__picker-search');
					if (field) {
						field.focus();
						field.value = state.pickerQuery;
					}
				});
				results.appendChild(item);
			});
		}

		var actions = el('div', 'cp-inbox__picker-actions');
		var cancel = el('button', 'button', t('cancel', 'Cancel'));
		cancel.type = 'button';
		cancel.addEventListener('click', function () {
			closePicker(true);
			paintThread();
			focusComposer();
		});

		var confirm = el('button', 'button button-primary', t('shareSelected', 'Share selected'));
		confirm.type = 'button';
		confirm.disabled = state.busy || selectedProductIds().length === 0;
		confirm.addEventListener('click', function () {
			shareProducts(input);
		});

		actions.appendChild(cancel);
		actions.appendChild(confirm);

		picker.appendChild(search);
		picker.appendChild(results);
		picker.appendChild(actions);

		window.setTimeout(function () {
			var field = threadEl.querySelector('.cp-inbox__picker-search');
			if (field) {
				field.focus();
			}
		}, 0);

		return picker;
	}

	function closePicker(reset) {
		state.pickerOpen = false;
		if (reset) {
			state.pickerQuery = '';
			state.pickerResults = [];
			state.pickerSelected = {};
			state.pickerSearching = false;
		}
		if (timers.search) {
			window.clearTimeout(timers.search);
			timers.search = null;
		}
	}

	function selectedProductIds() {
		return Object.keys(state.pickerSelected).map(function (id) {
			return parseInt(id, 10);
		}).filter(function (id) {
			return !isNaN(id) && id > 0;
		});
	}

	function toggleProductSelection(product) {
		var id = parseInt(product.id, 10);
		if (isNaN(id) || id < 1) {
			return;
		}
		if (state.pickerSelected[id]) {
			delete state.pickerSelected[id];
			return;
		}
		if (selectedProductIds().length >= MAX_SHARED_PRODUCTS) {
			window.alert(t('maxProducts', 'You can share up to 5 products at once.'));
			return;
		}
		state.pickerSelected[id] = true;
	}

	function scheduleProductSearch(query) {
		if (timers.search) {
			window.clearTimeout(timers.search);
		}
		timers.search = window.setTimeout(function () {
			searchProducts(query);
		}, 250);
	}

	function searchProducts(query) {
		state.pickerSearching = true;
		api('GET', 'admin/products/search', null, { query: query || '', limit: 8 })
			.then(function (json) {
				if (!state.pickerOpen) {
					return;
				}
				state.pickerResults = json.products || [];
				state.pickerSearching = false;
				paintThread();
			})
			.catch(function (error) {
				if (!state.pickerOpen) {
					return;
				}
				state.pickerResults = [];
				state.pickerSearching = false;
				paintThread();
				window.alert(error.message);
			});
	}

	function shareProducts(input) {
		var agentMode = !!cfg.agentMode;
		var canReply = !agentMode || state.mode === 'human';
		var ids = selectedProductIds();
		if (!ids.length) {
			window.alert(t('selectProduct', 'Select at least one product.'));
			return;
		}
		if (state.busy || !canReply) {
			return;
		}

		var content = (input.value || '').trim();
		state.busy = true;
		input.value = '';
		closePicker(true);
		paintThread();

		api('POST', 'admin/conversations/' + encodeURIComponent(state.activeId) + '/reply', {
			content: content,
			product_ids: ids
		})
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

	function setMode(action) {
		if (!cfg.agentMode || !state.activeId || state.busy) {
			return;
		}
		state.busy = true;
		closePicker(true);
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
		var agentMode = !!cfg.agentMode;
		var canReply = !agentMode || state.mode === 'human';
		var content = (input.value || '').trim();
		if (!content || state.busy || !canReply) {
			return;
		}

		state.busy = true;
		input.value = '';
		closePicker(true);
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
