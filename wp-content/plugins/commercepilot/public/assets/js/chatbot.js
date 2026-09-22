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
		welcomeKey: 'commercepilot_welcome_delivered',
		unreadKey: 'commercepilot_unread_count',
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
		clearConversation: function () {
			localStorage.removeItem(this.conversationKey);
		},
		hasDeliveredWelcome: function () {
			return localStorage.getItem(this.welcomeKey) === '1';
		},
		markWelcomeDelivered: function () {
			localStorage.setItem(this.welcomeKey, '1');
		},
		getUnread: function () {
			var value = parseInt(sessionStorage.getItem(this.unreadKey) || '0', 10);
			return isNaN(value) || value < 0 ? 0 : value;
		},
		setUnread: function (count) {
			var value = Math.max(0, parseInt(count, 10) || 0);
			if (value === 0) {
				sessionStorage.removeItem(this.unreadKey);
			} else {
				sessionStorage.setItem(this.unreadKey, String(value));
			}
			return value;
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
		historyLoaded: false,
		unread: Storage.getUnread(),
		notifyAudio: null,
		pendingNotifySound: false,
		notifyUnlockArmed: false
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
		var badge = el('span', 'cp-chatbot-badge');
		badge.hidden = true;
		badge.setAttribute('aria-live', 'polite');
		toggle.appendChild(badge);
		toggle.addEventListener('click', openChat);

		root.appendChild(windowEl);
		root.appendChild(toggle);
		root._messages = messages;
		root._input = input;
		root._send = send;
		root._toggle = toggle;
		root._badge = badge;

		deliverWelcome();
		paintBadge();
		paintMessages();
	}

	/**
	 * First visit: queue the configured greeting, bump the unread badge, and
	 * play a soft chime as soon as the page loads. Incognito/private windows
	 * block autoplay, so if that fails we play on the next user gesture
	 * (pointerdown) — still before the chat panel opens.
	 */
	function deliverWelcome() {
		var welcome = resolveWelcome();
		if (!welcome) {
			return;
		}

		state.messages.push({ role: 'assistant', content: welcome, id: 'welcome' });

		if (Storage.hasDeliveredWelcome()) {
			return;
		}

		Storage.markWelcomeDelivered();
		bumpUnread(1, false);

		// Warm the audio element early so the first play() is as fast as possible.
		state.notifyAudio = buildNotifyAudio();

		var play = function () {
			if (state.isOpen) {
				return;
			}
			playNotifySound();
		};

		if (document.readyState === 'complete') {
			window.setTimeout(play, 300);
		} else {
			window.addEventListener('load', function () {
				window.setTimeout(play, 300);
			}, { once: true });
		}
	}

	function resolveWelcome() {
		var welcome = (cfg.config.welcome_message || '').trim();
		if (!welcome) {
			return '';
		}
		return welcome.replace(/\{assistant_name\}/gi, assistantName());
	}

	function bumpUnread(count, withSound) {
		if (state.isOpen) {
			return;
		}
		state.unread = Storage.setUnread(state.unread + (count || 1));
		paintBadge();
		if (withSound) {
			playNotifySound();
		}
	}

	function clearUnread() {
		state.unread = Storage.setUnread(0);
		paintBadge();
	}

	function paintBadge() {
		var badge = root._badge;
		if (!badge) {
			return;
		}
		if (state.unread > 0 && !state.isOpen) {
			badge.hidden = false;
			badge.textContent = state.unread > 99 ? '99+' : String(state.unread);
			badge.setAttribute('aria-label', state.unread + ' unread messages');
			badge.classList.toggle('is-pending-sound', state.pendingNotifySound);
		} else {
			badge.hidden = true;
			badge.textContent = '';
			badge.removeAttribute('aria-label');
			badge.classList.remove('is-pending-sound');
		}
	}

	/**
	 * Soft chime. Tries immediately (page load). If the browser blocks autoplay
	 * (common in Incognito), queues the sound for the next real user gesture.
	 */
	function playNotifySound() {
		if (state.isOpen) {
			return;
		}

		try {
			var audio = state.notifyAudio || buildNotifyAudio();
			state.notifyAudio = audio;
			audio.currentTime = 0;
			var result = audio.play();
			if (result && typeof result.then === 'function') {
				result.then(function () {
					state.pendingNotifySound = false;
					disarmNotifyUnlock();
					paintBadge();
				}).catch(function () {
					queueNotifySound();
				});
				return;
			}
			state.pendingNotifySound = false;
			disarmNotifyUnlock();
		} catch (err) {
			queueNotifySound();
		}
	}

	function queueNotifySound() {
		state.pendingNotifySound = true;
		paintBadge();
		armNotifyUnlock();
	}

	/**
	 * Incognito/private mode requires a user gesture. Capture-phase listeners
	 * run before the chat toggle's click handler, so the chime can still fire
	 * on page interaction without waiting until the inbox is open.
	 */
	function armNotifyUnlock() {
		if (state.notifyUnlockArmed) {
			return;
		}
		state.notifyUnlockArmed = true;
		window.addEventListener('pointerdown', flushNotifySound, true);
		window.addEventListener('keydown', flushNotifySound, true);
		window.addEventListener('touchstart', flushNotifySound, true);
	}

	function disarmNotifyUnlock() {
		if (!state.notifyUnlockArmed) {
			return;
		}
		state.notifyUnlockArmed = false;
		window.removeEventListener('pointerdown', flushNotifySound, true);
		window.removeEventListener('keydown', flushNotifySound, true);
		window.removeEventListener('touchstart', flushNotifySound, true);
	}

	function flushNotifySound() {
		if (!state.pendingNotifySound || state.isOpen) {
			return;
		}

		state.pendingNotifySound = false;
		disarmNotifyUnlock();
		paintBadge();

		try {
			var audio = state.notifyAudio || buildNotifyAudio();
			state.notifyAudio = audio;
			audio.currentTime = 0;
			var result = audio.play();
			if (result && typeof result.catch === 'function') {
				result.catch(function () {
					playWebAudioChime();
				});
			}
		} catch (err) {
			playWebAudioChime();
		}
	}

	function playWebAudioChime() {
		try {
			var Ctx = window.AudioContext || window.webkitAudioContext;
			if (!Ctx) {
				return;
			}
			var ctx = new Ctx();
			var play = function () {
				var now = ctx.currentTime;
				function tone(freq, start, duration) {
					var osc = ctx.createOscillator();
					var gain = ctx.createGain();
					osc.type = 'sine';
					osc.frequency.value = freq;
					gain.gain.setValueAtTime(0.0001, start);
					gain.gain.exponentialRampToValueAtTime(0.08, start + 0.02);
					gain.gain.exponentialRampToValueAtTime(0.0001, start + duration);
					osc.connect(gain);
					gain.connect(ctx.destination);
					osc.start(start);
					osc.stop(start + duration + 0.02);
				}
				tone(880, now, 0.12);
				tone(1174.7, now + 0.12, 0.18);
				window.setTimeout(function () {
					if (ctx.state !== 'closed') {
						ctx.close();
					}
				}, 500);
			};
			if (ctx.state === 'suspended') {
				ctx.resume().then(play).catch(function () {});
			} else {
				play();
			}
		} catch (err) {
			// ignore
		}
	}

	/**
	 * Tiny two-beep WAV encoded as a data URI — no external file needed.
	 */
	function buildNotifyAudio() {
		var sampleRate = 22050;
		var duration = 0.32;
		var samples = Math.floor(sampleRate * duration);
		var dataSize = samples * 2;
		var buffer = new ArrayBuffer(44 + dataSize);
		var view = new DataView(buffer);

		function writeString(offset, value) {
			for (var i = 0; i < value.length; i++) {
				view.setUint8(offset + i, value.charCodeAt(i));
			}
		}

		writeString(0, 'RIFF');
		view.setUint32(4, 36 + dataSize, true);
		writeString(8, 'WAVE');
		writeString(12, 'fmt ');
		view.setUint32(16, 16, true);
		view.setUint16(20, 1, true);
		view.setUint16(22, 1, true);
		view.setUint32(24, sampleRate, true);
		view.setUint32(28, sampleRate * 2, true);
		view.setUint16(32, 2, true);
		view.setUint16(34, 16, true);
		writeString(36, 'data');
		view.setUint32(40, dataSize, true);

		for (var i = 0; i < samples; i++) {
			var t = i / sampleRate;
			var envelope = 0;
			var freq = 880;
			if (t < 0.14) {
				envelope = Math.sin((Math.PI * t) / 0.14);
				freq = 880;
			} else if (t > 0.16 && t < 0.32) {
				envelope = Math.sin((Math.PI * (t - 0.16)) / 0.16);
				freq = 1175;
			}
			var sample = Math.max(-1, Math.min(1, Math.sin(2 * Math.PI * freq * t) * envelope * 0.35));
			view.setInt16(44 + i * 2, sample < 0 ? sample * 0x8000 : sample * 0x7fff, true);
		}

		var bytes = new Uint8Array(buffer);
		var binary = '';
		for (var j = 0; j < bytes.length; j++) {
			binary += String.fromCharCode(bytes[j]);
		}

		var audio = new Audio('data:audio/wav;base64,' + btoa(binary));
		audio.preload = 'auto';
		audio.volume = 0.85;
		return audio;
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
		if (state.pollTimer || !state.conversationId) {
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
				var unreadDelta = 0;

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
						if (!state.isOpen && message.role === 'assistant') {
							unreadDelta += 1;
						}
					}
				});

				if (includeUser) {
					state.historyLoaded = true;
				}

				if (includeUser && rendered.length) {
					// Replace the standalone welcome message with the real thread.
					state.messages = rendered;
				} else if (rendered.length) {
					state.messages = state.messages.concat(rendered);
				}

				var before = state.mode;
				applyMode(json.mode);

				if (unreadDelta > 0) {
					bumpUnread(unreadDelta, true);
				}

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
		clearUnread();

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
		// Keep polling while a human agent owns the thread so new replies can
		// land on the launcher badge even when the panel is closed.
		if (state.mode === 'human' && state.conversationId) {
			startPolling();
		} else {
			stopPolling();
		}
		paintBadge();
	}

	document.addEventListener('keydown', function (event) {
		if (event.key === 'Escape' && state.isOpen) {
			closeChat();
		}
	});

	renderShell();
})();
