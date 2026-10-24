<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for the user_admin_tab and user_group_admin_tab hooks in
 * includes/settings.php: each prints one sub-tab link that points at the
 * plugin's edit tab.
 */

require_once __DIR__ . '/../../includes/settings.php';

it('prints the user admin sub-tab link', function () {
	global $config;

	$config['url_path'] = '/cacti/';

	ob_start();
	intropage_user_admin_tab();
	$html = ob_get_clean();

	expect($html)->toStartWith('<li class="subTab">');
	expect($html)->toContain('href="/cacti/user_admin.php?action=user_edit&amp;tab=intropage_settings_edit&amp;id="');
	expect($html)->toContain('>Intropage</a></li>');
});

it('prints the user group admin sub-tab link', function () {
	global $config;

	$config['url_path'] = '/cacti/';

	ob_start();
	intropage_user_group_admin_tab();
	$html = ob_get_clean();

	expect($html)->toStartWith('<li class="subTab">');
	expect($html)->toContain('href="/cacti/user_group_admin.php?action=edit&amp;tab=intropage_group_settings_edit&amp;id="');
	expect($html)->toContain('>Intropage</a></li>');
});
