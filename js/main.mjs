/* SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
const { mountApp, h, put, badge, toast, formatDate, lrPageHeader, field, formActions, emptyState, icon, personPicker } = window.LeiterrundeUI;
const { t: _t } = window.LeiterrundeUI.l10n('leiterrunde_messdienerplan');

const APP = 'leiterrunde_messdienerplan';

const NAV = [
	{ route: '#/plan', label: _t('Plan'), cap: 'messdiener.read', icon: 'calendar' },
	{ route: '#/mein', label: _t('My duties'), icon: 'account' },
	{ route: '#/verwaltung', label: _t('Administration'), cap: 'messdiener.manage', section: _t('Management'), icon: 'clipboard' },
	{ route: '#/matrix', label: _t('Availability'), cap: 'messdiener.manage', icon: 'people' },
	{ route: '#/statistik', label: _t('Statistics'), cap: 'messdiener.manage', icon: 'dashboard' },
	{ route: '#/orte', label: _t('Locations'), cap: 'messdiener.manage', icon: 'list' },
];

let periodsCache = null;
async function periods(api, fresh = false) {
	if (periodsCache === null || fresh) periodsCache = await api('/periods');
	return periodsCache;
}

function periodSelect(api, current, onChange) {
	const sel = h('select', { onchange: (e) => onChange(+e.target.value) });
	periods(api).then(list => {
		put(sel, ...list.map(p => h('option', { value: p.id, selected: p.id === current ? '' : null },
			p.title + (p.status === 'closed' ? ' (' + _t('closed') + ')' : ''))));
		if (!current && list.length) onChange(list[0].id);
	});
	return sel;
}

function fillBadge(s) {
	const n = s.assigned.length;
	if (n >= s.required) return h('span', { class: 'lr-badge ok' }, n + '/' + s.required);
	if (n > 0) return h('span', { class: 'lr-badge warn' }, n + '/' + s.required);
	return h('span', { class: 'lr-badge fail' }, n + '/' + s.required);
}

function serviceRow(s, canManage, extraCell) {
	return h('tr', {},
		h('td', {}, formatDate(s.service_date)),
		h('td', {}, s.start_time || ''),
		h('td', {}, canManage ? h('a', { href: '#/dienst/' + s.id }, s.title) : s.title),
		h('td', {}, s.location_name || ''),
		h('td', {}, ...s.assigned.map(a =>
			h('span', { class: 'lr-badge ' + (a.source === 'manual' ? 'info' : 'missing') , style: 'margin-right:4px' }, a.person_name))),
		h('td', {}, fillBadge(s)),
		extraCell || null);
}

/* ---- Plan: services + assignments of a period ---- */
async function viewPlan(v, ME, { api }) {
	const canManage = ME.caps['messdiener.manage'];
	let current = null;
	const body = h('div', {});
	const render = async () => {
		if (!current) { put(body, emptyState(_t('No periods.'))); return; }
		const services = await api('/services?period_id=' + current);
		put(body, services.length ? h('table', { class: 'lr-table' },
			h('thead', {}, h('tr', {}, h('th', {}, _t('Date')), h('th', {}, _t('Time')), h('th', {}, _t('Service')), h('th', {}, _t('Location')), h('th', {}, _t('Assigned')), h('th', {}, _t('Fill')))),
			h('tbody', {}, ...services.map(s => serviceRow(s, canManage))))
			: emptyState(_t('No services in this period.')));
	};
	put(v, lrPageHeader({ kicker: _t('Leiterrunde · Altar server plan'), title: _t('Plan'),
		actions: [periodSelect(api, null, (id) => { current = id; render(); })] }), body);
	await render();
}

