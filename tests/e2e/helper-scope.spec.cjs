/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
'use strict';
const { test, expect } = require('@playwright/test');
const { resolve } = require('node:path');
const { writeFileSync } = require('node:fs');
const root = resolve(__dirname, '../..');
const core = process.env.CACTI_ROOT || resolve(root, '../..');

for (const theme of ['cacti', 'midwinter']) {
	test(`${theme} helpers affect dashboard and overlay without restyling other pages`, async ({ page }, info) => {
		await page.coverage.startCSSCoverage();
		await page.route('http://helper-scope.test/**', route => {
			const path = new URL(route.request().url()).pathname;
			const base = path.startsWith('/plugin/') ? root : core;
			const file = resolve(base, path.replace(/^\/(plugin\/)?/, ''));
			return file.startsWith(base + '/') ? route.fulfill({ path: file }) : route.abort();
		});
		const classes = ['txt_med', 'txt_big', 'txt_huge', 'rpad', 'fa', 'block', 'float_left', 'float_right', 'w60'];
		await page.setContent('<!DOCTYPE html>' + ['intropage_main', 'overlay', 'outside'].map(id =>
			`<section id="${id}" style="width:400px;display:block">${classes.map(name => `<div class="${name}">Device response 1.8 ms</div>`).join('')}</section>`).join(''));
		await page.evaluate(() => {
			document.documentElement.setAttribute('data-theme-color', 'dark');
			document.documentElement.setAttribute('data-theme-state', 'ready');
		});
		await page.addStyleTag({ url: `http://helper-scope.test/include/themes/${theme}/main.css` });
		const snapshot = () => Array.from(document.querySelectorAll('#outside div'), element => {
			const s = getComputedStyle(element);
			return [s.fontSize, s.paddingRight, s.display, s.cssFloat, s.width, s.textAlign];
		});
		const before = await page.evaluate(snapshot);
		await page.addStyleTag({ url: 'http://helper-scope.test/plugin/css/common.css' });
		await page.addStyleTag({ url: `http://helper-scope.test/plugin/css/${theme}.css` });
		expect(await page.evaluate(snapshot)).toEqual(before);
		for (const id of ['intropage_main', 'overlay']) {
			const host = page.locator('#' + id);
			await expect(host.locator('.fa')).toHaveCSS('padding-right', '3px');
			await expect(host.locator('.float_left')).toHaveCSS('float', 'left');
			await expect(host.locator('.float_right')).toHaveCSS('float', 'right');
			await expect(host.locator('.float_right')).toHaveCSS('width', '320px');
			await expect(host.locator('.float_right')).toHaveCSS('text-align', 'right');
			await expect(host.locator('.w60')).toHaveCSS('width', '240px');
			await expect(host.locator('.block')).toHaveCSS('display', 'block ruby');
			for (const name of ['txt_med', 'txt_big', 'txt_huge', 'rpad']) {
				const value = await host.locator('.' + name).evaluate((element, name) => {
					const s = getComputedStyle(element), base = parseFloat(getComputedStyle(element.parentElement).fontSize);
					return name === 'rpad' ? parseFloat(s.paddingRight) / parseFloat(s.fontSize) : parseFloat(s.fontSize) / base;
				}, name);
				if (name === 'rpad') expect(value).toBe(1);
				else expect(value).toBeGreaterThan(1);
			}
		}
		writeFileSync(info.outputPath('changed-coverage.json'), JSON.stringify({ js: [], css: await page.coverage.stopCSSCoverage() }));
	});
}
