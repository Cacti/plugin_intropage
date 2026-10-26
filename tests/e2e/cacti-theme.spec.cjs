/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
'use strict';
const { test, expect } = require('@playwright/test');
const { mkdirSync, readFileSync, writeFileSync } = require('node:fs');
const { dirname, relative, resolve } = require('node:path');
const root = resolve(__dirname, '../..');
const core = process.env.CACTI_ROOT || resolve(root, '../..');

async function installCss(page, file) {
	const path = file.startsWith(root + '/') ? 'plugin/' + relative(root, file) : relative(core, file);
	await page.addStyleTag({ url: 'http://cacti-theme.test/' + path });
}

for (const theme of ['cacti', 'carrot', 'dark']) {
	test(`${theme} seeded dashboard displays measurements and opens alert details`, async ({ page }, info) => {
		await page.setViewportSize({ width: 1140, height: 520 });
		await page.setContent(readFileSync(resolve(root, 'tests/fixtures/Ui/cacti-theme.html'), 'utf8').replaceAll('Cacti theme', `${theme[0].toUpperCase() + theme.slice(1)} theme`));
		for (const file of [resolve(core, `include/themes/${theme}/jquery-ui.css`), resolve(core, `include/themes/${theme}/main.css`), resolve(core, 'include/fa/css/all.css'), resolve(root, 'includes/cards/cards.css'), resolve(root, 'css/common.css'), resolve(root, `css/${theme}.css`)]) {
			await installCss(page, file);
		}
		await page.addScriptTag({ path: resolve(core, 'include/js/jquery.js') });
		await page.addScriptTag({ path: resolve(core, 'include/js/jquery-ui.js') });
		await page.evaluate(() => {
			document.getElementById('open-details').addEventListener('click', () => {
				window.jQuery('#overlay').dialog({
					modal: true, width: 850, title: 'Alert details',
					buttons: [{ text: 'Close', click() { window.jQuery(this).dialog('destroy'); } }],
				});
			});
		});
		await expect(page.getByRole('table', { name: 'Device response times' }).locator('tbody tr')).toHaveCount(4);
		await expect(page.getByText('1.8 ms', { exact: true })).toBeVisible();
		const capture = async name => {
			const path = process.env.UPDATE_THEME_SCREENSHOTS === '1'
				? resolve(root, `images/${name}.png`) : info.outputPath(`${name}.png`);
			mkdirSync(dirname(path), { recursive: true });
			await page.screenshot({ path, fullPage: true });
			await info.attach(name, { path, contentType: 'image/png' });
		};
		await capture(`${theme}-theme-dashboard`);
		await page.getByRole('button', { name: 'Open alert details' }).click();
		await expect(page.getByRole('dialog')).toBeVisible();
		await expect(page.getByRole('table', { name: 'Alert details' }).getByText(/branch-firewall-03/)).toBeVisible();
		await capture(`${theme}-theme-status`);
		await page.locator('.ui-dialog-buttonpane').getByRole('button', { name: 'Close', exact: true }).click();
		await expect(page.getByRole('dialog')).toHaveCount(0);
	});
}

test.beforeEach(async ({ page }) => {
	await page.coverage.startCSSCoverage({ resetOnNavigation: false });
	await page.route('http://cacti-theme.test/**', route => {
		const name = new URL(route.request().url()).pathname.slice(1);
		if (name === 'blank') return route.fulfill({ contentType: 'text/html', body: '<!DOCTYPE html>' });
		const base = name.startsWith('plugin/') ? root : core;
		const path = resolve(base, name.startsWith('plugin/') ? name.slice(7) : name);
		if (!path.startsWith(base + '/')) return route.abort();
		return route.fulfill({ path });
	});
	await page.goto('http://cacti-theme.test/blank');
});

test.afterEach(async ({ page }, info) => {
	const coverage = { js: [], css: await page.coverage.stopCSSCoverage() };
	writeFileSync(info.outputPath('changed-coverage.json'), JSON.stringify(coverage));
});

for (const theme of ['cacti', 'carrot', 'dark']) {
	for (const row of ['odd', 'even', '']) {
		test(`${theme} severity indicators contrast with titlebars and ${row || 'white'} rows`, async ({ page }) => {
			await page.setContent('<!DOCTYPE html><div id="overlay_detail">' + ['green', 'red', 'yellow', 'grey'].map(color =>
				`<div class="cactiTableTitleRow"><div class="cactiTableTitle">Panel Details</div>` +
				`<div class="cactiTableButton"><i id="bubble-${color}" class="color_${color}_bubble">●</i></div></div>` +
				`<table class="cactiTable"><tr class="${row}"><td><span id="square-${color}" class="inpa_sq color_${color}"></span>Device status</td></tr></table>`
			).join('') + '</div>');
			for (const file of [resolve(core, `include/themes/${theme}/main.css`), resolve(root, 'css/common.css'), resolve(root, `css/${theme}.css`)]) {
				await installCss(page, file);
			}
			const indicators = await page.locator('[id^="bubble-"], [id^="square-"]').evaluateAll(elements => {
				const rgba = value => {
					const parts = value.match(/[\d.]+/g).map(Number);
					return [parts[0], parts[1], parts[2], parts[3] ?? 1];
				};
				const lum = c => c.slice(0, 3).map(v => {
					v /= 255;
					return v <= .04045 ? v / 12.92 : ((v + .055) / 1.055) ** 2.4;
				}).reduce((sum, v, i) => sum + v * [.2126, .7152, .0722][i], 0);
				const over = (fg, bg) => bg.map((v, i) => fg[i] * fg[3] + v * (1 - fg[3]));
				return elements.map(element => {
					const square = element.id.startsWith('square-');
					let surfaces = [[255, 255, 255]];
					const ancestors = [];
					for (let e = square ? element.parentElement : element; e; e = e.parentElement) ancestors.unshift(e);
					for (const e of ancestors) {
						const c = rgba(getComputedStyle(e).backgroundColor);
						surfaces = surfaces.map(bg => over(c, bg));
						const image = getComputedStyle(e).backgroundImage;
						if (image.startsWith('linear-gradient(')) {
							const stops = (image.match(/rgba?\([^)]+\)/g) || []).map(rgba);
							surfaces = surfaces.flatMap(bg => stops.map(stop => over(stop, bg)));
						}
					}
					const style = getComputedStyle(element), color = rgba(square ? style.backgroundColor : style.color);
					return { id: element.id, ratio: Math.min(...surfaces.map(bg => {
						const foreground = lum(over(color, bg)), surface = lum(bg);
						return (Math.max(foreground, surface) + .05) / (Math.min(foreground, surface) + .05);
					})) };
				});
			});
			expect(indicators).toHaveLength(8);
			for (const indicator of indicators) expect(indicator.ratio, indicator.id).toBeGreaterThanOrEqual(3);
		});
	}
}
