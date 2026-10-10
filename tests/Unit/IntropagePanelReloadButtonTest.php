<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for intropage_panel_reload_button() in includes/functions.php:
 * a force-enabled panel header must render the fa-sync-alt reload glyph, which
 * includes/intropage.js spins while a reload is in flight.
 */

require_once __DIR__ . '/../../includes/functions.php';

it('renders the force-reload tool with the fa-sync-alt glyph', function () {
	$html = intropage_panel_reload_button(42);

	expect($html)->toContain("id='reloadid_42'");
	expect($html)->toContain("class='ccTool reload_panel_now'");
	expect($html)->toContain("data-tool='refresh'");
	expect($html)->toContain('fa fa-sync-alt');
});

it('escapes the panel id into the control id', function () {
	$html = intropage_panel_reload_button('core_rtm');

	expect($html)->toContain("id='reloadid_core_rtm'");
	expect($html)->toContain('fa fa-sync-alt');
});
