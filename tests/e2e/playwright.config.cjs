/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
'use strict';
const { defineConfig } = require('@playwright/test');
const { resolve } = require('node:path');

module.exports = defineConfig({
	testDir: '.',
	outputDir: resolve(__dirname, 'test-results'),
	fullyParallel: true,
	forbidOnly: !!process.env.CI,
	use: { browserName: 'chromium' },
});
