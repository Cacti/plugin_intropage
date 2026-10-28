<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Fires the graph_buttons hook through Cacti's own plugin API, as a graph
 * page does, for the end-to-end tests. The graph pages only call the hook
 * for graphs that have data sources and round robin archives, which a test
 * installation does not have.
 *
 * tests/ ships inside the plugin directory, so this file is inert unless the
 * CLI runs it with the environment of the test process:
 *
 *   INTROPAGE_E2E_CACTI          Cacti root directory
 *   INTROPAGE_E2E_HOOK_USER      id of the user the hook runs for
 *   INTROPAGE_E2E_HOOK_GRAPH     local graph id passed to the hook
 *   INTROPAGE_E2E_HOOK_TIMESPAN  optional graph timespan kept in the session
 */

if (PHP_SAPI !== 'cli') {
	http_response_code(404);

	exit;
}

$intropage_cacti = getenv('INTROPAGE_E2E_CACTI');
$intropage_user  = getenv('INTROPAGE_E2E_HOOK_USER');
$intropage_graph = getenv('INTROPAGE_E2E_HOOK_GRAPH');

if (
	$intropage_cacti === false || $intropage_user === false || $intropage_graph === false
	|| !is_file($intropage_cacti . '/include/cli_check.php')
	|| ctype_digit($intropage_user) === false || ctype_digit($intropage_graph) === false
) {
	exit(0);
}

require $intropage_cacti . '/include/cli_check.php';

$_SESSION['sess_user_id'] = (int) $intropage_user;

$intropage_span = getenv('INTROPAGE_E2E_HOOK_TIMESPAN');

if ($intropage_span !== false && ctype_digit($intropage_span)) {
	$_SESSION['sess_current_timespan'] = (int) $intropage_span;
}

// A graph page calls the hook once per graph; two calls show what is printed only once
for ($intropage_call = 1; $intropage_call <= 2; $intropage_call++) {
	print "--- call $intropage_call\n";

	api_plugin_hook('graph_buttons', ['hook' => 'view', 'local_graph_id' => (int) $intropage_graph, 'rra' => 1, 'view_type' => '']);

	print "\n";
}
