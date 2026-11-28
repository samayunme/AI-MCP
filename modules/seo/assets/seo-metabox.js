/* AI MCP SEO box on the post edit screen: Google preview, counters, live analysis, AI writing. */
(function () {
	'use strict';
	var C = window.MoshAISEOBox || {};
	var box = document.querySelector('.mx-seo-box[data-post]');
	if (!box) { return; }
	var $ = function (sel, ctx) { return (ctx || box).querySelector(sel); };
	var $$ = function (sel, ctx) { return Array.prototype.slice.call((ctx || box).querySelectorAll(sel)); };
	var postId = +box.getAttribute('data-post');
	function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
	function field(name) { return $('[data-seo-field="' + name + '"]'); }
	function val(name) { var f = field(name); return f ? f.value : ''; }
	function rating(score) { if (score === '' || score == null) { return 'none'; } score = +score; return score >= 70 ? 'good' : (score >= 40 ? 'ok' : 'bad'); }
	function api(path, data) {
		return fetch(C.rest + path, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': C.nonce }, body: JSON.stringify(data) }).then(function (r) {
			return r.json().then(function (j) { if (!r.ok) { throw new Error(j && j.message ? j.message : 'Request failed'); } return j; });
		});
	}

	/* ---------- Editor state (classic and block editor) ---------- */

	function blockEditor() { return window.wp && wp.data && wp.data.select && wp.data.select('core/editor'); }
	function editorContent() {
		var be = blockEditor();
		if (be && be.getEditedPostContent) { return be.getEditedPostContent(); }
		if (window.tinymce && tinymce.get('content') && !tinymce.get('content').isHidden()) { return tinymce.get('content').getContent(); }
		var ta = document.getElementById('content');
		return ta ? ta.value : '';
	}
	function editorTitle() {
		var be = blockEditor();
		if (be && be.getEditedPostAttribute) { return be.getEditedPostAttribute('title') || ''; }
		var t = document.getElementById('title');
		return t ? t.value : '';
	}
	function editorSlug() {
		var be = blockEditor();
		if (be && be.getEditedPostAttribute) { return be.getEditedPostAttribute('slug') || ''; }
		var s = document.getElementById('post_name') || document.getElementById('editable-post-name-full');
		return s ? (s.value || s.textContent || '') : '';
	}
	function plain(html) { var d = document.createElement('div'); d.innerHTML = html; return (d.textContent || '').replace(/\s+/g, ' ').trim(); }

	/* ---------- Preview and counters ---------- */

	var resolved = { title: box.getAttribute('data-resolved-title'), desc: box.getAttribute('data-resolved-desc') };
	function quickVars(tpl) {
		var text = plain(editorContent());
		return String(tpl || '')
			.replace(/%%title%%/g, editorTitle())
			.replace(/%%sitename%%/g, C.site || '')
			.replace(/%%sep%%/g, C.sep || '-')
			.replace(/%%excerpt%%/g, text.slice(0, 155))
			.replace(/%%focuskw%%/g, val('focus_keyphrase'))
			.replace(/%%[a-z_]+%%/g, '')
			.replace(/\s+/g, ' ').trim();
	}
	function currentTitle() { var own = val('title'); return own ? quickVars(own) : (resolved.title || quickVars(box.getAttribute('data-title-template'))); }
	function currentDesc() { var own = val('description'); return own ? quickVars(own) : (resolved.desc || ''); }
	function meter(name, len, lo, hi) {
		var m = $('[data-meter="' + name + '"]');
		var c = $('[data-count="' + name + '"]');
		if (c) { c.textContent = len + ' / ' + hi; }
		if (!m) { return; }
		m.style.width = Math.min(100, Math.round(len / hi * 100)) + '%';
		m.setAttribute('data-rating', len === 0 ? 'none' : (len >= lo && len <= hi ? 'good' : (len > hi + 10 || len < lo / 2 ? 'bad' : 'ok')));
	}
	function preview() {
		var t = currentTitle(), d = currentDesc();
		$('[data-serp-title]').textContent = t.length > 62 ? t.slice(0, 60) + '…' : t;
		$('[data-serp-desc]').textContent = d ? (d.length > 158 ? d.slice(0, 156) + '…' : d) : 'Add a meta description so Google shows your text here.';
		var slug = editorSlug();
		if (slug) { $('[data-seo-slug]').textContent = slug; }
		meter('title', t.length, 40, 60);
		meter('description', d.length, 120, 156);
	}

	/* ---------- Analysis ---------- */

	function renderChecks(list, checks) {
		var order = { bad: 0, ok: 1, good: 2 };
		checks = checks.slice().sort(function (a, b) { return order[a.status] - order[b.status]; });
		list.innerHTML = checks.map(function (c) { return '<li class="is-' + c.status + '"><span class="mx-seo-dot" data-rating="' + c.status + '"></span>' + esc(c.text) + '</li>'; }).join('');
	}
	function setScore(key, s) {
		var label = $('[data-score-label="' + key + '"]');
		var d = $('[data-dot="' + key + '"]');
		var r = rating(s.score);
		if (label) { label.textContent = s.score + '/100 · ' + (C.i18n ? C.i18n[r] : r); label.setAttribute('data-rating', r); }
		if (d) { d.setAttribute('data-rating', r); }
	}
	var seq = 0;
	function analyze() {
		var my = ++seq;
		var html = editorContent();
		var data = { post_id: postId, title: val('title'), description: val('description'), focus_keyphrase: val('focus_keyphrase'), post_title: editorTitle(), slug: editorSlug() };
		if (plain(html).length > 50) { data.content = html; }
		api('seo/analyze', data).then(function (r) {
			if (my !== seq) { return; }
			resolved.title = r.title; resolved.desc = r.description;
			renderChecks($('[data-checks="seo"]'), r.seo.checks);
			renderChecks($('[data-checks="read"]'), r.readability.checks);
			setScore('seo', r.seo);
			setScore('read', r.readability);
			preview();
		}).catch(function () { /* keep the last result */ });
	}
	var timer;
	function later() { preview(); clearTimeout(timer); timer = setTimeout(analyze, 700); }

	box.addEventListener('input', function (e) { if (e.target.matches('[data-seo-field]')) { if (e.target.matches('[data-seo-field="title"], [data-seo-field="description"]')) { resolved[e.target.getAttribute('data-seo-field') === 'title' ? 'title' : 'desc'] = ''; } later(); } });
	var titleEl = document.getElementById('title');
	if (titleEl) { titleEl.addEventListener('input', later); }
	var ta = document.getElementById('content');
	if (ta) { ta.addEventListener('input', later); }
	if (window.tinymce) {
		var hook = function (ed) { if (ed && ed.id === 'content') { ed.on('keyup change', later); } };
		if (tinymce.get('content')) { hook(tinymce.get('content')); }
		if (tinymce.on) { tinymce.on('AddEditor', function (e) { hook(e.editor); }); }
	}
	var be = blockEditor();
	if (be && wp.data.subscribe) {
		var last = '';
		wp.data.subscribe(function () {
			var now = editorTitle() + '|' + editorContent().length;
			if (now !== last) { last = now; later(); }
		});
	}

	/* ---------- Tabs, device, media, AI ---------- */

	box.addEventListener('click', function (e) {
		var tab = e.target.closest('[data-seo-tab]');
		var dev = e.target.closest('[data-seo-device]');
		var media = e.target.closest('[data-seo-media]');
		var ai = e.target.closest('[data-seo-ai]');
		if (tab) {
			$$('[data-seo-tab]').forEach(function (b) { b.classList.toggle('is-active', b === tab); });
			$$('[data-seo-pane]').forEach(function (p) { p.hidden = p.getAttribute('data-seo-pane') !== tab.getAttribute('data-seo-tab'); });
		} else if (dev) {
			$$('[data-seo-device]').forEach(function (b) { b.classList.toggle('is-active', b === dev); });
			$('[data-serp]').classList.toggle('is-mobile', dev.getAttribute('data-seo-device') === 'mobile');
		} else if (media && window.wp && wp.media) {
			var input = document.getElementById(media.getAttribute('data-seo-media'));
			var frame = wp.media({ title: 'Choose image', library: { type: 'image' }, multiple: false });
			frame.on('select', function () { input.value = frame.state().get('selection').first().toJSON().url; });
			frame.open();
		} else if (ai) {
			var task = ai.getAttribute('data-seo-ai');
			var label = ai.textContent;
			ai.disabled = true;
			ai.textContent = (C.i18n && C.i18n.writing) || 'Writing…';
			var html = editorContent();
			var data = { task: task, post_id: postId, focus_keyphrase: val('focus_keyphrase'), post_title: editorTitle() };
			if (plain(html).length > 50) { data.content = html; }
			api('seo/ai', data).then(function (r) {
				var f = field(task);
				f.value = r.text;
				resolved[task === 'title' ? 'title' : 'desc'] = '';
				later();
			}).catch(function (err) { window.alert(err.message); }).then(function () { ai.disabled = false; ai.textContent = label; });
		}
	});

	preview();
	analyze();
})();
