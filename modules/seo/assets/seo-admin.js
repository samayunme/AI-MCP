/* AI MCP SEO dashboard. Needs admin.js (window.MoshAIApp). No other dependencies. */
(function () {
	'use strict';
	var S = window.MoshAISEO || {};
	var App = window.MoshAIApp;
	var panel = document.querySelector('[data-panel="seo"]');
	if (!panel || !App) { return; }
	var $ = function (sel, ctx) { return (ctx || panel).querySelector(sel); };
	var $$ = function (sel, ctx) { return Array.prototype.slice.call((ctx || panel).querySelectorAll(sel)); };
	var esc = App.esc, toast = App.toast;

	function get(path, params) {
		var q = Object.keys(params || {}).filter(function (k) { return params[k] !== '' && params[k] != null; }).map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]); }).join('&');
		return fetch(S.rest + path + (q ? '?' + q : ''), { credentials: 'same-origin', headers: { 'X-WP-Nonce': S.nonce } }).then(function (r) {
			return r.json().then(function (j) { if (!r.ok) { throw new Error(j && j.message ? j.message : 'Request failed'); } return j; });
		});
	}
	var post = App.api;
	function fail(e) { toast(e.message, 'error'); }
	function debounce(fn, ms) { var t; return function () { var a = arguments, self = this; clearTimeout(t); t = setTimeout(function () { fn.apply(self, a); }, ms); }; }
	function rating(score) { if (score === null || score === '' || score === undefined) { return 'none'; } score = +score; return score >= 70 ? 'good' : (score >= 40 ? 'ok' : 'bad'); }
	function dot(score, label) { return '<span class="mx-seo-dot" data-rating="' + rating(score) + '" title="' + esc(label + ': ' + (score === null ? '-' : score)) + '"></span>'; }

	/* ---------- Sections ---------- */

	var loaded = {};
	function show(sec) {
		if (!$('[data-seo-section="' + sec + '"]')) { sec = 'overview'; }
		$$('[data-seo-section]').forEach(function (s) { s.hidden = s.getAttribute('data-seo-section') !== sec; });
		$$('[data-seo-sub]').forEach(function (b) { b.classList.toggle('is-active', b.getAttribute('data-seo-sub') === sec); });
		if (window.history && history.replaceState && location.search.indexOf('page=moshai-seo') > -1) { history.replaceState(null, '', location.pathname + location.search + '#' + sec); }
		if (!loaded[sec]) {
			loaded[sec] = true;
			if (sec === 'redirects') { loadRedirects(); load404(); }
			if (sec === 'bulk') { loadBulk(1); }
		}
	}
	panel.addEventListener('click', function (e) {
		var b = e.target.closest('[data-seo-sub], [data-seo-go]');
		if (!b) { return; }
		show(b.getAttribute('data-seo-sub') || b.getAttribute('data-seo-go'));
	});
	var start = (location.hash || '').slice(1);
	if (start && location.search.indexOf('page=moshai-seo') > -1) { show(start); }

	/* ---------- Instant settings ---------- */

	function save(path, value) {
		var changes = {};
		changes[path] = value;
		return post('seo/settings', { changes: changes }).then(function () { toast('Saved'); }).catch(fail);
	}
	panel.addEventListener('change', function (e) {
		var f = e.target;
		var list = f.closest('[data-seo-list]');
		if (list) {
			var vals = $$('input[type=checkbox]:checked', list).map(function (c) { return c.value; });
			save(list.getAttribute('data-seo-list'), vals);
			return;
		}
		if (!f.matches('[data-seo-setting]')) { return; }
		save(f.getAttribute('data-seo-setting'), f.type === 'checkbox' ? (f.checked ? 1 : 0) : f.value);
		if (f.closest('.mx-seo-image-row')) { thumb(f); }
	});
	function thumb(input) {
		var t = input.parentNode.querySelector('.mx-seo-thumb');
		if (t) { t.style.background = input.value ? 'url("' + input.value.replace(/"/g, '') + '") center / cover no-repeat' : ''; }
	}
	var seps = $('[data-seo-sep]');
	if (seps) {
		seps.addEventListener('click', function (e) {
			var b = e.target.closest('button');
			if (!b) { return; }
			$$('button', seps).forEach(function (x) { x.classList.toggle('is-active', x === b); });
			var input = seps.parentNode.querySelector('[data-seo-setting="general.sep"]');
			input.value = b.getAttribute('data-value');
			save('general.sep', input.value);
		});
	}
	panel.addEventListener('click', function (e) {
		var b = e.target.closest('[data-seo-pick]');
		if (!b || !window.wp || !wp.media) { return; }
		var input = b.parentNode.querySelector('input');
		var frame = wp.media({ title: 'Choose image', library: { type: 'image' }, multiple: false });
		frame.on('select', function () {
			var a = frame.state().get('selection').first().toJSON();
			input.value = a.url;
			thumb(input);
			save(b.getAttribute('data-seo-pick'), a.url);
		});
		frame.open();
	});

	/* ---------- Overview ---------- */

	var rescore = $('[data-seo-rescore]');
	if (rescore) {
		rescore.addEventListener('click', function () {
			var status = $('[data-seo-rescore-status]');
			rescore.disabled = true;
			(function step(offset) {
				post('seo/rescore', { offset: offset }).then(function (r) {
					var done = r.next_offset === null ? r.total : r.next_offset;
					status.textContent = 'Analyzed ' + done + ' of ' + r.total + '…';
					if (r.next_offset !== null) { step(r.next_offset); return; }
					status.textContent = 'Done. Reloading…';
					location.reload();
				}).catch(function (e) { rescore.disabled = false; fail(e); });
			})(0);
		});
	}
	panel.addEventListener('click', function (e) {
		var b = e.target.closest('[data-seo-problem]');
		if (!b) { return; }
		var sel = $('[data-seo-bulk-problem]');
		if (sel) { sel.value = b.getAttribute('data-seo-problem'); }
		loaded.bulk = true;
		show('bulk');
		loadBulk(1);
	});

	/* ---------- Redirects & 404s ---------- */

	function loadRedirects(page) {
		var box = $('[data-seo-redirects]');
		var search = $('[data-seo-redirect-search]');
		get('seo/redirects', { search: search ? search.value : '', page: page || 1 }).then(function (r) {
			if (!r.items.length) { box.innerHTML = '<p class="mx-help">No redirects yet.</p>'; return; }
			var h = '<table class="widefat striped mx-table"><thead><tr><th>From</th><th>To</th><th>Type</th><th>Hits</th><th></th></tr></thead><tbody>';
			r.items.forEach(function (it) {
				h += '<tr><td><code>' + esc(it.source) + '</code>' + (+it.regex ? ' <span class="mx-badge">regex</span>' : '') + (it.note ? '<br><small class="mx-help">' + esc(it.note) + '</small>' : '') + '</td><td>' + (it.target ? '<code>' + esc(it.target) + '</code>' : '<span class="mx-help">-</span>') + '</td><td>' + esc(it.code) + '</td><td>' + esc(it.hits) + '</td><td><button type="button" class="button-link mx-seo-del" data-seo-redirect-del="' + esc(it.id) + '">Delete</button></td></tr>';
			});
			box.innerHTML = h + '</tbody></table><p class="mx-help">' + r.total + ' redirects</p>';
		}).catch(function (e) { box.innerHTML = '<p class="mx-help">' + esc(e.message) + '</p>'; });
	}
	function load404() {
		var box = $('[data-seo-404]');
		get('seo/404', {}).then(function (r) {
			if (!r.items.length) { box.innerHTML = '<p class="mx-help">' + (r.logging ? 'No broken URLs logged yet.' : 'Turn on the 404 monitor to start logging.') + '</p>'; return; }
			var h = '<table class="widefat striped mx-table"><thead><tr><th>URL</th><th>Hits</th><th>Last seen</th><th></th></tr></thead><tbody>';
			r.items.forEach(function (it) {
				h += '<tr><td><code>' + esc(it.url) + '</code>' + (it.referrer ? '<br><small class="mx-help">from ' + esc(it.referrer) + '</small>' : '') + '</td><td>' + esc(it.hits) + '</td><td>' + esc(it.last_seen) + '</td><td><button type="button" class="button button-small" data-seo-fix404="' + esc(it.url) + '">Redirect</button> <button type="button" class="button-link mx-seo-del" data-seo-del404="' + esc(it.id) + '">Dismiss</button></td></tr>';
			});
			box.innerHTML = h + '</tbody></table><p><button type="button" class="button" data-seo-del404="0">Clear the log</button></p>';
		}).catch(function (e) { box.innerHTML = '<p class="mx-help">' + esc(e.message) + '</p>'; });
	}
	var rsearch = $('[data-seo-redirect-search]');
	if (rsearch) { rsearch.addEventListener('input', debounce(function () { loadRedirects(1); }, 400)); }
	panel.addEventListener('click', function (e) {
		var add = e.target.closest('[data-seo-redirect-add]');
		var del = e.target.closest('[data-seo-redirect-del]');
		var fix = e.target.closest('[data-seo-fix404]');
		var d404 = e.target.closest('[data-seo-del404]');
		var form = $('[data-seo-redirect-form]');
		if (add) {
			var data = { source: form.querySelector('[name=source]').value, target: form.querySelector('[name=target]').value, code: +form.querySelector('[name=code]').value, regex: form.querySelector('[name=regex]').checked };
			add.disabled = true;
			post('seo/redirects', data).then(function () {
				toast('Redirect saved');
				form.querySelector('[name=source]').value = '';
				form.querySelector('[name=target]').value = '';
				loadRedirects(1); load404();
			}).catch(fail).then(function () { add.disabled = false; });
		} else if (del) {
			if (!window.confirm('Delete this redirect?')) { return; }
			post('seo/redirects/delete', { id: +del.getAttribute('data-seo-redirect-del') }).then(function () { toast('Deleted'); loadRedirects(1); }).catch(fail);
		} else if (fix) {
			form.querySelector('[name=source]').value = fix.getAttribute('data-seo-fix404');
			form.querySelector('[name=target]').focus();
			form.scrollIntoView({ behavior: 'smooth', block: 'center' });
		} else if (d404) {
			post('seo/404/clear', { id: +d404.getAttribute('data-seo-del404') }).then(function () { load404(); }).catch(fail);
		}
	});

	/* ---------- Bulk editor ---------- */

	function loadBulk(page) {
		var box = $('[data-seo-bulk]');
		if (!box) { return; }
		box.innerHTML = '<p class="mx-help">Loading…</p>';
		get('seo/report', { type: $('[data-seo-bulk-type]').value, problem: $('[data-seo-bulk-problem]').value, search: $('[data-seo-bulk-search]').value, page: page }).then(function (r) {
			if (!r.items.length) { box.innerHTML = '<p class="mx-help">Nothing here. Nice.</p>'; return; }
			var h = '<table class="widefat mx-table mx-seo-bulk"><thead><tr><th style="width:22%">Page</th><th>SEO title</th><th>Meta description</th><th style="width:15%">Focus keyphrase</th><th style="width:70px">Score</th></tr></thead><tbody>';
			r.items.forEach(function (it) {
				h += '<tr data-id="' + it.id + '"><td><a href="' + esc(it.edit) + '"><strong>' + esc(it.title || '(no title)') + '</strong></a><br><a class="mx-help" href="' + esc(it.url) + '" target="_blank" rel="noopener">' + esc(it.type) + ' · view</a>' + (it.noindex ? ' <span class="mx-seo-noindex">noindex</span>' : '') + '</td>' +
					'<td><input type="text" data-bulk="title" value="' + esc(it.seo_title) + '" placeholder="' + esc(it.resolved_title) + '"></td>' +
					'<td><textarea rows="2" data-bulk="description" placeholder="' + esc(it.resolved_description) + '">' + esc(it.description) + '</textarea></td>' +
					'<td><input type="text" data-bulk="focus_keyphrase" value="' + esc(it.focus_keyphrase) + '"></td>' +
					'<td class="mx-seo-scores">' + dot(it.seo_score, 'SEO') + dot(it.readability, 'Readability') + '</td></tr>';
			});
			h += '</tbody></table><div class="mx-row mx-seo-pager"><span class="mx-help">' + r.total + ' items · page ' + r.page + ' of ' + Math.max(1, r.pages) + '</span>';
			if (r.page > 1) { h += '<button type="button" class="button" data-bulk-page="' + (r.page - 1) + '">Previous</button>'; }
			if (r.page < r.pages) { h += '<button type="button" class="button" data-bulk-page="' + (r.page + 1) + '">Next</button>'; }
			box.innerHTML = h + '</div>';
		}).catch(function (e) { box.innerHTML = '<p class="mx-help">' + esc(e.message) + '</p>'; });
	}
	['[data-seo-bulk-type]', '[data-seo-bulk-problem]'].forEach(function (s) { var el = $(s); if (el) { el.addEventListener('change', function () { loadBulk(1); }); } });
	var bsearch = $('[data-seo-bulk-search]');
	if (bsearch) { bsearch.addEventListener('input', debounce(function () { loadBulk(1); }, 400)); }
	panel.addEventListener('click', function (e) {
		var p = e.target.closest('[data-bulk-page]');
		if (p) { loadBulk(+p.getAttribute('data-bulk-page')); }
	});
	panel.addEventListener('change', function (e) {
		var f = e.target.closest('[data-bulk]');
		if (!f) { return; }
		var row = f.closest('tr');
		var fields = {};
		fields[f.getAttribute('data-bulk')] = f.value;
		f.classList.add('is-saving');
		post('seo/post', { id: +row.getAttribute('data-id'), fields: fields }).then(function (r) {
			f.classList.remove('is-saving');
			row.querySelector('.mx-seo-scores').innerHTML = dot(r.seo_score, 'SEO') + dot(r.readability, 'Readability');
			toast('Saved');
		}).catch(function (err) { f.classList.remove('is-saving'); fail(err); });
	});

	/* ---------- Import ---------- */

	panel.addEventListener('click', function (e) {
		var b = e.target.closest('[data-seo-import]');
		if (!b) { return; }
		var from = b.getAttribute('data-seo-import');
		var status = $('[data-seo-import-status]');
		var total = 0;
		b.disabled = true;
		(function step(offset) {
			post('seo/import', { from: from, offset: offset }).then(function (r) {
				total += r.imported;
				status.textContent = 'Imported ' + total + ' posts…';
				if (r.next_offset !== null) { step(r.next_offset); return; }
				status.textContent = 'Done: imported SEO data for ' + total + ' posts.';
				toast('Import finished', 'success');
				b.disabled = false;
			}).catch(function (err) { b.disabled = false; fail(err); });
		})(0);
	});
})();