/* ---- Service detail (managers): edit + manual assignments ---- */
async function viewDienst(v, ME, { api, coreApi }) {
	const id = location.hash.split('/')[2];
	const s = await api('/services/' + id);
	const canManage = ME.caps['messdiener.manage'];
	const body = h('div', {});
	const reload = () => viewDienst(v, ME, { api, coreApi });

	const [invites, persons] = await Promise.all([
		canManage ? api('/periods/' + s.period_id + '/invites').catch(() => []) : [],
		canManage ? coreApi('/persons/options').catch(() => []) : [],
	]);
	const invitedIds = new Set(invites.map(i => i.person_id));
	const invitedPersons = persons.filter(p => invitedIds.has(p.id));

	const removeAssign = async (a) => {
		if (!confirm(_t('Remove {name} from this service?', { name: a.person_name }))) return;
		try { await api('/services/' + s.id + '/assignments/' + a.id, { method: 'DELETE' }); reload(); }
		catch (e) { toast(e.message); }
	};

	let pSel = null;
	const assignForm = canManage ? h('div', { class: 'lr-form lr-formgrid' },
		field(_t('Person'), personPicker({ persons: invitedPersons, onChange: (pid) => { pSel = pid; } }), 6),
		formActions(h('button', { class: 'lr-btn', onclick: async () => {
			if (!pSel) { toast(_t('Please select a person')); return; }
			try { await api('/services/' + s.id + '/assignments', { method: 'POST', json: { person_id: +pSel } }); reload(); }
			catch (e) { toast(e.message); }
		} }, _t('Assign')))) : null;

	const del = async () => {
		if (!confirm(_t('Delete service "{title}"?', { title: s.title }))) return;
		try { await api('/services/' + s.id, { method: 'DELETE' }); location.hash = '#/plan'; }
		catch (e) { toast(e.message); }
	};
	const delSeries = s.series_id && canManage ? async () => {
		if (!confirm(_t('Delete all services of this series?'))) return;
		try { await api('/services/series/' + s.series_id, { method: 'DELETE' }); location.hash = '#/plan'; }
		catch (e) { toast(e.message); }
	} : null;

	const inputs = {};
	const form = h('div', { class: 'lr-form lr-formgrid' });
	const wrap = (k, l, span, type) => {
		inputs[k] = h('input', { value: s[k] ?? '', type: type || 'text', readonly: !canManage });
		return h('div', { class: 'lr-f col-' + span }, h('label', { class: 'lr-flabel' }, l), inputs[k]);
	};
	form.append(wrap('title', _t('Title'), 6), wrap('service_date', _t('Date'), 3, 'date'),
		wrap('start_time', _t('Time'), 3, 'time'), wrap('required', _t('Required servers'), 3, 'number'));
	const notes = h('textarea', { rows: 2, readonly: !canManage }, s.notes || '');
	form.append(field(_t('Notes'), notes, 12));
	if (canManage) {
		const locSel = h('select', {});
		api('/locations').then(locs => put(locSel, ...locs.filter(l => l.active || l.id === s.location_id).map(l =>
			h('option', { value: l.id, selected: l.id === s.location_id ? '' : null }, l.name))));
		form.append(field(_t('Location'), locSel, 6));
		form.append(formActions(h('button', { class: 'lr-btn', onclick: async () => {
			try {
				await api('/services/' + s.id, { method: 'PUT', json: {
					title: inputs.title.value, service_date: inputs.service_date.value,
					start_time: inputs.start_time.value || null, required: +inputs.required.value || 0,
					location_id: +locSel.value, notes: notes.value || null } });
				toast(_t('Saved'), false); reload();
			} catch (e) { toast(e.message); }
		} }, _t('Save'))));
	}

	put(body, form,
		h('h3', {}, _t('Assigned')),
		assignForm,
		s.assigned.length ? h('table', { class: 'lr-table' },
			h('thead', {}, h('tr', {}, h('th', {}, _t('Person')), h('th', {}, _t('Source')), canManage ? h('th', {}, '') : null)),
			h('tbody', {}, ...s.assigned.map(a => h('tr', {},
				h('td', {}, a.person_name), h('td', {}, badge(a.source === 'manual' ? 'info' : 'aktiv')),
				canManage ? h('td', { class: 'lr-actions' }, h('button', { class: 'lr-btn quiet', onclick: () => removeAssign(a) }, _t('Remove'))) : null))))
			: emptyState(_t('Nobody assigned yet.')));

	put(v, lrPageHeader({ kicker: _t('Leiterrunde · Altar server plan'), title: s.title,
		lede: [formatDate(s.service_date), s.start_time, s.location_name].filter(Boolean).join(' · '),
		actions: canManage ? [delSeries ? h('button', { class: 'lr-btn quiet', onclick: delSeries }, _t('Delete series')) : null, h('button', { class: 'lr-btn quiet', onclick: del }, _t('Delete'))].filter(Boolean) : [] }),
		h('div', { style: 'margin-bottom:12px' }, h('a', { href: '#/plan' }, '← ' + _t('Back to plan'))), body);
}

