<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * The console_after hook may only render the dashboard for users who hold
 * the Intropage Viewer realm, on the main poller and on an online remote
 * poller alike.
 */

require_once __DIR__ . '/../../includes/settings.php';

beforeEach(function () {
	$GLOBALS['__test_db_calls'] = [];
	$GLOBALS['__test_realms']   = [];
	$GLOBALS['__test_request']  = [];
	$GLOBALS['__test_query']    = function ($kind, $sql, $params) {
		return $kind === 'cell' ? '1' : [];
	};

	$_SESSION['sess_user_id'] = 401;

	$this->restore = $GLOBALS['config'];
});

afterEach(function () {
	$GLOBALS['config'] = $this->restore;

	unset($GLOBALS['__test_query']);
});

it('renders nothing on an online remote poller without the viewer realm', function () {
	$GLOBALS['config']['poller_id']  = 2;
	$GLOBALS['config']['connection'] = 'online';

	ob_start();
	intropage_console_after();
	$output = ob_get_clean();

	expect($output)->toBe('');
});

it('renders nothing on the main poller without the viewer realm', function () {
	$GLOBALS['config']['poller_id']  = 1;
	$GLOBALS['config']['connection'] = 'online';

	ob_start();
	intropage_console_after();
	$output = ob_get_clean();

	expect($output)->toBe('');
});

it('renders nothing for a viewer on an offline remote poller', function () {
	$GLOBALS['__test_realms']['intropage.php'] = true;
	$GLOBALS['config']['poller_id']             = 2;
	$GLOBALS['config']['connection']            = 'offline';

	ob_start();
	intropage_console_after();
	$output = ob_get_clean();

	expect($output)->toBe('');
});
