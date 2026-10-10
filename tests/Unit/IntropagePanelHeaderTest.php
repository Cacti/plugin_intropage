<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for intropage_panel_header() in includes/functions.php: the
 * shared card-chrome header must carry the left drag handle, the title, the
 * theme severity class, and only the tools the panel's state enables
 * (shrink/grow height, maximize, reload, remove).
 */

require_once __DIR__ . '/../../includes/functions.php';

it('renders the drag handle, title and severity class', function () {
	$html = intropage_panel_header(7, 'My Panel', 'red', 'normal', '/drop?x=1', false, false, false);

	expect($html)->toContain('<header class="ccCardHeader color_red">');
	expect($html)->toContain('class="ccDrag"');
	expect($html)->toContain('<h2 class="ccTitle">My Panel</h2>');
	expect($html)->toContain('data-tool="remove"');
});

it('shows the maximize tool only when the panel has details', function () {
	$with    = intropage_panel_header(7, 'P', 'grey', 'normal', '/d', true, false, false);
	$without = intropage_panel_header(7, 'P', 'grey', 'normal', '/d', false, false, false);

	expect($with)->toContain('data-tool="maximize"');
	expect($without)->not->toContain('data-tool="maximize"');
});

it('shows the reload tool only when force is enabled', function () {
	$with    = intropage_panel_header(7, 'P', 'grey', 'normal', '/d', false, true, false);
	$without = intropage_panel_header(7, 'P', 'grey', 'normal', '/d', false, false, false);

	expect($with)->toContain("data-tool='refresh'");
	expect($without)->not->toContain("data-tool='refresh'");
});

it('offers grow at normal height and shrink at triple height', function () {
	$normal = intropage_panel_header(7, 'P', 'grey', 'normal', '/d', false, false, false);
	$triple = intropage_panel_header(7, 'P', 'grey', 'triple', '/d', false, false, false);

	expect($normal)->toContain('data-tool="grow"');
	expect($normal)->not->toContain('data-tool="shrink"');

	expect($triple)->toContain('data-tool="shrink"');
	expect($triple)->not->toContain('data-tool="grow"');
});

it('hides the height tools when the height is fixed', function () {
	$html = intropage_panel_header(7, 'P', 'grey', 'double', '/d', false, false, true);

	expect($html)->not->toContain('data-tool="grow"');
	expect($html)->not->toContain('data-tool="shrink"');
});