/* ---- My duties: self-service availability ---- */
async function viewMein(v, ME, { api }) {
	const data = await api('/availability/mine');
	if (!data.periods.length) {
		put(v, lrPageHeader({ kicker: _t('Leiterrunde · Altar server plan'), title: _t('My duties') }), emptyState(_t('You are not invited to any period.')));
		return;
	}
	const blocks = data.periods.map(({ period, services }) => {
		const open = period.status === 'open';
		return h('div', {},
			h('h3', {}, period.title + ' ', badge(open ? 'info' : 'aktiv')),
			services.length ? h('table', { class: 'lr-table' },
				h('thead', {}, h('tr', {}, h('th', {}, _t('Date')), h('th', {}, _t('Time')), h('th', {}, _t('Service')), h('th', {}, _t('Location')), h('th', {}, open ? _t('Available?') : _t('Assigned?')))),
				h('tbody', {}, ...services.map(s => h('tr', {},
					h('td', {}, formatDate(s.service_date)), h('td', {}, s.start_time || ''),
					h('td', {}, s.title), h('td', {}, s.location_name || ''),
					h('td', {}, open
						? h('span', {},
							h('button', { class: 'lr-btn' + (s.state === 'yes' ? '' : ' quiet'), style: 'margin-right:6px', onclick: () => setState(s, 'yes') }, _t('Yes')),
							h('button', { class: 'lr-btn' + (s.state === 'no' ? '' : ' quiet'), onclick: () => setState(s, 'no') }, _t('No')))
						: (s.assigned ? badge('ok') : h('span', { class: 'lr-muted' }, '–')))))))
				: emptyState(_t('No services in this period.')));
	});
	async function setState(s, state) {
		try { await api('/availability/mine', { method: 'PUT', json: { service_id: s.id, state: s.state === state ? null : state } }); viewMein(v, ME, { api }); }
		catch (e) { toast(e.message); }
	}
	put(v, lrPageHeader({ kicker: _t('Leiterrunde · Altar server plan'), title: _t('My duties'),
		lede: _t('Mark the dates you can serve. After the period closes, your assignments appear here.') }), ...blocks);
}

