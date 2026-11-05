/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
'use strict';
const { test, expect } = require('@playwright/test');
const { resolve } = require('node:path');
const { readFileSync } = require('node:fs');

const root = resolve(__dirname, '../..');
const core = process.env.CACTI_ROOT || resolve(root, '../..');

/* Load the real resize helpers from includes/intropage.js without running its
 * jQuery ready block (the only top-level statement in the file), which would
 * call initPage() and the rest of the dashboard bootstrap. */
const intropageSource = readFileSync(resolve(root, 'includes/intropage.js'), 'utf8')
	.replace(/\$\(function\(\) \{[\s\S]*?\n\}\);/, '/* ready block removed for the isolated helper test */');

test('a chart that renders after reload_panel still gets resized to its container', async ({ page }) => {
	await page.route('http://chart-resize.test/**', route => {
		const path = new URL(route.request().url()).pathname;
		const file = resolve(core, path.replace(/^\/core\//, ''));
		return file.startsWith(core + '/') ? route.fulfill({ path: file }) : route.abort();
	});

	await page.setViewportSize({ width: 1280, height: 720 });

	await page.setContent('<!DOCTYPE html><ul id="panel_container">' +
		'<li id="panel_1" class="grid_item">' +
		'<div class="panel_wrapper" style="width:600px">' +
		'<div style="height:180px" class="chart_wrapper center" id="line_t"></div>' +
		'</div></li></ul>');

	for (const file of ['jquery.js', 'd3.js', 'billboard.js']) {
		await page.addScriptTag({ url: `http://chart-resize.test/core/include/js/${file}` });
	}

	await page.evaluate(() => { window.panels = {}; });
	await page.addScriptTag({ content: intropageSource });

	// The chart has not rendered yet: start watching for it, exactly as
	// reload_panel() does once the reloaded markup is in place.
	await page.evaluate(() => { window.resizeChartWhenRendered('1'); });

	// Render the chart well after any fixed requestAnimationFrame budget would
	// have elapsed at a high display refresh rate, with a fixed generated width
	// that only a successful resize can change.
	await page.waitForTimeout(400);
	await page.evaluate(() => {
		const cols = [['x'], ['A']];
		const base = Date.now();
		for (let i = 0; i < 20; i++) { cols[0].push(new Date(base - (20 - i) * 60000).toISOString()); cols[1].push(i); }
		window.panels.line_t = window.bb.generate({
			bindto: '#line_t',
			size: { height: 180, width: 150 },
			data: { type: 'line', x: 'x', columns: cols },
			axis: { x: { type: 'timeseries' } }
		});
	});

	// The MutationObserver must notice the late render and resize the chart to
	// its container (panel_wrapper width 600 - 34), not leave it at the
	// generated 150px width.
	await expect.poll(
		() => page.locator('#line_t svg').getAttribute('width'),
		{ timeout: 2000 }
	).toBe('566');
});
