/* SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import vm from 'node:vm';

const source = await readFile(new URL('../js/main.mjs', import.meta.url), 'utf8');
function setup() {
	let config;
	const pickers = [];
	const h = (tag, attrs = {}, ...children) => ({ tag, attrs, children: children.flat().filter(Boolean),
		append(...items) { this.children.push(...items); },
		replaceChildren(...items) { this.children = items.filter(Boolean); },
		addEventListener(name, fn) { this.attrs['on' + name] = fn; },
	});
	const UI = { h, mountApp: c => { config = c; }, put: (node, ...items) => node.replaceChildren(...items),
		l10n: () => ({ t: s => s }), badge: s => s, toast() {}, formatDate: s => s,
		lrPageHeader: x => h('header', x), field: (label, input) => h('field', { label }, input),
		formActions: (...items) => h('actions', {}, ...items), emptyState: s => s,
		personPicker: opts => { pickers.push(opts); return h('picker'); },
	};
	vm.runInNewContext(source, { window: { LeiterrundeUI: UI }, location: { hash: '#/dienst/1' }, confirm: () => true });
	const service = { id: 1, period_id: 2, title: 'Mass', service_date: '2026-11-10', location_id: 4, required: 2, assigned: [] };
	const persons = [
		{ id: 1, first_name: 'A', last_name: 'One', default_location_id: '4' },
		{ id: 2, first_name: 'B', last_name: 'Two', default_location_id: '4' },
		{ id: 3, first_name: 'C', last_name: 'Three', default_location_id: '5' },
	];
	const api = async path => {
		if (path === '/locations') return [{ id: 4, name: 'Church A', active: true }, { id: 5, name: 'Church B', active: true }];
		if (path.startsWith('/availability?')) return { period: { id: 2, status: 'open' }, persons, services: [service], cells: { 1: { 1: 'yes', 2: 'no' } } };
		if (path === '/services/1') return service;
		if (path.startsWith('/services?')) return [service, { ...service, id: 2, location_id: 5 }];
		if (path === '/periods') return [{ id: 2, title: 'November' }];
		throw new Error(path);
	};
	return { config, h, api, pickers, service };
}
function find(node, predicate) {
	if (!node || typeof node !== 'object') return null;
	if (predicate(node)) return node;
	for (const child of node.children || []) { const result = find(child, predicate); if (result) return result; }
	return null;
}
const me = { caps: { 'messdiener.manage': true, 'messdiener.read': true } };

test('Kirchort selector is provided through the sidebar, not the page header', async () => {
	const { config, api, h } = setup();
	let refreshed = false;
	const sidebar = await config.navigationHeader(me, { api, refresh: () => { refreshed = true; } });
	const select = find(sidebar, n => n.tag === 'select');
	assert.equal(select.id, 'lr-location-filter');
	select.attrs.onchange({ target: { value: '5' } });
	assert.equal(refreshed, true);
	const view = h('div');
	await config.routes['#/plan'](view, me, { api });
	await new Promise(resolve => setImmediate(resolve));
	const body = find(view, n => n.tag === 'tbody');
	assert.equal(body.children.length, 1);
	assert.equal(view.children[0].attrs.actions.length, 1);
});

test('assignment suggestions show answers and can include people from other Kirchorte', async () => {
	const { config, api, h, pickers } = setup();
	const view = h('div');
	await config.routes['#/dienst'](view, me, { api });
	assert.deepEqual(Array.from(pickers.at(-1).persons, p => p.picker_sublabel), ['Yes', 'No']);
	const checkbox = find(view, n => n.tag === 'input' && n.attrs.type === 'checkbox');
	checkbox.checked = true;
	checkbox.attrs.onchange();
	assert.deepEqual(Array.from(pickers.at(-1).persons, p => p.picker_sublabel), ['Yes', 'No', 'No answer']);
});

test('availability matrix keeps a horizontal table layout', async () => {
	const { config, api, h } = setup();
	const view = h('div');
	await config.routes['#/matrix'](view, me, { api });
	await new Promise(resolve => setImmediate(resolve));
	const table = find(view, n => n.tag === 'table');
	assert.match(table.attrs.class, /lr-matrix/);
	const head = find(view, n => n.tag === 'thead');
	assert.equal(head.children[0].children.length, 2);
	const body = find(view, n => n.tag === 'tbody');
	assert.equal(body.children.length, 3);
	assert.equal(body.children[0].children.length, 2);
	// display:flex on a td removes it from table layout — every consecutive
	// flex cell collapses into one anonymous cell, so td must stay a real cell
	const cell = body.children[0].children[1];
	assert.notEqual(cell.attrs.class, 'lr-actions');
	assert.ok(find(cell, n => n.attrs?.class === 'lr-actions'));
});

test('already assigned people are excluded from suggestions', async () => {
	const { config, api, h, pickers, service } = setup();
	service.assigned.push({ id: 9, person_id: 1, person_name: 'A', source: 'manual' });
	await config.routes['#/dienst'](h('div'), me, { api });
	assert.deepEqual(Array.from(pickers.at(-1).persons, p => p.id), [2]);
});