/* ---- Administration: periods, services (with recurrence), invites ---- */
async function viewVerwaltung(v, ME, { api, coreApi }) {
	let current = null;
	const body = h('div', {});
	const render = async () => {
		const list = await periods(api, true);
		if (!current && list.length) current = list[0].id;
		if (!current) {
			put(body, emptyState(_t('No periods yet. Create one above.')));
			return;
		}
		const [period, services, invites, persons, locations] = await Promise.all([
			api('/periods/' + current),
			api('/services?period_id=' + current),
			api('/periods/' + current + '/invites'),
			coreApi('/persons/options').catch(() => []),
			api('/locations'),
		]);
		const invitedIds = new Set(invites.map(i => i.person_id));

		const delPeriod = async () => {
			if (!confirm(_t('Delete period "{title}" incl. services and answers?', { title: period.title }))) return;
			try { await api('/periods/' + period.id, { method: 'DELETE' }); periodsCache = null; current = null; render(); }
			catch (e) { toast(e.message); }
		};
		const toggle = async () => {
			try {
				if (period.status === 'open') {
					const r = await api('/periods/' + period.id + '/close', { method: 'POST', json: {} });
					toast(_t('Period closed, {n} assignments made', { n: r.assignment.assigned }), false);
				} else {
					await api('/periods/' + period.id + '/reopen', { method: 'POST', json: {} });
					toast(_t('Period reopened'), false);
				}
				periodsCache = null; render();
			} catch (e) { toast(e.message); }
		};
		const reassign = async () => {
			try {
				const r = await api('/periods/' + period.id + '/assign', { method: 'POST', json: {} });
				const gaps = Object.keys(r.unfilled || {}).length;
				toast(_t('{n} assignments made', { n: r.assigned }) + (gaps ? ' — ' + _t('{n} services understaffed', { n: gaps }) : ''), false);
				render();
			} catch (e) { toast(e.message); }
		};

		/* service create form */
		const tI = h('input', {}), dI = h('input', { type: 'date' }), timeI = h('input', { type: 'time' }),
			reqI = h('input', { type: 'number', value: 2, min: 0, max: 50 }), notesI = h('input', {}),
			repeatSel = h('select', {}, ...[['none', _t('No repeat')], ['weekly', _t('Weekly')], ['biweekly', _t('Every 2 weeks')], ['monthly', _t('Monthly')]].map(([v2, l]) => h('option', { value: v2 }, l))),
			untilI = h('input', { type: 'date' });
		const locSel = h('select', {}, ...locations.filter(l => l.active).map(l => h('option', { value: l.id }, l.name)));
		const addService = h('button', { class: 'lr-btn', onclick: async () => {
			if (!tI.value.trim() || !dI.value || !locSel.value) { toast(_t('Please fill title, date and location')); return; }
			try {
				await api('/services', { method: 'POST', json: {
					title: tI.value, period_id: period.id, location_id: +locSel.value, service_date: dI.value,
					start_time: timeI.value || null, required: +reqI.value || 0, notes: notesI.value || null,
					repeat: repeatSel.value, repeat_until: untilI.value || null } });
				toast(_t('Created'), false); render();
			} catch (e) { toast(e.message); }
		} }, _t('Create'));

		/* invites */
		let pSel = null;
		const addInvite = h('button', { class: 'lr-btn', onclick: async () => {
			if (!pSel) { toast(_t('Please select a person')); return; }
			try { await api('/periods/' + period.id + '/invites', { method: 'POST', json: { person_id: +pSel } }); render(); }
			catch (e) { toast(e.message); }
		} }, _t('Invite'));
		const removeInvite = async (inv) => {
			if (!confirm(_t('Remove {name} from this period?', { name: inv.person_name }))) return;
			try { await api('/periods/' + period.id + '/invites/' + inv.id, { method: 'DELETE' }); render(); }
			catch (e) { toast(e.message); }
		};

		put(body,
			lrPageHeader({ kicker: _t('Leiterrunde · Altar server plan'), title: period.title,
				lede: period.status === 'open' ? _t('Poll is open – invited people can answer.') : _t('Poll closed – assignment done.'),
				actions: [
					h('button', { class: 'lr-btn', onclick: toggle }, period.status === 'open' ? _t('Close & assign') : _t('Reopen')),
					period.status === 'closed' ? h('button', { class: 'lr-btn secondary', onclick: reassign }, _t('Re-run assignment')) : null,
					h('button', { class: 'lr-btn quiet', onclick: delPeriod }, _t('Delete')),
				].filter(Boolean) }),
			h('h3', {}, _t('Services')),
			services.length ? h('table', { class: 'lr-table' },
				h('thead', {}, h('tr', {}, h('th', {}, _t('Date')), h('th', {}, _t('Time')), h('th', {}, _t('Service')), h('th', {}, _t('Location')), h('th', {}, _t('Assigned')), h('th', {}, _t('Fill')))),
				h('tbody', {}, ...services.map(s => serviceRow(s, true))))
				: emptyState(_t('No services yet.')),
			h('div', { class: 'lr-form lr-formgrid' },
				field(_t('Title'), tI, 4), field(_t('Location'), locSel, 4), field(_t('Date'), dI, 2), field(_t('Time'), timeI, 2),
				field(_t('Required servers'), reqI, 2), field(_t('Repeat'), repeatSel, 3), field(_t('Repeat until'), untilI, 3), field(_t('Notes'), notesI, 4),
				formActions(addService)),
			h('h3', {}, _t('Invited people')),
			h('div', { class: 'lr-form lr-formgrid' },
				field(_t('Person'), personPicker({ persons, onChange: (id) => { pSel = id; } }), 6), formActions(addInvite)),
			invites.length ? h('table', { class: 'lr-table' },
				h('thead', {}, h('tr', {}, h('th', {}, _t('Person')), h('th', {}, ''))),
				h('tbody', {}, ...invites.map(i => h('tr', {},
					h('td', {}, i.person_name),
					h('td', { class: 'lr-actions' }, h('button', { class: 'lr-btn quiet', onclick: () => removeInvite(i) }, _t('Remove')))))))
				: emptyState(_t('Nobody invited yet.')));
	};

	const newTitle = h('input', {});
	put(v, lrPageHeader({ kicker: _t('Leiterrunde · Altar server plan'), title: _t('Administration') }),
		h('div', { class: 'lr-form lr-formgrid' },
			field(_t('Period'), periodSelect(api, null, (id) => { current = id; render(); }), 4),
			field(_t('New period'), newTitle, 5),
			formActions(h('button', { class: 'lr-btn', onclick: async () => {
				if (!newTitle.value.trim()) { toast(_t('Titel ist erforderlich')); return; }
				try { const p = await api('/periods', { method: 'POST', json: { title: newTitle.value } }); periodsCache = null; current = p.id; render(); }
				catch (e) { toast(e.message); }
			} }, _t('Create period')))),
		body);
	await render();
}

