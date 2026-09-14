(function () {
	'use strict';

	var cfg = window.commercePilot || {};
	var root = document.getElementById('cp-chatbot');
	if (!root || !cfg.restUrl || !cfg.config || !cfg.config.enabled) {
		return;
	}

	var Storage = {
		visitorKey: 'commercepilot_visitor_id',
		conversationKey: 'commercepilot_conversation_id',
		getVisitor: function () {
			var id = localStorage.getItem(this.visitorKey);
			if (!id) {
				id = this.uuid();
				localStorage.setItem(this.visitorKey, id);
			}
			return id;
		},
		getConversation: function () {
			return localStorage.getItem(this.conversationKey) || '';
		},
		setConversation: function (id) {
			if (id) {
				localStorage.setItem(this.conversationKey, id);
			}
		},
		uuid: function () {
			if (window.crypto && typeof window.crypto.randomUUID === 'function') {
				return window.crypto.randomUUID();
			}
			return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
				var r = (Math.random() * 16) | 0;
				return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
			});
		}
	};

	var POLL_INTERVAL = 5000;

	var state = {
		visitorId: Storage.getVisitor(),
		conversationId: Storage.getConversation(),
		isOpen: false,
		isLoading: false,
		messages: [],
		cart: null,
		usage: null,
		mode: 'ai',
		lastMessageId: 0,
		pollTimer: null,
		historyLoaded: false
	};

	function restHref(path, query) {
		var url = new URL(cfg.restUrl, window.location.origin);
		var route = url.searchParams.get('rest_route');
		var cleanPath = String(path || '').replace(/^\//, '');
		var params = new URLSearchParams();

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

	var API = {
		request: function (method, path, body, query) {
			return fetch(restHref(path, query), {
				method: method,
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': cfg.nonce
				},
				body: body ? JSON.stringify(body) : undefined
			}).then(function (res) {
				return res.json().then(function (json) {
					return { ok: res.ok, status: res.status, json: json };
				});
			});
		},
		chat: function (message) {
			return this.request('POST', 'chat', {
				visitor_id: state.visitorId,
				conversation_id: state.conversationId || undefined,
				message: message
			});
		},
		addToCart: function (productId) {
			return this.request('POST', 'cart/add', {
				visitor_id: state.visitorId,
				product_id: productId,
				quantity: 1
			});
		},
		checkout: function () {
			return this.request('GET', 'checkout', null, { visitor_id: state.visitorId });
		},
		messages: function () {
			return this.request('GET', 'messages', null, {
				visitor_id: state.visitorId,
				conversation_id: state.conversationId,
				after_id: state.lastMessageId
			});
		}
	};

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

	function assistantName() {
		return cfg.config.assistant_name || 'CommercePilot';
	}

	function avatarNode(className) {
		if (cfg.config.avatar) {
			var img = el('img', className);
			img.alt = (cfg.i18n && cfg.i18n.avatarAlt) || '';
			img.src = cfg.config.avatar;
			img.loading = 'lazy';
			return img;
		}
		return el('span', className + ' cp-chatbot-avatar--fallback', assistantName().charAt(0).toUpperCase());
	}

	function statusState() {
		var allowed = ['online', 'away', 'offline'];
		return allowed.indexOf(cfg.config.online_status) !== -1 ? cfg.config.online_status : 'online';
	}

	function statusLabel() {
		if (cfg.config.status_text) {
			return cfg.config.status_text;
		}
		var labels = {
			online: (cfg.i18n && cfg.i18n.statusOnline) || 'Online',
			away: (cfg.i18n && cfg.i18n.statusAway) || 'Away',
			offline: (cfg.i18n && cfg.i18n.statusOffline) || 'Offline'
		};
		return labels[statusState()];
	}

	function renderShell() {
		root.hidden = false;
		root.innerHTML = '';

		var windowEl = el('div', 'cp-chatbot-window');
		windowEl.setAttribute('role', 'dialog');
		windowEl.setAttribute('aria-label', assistantName());

		var header = el('div', 'cp-chatbot-header');
		var title = el('div', 'cp-chatbot-title');

		var identity = el('span', 'cp-chatbot-identity');
		identity.appendChild(avatarNode('cp-chatbot-avatar'));
		if (cfg.config.show_status) {
			var dot = el('span', 'cp-chatbot-status-dot cp-chatbot-status-dot--' + statusState());
			dot.setAttribute('aria-hidden', 'true');
			identity.appendChild(dot);
		}
		title.appendChild(identity);

		var meta = el('span', 'cp-chatbot-meta');
		meta.appendChild(el('span', 'cp-chatbot-name', assistantName()));
		if (cfg.config.show_status) {
			meta.appendChild(el('span', 'cp-chatbot-status', statusLabel()));
		}
		title.appendChild(meta);

		var close = el('button', 'cp-chatbot-icon', '×');
		close.type = 'button';
		close.setAttribute('aria-label', (cfg.i18n && cfg.i18n.close) || 'Close chat');
		close.addEventListener('click', closeChat);
		header.appendChild(title);
		header.appendChild(close);

		var messages = el('div', 'cp-chatbot-messages');
		messages.setAttribute('aria-live', 'polite');

		var composer = el('form', 'cp-chatbot-composer');
		var input = document.createElement('textarea');
		input.rows = 1;
		input.setAttribute('aria-label', (cfg.i18n && cfg.i18n.send) || 'Message');
		var send = el('button', 'cp-chatbot-send', (cfg.i18n && cfg.i18n.send) || 'Send');
		send.type = 'submit';
		composer.appendChild(input);
		composer.appendChild(send);
		composer.addEventListener('submit', function (event) {
			event.preventDefault();
			submitMessage(input);
		});
		input.addEventListener('keydown', function (event) {
			if (event.key === 'Enter' && !event.shiftKey) {
				event.preventDefault();
				submitMessage(input);
			}
		});

		windowEl.appendChild(header);
		windowEl.appendChild(messages);
		windowEl.appendChild(composer);

		var toggle = el('button', 'cp-chatbot-toggle');
		toggle.type = 'button';
		toggle.setAttribute('aria-label', (cfg.i18n && cfg.i18n.open) || 'Open chat');
		var icon = el('img', 'cp-chatbot-toggle-icon');
		icon.src = cfg.iconUrl || '';
		icon.alt = '';
		icon.width = 56;
		icon.height = 56;
		icon.setAttribute('aria-hidden', 'true');
		toggle.appendChild(icon);
		toggle.appendChild(el('span', 'cp-chatbot-toggle-label', cfg.config.button_text || 'Chat'));
		toggle.addEventListener('click', openChat);

		root.appendChild(windowEl);
		root.appendChild(toggle);
		root._messages = messages;
		root._input = input;
		root._send = send;

		if (cfg.config.welcome_message) {
			state.messages.push({ role: 'assistant', content: cfg.config.welcome_message });
		}
		paintMessages();
	}

	function paintMessages() {
		var box = root._messages;
		if (!box) {
			return;
		}
		box.innerHTML = '';
		state.messages.forEach(function (item) {
			if (item.type === 'products') {
				box.appendChild(renderProducts(item.products));
				return;
			}
			if (item.type === 'upgrade') {
				box.appendChild(renderUpgrade(item));
				return;
			}
			if (item.type === 'notice') {
				box.appendChild(el('div', 'cp-chatbot-notice', item.content));
				return;
			}
			if (item.type === 'error') {
				var err = el('div', 'cp-chatbot-error', item.content);
				if (item.retry) {
					var retry = el('button', '', (cfg.i18n && cfg.i18n.retry) || 'Retry');
					retry.type = 'button';
					retry.addEventListener('click', item.retry);
					err.appendChild(retry);
				}
				box.appendChild(err);
				return;
			}
			var bubble = el('div', 'cp-chatbot-message cp-chatbot-message--' + item.role, item.content);
			box.appendChild(item.role === 'assistant' ? withAvatar(bubble) : bubble);
		});
		if (state.isLoading) {
			var typing = el('div', 'cp-chatbot-typing');
			typing.setAttribute('aria-label', 'Assistant is typing');
			typing.setAttribute('role', 'status');
			typing.appendChild(el('span', 'cp-chatbot-typing-dot'));
			typing.appendChild(el('span', 'cp-chatbot-typing-dot'));
			typing.appendChild(el('span', 'cp-chatbot-typing-dot'));
			box.appendChild(withAvatar(typing));
		}
		box.scrollTop = box.scrollHeight;
	}

	/**
	 * Wraps an assistant bubble in a row prefixed by the profile picture.
	 */
	function withAvatar(node) {
		if (!cfg.config.avatar_in_messages) {
			return node;
		}
		var row = el('div', 'cp-chatbot-row');
		row.appendChild(avatarNode('cp-chatbot-message-avatar'));
		row.appendChild(node);
		return row;
	}

	function renderProducts(products) {
		var wrap = el('div', 'cp-chatbot-products');
		products.forEach(function (product) {
			var card = el('article', 'cp-chatbot-card');
			if (product.image) {
				var image = el('img');
				image.alt = '';
				image.src = product.image;
				card.appendChild(image);
			} else {
				card.appendChild(el('div'));
			}
			var body = el('div');
			body.appendChild(el('h3', '', product.name || ''));
			body.appendChild(el('p', '', (product.currency || '') + ' ' + (product.price || '')));
			var actions = el('div', 'cp-chatbot-card-actions');
			if (product.url) {
				var view = el('a', '', (cfg.i18n && cfg.i18n.view) || 'View product');
				view.href = product.url;
				actions.appendChild(view);
			}
			if (cfg.config.features && cfg.config.features.cart && product.id) {
				var add = el('button', '', (cfg.i18n && cfg.i18n.addToCart) || 'Add to cart');
				add.type = 'button';
				add.addEventListener('click', function () {
					addProduct(product.id);
				});
				actions.appendChild(add);
			}
			body.appendChild(actions);
			card.appendChild(body);
			wrap.appendChild(card);
		});
		return wrap;
	}

	function renderUpgrade(item) {
		var box = el('div', 'cp-chatbot-upgrade', item.content || ((cfg.i18n && cfg.i18n.upgrade) || ''));
		if (item.url) {
			var link = el('a', '', (cfg.i18n && cfg.i18n.upgradeCta) || 'Upgrade Plan');
			link.href = item.url;
			link.target = '_blank';
			link.rel = 'noopener noreferrer';
			box.appendChild(link);
		}
		return box;
	}

	function setLoading(value) {
		state.isLoading = value;
		if (root._send) {
			root._send.disabled = value;
		}
		if (root._input) {
			root._input.disabled = value;
		}
		paintMessages();
	}

	function submitMessage(input) {
		var text = (input.value || '').trim();
		if (!text || state.isLoading) {
			return;
		}
		input.value = '';
		state.messages.push({ role: 'user', content: text });
		sendChat(text);
	}

	function sendChat(text) {
		setLoading(true);
		API.chat(text)
			.then(function (result) {
				setLoading(false);
				var json = result.json || {};
				if (json.error && json.error.code === 'usage_limit_reached') {
					state.messages.push({
						type: 'upgrade',
						content: json.error.message || ((cfg.i18n && cfg.i18n.upgrade) || ''),
						url: json.error.upgrade_url || ''
					});
					paintMessages();
					return;
				}
				if (!result.ok) {
					state.messages.push({
						type: 'error',
						content: (json.error && json.error.message) || ((cfg.i18n && cfg.i18n.unavailable) || 'Sorry, the assistant is temporarily unavailable. Please try again.'),
						retry: function () {
							state.messages.pop();
							sendChat(text);
						}
					});
					paintMessages();
					return;
				}
				if (json.conversation_id) {
					state.conversationId = json.conversation_id;
					Storage.setConversation(json.conversation_id);
					startPolling();
				}
				if (json.usage) {
					state.usage = json.usage;
				}
				if (json.message && json.message.content) {
					state.messages.push({ role: 'assistant', content: json.message.content, id: json.message.id });
					trackMessageId(json.message.id);
				}
				if (json.products && json.products.length) {
					state.messages.push({ type: 'products', products: json.products });
				}
				// In human mode there is no inline reply: an agent answers from
				// wp-admin and we pick it up by polling.
				applyMode(json.mode);
				paintMessages();
			})
			.catch(function () {
				setLoading(false);
				state.messages.push({
					type: 'error',
					content: (cfg.i18n && cfg.i18n.unavailable) || 'Sorry, the assistant is temporarily unavailable. Please try again.',
					retry: function () {
						state.messages.pop();
						sendChat(text);
					}
				});
				paintMessages();
			});
	}

	function addProduct(productId) {
		API.addToCart(productId).then(function (result) {
			if (!result.ok) {
				state.messages.push({ type: 'error', content: (result.json && result.json.message) || ((cfg.i18n && cfg.i18n.unavailable) || '') });
				paintMessages();
				return;
			}
			state.cart = result.json;
			if (cfg.config.features && cfg.config.features.checkout) {
				var checkout = el('div', 'cp-chatbot-upgrade', (cfg.i18n && cfg.i18n.checkout) || 'Checkout');
				API.checkout().then(function (check) {
					if (check.ok && check.json && check.json.url) {
						var link = el('a', '', (cfg.i18n && cfg.i18n.checkout) || 'Checkout');
						link.href = check.json.url;
						checkout.textContent = '';
						checkout.appendChild(link);
						root._messages.appendChild(checkout);
					}
				});
			}
			state.messages.push({ role: 'assistant', content: 'Added to cart.' });
			paintMessages();
		});
	}

	function trackMessageId(id) {
		var value = parseInt(id, 10);
		if (!isNaN(value) && value > state.lastMessageId) {
			state.lastMessageId = value;
		}
	}

	function hasMessage(id) {
		var value = parseInt(id, 10);
		if (isNaN(value) || value < 1) {
			return false;
		}
		return state.messages.some(function (item) {
			return item.id === value;
		});
	}

	/**
	 * Switches between AI and human handling, showing the visitor a notice the
	 * first time a person takes over and polling for their replies.
	 */
	function applyMode(mode) {
		var next = mode === 'human' ? 'human' : 'ai';
		if (next === state.mode) {
			return;
		}
		state.mode = next;

		if (next === 'human') {
			state.messages.push({
				type: 'notice',
				content: (cfg.i18n && cfg.i18n.humanMode) || 'You are now chatting with our team.'
			});
		}

		startPolling();
	}

	function startPolling() {
		if (state.pollTimer || !state.conversationId || !state.isOpen) {
			return;
		}
		state.pollTimer = window.setInterval(function () {
			pollMessages(false);
		}, POLL_INTERVAL);
	}

	function stopPolling() {
		if (state.pollTimer) {
			window.clearInterval(state.pollTimer);
			state.pollTimer = null;
		}
	}

	function pollMessages(includeUser) {
		if (!state.conversationId || (document.hidden && !includeUser)) {
			return;
		}

		API.messages()
			.then(function (result) {
				if (!result.ok) {
					return;
				}
				var json = result.json || {};
				var incoming = json.messages || [];
				var rendered = [];

				incoming.forEach(function (message) {
					trackMessageId(message.id);
					if (!message.content) {
						return;
					}
					if (hasMessage(message.id)) {
						return;
					}
					// Outside the initial restore the visitor's own turns are
					// already on screen, so only agent replies are rendered.
					if (message.role === 'assistant' || includeUser) {
						rendered.push({ role: message.role, content: message.content, id: message.id });
					}
				});

				if (includeUser) {
					state.historyLoaded = true;
				}

				if (includeUser && rendered.length) {
					// Replace the standalone welcome message with the real thread.
					state.messages = rendered;
				} else {
					state.messages = state.messages.concat(rendered);
				}

				var before = state.mode;
				applyMode(json.mode);

				if (rendered.length || before !== state.mode) {
					paintMessages();
				}
			})
			.catch(function () {
				// Transient failure; the next tick retries.
			});
	}

	function openChat() {
		state.isOpen = true;
		root.classList.add('is-open');

		// First open of a returning visitor: pull the thread back so replies
		// sent while they were away are not lost.
		if (state.conversationId && !state.historyLoaded) {
			pollMessages(true);
		}

		startPolling();
		if (root._input) {
			root._input.focus();
		}
	}

	function closeChat() {
		state.isOpen = false;
		root.classList.remove('is-open');
		stopPolling();
	}

	document.addEventListener('keydown', function (event) {
		if (event.key === 'Escape' && state.isOpen) {
			closeChat();
		}
	});

	renderShell();
})();
