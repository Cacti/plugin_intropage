/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
'use strict';
const { test, expect } = require('@playwright/test');
const { mkdirSync, readFileSync, writeFileSync } = require('node:fs');
const { dirname, relative, resolve } = require('node:path');
const root = resolve(__dirname, '../..');
const core = process.env.CACTI_ROOT || resolve(root, '../..');

const themes = ['cacti', 'carrot', 'dark', 'deepness', 'hollyberry'].map(theme => ({ theme }));
themes.push({ theme: 'midwinter', palette: 'dark' }, { theme: 'midwinter', palette: 'light' });

function elementContrast(element) {
	const square = element.classList.contains('inpa_sq');
	const rgba = (value) => {
		const parts = value.match(/[\d.]+/g).map(Number);
		return [parts[0], parts[1], parts[2], parts[3] ?? 1];
	};
	const over = (fg, bg) => bg.map((v, i) => fg[i] * fg[3] + v * (1 - fg[3]));
	let surfaces = [[255, 255, 255]];
	const ancestors = [];
	for (let e = square ? element.parentElement : element; e; e = e.parentElement) ancestors.unshift(e);
	for (const e of ancestors) {
		const c = rgba(getComputedStyle(e).backgroundColor);
		surfaces = surfaces.map(bg => over(c, bg));
		// Sample stops and intermediate sRGB colors; raster textures use their fallback color.
		const image = getComputedStyle(e).backgroundImage;
		if (image.startsWith('linear-gradient(')) {
			const stops = (image.match(/rgba?\([^)]+\)/g) || []).map(rgba);
			const samples = stops.flatMap((stop, index) => index === 0 ? [stop] :
				[.25, .5, .75, 1].map(t => stop.map((v, channel) => stops[index - 1][channel] * (1 - t) + v * t)));
			surfaces = surfaces.flatMap(bg => samples.map(stop => over(stop, bg)));
		}
	}
	const lum = (c) => c.slice(0, 3).map(v => {
		v /= 255;
		return v <= .04045 ? v / 12.92 : ((v + .055) / 1.055) ** 2.4;
	}).reduce((sum, v, i) => sum + v * [.2126, .7152, .0722][i], 0);
	const style = getComputedStyle(element), foreground = square ? style.backgroundColor : style.color;
	const color = rgba(foreground);
	return { id: element.id, foreground, ratio: Math.min(...surfaces.map(bg => {
		const fg = lum(over(color, bg)), surface = lum(bg);
		return (Math.max(fg, surface) + .05) / (Math.min(fg, surface) + .05);
	})) };
}

async function preparePalette(page, palette) {
	if (!palette) return;
	await page.evaluate(palette => {
		document.documentElement.setAttribute('data-theme-color', palette);
		document.documentElement.setAttribute('data-theme-state', 'ready');
		document.documentElement.setAttribute('data-animations', 'off');
		document.body.style.backgroundColor = 'var(--background-content)';
	}, palette);
}

async function installCss(page, file) {
	const path = file.startsWith(root + '/') ? 'plugin/' + relative(root, file) : relative(core, file);
	await page.addStyleTag({ url: 'http://cacti-theme.test/' + path });
}

for (const { theme, palette } of themes) {
	test(`${theme}${palette ? ' ' + palette : ''} seeded dashboard displays measurements and opens alert details`, async ({ page }, info) => {
		await page.setViewportSize({ width: 1140, height: 520 });
		await page.setContent(readFileSync(resolve(root, 'tests/fixtures/Ui/cacti-theme.html'), 'utf8').replaceAll('Cacti theme', `${theme[0].toUpperCase() + theme.slice(1)} theme`));
		await preparePalette(page, palette);
		for (const file of [resolve(core, `include/themes/${theme}/jquery-ui.css`), resolve(core, `include/themes/${theme}/main.css`), resolve(core, 'include/fa/css/all.css'), resolve(root, 'includes/cards/cards.css'), resolve(root, 'css/common.css'), resolve(root, `css/${theme}.css`)]) {
			await installCss(page, file);
		}
		if (palette) await expect(page.locator('body')).toHaveCSS('opacity', '1');
		if (palette) {
			for (const text of await page.locator('.panel_data p, .panel_data h4').all()) {
				expect.soft((await text.evaluate(elementContrast)).ratio, 'panel text').toBeGreaterThanOrEqual(4.5);
			}
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
		await capture(`${theme}${palette ? '-' + palette : ''}-theme-dashboard`);
		await page.getByRole('button', { name: 'Open alert details' }).click();
		await expect(page.getByRole('dialog')).toBeVisible();
		if (palette) await expect(page.locator('#overlay_detail .color_red_bubble')).toHaveCSS('color', palette === 'light' ? 'rgb(179, 38, 30)' : 'rgb(255, 107, 107)');
		await expect(page.getByRole('table', { name: 'Alert details' }).getByText(/branch-firewall-03/)).toBeVisible();
		await capture(`${theme}${palette ? '-' + palette : ''}-theme-status`);
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

for (const { theme, palette } of themes) {
	for (const row of ['odd', 'even', '']) {
		test(`${theme}${palette ? ' ' + palette : ''} severity indicators contrast with titlebars and ${row || 'white'} rows`, async ({ page }) => {
			await page.setContent(`<!DOCTYPE html><div id="overlay_detail" class="${palette ? 'ui-widget-content' : ''}">` + ['green', 'red', 'yellow', 'grey'].map(color =>
				`<div class="cactiTableTitleRow"><div class="cactiTableTitle">Panel Details</div>` +
				`<div class="cactiTableButton"><i id="bubble-${color}" class="color_${color}_bubble">●</i></div></div>` +
				`<div${palette ? ' class="grid_item"' : ''}><table class="cactiTable"><tr class="${row}"><td><span id="square-${color}" class="inpa_sq color_${color}"></span>Device status</td></tr></table></div>`
			).join('') + '</div>');
			await preparePalette(page, palette);
			for (const file of [resolve(core, `include/themes/${theme}/main.css`), resolve(root, 'css/common.css'), resolve(root, `css/${theme}.css`)]) {
				await installCss(page, file);
			}
			const indicators = [];
			for (const indicator of await page.locator('[id^="bubble-"], [id^="square-"]').all()) {
				indicators.push(await indicator.evaluate(elementContrast));
			}
			expect(indicators).toHaveLength(8);
			if (palette) expect(new Set(indicators.filter(item => item.id.startsWith('bubble-')).map(item => item.foreground)).size, 'distinct severity colors').toBe(4);
			for (const indicator of indicators) expect(indicator.ratio, indicator.id).toBeGreaterThanOrEqual(3);
		});
	}
}