/* ---- Availability matrix (managers) ---- */
async function viewMatrix(v, ME, { api }) {
	let current = null;
	const body = h('div', {});
	const render = async () => {
		if (!current) { put(body, emptyState(_t('No periods.'))); return; }
		const m = await api('/availability?period_id=' + current);
		const open = m.period.status === 'open';
		const setCell = async (s, p, state) => {
			try { await api('/availability', { method: 'PUT', json: { service_id: s.id, person_id: p.id, state } }); render(); }
			catch (e) { toast(e.message); }
		};
		put(body,
			open ? null : h('p', { class: 'lr-muted' }, _t('Poll is closed – answers are read-only.')),
			m.persons.length ? h('div', { style: 'overflow-x:auto' }, h('table', { class: 'lr-table' },
				h('thead', {}, h('tr', {}, h('th', {}, _t('Service')), ...m.persons.map(p => h('th', {}, p.name)))),
				h('tbody', {}, ...m.services.map(s => h('tr', {},
					h('td', {}, formatDate(s.service_date) + ' ' + (s.start_time || '') + ' ' + s.title + (s.location_name ? ' (' + s.location_name + ')' : '')),
					...m.persons.map(p => {
						const st = (m.cells[s.id] || {})[p.id];
						const cell = h('td', { class: 'lr-actions' });
						if (st === 'yes') cell.append(h('span', { class: 'lr-badge ok' }, _t('Yes')));
						else if (st === 'no') cell.append(h('span', { class: 'lr-badge fail' }, _t('No')));
						else cell.append(h('span', { class: 'lr-muted' }, '–'));
						if (open) {
							cell.append(h('button', { class: 'lr-btn quiet', title: _t('Yes'), onclick: () => setCell(s, p, st === 'yes' ? null : 'yes') }, '✓'));
							cell.append(h('button', { class: 'lr-btn quiet', title: _t('No'), onclick: () => setCell(s, p, st === 'no' ? null : 'no') }, '✗'));
						}
						return cell;
					}))))))
				: emptyState(_t('Nobody invited yet.')));
	};
	put(v, lrPageHeader({ kicker: _t('Leiterrunde · Altar server plan'), title: _t('Availability'),
		actions: [periodSelect(api, null, (id) => { current = id; render(); })] }), body);
	await render();
}

