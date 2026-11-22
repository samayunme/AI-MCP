/* AI MCP admin app. No dependencies. */
(function () {
	'use strict';
	var A = window.MoshAIAdmin || {};
	var app = document.querySelector('.moshai-app');
	if (!app) { return; }

	/* ---------- Helpers ---------- */

	function api(path, data) {
		return fetch(A.rest + path, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': A.nonce },
			body: JSON.stringify(data || {})
		}).then(function (r) {
			return r.json().catch(function () { return {}; }).then(function (j) {
				if (!r.ok) { throw new Error(j && j.message ? j.message : 'Request failed (' + r.status + ')'); }
				return j;
			});
		});
	}
	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}
	function $(sel, ctx) { return (ctx || document).querySelector(sel); }
	function $$(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }
	function collect(box) {
		var o = {};
		$$('[name]', box).forEach(function (f) {
			if (f.closest('[data-ignore]')) { return; }
			o[f.name] = f.type === 'checkbox' ? (f.checked ? 1 : 0) : f.value;
		});
		return o;
	}
	function fill(box, data) {
		$$('[name]', box).forEach(function (f) {
			var v = data[f.name];
			if (f.type === 'checkbox') { f.checked = !!(+v); }
			else { f.value = v == null ? '' : v; }
		});
		syncModelLists(box);
	}
	function busy(btn, on, label) {
		if (!btn) { return; }
		if (on) { btn.dataset.label = btn.textContent; btn.textContent = label || 'Working…'; btn.disabled = true; btn.classList.add('is-busy'); }
		else { btn.textContent = btn.dataset.label || btn.textContent; btn.disabled = false; btn.classList.remove('is-busy'); }
	}
	var toastEl = $('.mx-toast', app), toastTimer;
	function toast(msg, type) {
		toastEl.textContent = msg;
		toastEl.className = 'mx-toast' + (type ? ' is-' + type : '');
		toastEl.hidden = false;
		clearTimeout(toastTimer);
		toastTimer = setTimeout(function () { toastEl.hidden = true; }, type === 'error' ? 6000 : 2600);
	}
	function out(box, html, isError) {
		var o = $('.moshai-output', box);
		if (!o) { return; }
		o.innerHTML = html;
		o.classList.toggle('is-error', !!isError);
		o.hidden = false;
	}
	function fail(box) { return function (e) { if (box) { out(box, esc(e.message), true); } toast(e.message, 'error'); }; }
	function usage(r) { return (r.tokens_in || r.tokens_out) ? (r.tokens_in || 0) + ' in · ' + (r.tokens_out || 0) + ' out tokens' : ''; }
	function syncModelLists(scope) {
		$$('[data-moshai-provider]', scope).forEach(function (sel) {
			var holder = sel.closest('.mx-grid, .mx-row, [data-fields], .moshai-tool') || scope;
			var input = $('[data-moshai-model]', holder);
			if (input) { input.setAttribute('list', 'moshai-models-' + (sel.value || A.defaultProvider)); }
		});
	}

	/* ---------- Tabs ---------- */

	function showTab(tab, sub) {
		if (!$('[data-panel="' + tab + '"]', app)) { tab = 'home'; }
		$$('.mx-panel', app).forEach(function (p) { p.hidden = p.getAttribute('data-panel') !== tab; });
		$$('.mx-tab', app).forEach(function (t) { t.classList.toggle('is-active', t.getAttribute('data-go') === tab); });
		var slug = A.slugs[tab] || 'moshai';
		if (window.history && history.replaceState) { history.replaceState(null, '', A.adminUrl + '?page=' + slug + (sub ? '#' + sub : '')); }
		// Keep the WordPress sidebar in sync.
		$$('#toplevel_page_moshai .wp-submenu li').forEach(function (li) {
			var a = $('a', li);
			li.classList.toggle('current', !!a && /[?&]page=([^&#]+)/.test(a.getAttribute('href')) && RegExp.$1 === slug);
		});
		if (sub && tab === 'studio') { showSub(sub); }
		if (sub && tab === 'settings') { var t = document.getElementById('mx-s-' + sub); if (t) { t.scrollIntoView({ behavior: 'smooth' }); } }
		window.scrollTo({ top: 0 });
	}
	document.addEventListener('click', function (e) {
		var go = e.target.closest && e.target.closest('[data-go]');
		if (!go || e.metaKey || e.ctrlKey) { return; }
		e.preventDefault();
		var parts = go.getAttribute('data-go').split(':');
		showTab(parts[0], parts[1]);
	});
	// Sidebar submenu links switch tabs without reloading.
	$$('#toplevel_page_moshai .wp-submenu a').forEach(function (a) {
		a.addEventListener('click', function (e) {
			var m = /[?&]page=([^&#]+)/.exec(a.getAttribute('href') || '');
			if (!m) { return; }
			for (var k in A.slugs) { if (A.slugs[k] === m[1]) { e.preventDefault(); showTab(k); return; } }
		});
	});
	var hash = (location.hash || '').slice(1);
	if (hash) { showTab(A.tab, hash); }

	/* ---------- Global behaviours ---------- */

	document.addEventListener('change', function (e) {
		if (e.target.matches && e.target.matches('[data-moshai-provider]')) { syncModelLists(e.target.closest('.mx-panel') || document); }
	});
	document.addEventListener('click', function (e) {
		var c = e.target.closest && e.target.closest('.moshai-copy');
		if (!c || !navigator.clipboard) { return; }
		navigator.clipboard.writeText(c.textContent.trim()).then(function () { toast('Copied'); });
	});
	// Instant settings: save as soon as they change.
	$$('[data-instant]', app).forEach(function (f) {
		f.addEventListener('change', function () {
			var data = {};
			data[f.name] = f.type === 'checkbox' ? (f.checked ? 1 : 0) : f.value;
			api('admin/settings', data).then(function (r) {
				if ('popup_bot' in data) { A.popupBot = r.popup_bot; $$('[data-popup-select]').forEach(function (s) { s.value = r.popup_bot; }); }
				toast('Saved');
			}).catch(fail());
		});
	});
	syncModelLists(app);

	/* ---------- Home: one-click connect ---------- */

	var connect = $('[data-connect]', app);
	if (connect) {
		connect.addEventListener('click', function () {
			var box = $('[data-connect-box]', app);
			var d = collect(box);
			if (!d.key) { toast('Paste your key first', 'error'); return; }
			busy(connect, true, 'Checking…');
			api('admin/test-key', { provider: d.provider, key: d.key, save: 1, make_default: 1 }).then(function () {
				toast('Connected. Reloading…', 'success');
				setTimeout(function () { location.reload(); }, 700);
			}).catch(function (e) { busy(connect, false); toast(e.message, 'error'); });
		});
	}

	/* ---------- Settings ---------- */

	$$('[data-settings]', app).forEach(function (settings) {
		var bar = $('[data-savebar]', settings);
		var initial = JSON.stringify(collect(settings));
		var markDirty = function () { bar.hidden = JSON.stringify(collect(settings)) === initial; };
		settings.addEventListener('input', markDirty);
		settings.addEventListener('change', markDirty);
		$('[data-discard]', settings).addEventListener('click', function () { location.reload(); });

		var applyKeys = function (keys) {
			$$('.mx-key', settings).forEach(function (card) {
				var p = card.getAttribute('data-provider');
				var k = keys[p] || {};
				var badge = $('[data-key-status]', card);
				var input = $('[data-key-input]', card);
				var rm = $('[data-remove-key]', card);
				var on = !!(k.connected || k.mask);
				badge.textContent = on ? 'Connected' : 'Not set';
				badge.className = 'mx-badge ' + (on ? 'is-on' : 'is-off');
				if (input) { input.value = ''; input.placeholder = k.mask || (p === 'custom' ? 'API key (leave empty for Ollama / LM Studio)' : 'Paste your key'); }
				if (rm) { rm.hidden = !on; }
			});
		};
		var saveBtn = $('[data-save-settings]', settings);
		var save = function () {
			var data = collect(settings);
			Object.keys(data).forEach(function (k) { if (/_key$/.test(k) && data[k] === '') { delete data[k]; } });
			busy(saveBtn, true, 'Saving…');
			return api('admin/settings', data).then(function (r) {
				applyKeys(r.keys);
				initial = JSON.stringify(collect(settings));
				bar.hidden = true;
				toast('Settings saved', 'success');
				if (r.new_token) {
					showToken(r.new_token);
					toast('MCP is on. Copy your token now, it is shown only once.', 'success');
				}
				refreshStatus();
				// Locks and the header depend on which AI is connected.
				if (typeof r.has_ai !== 'undefined' && r.has_ai !== A.hasAI) { setTimeout(function () { location.reload(); }, 900); }
			}).catch(fail()).then(function () { busy(saveBtn, false); });
		};
		saveBtn.addEventListener('click', save);
		document.addEventListener('keydown', function (e) {
			if ((e.ctrlKey || e.metaKey) && e.key === 's' && !settings.closest('.mx-panel').hidden) { e.preventDefault(); save(); }
		});

		$$('[data-test-key]', settings).forEach(function (btn) {
			btn.addEventListener('click', function () {
				var p = btn.getAttribute('data-test-key');
				var card = btn.closest('.mx-key');
				var input = $('[data-key-input]', card);
				var payload = { provider: p, key: input ? input.value : '', save: input && input.value ? 1 : 0 };
				if (p === 'custom') {
					payload.url = $('[name=custom_url]', card).value;
					payload.model = $('[name=custom_model]', card).value;
					payload.name = $('[name=custom_name]', card).value;
					payload.save = 1;
				}
				busy(btn, true, 'Testing…');
				api('admin/test-key', payload).then(function (r) {
					applyKeys(r.keys);
					var dl = document.getElementById('moshai-models-' + p);
					if (dl && r.models) { dl.innerHTML = r.models.map(function (m) { return '<option value="' + esc(m) + '"></option>'; }).join(''); }
					markDirty();
					toast('Key works · ' + r.count + ' models available', 'success');
				}).catch(fail()).then(function () { busy(btn, false); });
			});
		});
		$$('[data-remove-key]', settings).forEach(function (btn) {
			btn.addEventListener('click', function () {
				if (!window.confirm('Remove this API key?')) { return; }
				var data = {};
				data[btn.getAttribute('data-remove-key') + '_key'] = '';
				if (btn.getAttribute('data-remove-key') === 'custom') { data.custom_url = ''; }
				api('admin/settings', data).then(function (r) { applyKeys(r.keys); toast('Key removed'); }).catch(fail());
			});
		});
		var gen = $('[data-mcp-generate]', settings);
		if (gen) {
			gen.addEventListener('click', function () {
				var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
				var arr = new Uint32Array(48);
				window.crypto.getRandomValues(arr);
				$('[data-mcp-token]', settings).value = Array.prototype.map.call(arr, function (n) { return chars[n % chars.length]; }).join('');
				markDirty();
			});
		}
		// Side nav scroll + highlight.
		$$('.mx-side a', settings).forEach(function (a) {
			a.addEventListener('click', function (e) {
				e.preventDefault();
				var t = $(a.getAttribute('href'));
				if (t) { t.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
			});
		});
	});

	/* ---------- Chatbots + forms editors ---------- */

	function parseFields(text) {
		return String(text || '').split(/\r?\n/).map(function (l) { return l.trim(); }).filter(Boolean).map(function (l) {
			var p = l.split('|').map(function (x) { return x.trim(); });
			return { name: p[0].toLowerCase().replace(/[^a-z0-9_\-]/g, ''), label: p[1] || p[0], type: (p[2] || 'text').toLowerCase(), options: p[3] ? p[3].split(',').map(function (o) { return o.trim(); }).filter(Boolean) : [] };
		}).filter(function (f) { return f.name; });
	}

	var editors = {
		bots: {
			items: function () { return A.bots; },
			set: function (v) { A.bots = v; },
			defaults: A.botDefaults,
			save: 'admin/bot/save',
			del: 'admin/bot/delete',
			noun: 'chatbot',
			shortcode: function (id) { return '[ai_mcp_chatbot id="' + id + '"]'; },
			extra: function (item) { return { site_popup: A.popupBot === item.id ? 1 : 0 }; },
			badge: function (item) { return A.popupBot === item.id ? '<span class="mx-badge is-on">bubble</span>' : ''; },
			preview: function (item) {
				var pub = { id: item.id, title: item.title, start: item.start, placeholder: item.placeholder, send: item.send, max_input: +item.max_input, popup: false };
				return '<div class="moshai-chatbot moshai-inline" data-moshai="' + esc(JSON.stringify(pub)) + '" style="--moshai-color:' + esc(item.color) + ';--moshai-on:' + onColor(item.color) + '"></div>';
			},
			after: function (r) { A.popupBot = r.popup; $$('[data-popup-select]').forEach(function (s) { s.value = r.popup || ''; }); }
		},
		forms: {
			items: function () { return A.forms; },
			set: function (v) { A.forms = v; },
			defaults: A.formDefaults,
			save: 'admin/form/save',
			del: 'admin/form/delete',
			noun: 'form',
			shortcode: function (id) { return '[ai_mcp_form id="' + id + '"]'; },
			extra: function () { return {}; },
			badge: function () { return ''; },
			preview: function (item) {
				var h = '<form class="moshai-form" data-form="' + esc(item.id) + '">';
				parseFields(item.fields).forEach(function (f) {
					h += '<p class="moshai-field"><label>' + esc(f.label) + '</label>';
					if (f.type === 'textarea') { h += '<textarea name="' + esc(f.name) + '" rows="3" required></textarea>'; }
					else if (f.type === 'select') { h += '<select name="' + esc(f.name) + '">' + f.options.map(function (o) { return '<option>' + esc(o) + '</option>'; }).join('') + '</select>'; }
					else { h += '<input type="' + esc(['number', 'email', 'url'].indexOf(f.type) > -1 ? f.type : 'text') + '" name="' + esc(f.name) + '" required>'; }
					h += '</p>';
				});
				return h + '<p><button type="submit" class="moshai-form-submit">' + esc(item.button || 'Generate') + '</button></p><div class="moshai-form-output" hidden></div></form>';
			},
			after: function () {}
		}
	};

	$$('[data-editor]', app).forEach(function (root) {
		var cfg = editors[root.getAttribute('data-editor')];
		if (!cfg) { return; }
		var list = $('[data-list]', root), fields = $('[data-fields]', root), preview = $('[data-preview]', root);
		var current = null;

		function renderList() {
			var items = cfg.items();
			list.innerHTML = Object.keys(items).map(function (id) {
				return '<li><button type="button" data-pick="' + esc(id) + '" class="' + (current === id ? 'is-active' : '') + '"><strong>' + esc(items[id].name || id) + '</strong>' + cfg.badge(items[id]) + '<small>' + esc(id) + '</small></button></li>';
			}).join('') || '<li class="mx-help">Nothing yet. Click + New.</li>';
		}
		function renderPreview(item) {
			if (!item) { preview.innerHTML = '<p class="mx-help">Save to try it here.</p>'; return; }
			preview.innerHTML = cfg.preview(item);
			if (window.MoshAI) { window.MoshAI.boot(); }
		}
		function pick(id) {
			var items = cfg.items();
			var isNew = !id || !items[id];
			current = isNew ? null : id;
			var item = isNew ? Object.assign({}, cfg.defaults, { id: '', name: '' }) : items[id];
			fill(fields, Object.assign({}, item, cfg.extra(item), { slug: '' }));
			root.classList.toggle('is-new', isNew);
			$('[data-edit-title]', root).textContent = isNew ? 'New ' + cfg.noun : (item.name || id);
			var sc = $('[data-shortcode]', root);
			sc.textContent = isNew ? '' : cfg.shortcode(id);
			sc.hidden = isNew;
			$('[data-delete]', root).hidden = isNew || id === 'default';
			renderList();
			renderPreview(isNew ? null : item);
		}
		list.addEventListener('click', function (e) {
			var b = e.target.closest('[data-pick]');
			if (b) { pick(b.getAttribute('data-pick')); }
		});
		$('[data-new]', root).addEventListener('click', function () { pick(null); $('[name="name"]', fields).focus(); });
		var saveBtn = $('[data-save]', root);
		saveBtn.addEventListener('click', function () {
			var data = collect(fields);
			if (!data.name) { toast('Give it a name', 'error'); return; }
			busy(saveBtn, true, 'Saving…');
			api(cfg.save, data).then(function (r) {
				cfg.set(r.bots || r.forms);
				cfg.after(r);
				pick((r.bot || r.form).id);
				toast('Saved', 'success');
			}).catch(fail()).then(function () { busy(saveBtn, false); });
		});
		$('[data-delete]', root).addEventListener('click', function () {
			if (!current || !window.confirm('Delete this ' + cfg.noun + '?')) { return; }
			api(cfg.del, { id: current }).then(function (r) {
				cfg.set(r.bots || r.forms);
				cfg.after(r);
				var ids = Object.keys(cfg.items());
				pick(ids[0] || null);
				toast('Deleted');
			}).catch(fail());
		});
		var first = Object.keys(cfg.items())[0];
		pick(first || null);
	});

	/* ---------- Studio ---------- */

	function showSub(sub) {
		var studio = $('[data-panel="studio"]', app);
		if (!studio || !$('[data-subpanel="' + sub + '"]', studio)) { return; }
		$$('[data-subpanel]', studio).forEach(function (p) { p.hidden = p.getAttribute('data-subpanel') !== sub; });
		$$('[data-sub]', studio).forEach(function (b) { b.classList.toggle('is-active', b.getAttribute('data-sub') === sub); });
	}
	$$('[data-sub]', app).forEach(function (b) { b.addEventListener('click', function () { showSub(b.getAttribute('data-sub')); }); });

	var tools = {
		playground: function (box) {
			var btn = $('[data-run]', box);
			var run = function () {
				busy(btn, true, 'Thinking…');
				$('.moshai-meta', box).textContent = '';
				api('admin/generate', collect(box)).then(function (r) {
					out(box, '<pre class="mx-pre">' + esc(r.text) + '</pre><button type="button" class="button button-small mx-copy-out">Copy</button>');
					$('.mx-copy-out', box).addEventListener('click', function () { navigator.clipboard.writeText(r.text); toast('Copied'); });
					$('.moshai-meta', box).textContent = usage(r);
				}).catch(fail(box)).then(function () { busy(btn, false); });
			};
			btn.addEventListener('click', run);
			$('[name="prompt"]', box).addEventListener('keydown', function (e) { if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') { run(); } });
		},
		content: function (box) {
			var btn = $('[data-run]', box), res = $('.moshai-content-result', box), preview = $('.moshai-preview', box);
			var f = function (n) { return $('[data-field="' + n + '"]', box); };
			btn.addEventListener('click', function () {
				var data = collect(box);
				if (!data.topic) { toast('Give it a topic', 'error'); return; }
				busy(btn, true, 'Writing… (up to a minute)');
				$('.moshai-output', box).hidden = true;
				api('admin/content', data).then(function (r) {
					f('title').value = r.title || '';
					f('excerpt').value = r.excerpt || '';
					f('content').value = r.content || '';
					preview.innerHTML = r.content || '';
					res.hidden = false;
					$('.moshai-meta', box).textContent = usage(r);
				}).catch(fail(box)).then(function () { busy(btn, false); });
			});
			f('content').addEventListener('input', function () { preview.innerHTML = f('content').value; });
			$$('[data-save-draft]', box).forEach(function (b) {
				b.addEventListener('click', function () {
					busy(b, true, 'Saving…');
					api('admin/create-post', { title: f('title').value, excerpt: f('excerpt').value, content: f('content').value, post_type: b.getAttribute('data-save-draft') }).then(function (r) {
						$('.moshai-saved', box).innerHTML = 'Draft saved. <a href="' + esc(r.edit) + '">Open it &rarr;</a>';
						toast('Draft saved', 'success');
					}).catch(fail(box)).then(function () { busy(b, false); });
				});
			});
		},
		image: function (box) {
			var btn = $('[data-run]', box), gallery = $('.moshai-gallery', box);
			btn.addEventListener('click', function () {
				var data = collect(box);
				if (!data.prompt) { toast('Describe the image first', 'error'); return; }
				busy(btn, true, 'Painting… (20-60s)');
				$('.moshai-output', box).hidden = true;
				api('admin/image', data).then(function (r) {
					var fig = document.createElement('figure');
					fig.className = 'mx-image';
					fig.innerHTML = '<a href="' + esc(r.edit) + '"><img src="' + esc(r.url) + '" alt=""></a><figcaption><a href="' + esc(r.edit) + '">Open in Media Library</a><span class="moshai-copy">' + esc(r.url) + '</span></figcaption>';
					gallery.insertBefore(fig, gallery.firstChild);
					toast('Saved to Media Library', 'success');
				}).catch(fail(box)).then(function () { busy(btn, false); });
			});
		},
		kb: function (box) {
			var rows = $('.moshai-kb-rows', box), total = $('.moshai-kb-total', box);
			function load() {
				api('admin/kb/list').then(function (r) {
					total.textContent = r.total;
					rows.innerHTML = r.items.length ? r.items.map(function (it) {
						return '<tr><td><strong>' + esc(it.title) + '</strong></td><td>' + esc(it.preview) + '…</td><td>' + (+it.post_id ? 'Post #' + esc(it.post_id) : 'Custom') + '</td><td><button type="button" class="button-link button-link-delete" data-kb-del="' + esc(it.id) + '">Delete</button></td></tr>';
					}).join('') : '<tr><td colspan="4">Nothing indexed yet. Hit "Sync all content".</td></tr>';
				}).catch(fail(box));
			}
			rows.addEventListener('click', function (e) {
				var id = e.target.getAttribute('data-kb-del');
				if (id) { api('admin/kb/delete', { id: id }).then(load).catch(fail(box)); }
			});
			var add = $('[data-kb-add]', box);
			add.addEventListener('click', function () {
				busy(add, true);
				api('admin/kb/add', { title: $('[data-kb-title]', box).value, content: $('[data-kb-content]', box).value }).then(function (r) {
					toast('Added ' + r.added + ' chunk(s)', 'success');
					$('[data-kb-title]', box).value = '';
					$('[data-kb-content]', box).value = '';
					load();
				}).catch(fail(box)).then(function () { busy(add, false); });
			});
			var sync = $('[data-kb-sync]', box), prog = $('.moshai-progress', box), pbar = $('.mx-progressbar', box);
			sync.addEventListener('click', function () {
				busy(sync, true, 'Syncing…');
				pbar.hidden = false;
				var added = 0;
				(function step(offset) {
					api('admin/kb/sync', { offset: offset }).then(function (r) {
						added += r.added;
						var done = Math.min(r.next, r.total);
						prog.textContent = done + ' / ' + r.total + ' items · ' + added + ' chunks';
						$('span', pbar).style.width = (r.total ? Math.round(100 * done / r.total) : 100) + '%';
						if (r.done) { busy(sync, false); toast('Knowledge synced', 'success'); load(); }
						else { step(r.next); }
					}).catch(function (e) { busy(sync, false); fail(box)(e); load(); });
				})(0);
			});
			$('[data-kb-clear]', box).addEventListener('click', function () {
				if (!window.confirm('Delete the whole knowledge base?')) { return; }
				api('admin/kb/clear').then(function () { toast('Knowledge cleared'); load(); }).catch(fail(box));
			});
			var sbtn = $('[data-kb-search]', box);
			sbtn.addEventListener('click', function () {
				busy(sbtn, true);
				api('admin/kb/search', { query: $('[data-kb-query]', box).value }).then(function (r) {
					$('.moshai-kb-hits', box).innerHTML = r.items.length ? r.items.map(function (h) {
						return '<div class="mx-hit"><strong>' + esc(h.title) + '</strong> <span class="mx-badge">' + Math.round(h.score * 100) + '% match</span><p>' + esc(String(h.content).slice(0, 280)) + '…</p></div>';
					}).join('') : '<p class="mx-help">No relevant content found.</p>';
				}).catch(fail(box)).then(function () { busy(sbtn, false); });
			});
			load();
		}
	};
	$$('.moshai-tool[data-tool]', app).forEach(function (box) {
		var fn = tools[box.getAttribute('data-tool')];
		if (fn) { fn(box); }
	});

	/* ---------- Activity ---------- */

	$$('[data-clear]', app).forEach(function (b) {
		b.addEventListener('click', function () {
			var what = b.getAttribute('data-clear');
			if (!window.confirm(what === 'logs' ? 'Reset the usage log? Daily limits restart from zero.' : 'Delete all chat conversations?')) { return; }
			api('admin/clear', { what: what }).then(function () { toast('Done'); setTimeout(function () { location.reload(); }, 600); }).catch(fail());
		});
	});

	/* ---------- AI Agent ---------- */

	function md(src) {
		var blocks = [];
		var h = esc(src || '').replace(/```[a-z0-9]*\n?([\s\S]*?)```/gi, function (m, code) {
			blocks.push('<pre><code>' + code + '</code></pre>');
			return '@@MXCODE' + (blocks.length - 1) + '@@';
		});
		h = h.replace(/`([^`\n]+)`/g, '<code>$1</code>')
			.replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>')
			.replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>')
			.replace(/(^|[\s(])(https?:\/\/[^\s<)]+)/g, '$1<a href="$2" target="_blank" rel="noopener">$2</a>')
			.replace(/^#{1,4} (.+)$/gm, '<strong>$1</strong>')
			.replace(/^\s*[-*] (.+)$/gm, '<span class="mx-li">$1</span>')
			.replace(/\n/g, '<br>');
		return h.replace(/@@MXCODE(\d+)@@/g, function (m, i) { return blocks[+i]; });
	}

	var agent = $('[data-agent]', app);
	if (agent) {
		var log = $('[data-agent-log]', agent);
		var empty = $('[data-agent-empty]', agent);
		var pendingBox = $('[data-agent-pending]', agent);
		var form = $('[data-agent-form]', agent);
		var input = $('[data-agent-text]', agent);
		var sendBtn = $('[data-agent-send]', agent);
		var working = false;
		var labels = {
			wp_site_info: 'Read site info', wp_list_posts: 'Looked up posts', wp_get_post: 'Opened a post',
			wp_create_post: 'Create post', wp_update_post: 'Update post', wp_replace_in_post: 'Edit post text',
			wp_delete_post: 'Delete post', wp_get_post_meta: 'Read post meta', wp_update_post_meta: 'Update post meta',
			wp_replace_in_post_meta: 'Edit Elementor/meta text', wp_list_terms: 'Looked up terms', wp_list_users: 'Looked up users',
			wp_list_media: 'Looked up media', wp_update_media: 'Update media', wp_upload_media_from_url: 'Upload media',
			wp_list_plugins: 'Checked plugins', wp_get_option: 'Read a setting', elementor_clear_cache: 'Clear Elementor cache',
			ai_generate_text: 'Generated text', ai_generate_image: 'Generate image', kb_search: 'Searched knowledge',
			wp_rest_request: 'REST request', wp_list_comments: 'Looked up comments', wp_moderate_comment: 'Moderate comment',
			wp_create_term: 'Create term', wp_set_post_terms: 'Set terms', wp_set_featured_image: 'Set featured image',
			wp_create_user: 'Create user', wp_update_user: 'Update user', wp_toggle_plugin: 'Switch plugin',
			wp_update_option: 'Update setting', wc_list_orders: 'Looked up orders', wc_get_order: 'Opened an order',
			wc_update_order: 'Update order', wc_create_refund: 'Refund order', wc_list_products: 'Looked up products',
			wc_get_product: 'Opened a product', wc_update_product: 'Update product', wc_update_stock: 'Change stock',
			wc_create_product: 'Create product', wc_list_customers: 'Looked up customers', wc_sales_report: 'Ran sales report',
			wc_list_coupons: 'Looked up coupons', wc_create_coupon: 'Create coupon'
		};
		function label(name) { return labels[name] || name.replace(/_/g, ' '); }
		function argLine(args) {
			var parts = [];
			Object.keys(args || {}).forEach(function (k) {
				var v = args[k];
				if (v === '' || v == null) { return; }
				v = typeof v === 'object' ? JSON.stringify(v) : String(v);
				if (v.length > 60) { v = v.slice(0, 57) + '...'; }
				parts.push(k + ': ' + v);
			});
			return parts.slice(0, 4).join(' · ');
		}
		function pretty(v) { try { return JSON.stringify(typeof v === 'string' ? JSON.parse(v) : v, null, 2); } catch (e) { return String(v); } }
		var icons = { done: 'yes-alt', error: 'warning', denied: 'dismiss', waiting: 'clock' };

		function render(r) {
			log.innerHTML = '';
			(r.items || []).forEach(function (it) {
				var el = document.createElement('div');
				if (it.type === 'user' || it.type === 'assistant') {
					el.className = 'mx-msg mx-msg-' + it.type;
					el.innerHTML = it.type === 'user' ? esc(it.text).replace(/\n/g, '<br>') : md(it.text);
				} else {
					el.className = 'mx-step is-' + it.status + (it.write ? ' is-write' : '');
					el.innerHTML = '<details><summary><span class="dashicons dashicons-' + (icons[it.status] || 'admin-tools') + '"></span><strong>' + esc(label(it.name)) + '</strong> <span class="mx-step-args">' + esc(argLine(it.args)) + '</span></summary>' +
						'<div class="mx-step-body"><div><em>Input</em><pre>' + esc(pretty(it.args)) + '</pre></div>' + (it.result ? '<div><em>Result</em><pre>' + esc(pretty(it.result)) + '</pre></div>' : '') + '</div></details>';
				}
				log.appendChild(el);
			});
			if (r.error) {
				var er = document.createElement('div');
				er.className = 'mx-msg mx-msg-error';
				er.textContent = r.error;
				log.appendChild(er);
			}
			empty.hidden = !!(r.items && r.items.length) || !!r.error;
			renderPending(r.pending || []);
			log.scrollTop = log.scrollHeight;
		}
		function renderPending(list) {
			pendingBox.hidden = !list.length;
			if (!list.length) { pendingBox.innerHTML = ''; return; }
			var html = '<div class="mx-pending-head"><span class="dashicons dashicons-shield"></span><strong>' + (list.length > 1 ? 'The agent wants to make ' + list.length + ' changes' : 'The agent wants to make a change') + '</strong></div>';
			list.forEach(function (c) {
				html += '<div class="mx-pending-item"><strong>' + esc(label(c.name)) + '</strong><pre>' + esc(pretty(c.args)) + '</pre></div>';
			});
			html += '<div class="mx-pending-actions"><button type="button" class="button button-primary" data-agent-approve>Approve & run</button><button type="button" class="button" data-agent-reject>Don\'t do it</button><span class="mx-help">Or type a correction below.</span></div>';
			pendingBox.innerHTML = html;
		}
		function thinking(on) {
			working = on;
			sendBtn.disabled = on;
			agent.classList.toggle('is-working', on);
			var t = $('.mx-typing', log);
			if (on && !t) {
				t = document.createElement('div');
				t.className = 'mx-msg mx-msg-assistant mx-typing';
				t.innerHTML = '<span></span><span></span><span></span><em>Working on it...</em>';
				log.appendChild(t);
				log.scrollTop = log.scrollHeight;
			} else if (!on && t) { t.remove(); }
		}
		function send(payload, optimistic) {
			if (working) { return; }
			if (optimistic) {
				empty.hidden = true;
				var u = document.createElement('div');
				u.className = 'mx-msg mx-msg-user';
				u.innerHTML = esc(optimistic).replace(/\n/g, '<br>');
				log.appendChild(u);
			}
			pendingBox.hidden = true;
			thinking(true);
			api('admin/agent', payload).then(function (r) {
				thinking(false);
				render(r);
				if (r.continue) { send({ continue: 1 }); }
			}).catch(function (e) {
				thinking(false);
				toast(e.message, 'error');
				api('admin/agent', { load: 1 }).then(function (r) { r.error = e.message; render(r); });
			});
		}
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var text = input.value.trim();
			if (!text) { return; }
			input.value = '';
			input.style.height = '';
			send({ message: text }, text);
		});
		input.addEventListener('keydown', function (e) {
			if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event('submit')); }
		});
		input.addEventListener('input', function () { input.style.height = 'auto'; input.style.height = Math.min(input.scrollHeight, 220) + 'px'; });
		agent.addEventListener('click', function (e) {
			var t = e.target.closest && e.target.closest('button');
			if (!t) { return; }
			if (t.hasAttribute('data-agent-suggest')) {
				input.value = t.textContent;
				input.focus();
				if (t.textContent.indexOf('[') === -1 && t.textContent.indexOf('#123') === -1) { form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event('submit')); }
			} else if (t.hasAttribute('data-agent-approve')) {
				send({ decision: 'approve' });
			} else if (t.hasAttribute('data-agent-reject')) {
				send({ decision: 'reject' });
			} else if (t.hasAttribute('data-agent-new')) {
				if (working) { return; }
				api('admin/agent', { reset: 1 }).then(function (r) { render(r); input.focus(); });
			}
		});

		// Load the current conversation, or run a prompt sent from the WP dashboard widget.
		var params = new URLSearchParams(location.search);
		var initial = (params.get('prompt') || '').trim();
		if (initial && window.history && history.replaceState) {
			params.delete('prompt');
			history.replaceState(null, '', location.pathname + '?' + params.toString());
		}
		api('admin/agent', { load: 1 }).then(function (r) {
			render(r);
			if (initial) { send({ message: initial }, initial); }
		}).catch(function (e) { toast(e.message, 'error'); });
	}

	/* ---------- MCP: one-time tokens, live status, activity filters ---------- */

	function showToken(token) {
		var box = $('[data-token-reveal]', app);
		if (!box) { return; }
		box.hidden = false;
		$('[data-token-value]', box).textContent = token;
		$$('[data-token-tpl]', app).forEach(function (el) { el.textContent = el.getAttribute('data-token-tpl').split('YOUR_TOKEN').join(token); });
		var d = $('[data-mcp-disconnect]', app);
		if (d) { d.hidden = false; }
		box.scrollIntoView({ behavior: 'smooth', block: 'center' });
	}

	var newTok = $('[data-mcp-new-token]', app);
	if (newTok) {
		newTok.addEventListener('click', function () {
			if (newTok.getAttribute('data-has-token') === '1' && !window.confirm('Create a new token? The old one stops working right away, so every connected AI app must get the new one.')) { return; }
			busy(newTok, true, 'Creating…');
			api('admin/mcp/token', {}).then(function (r) {
				showToken(r.token);
				newTok.setAttribute('data-has-token', '1');
				newTok.textContent = 'Create a new token';
				toast('Token created. Copy it now, it is shown only once.', 'success');
			}).catch(fail()).then(function () { busy(newTok, false); });
		});
	}

	var disc = $('[data-mcp-disconnect]', app);
	if (disc) {
		disc.addEventListener('click', function () {
			if (!window.confirm('Disconnect every AI app now? The token is replaced and all MCP clients lose access immediately.')) { return; }
			busy(disc, true, 'Disconnecting…');
			api('admin/mcp/token', { disconnect: 1 }).then(function (r) {
				showToken(r.token);
				refreshStatus();
				toast('Everything disconnected. Your new token is below.', 'success');
			}).catch(fail()).then(function () { busy(disc, false); });
		});
	}

	function feedHtml(items) {
		if (!items.length) { return '<li class="mx-feed-empty">No AI changes yet. When an AI edits something, it shows up here.</li>'; }
		return items.map(function (it) {
			var cls = it.blocked ? 'is-blocked' : (it.ok ? 'is-ok' : 'is-fail');
			var icon = it.blocked ? 'shield' : (it.ok ? 'yes-alt' : 'warning');
			var what = it.link ? '<a href="' + esc(it.link) + '">' + esc(it.summary) + '</a>' : esc(it.summary);
			return '<li class="' + cls + '"><span class="dashicons dashicons-' + icon + '"></span><div><div class="mx-feed-what">' + what + '</div><div class="mx-feed-meta">' + esc(it.who) + ' · <span title="' + esc(it.date) + '">' + esc(it.ago) + '</span></div></div></li>';
		}).join('');
	}

	var stateLabel = { live: 'Connected', idle: 'Idle', off: 'Not active' };
	function clientsHtml(list) {
		if (!list.length) { return '<tr><td colspan="5">No AI app has connected yet.</td></tr>'; }
		return list.map(function (c) {
			return '<tr><td><strong>' + esc(c.name) + '</strong>' + (c.version ? ' <small>' + esc(c.version) + '</small>' : '') + '</td><td><span class="mx-state mx-state-' + esc(c.state) + '"></span>' + esc(stateLabel[c.state] || c.state) + '</td><td>' + esc(c.ago) + '</td><td>' + (parseInt(c.changes, 10) || 0) + '</td><td>' + esc(c.first) + '</td></tr>';
		}).join('');
	}

	function applyStatus(r) {
		var m = r.mcp || {};
		$$('[data-mcp-pill]', app).forEach(function (el) {
			el.className = 'mx-pill mcp-' + m.state;
			var t = $('[data-mcp-text]', el);
			if (t) { t.textContent = m.label; }
		});
		$$('[data-feed]', app).forEach(function (el) { el.innerHTML = feedHtml(r.recent || []); });
		var tb = $('[data-clients]', app);
		if (tb && r.clients) { tb.innerHTML = clientsHtml(r.clients); }
	}

	function refreshStatus() {
		if (!A.canManage) { return Promise.resolve(); }
		return api('admin/mcp/status', {}).then(applyStatus).catch(function () {});
	}
	if (A.canManage) {
		setInterval(function () { if (!document.hidden) { refreshStatus(); } }, 30000);
	}

	var af = $('[data-activity-filter]', app);
	if (af) {
		af.addEventListener('click', function (e) {
			var b = e.target.closest('button[data-f]');
			if (!b) { return; }
			$$('button', af).forEach(function (x) { x.classList.toggle('is-active', x === b); });
			var f = b.getAttribute('data-f');
			$$('[data-activity-rows] tr', app).forEach(function (tr) {
				tr.hidden = !(f === 'all' || tr.getAttribute('data-source') === f || (f === 'blocked' && tr.getAttribute('data-blocked') === '1'));
			});
		});
	}

	var preset = $('[data-custom-preset]', app);
	if (preset) {
		preset.addEventListener('change', function () {
			var o = preset.options[preset.selectedIndex];
			if (!o || !o.value) { return; }
			var card = preset.closest('.mx-key');
			$('[name=custom_name]', card).value = o.getAttribute('data-name');
			$('[name=custom_url]', card).value = o.getAttribute('data-url');
			$('[name=custom_model]', card).value = o.getAttribute('data-model');
			card.dispatchEvent(new Event('input', { bubbles: true }));
			toast('Preset filled in. Add the key and hit Test and save.');
		});
	}

	function onColor(hex) {
		hex = String(hex || '').replace('#', '');
		if (hex.length === 3) { hex = hex.split('').map(function (c) { return c + c; }).join(''); }
		if (!/^[0-9a-f]{6}$/i.test(hex)) { return '#fff'; }
		var w = [0.2126, 0.7152, 0.0722], lum = 0;
		for (var i = 0; i < 3; i++) {
			var c = parseInt(hex.substr(i * 2, 2), 16) / 255;
			c = c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
			lum += w[i] * c;
		}
		return lum > 0.35 ? '#000' : '#fff';
	}
})();
