/* AI MCP front end: chatbots + AI forms. No dependencies. */
(function () {
	'use strict';
	var C = window.MoshAIChat || {};
	var T = C.i18n || {};

	function esc(s) {
		return String(s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}

	// Small, safe markdown: code blocks, inline code, bold, italics, links, lists, headings, line breaks.
	function md(src) {
		var blocks = [];
		var h = esc(src).replace(/```[a-zA-Z]*\n?([\s\S]*?)```/g, function (m, code) {
			blocks.push('<pre><code>' + code.replace(/\n$/, '') + '</code></pre>');
			return '@@MOSHAICODE' + (blocks.length - 1) + '@@';
		});
		h = h.replace(/`([^`\n]+)`/g, '<code>$1</code>');
		h = h.replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>');
		h = h.replace(/(^|[^*])\*([^*\n]+)\*/g, '$1<em>$2</em>');
		h = h.replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');
		h = h.replace(/(^|[\s(])(https?:\/\/[^\s<)]+)/g, '$1<a href="$2" target="_blank" rel="noopener">$2</a>');
		h = h.replace(/^#{1,6}\s+(.+)$/gm, '<strong>$1</strong>');
		h = h.replace(/^\s*[-*•]\s+(.+)$/gm, '<span class="moshai-li">$1</span>');
		h = h.replace(/\n/g, '<br>');
		h = h.replace(/@@MOSHAICODE(\d+)@@/g, function (m, i) { return blocks[+i] || ''; });
		return h;
	}

	function uuid() {
		if (window.crypto && crypto.randomUUID) { return crypto.randomUUID(); }
		return 'c' + Date.now().toString(36) + Math.random().toString(36).slice(2, 12);
	}

	function post(path, data) {
		var headers = { 'Content-Type': 'application/json' };
		if (C.nonce) { headers['X-WP-Nonce'] = C.nonce; }
		return fetch(C.rest + path, {
			method: 'POST',
			credentials: 'same-origin',
			headers: headers,
			body: JSON.stringify(data)
		}).then(function (r) {
			return r.json().catch(function () { return {}; }).then(function (j) {
				if (!r.ok) { throw new Error(j && j.message ? j.message : (T.error || 'Error')); }
				return j;
			});
		});
	}

	function el(tag, cls, html) {
		var e = document.createElement(tag);
		if (cls) { e.className = cls; }
		if (html !== undefined) { e.innerHTML = html; }
		return e;
	}

	/* ---------- Chatbot ---------- */

	function initBot(root) {
		var cfg;
		try { cfg = JSON.parse(root.getAttribute('data-moshai') || '{}'); } catch (e) { return; }
		var key = 'moshai_chat_' + cfg.id;
		var state = null;
		try { state = JSON.parse(sessionStorage.getItem(key)); } catch (e) { state = null; }
		if (!state || !state.chatId || !Array.isArray(state.messages)) { state = { chatId: uuid(), messages: [] }; }
		var busy = false;

		var win = el('div', 'moshai-window');
		var head = el('div', 'moshai-header');
		head.appendChild(el('span', 'moshai-header-title', esc(cfg.title || 'AI Assistant')));
		var tools = el('span', 'moshai-header-tools');
		var resetBtn = el('button', 'moshai-icon-btn', '&#8635;');
		resetBtn.type = 'button';
		resetBtn.title = T.reset || 'New conversation';
		resetBtn.setAttribute('aria-label', resetBtn.title);
		tools.appendChild(resetBtn);
		var closeBtn = null;
		if (cfg.popup) {
			closeBtn = el('button', 'moshai-icon-btn', '&times;');
			closeBtn.type = 'button';
			closeBtn.title = T.close || 'Close';
			closeBtn.setAttribute('aria-label', closeBtn.title);
			tools.appendChild(closeBtn);
		}
		head.appendChild(tools);

		var list = el('div', 'moshai-messages');
		list.setAttribute('aria-live', 'polite');
		var form = el('form', 'moshai-input');
		var input = el('textarea');
		input.rows = 1;
		input.placeholder = cfg.placeholder || '';
		input.maxLength = cfg.max_input || 1000;
		input.setAttribute('aria-label', cfg.placeholder || 'Message');
		var send = el('button', 'moshai-send', esc(cfg.send || 'Send'));
		send.type = 'submit';
		form.appendChild(input);
		form.appendChild(send);

		win.appendChild(head);
		win.appendChild(list);
		win.appendChild(form);

		if (cfg.popup) {
			var launcher = el('button', 'moshai-launcher', '<svg viewBox="0 0 24 24" width="26" height="26" aria-hidden="true"><path fill="currentColor" d="M12 3C6.5 3 2 6.8 2 11.5c0 2.4 1.2 4.6 3.1 6.1L4 21l4.2-2.1c1.2.4 2.5.6 3.8.6 5.5 0 10-3.8 10-8.5S17.5 3 12 3z"/></svg>');
			launcher.type = 'button';
			launcher.setAttribute('aria-label', T.open || 'Open chat');
			win.hidden = true;
			root.appendChild(win);
			root.appendChild(launcher);
			launcher.addEventListener('click', function () {
				win.hidden = !win.hidden;
				root.classList.toggle('is-open', !win.hidden);
				if (!win.hidden) { input.focus(); list.scrollTop = list.scrollHeight; }
			});
			closeBtn.addEventListener('click', function () { win.hidden = true; root.classList.remove('is-open'); });
		} else {
			root.appendChild(win);
		}

		function save() {
			try { sessionStorage.setItem(key, JSON.stringify(state)); } catch (e) {}
		}

		function bubble(role, text, extraCls) {
			var b = el('div', 'moshai-msg moshai-' + role + (extraCls ? ' ' + extraCls : ''), role === 'user' ? esc(text).replace(/\n/g, '<br>') : md(text));
			list.appendChild(b);
			list.scrollTop = list.scrollHeight;
			return b;
		}

		function render() {
			list.innerHTML = '';
			if (cfg.start) { bubble('assistant', cfg.start); }
			state.messages.forEach(function (m) { bubble(m.role, m.content); });
		}

		function submit() {
			var text = input.value.trim();
			if (!text || busy) { return; }
			busy = true;
			send.disabled = true;
			var history = state.messages.slice();
			state.messages.push({ role: 'user', content: text });
			save();
			bubble('user', text);
			input.value = '';
			input.style.height = '';
			var typing = bubble('assistant', '', 'moshai-typing');
			typing.innerHTML = '<span></span><span></span><span></span>';
			post('chat', { bot_id: cfg.id, chat_id: state.chatId, message: text, history: history })
				.then(function (r) {
					typing.remove();
					state.messages.push({ role: 'assistant', content: r.reply || '' });
					save();
					var b = bubble('assistant', r.reply || '');
					if (r.sources && r.sources.length) {
						var src = el('div', 'moshai-sources');
						r.sources.forEach(function (x) {
							var a = el('a', '', esc(x.title || x.url));
							a.href = x.url;
							src.appendChild(a);
						});
						b.appendChild(src);
						list.scrollTop = list.scrollHeight;
					}
				})
				.catch(function (err) {
					typing.remove();
					state.messages.pop();
					save();
					bubble('assistant', err.message || T.error, 'moshai-error');
				})
				.then(function () {
					busy = false;
					send.disabled = false;
					input.focus();
				});
		}

		form.addEventListener('submit', function (e) { e.preventDefault(); submit(); });
		input.addEventListener('keydown', function (e) {
			if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); submit(); }
		});
		input.addEventListener('input', function () {
			input.style.height = 'auto';
			input.style.height = Math.min(input.scrollHeight, 140) + 'px';
		});
		resetBtn.addEventListener('click', function () {
			state = { chatId: uuid(), messages: [] };
			save();
			render();
		});

		render();
	}

	/* ---------- AI forms ---------- */

	function initForm(form) {
		var out = form.querySelector('.moshai-form-output');
		var btn = form.querySelector('.moshai-form-submit');
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var fields = {};
			Array.prototype.forEach.call(form.elements, function (f) {
				if (f.name) { fields[f.name] = f.value; }
			});
			btn.disabled = true;
			form.classList.add('is-busy');
			out.hidden = false;
			out.className = 'moshai-form-output is-loading';
			out.innerHTML = '<span class="moshai-typing"><span></span><span></span><span></span></span>';
			post('form', { form_id: form.getAttribute('data-form'), fields: fields })
				.then(function (r) {
					out.className = 'moshai-form-output';
					out.innerHTML = md(r.reply || '');
					var copy = el('button', 'moshai-copy-btn', esc(T.copy || 'Copy'));
					copy.type = 'button';
					copy.addEventListener('click', function () {
						if (navigator.clipboard) { navigator.clipboard.writeText(r.reply || ''); }
						copy.textContent = '✓';
					});
					out.appendChild(copy);
				})
				.catch(function (err) {
					out.className = 'moshai-form-output moshai-error';
					out.textContent = err.message || T.error;
				})
				.then(function () {
					btn.disabled = false;
					form.classList.remove('is-busy');
				});
		});
	}

	function boot() {
		document.querySelectorAll('.moshai-chatbot:not([data-ready])').forEach(function (n) {
			n.setAttribute('data-ready', '1');
			initBot(n);
		});
		document.querySelectorAll('form.moshai-form:not([data-ready])').forEach(function (n) {
			n.setAttribute('data-ready', '1');
			initForm(n);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
	// Elementor popups and other dynamic content.
	window.addEventListener('elementor/popup/show', boot);
	window.MoshAI = { boot: boot };
})();