/* ---- Statistics ---- */
async function viewStatistik(v, ME, { api }) {
	let current = null;
	const body = h('div', {});
	const render = async () => {
		if (!current) { put(body, emptyState(_t('No periods.'))); return; }
		const st = await api('/stats?period_id=' + current);
		const locRows = Object.entries(st.by_location).map(([name, d]) => h('tr', {},
			h('td', {}, name), h('td', { class: 'num' }, d.services), h('td', { class: 'num' }, d.filled + '/' + d.slots)));
		put(body,
			window.LeiterrundeUI.lrFigures([
				{ label: _t('Services'), value: st.services },
				{ label: _t('Slots filled'), value: st.filled + '/' + st.slots, state: st.filled >= st.slots ? 'ok' : 'warn' },
				{ label: _t('Invited'), value: st.invited },
				{ label: _t('Responded'), value: st.responded + '/' + st.invited, state: st.responded >= st.invited ? 'ok' : 'warn' },
			]),
			h('h3', {}, _t('Per person')),
			st.per_person.length ? h('table', { class: 'lr-table' },
				h('thead', {}, h('tr', {}, h('th', {}, _t('Person')), h('th', { class: 'num' }, _t('Assignments')), h('th', { class: 'num' }, _t('Available')), h('th', { class: 'num' }, _t('Blocked')))),
				h('tbody', {}, ...st.per_person.map(p => h('tr', {},
					h('td', {}, p.name), h('td', { class: 'num' }, p.assigned), h('td', { class: 'num' }, p.yes), h('td', { class: 'num' }, p.no)))))
				: emptyState(_t('Nobody invited yet.')),
			h('h3', {}, _t('Per location')),
			locRows.length ? h('table', { class: 'lr-table' },
				h('thead', {}, h('tr', {}, h('th', {}, _t('Location')), h('th', { class: 'num' }, _t('Services')), h('th', { class: 'num' }, _t('Filled')))),
				h('tbody', {}, ...locRows)) : null,
			h('h3', {}, _t('Per service')),
			st.per_service.length ? h('table', { class: 'lr-table' },
				h('thead', {}, h('tr', {}, h('th', {}, _t('Date')), h('th', {}, _t('Service')), h('th', {}, _t('Location')), h('th', { class: 'num' }, _t('Yes')), h('th', { class: 'num' }, _t('Assigned')))),
				h('tbody', {}, ...st.per_service.map(s => h('tr', {},
					h('td', {}, formatDate(s.date)), h('td', {}, s.title), h('td', {}, s.location || ''),
					h('td', { class: 'num' }, s.yes), h('td', { class: 'num' }, s.assigned + '/' + s.required)))))
				: emptyState(_t('No services.')));
	};
	put(v, lrPageHeader({ kicker: _t('Leiterrunde · Altar server plan'), title: _t('Statistics'),
		actions: [periodSelect(api, null, (id) => { current = id; render(); })] }), body);
	await render();
}

/* ---- Locations (Kirchorte) ---- */
async function viewOrte(v, ME, { api }) {
	const reload = () => viewOrte(v, ME, { api });
	const locs = await api('/locations');
	const nameI = h('input', {}), addrI = h('input', {});
	const add = h('button', { class: 'lr-btn', onclick: async () => {
		if (!nameI.value.trim()) { toast(_t('Name ist erforderlich')); return; }
		try { await api('/locations', { method: 'POST', json: { name: nameI.value, address: addrI.value || null } }); reload(); }
		catch (e) { toast(e.message); }
	} }, _t('Add'));
	const del = async (l) => {
		if (!confirm(_t('Delete location "{name}"? Services at it keep their reference.', { name: l.name }))) return;
		try { await api('/locations/' + l.id, { method: 'DELETE' }); reload(); }
		catch (e) { toast(e.message); }
	};
	const toggle = async (l) => {
		try { await api('/locations/' + l.id, { method: 'PUT', json: { active: !l.active } }); reload(); }
		catch (e) { toast(e.message); }
	};
	put(v, lrPageHeader({ kicker: _t('Leiterrunde · Altar server plan'), title: _t('Locations') }),
		h('div', { class: 'lr-form lr-formgrid' }, field(_t('Name'), nameI, 4), field(_t('Address'), addrI, 6), formActions(add)),
		locs.length ? h('table', { class: 'lr-table' },
			h('thead', {}, h('tr', {}, h('th', {}, _t('Name')), h('th', {}, _t('Address')), h('th', {}, _t('Status')), h('th', {}, ''))),
			h('tbody', {}, ...locs.map(l => h('tr', {},
				h('td', {}, l.name), h('td', {}, l.address || ''),
				h('td', {}, badge(l.active ? 'aktiv' : 'inaktiv')),
				h('td', { class: 'lr-actions' },
					h('button', { class: 'lr-btn quiet', onclick: () => toggle(l) }, l.active ? _t('Deactivate') : _t('Activate')),
					h('button', { class: 'lr-btn quiet', onclick: () => del(l) }, _t('Delete')))))))
			: emptyState(_t('No locations yet.')));
}

mountApp({
	appId: APP,
	title: _t('Altar server plan'),
	nav: NAV,
	routes: {
		'#/plan': viewPlan,
		'#/dienst': viewDienst,
		'#/mein': viewMein,
		'#/verwaltung': viewVerwaltung,
		'#/matrix': viewMatrix,
		'#/statistik': viewStatistik,
		'#/orte': viewOrte,
	},
	defaultRoute: '#/plan',
});
