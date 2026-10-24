<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * tests/ ships inside the plugin directory, so this file must do nothing when
 * a web server is asked for it, or when PHP runs it outside the test runner.
 * It reads no request data and takes every setting from the environment.
 */
if (!function_exists('beforeAll')) {
	if (PHP_SAPI !== 'cli') {
		http_response_code(404);
	}

	exit;
}

/*
 * End-to-end coverage against a running Cacti with this plugin installed:
 * real login, real CSRF token, real requests to intropage.php, the real poller
 * script and the real database. It needs a web server and the Cacti database,
 * so it is skipped unless these environment variables are set:
 *
 *   INTROPAGE_E2E_HOST     host and port the web server listens on
 *   INTROPAGE_E2E_SCHEME   scheme for that host, defaults to http
 *   INTROPAGE_E2E_DB_HOST  database host
 *   INTROPAGE_E2E_DB_NAME  database name
 *   INTROPAGE_E2E_DB_USER  database user
 *   INTROPAGE_E2E_DB_PASS  database password
 *   INTROPAGE_E2E_CACTI    Cacti root directory (for the poller script and
 *                          cacti.log); defaults to two levels above the plugin
 *   INTROPAGE_COVERAGE_DIR optional; see coverage-prepend.php
 *
 * The test creates local users named e2e_* and removes them again. Do not run
 * it against a production installation.
 */

const INTROPAGE_E2E_PASS = 'e2e-pass-1';
const INTROPAGE_E2E_PATH = '/plugins/intropage/intropage.php';

/*
 * Realm ids in user_auth_realm. The plugin realm id is 100 plus its row in
 * plugin_realms.
 */
const INTROPAGE_E2E_REALM_USER_ADMIN = 1;
const INTROPAGE_E2E_REALM_CONSOLE    = 8;

/**
 * Reads the environment configuration for the end-to-end tests.
 *
 * @return array|null The settings, or null when the stack is not configured.
 */
function intropage_e2e_config(): ?array {
	$host = getenv('INTROPAGE_E2E_HOST');
	$db   = getenv('INTROPAGE_E2E_DB_NAME');

	if ($host === false || $host === '' || $db === false || $db === '') {
		return null;
	}

	return [
		'base'  => (getenv('INTROPAGE_E2E_SCHEME') ?: 'http') . '://' . $host,
		'dsn'   => 'mysql:host=' . (getenv('INTROPAGE_E2E_DB_HOST') ?: '127.0.0.1') . ';dbname=' . $db,
		'user'  => (string) getenv('INTROPAGE_E2E_DB_USER'),
		'pass'  => (string) getenv('INTROPAGE_E2E_DB_PASS'),
		'cacti' => getenv('INTROPAGE_E2E_CACTI') ?: dirname(__DIR__, 3),
		'cov'   => getenv('INTROPAGE_COVERAGE_DIR') ?: '',
	];
}

/**
 * Minimal cookie-keeping HTTP client; redirects are reported, not followed.
 */
final class IntropageE2eClient {
	/** @var array<string, string> */
	private array $cookies = [];

	private string $base;

	public function __construct(string $base) {
		$this->base = $base;
	}

	/**
	 * @param array<string, mixed> $fields
	 *
	 * @return array{status: int, location: string, body: string}
	 */
	public function request(string $method, string $path, array $fields = []): array {
		$header = "User-Agent: intropage-e2e\r\n";

		foreach ($this->cookies as $name => $value) {
			$header .= "Cookie: $name=$value\r\n";
		}

		$http = [
			'method'          => $method,
			'ignore_errors'   => true,
			'follow_location' => 0,
			'timeout'         => 120,
		];

		if ($method === 'POST') {
			$header .= "Content-Type: application/x-www-form-urlencoded\r\n";
			$http['content'] = http_build_query($fields);
		}

		$http['header'] = $header;

		$body = file_get_contents($this->base . $path, false, stream_context_create(['http' => $http]));

		$status   = 0;
		$location = '';

		foreach ($http_response_header ?? [] as $line) {
			if (preg_match('#^HTTP/\S+ (\d+)#', $line, $m)) {
				$status = (int) $m[1];
			} elseif (stripos($line, 'Location:') === 0) {
				$location = trim(substr($line, 9));
			} elseif (stripos($line, 'Set-Cookie:') === 0 && preg_match('/^Set-Cookie:\s*([^=;\s]+)=([^;]*)/i', $line, $m)) {
				$this->cookies[$m[1]] = $m[2];
			}
		}

		return ['status' => $status, 'location' => $location, 'body' => (string) $body];
	}

	public function login(string $user, string $pass): bool {
		$page = $this->request('GET', '/index.php');

		if (!preg_match('/name=.__csrf_magic. value="([^"]+)"/', $page['body'], $m)) {
			return false;
		}

		$result = $this->request('POST', '/index.php', [
			'__csrf_magic'   => $m[1],
			'action'         => 'login',
			'login_username' => $user,
			'login_password' => $pass,
		]);

		return $result['status'] === 302;
	}

	/**
	 * Fetches the CSRF token embedded in a rendered dashboard page.
	 */
	public function token(string $path = INTROPAGE_E2E_PATH): string {
		$page = $this->request('GET', $path);

		preg_match('/csrfMagicToken\s*=\s*[\'"]([^\'"]+)[\'"]/', $page['body'], $m);

		return $m[1] ?? '';
	}

	/**
	 * Sends a POST that carries a valid CSRF token.
	 *
	 * @param array<string, mixed> $fields
	 *
	 * @return array{status: int, location: string, body: string}
	 */
	public function post(string $path, array $fields): array {
		$fields['__csrf_magic'] = $this->token();

		return $this->request('POST', $path, $fields);
	}

	public function get(string $query = '', string $path = INTROPAGE_E2E_PATH): array {
		return $this->request('GET', $path . ($query === '' ? '' : '?' . $query));
	}
}

/* ------------------------------------------------------------------ */
/* database helpers                                                    */
/* ------------------------------------------------------------------ */

function intropage_e2e_db(): PDO {
	static $pdo = null;

	if ($pdo === null) {
		$config = intropage_e2e_config();

		$pdo = new PDO($config['dsn'], $config['user'], $config['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
	}

	return $pdo;
}

function intropage_e2e_cell(string $sql, array $params = []) {
	$stmt = intropage_e2e_db()->prepare($sql);
	$stmt->execute($params);

	return $stmt->fetchColumn();
}

function intropage_e2e_rows(string $sql, array $params = []): array {
	$stmt = intropage_e2e_db()->prepare($sql);
	$stmt->execute($params);

	return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function intropage_e2e_exec(string $sql, array $params = []): void {
	intropage_e2e_db()->prepare($sql)->execute($params);
}

function intropage_e2e_realm(string $file): int {
	return 100 + (int) intropage_e2e_cell('SELECT id FROM plugin_realms WHERE file = ?', [$file]);
}

/**
 * Creates (or recreates) a local user with exactly the given realms.
 *
 * @param int[] $realms
 */
function intropage_e2e_make_user(string $name, array $realms, int $login_opts = 4): int {
	intropage_e2e_drop_user($name);

	intropage_e2e_exec(
		'INSERT INTO user_auth (username, password, realm, full_name, must_change_password, enabled, login_opts) VALUES (?, ?, 0, ?, ?, ?, ?)',
		[$name, password_hash(INTROPAGE_E2E_PASS, PASSWORD_DEFAULT), $name, '', 'on', $login_opts]
	);

	$id = (int) intropage_e2e_cell('SELECT id FROM user_auth WHERE username = ?', [$name]);

	foreach ($realms as $realm) {
		intropage_e2e_exec('INSERT INTO user_auth_realm (user_id, realm_id) VALUES (?, ?)', [$id, $realm]);
	}

	return $id;
}

function intropage_e2e_user_id(string $name): int {
	return (int) intropage_e2e_cell('SELECT id FROM user_auth WHERE username = ?', [$name]);
}

function intropage_e2e_drop_user(string $name): void {
	$id = intropage_e2e_user_id($name);

	if ($id === 0) {
		return;
	}

	foreach ([
		'DELETE FROM plugin_intropage_panel_dashboard WHERE user_id = ?',
		'DELETE FROM plugin_intropage_panel_data WHERE user_id = ?',
		'DELETE FROM plugin_intropage_dashboard WHERE user_id = ?',
		'DELETE FROM plugin_intropage_user_auth WHERE user_id = ?',
		'DELETE FROM user_auth_realm WHERE user_id = ?',
		'DELETE FROM user_auth_perms WHERE user_id = ?',
		'DELETE FROM settings_user WHERE user_id = ?',
	] as $sql) {
		intropage_e2e_exec($sql, [$id]);
	}

	intropage_e2e_exec('DELETE FROM user_auth WHERE id = ?', [$id]);
}

/**
 * Stores the panel permissions of a user as the plugin does.
 *
 * @param string[] $panels
 */
function intropage_e2e_set_permissions(string $user, array $panels): void {
	$permissions = [];

	foreach ($panels as $panel) {
		$permissions[$panel] = 'on';
	}

	intropage_e2e_exec('REPLACE INTO plugin_intropage_user_auth (user_id, permissions) VALUES (?, ?)', [intropage_e2e_user_id($user), json_encode($permissions)]);
}

/**
 * Removes the dashboards and panel rows of a user and gives it one dashboard.
 */
function intropage_e2e_reset(string $user): void {
	$id = intropage_e2e_user_id($user);

	foreach ([
		'DELETE FROM plugin_intropage_panel_dashboard WHERE user_id = ?',
		'DELETE FROM plugin_intropage_panel_data WHERE user_id = ?',
		'DELETE FROM plugin_intropage_dashboard WHERE user_id = ?',
		'DELETE FROM settings_user WHERE user_id = ?',
	] as $sql) {
		intropage_e2e_exec($sql, [$id]);
	}

	intropage_e2e_exec('INSERT INTO plugin_intropage_dashboard (user_id, dashboard_id, name, shared) VALUES (?, 1, ?, 0)', [$id, 'Default']);
}

function intropage_e2e_panel_row(string $panel_id, int $user_id): int {
	return (int) intropage_e2e_cell('SELECT id FROM plugin_intropage_panel_data WHERE panel_id = ? AND user_id = ?', [$panel_id, $user_id]);
}

/**
 * Inserts a panel data row for a user and links it to a dashboard.
 *
 * @return int The id of the new panel data row.
 */
function intropage_e2e_seed_panel(string $user, string $panel_id, int $dashboard = 1, string $height = 'normal'): int {
	$id = intropage_e2e_user_id($user);

	intropage_e2e_exec(
		'INSERT INTO plugin_intropage_panel_data (panel_id, user_id, priority, alarm, refresh_interval, trend_interval, height, last_update, last_trend_update) VALUES (?, ?, 30, ?, 300, 300, ?, NOW(), NOW())',
		[$panel_id, $id, 'green', $height]
	);

	$row = (int) intropage_e2e_cell('SELECT MAX(id) FROM plugin_intropage_panel_data WHERE panel_id = ? AND user_id = ?', [$panel_id, $id]);

	intropage_e2e_exec('INSERT INTO plugin_intropage_panel_dashboard (panel_id, user_id, dashboard_id) VALUES (?, ?, ?)', [$row, $id, $dashboard]);

	return $row;
}

/**
 * Creates a device with one graph on it.
 *
 * @return array{int, int} The device id and the local graph id.
 */
function intropage_e2e_seed_graph(string $title): array {
	intropage_e2e_exec('INSERT INTO host (description, hostname) VALUES (?, ?)', ['e2e-graph-host', 'e2e-graph-host.invalid']);

	$host = (int) intropage_e2e_cell('SELECT MAX(id) FROM host WHERE description = ?', ['e2e-graph-host']);

	intropage_e2e_exec('INSERT INTO graph_local (host_id, graph_template_id, snmp_query_id, snmp_index) VALUES (?, 0, 0, ?)', [$host, '']);

	$graph = (int) intropage_e2e_cell('SELECT MAX(id) FROM graph_local WHERE host_id = ?', [$host]);

	intropage_e2e_exec('INSERT INTO graph_templates_graph (local_graph_id, title, title_cache, height, width, image_format_id) VALUES (?, ?, ?, 100, 200, 1)', [$graph, $title, $title]);

	return [$host, $graph];
}

/**
 * Removes the devices and graphs made by intropage_e2e_seed_graph().
 */
function intropage_e2e_drop_graphs(): void {
	foreach (intropage_e2e_rows('SELECT id FROM host WHERE description = ?', ['e2e-graph-host']) as $host) {
		foreach (intropage_e2e_rows('SELECT id FROM graph_local WHERE host_id = ?', [$host['id']]) as $graph) {
			intropage_e2e_exec('DELETE FROM graph_templates_graph WHERE local_graph_id = ?', [$graph['id']]);
			intropage_e2e_exec('DELETE FROM graph_local WHERE id = ?', [$graph['id']]);
		}

		intropage_e2e_exec('DELETE FROM host WHERE id = ?', [$host['id']]);
	}
}

/**
 * Returns the logged-in client for a fixture user, logging in on first use.
 */
function intropage_e2e_client(string $user): IntropageE2eClient {
	static $clients = [];

	if (!isset($clients[$user])) {
		$client = new IntropageE2eClient(intropage_e2e_config()['base']);

		expect($client->login($user, INTROPAGE_E2E_PASS))->toBeTrue("login as $user failed");

		$clients[$user] = $client;
	}

	return $clients[$user];
}

/* ------------------------------------------------------------------ */
/* cacti.log and coverage helpers                                      */
/* ------------------------------------------------------------------ */

function intropage_e2e_log_file(): string {
	return intropage_e2e_config()['cacti'] . '/log/cacti.log';
}

/**
 * Returns the size of cacti.log, or null when it is missing or not writable
 * by this process (the CI job makes it writable before the tests run).
 */
function intropage_e2e_log_size(): ?int {
	$log = intropage_e2e_log_file();

	return (is_file($log) && is_writable($log)) ? (int) filesize($log) : null;
}

/**
 * Lines added to cacti.log since a mark that match the workflow's error scan.
 *
 * @return string[]
 */
function intropage_e2e_log_findings(int $size): array {
	$text = (string) file_get_contents(intropage_e2e_log_file(), false, null, $size);

	return array_values(array_filter(explode("\n", $text), static function (string $line): bool {
		return preg_match('/FATAL:|ERROR:|WARNING:|ERROR |WARNING /', $line) === 1;
	}));
}

function intropage_e2e_log_restore(int $size): void {
	$handle = fopen(intropage_e2e_log_file(), 'r+');

	if ($handle !== false) {
		ftruncate($handle, $size);
		fclose($handle);
	}
}

/**
 * Registers log lines the running test provokes on purpose.
 */
function intropage_e2e_expect_log(string ...$patterns): void {
	$GLOBALS['__intropage_e2e_expected_log'] = array_merge($GLOBALS['__intropage_e2e_expected_log'] ?? [], $patterns);
}

/**
 * Tells the optional coverage prepend file which test is running.
 */
function intropage_e2e_coverage_token(string $name): void {
	$dir = intropage_e2e_config()['cov'];

	if ($dir !== '' && is_dir($dir)) {
		$slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower(str_replace('__pest_evaluable_', '', $name))), '-');

		file_put_contents($dir . '/current', substr($slug, 0, 80));
	}
}

/**
 * Runs the real poller script.
 *
 * @return array{status: int, output: string}
 */
function intropage_e2e_poller(): array {
	$config = intropage_e2e_config();
	$script = $config['cacti'] . '/plugins/intropage/poller_intropage.php';
	$prefix = '';

	if ($config['cov'] !== '') {
		$prepend = $config['cacti'] . '/plugins/intropage/tests/Integration/coverage-prepend.php';
		$prefix  = ' -d memory_limit=1G -d xdebug.mode=coverage -d auto_prepend_file=' . escapeshellarg($prepend);
	}

	$output = [];
	$status = 0;

	exec(escapeshellarg(PHP_BINARY) . $prefix . ' -q ' . escapeshellarg($script) . ' 2>&1', $output, $status);

	return ['status' => $status, 'output' => implode("\n", $output)];
}

/* ------------------------------------------------------------------ */
/* fixture                                                             */
/* ------------------------------------------------------------------ */

beforeAll(function () {
	if (intropage_e2e_config() === null) {
		return;
	}

	$viewer = intropage_e2e_realm('intropage.php');

	intropage_e2e_make_user('e2e_viewer', [$viewer]);
	intropage_e2e_make_user('e2e_other', [$viewer]);
	intropage_e2e_make_user('e2e_console', [$viewer, INTROPAGE_E2E_REALM_CONSOLE], 1);
	intropage_e2e_make_user('e2e_noplugin', [INTROPAGE_E2E_REALM_CONSOLE], 1);
	intropage_e2e_make_user('e2e_admin', [$viewer, INTROPAGE_E2E_REALM_USER_ADMIN]);
});

afterAll(function () {
	if (intropage_e2e_config() === null) {
		return;
	}

	foreach (['e2e_viewer', 'e2e_other', 'e2e_console', 'e2e_noplugin', 'e2e_admin'] as $user) {
		intropage_e2e_drop_user($user);
	}

	intropage_e2e_drop_graphs();
	intropage_e2e_exec("DELETE FROM host WHERE description LIKE 'e2e-%'");
	intropage_e2e_exec("DELETE FROM host_template WHERE name LIKE 'e2e-%'");
	intropage_e2e_exec('DELETE FROM host_template WHERE name = ?', [str_repeat("\u{20ac}", 8)]);
	intropage_e2e_exec("DELETE FROM user_auth_group WHERE name LIKE 'e2e-%'");
	intropage_e2e_exec('DELETE FROM plugin_intropage_panel_data WHERE user_id > 0 AND user_id NOT IN (SELECT id FROM user_auth)');
});

beforeEach(function () {
	if (intropage_e2e_config() === null) {
		$this->markTestSkipped('Set INTROPAGE_E2E_HOST and INTROPAGE_E2E_DB_NAME to run the end-to-end tests.');
	}

	$GLOBALS['__intropage_e2e_expected_log'] = [];
	$GLOBALS['__intropage_e2e_log_mark']     = intropage_e2e_log_size();

	intropage_e2e_coverage_token($this->name());

	foreach (['e2e_viewer', 'e2e_other'] as $user) {
		intropage_e2e_reset($user);
		intropage_e2e_set_permissions($user, ['alert_host', 'graph_host_template', 'favourite_graph']);
	}

	intropage_e2e_exec('UPDATE user_auth SET login_opts = 4 WHERE username IN (?, ?)', ['e2e_viewer', 'e2e_other']);

	// A client keeps the dashboard it last used in its session; start each test on dashboard 1
	foreach (['e2e_viewer', 'e2e_other'] as $user) {
		intropage_e2e_client($user)->get('dashboard_id=1&header=false');
	}
});

afterEach(function () {
	if (intropage_e2e_config() === null) {
		return;
	}

	$mark = $GLOBALS['__intropage_e2e_log_mark'];

	if ($mark === null) {
		return;
	}

	$allowed = array_merge(['DBCALL ERROR: Column: height contains an invalid value'], $GLOBALS['__intropage_e2e_expected_log']);

	$unexpected = [];
	$previous   = false;

	foreach (intropage_e2e_log_findings($mark) as $line) {
		$expected = false;

		foreach ($allowed as $pattern) {
			if (strpos($line, $pattern) !== false) {
				$expected = true;
			}
		}

		// The backtrace line that follows an expected PHP warning belongs to it
		if (!$expected && $previous && strpos($line, 'Backtrace:') !== false) {
			$expected = true;
		}

		$previous = $expected;

		if (!$expected) {
			$unexpected[] = $line;
		}
	}

	intropage_e2e_log_restore($mark);

	expect($unexpected)->toBe([], 'unexpected lines in cacti.log');
});

/* ------------------------------------------------------------------ */
/* panel permission on reload, details and add                         */
/* ------------------------------------------------------------------ */

describe('reloading and describing panels', function () {
	it('serves a panel the user was granted', function () {
		$row = intropage_e2e_seed_panel('e2e_viewer', 'alert_host');

		$reload = intropage_e2e_client('e2e_viewer')->get("action=reload&force=true&header=false&panel_id=$row");
		$detail = intropage_e2e_client('e2e_viewer')->get("action=details&header=false&panel_id=$row");

		expect($reload['body'])->toContain('ccCardHeader');
		expect($detail['body'])->toContain('cactiTableTitle');
	});

	it('refuses a system panel the user was not granted', function () {
		$row = intropage_e2e_panel_row('analyse_db', 0);

		if ($row === 0) {
			$this->markTestSkipped('analyse_db is not registered on this installation.');
		}

		$client = intropage_e2e_client('e2e_viewer');

		expect(trim($client->get("action=reload&force=true&header=false&panel_id=$row")['body']))->toBe('Panel not found');
		expect(trim($client->get("action=details&header=false&panel_id=$row")['body']))->toBe('Panel Not Found');
	});

	it('refuses a panel of its own after the permission is withdrawn', function () {
		$row = intropage_e2e_seed_panel('e2e_viewer', 'alert_host');

		intropage_e2e_set_permissions('e2e_viewer', ['favourite_graph']);

		$client = intropage_e2e_client('e2e_viewer');

		expect(trim($client->get("action=reload&header=false&panel_id=$row")['body']))->toBe('Panel not found');
		expect(trim($client->get("action=details&header=false&panel_id=$row")['body']))->toBe('Panel Not Found');
	});

	it('does not serve the panel row of another user', function () {
		$row = intropage_e2e_seed_panel('e2e_other', 'alert_host');

		$client = intropage_e2e_client('e2e_viewer');

		expect($client->get("action=reload&header=false&panel_id=$row")['body'])->toContain('Panel not found');
		expect(trim($client->get("action=details&header=false&panel_id=$row")['body']))->toBe('Panel Not Found');
	});

	it('reports a panel row that does not exist', function () {
		$client = intropage_e2e_client('e2e_viewer');

		expect($client->get('action=reload&header=false&panel_id=2147483000')['body'])->toContain('Panel not found');
		expect(trim($client->get('action=details&header=false&panel_id=2147483000')['body']))->toBe('Panel Not Found');
	});

	it('always serves the admin alert panel, which has no permission entry', function () {
		$row = intropage_e2e_panel_row('admin_alert', 0);

		if ($row === 0) {
			$this->markTestSkipped('admin_alert is not registered on this installation.');
		}

		intropage_e2e_set_permissions('e2e_viewer', []);

		$reload = intropage_e2e_client('e2e_viewer')->get("action=reload&header=false&panel_id=$row");

		expect($reload['body'])->not->toContain('Panel not found');
		expect($reload['body'])->toContain('ccCardHeader');
	});

	it('rejects a non-numeric panel id', function () {
		$result = intropage_e2e_client('e2e_viewer')->get('action=reload&header=false&panel_id=abc');

		expect($result['body'])->not->toContain('ccCardHeader');

		intropage_e2e_expect_log('input', 'Validation', 'validation');
	});

	it('sends an unauthenticated request to the login page', function () {
		$row    = intropage_e2e_seed_panel('e2e_viewer', 'alert_host');
		$client = new IntropageE2eClient(intropage_e2e_config()['base']);

		foreach (["action=reload&header=false&panel_id=$row", "action=details&header=false&panel_id=$row"] as $query) {
			$result = $client->get($query);

			expect($result['body'])->not->toContain('ccCardHeader');
			expect($result['body'])->not->toContain('cactiTableTitle');
		}
	});
});

describe('adding panels', function () {
	it('adds a granted panel by name and by row id', function () {
		$client = intropage_e2e_client('e2e_viewer');
		$user   = intropage_e2e_user_id('e2e_viewer');

		$client->post(INTROPAGE_E2E_PATH, ['intropage_addpanel' => 'alert_host', 'dashboard_id' => '1', 'header' => 'false']);

		$row = intropage_e2e_panel_row('alert_host', $user);

		expect($row)->toBeGreaterThan(0);
		expect((int) intropage_e2e_cell('SELECT COUNT(*) FROM plugin_intropage_panel_dashboard WHERE user_id = ? AND panel_id = ?', [$user, $row]))->toBe(1);

		intropage_e2e_exec('DELETE FROM plugin_intropage_panel_dashboard WHERE user_id = ?', [$user]);

		$client->post(INTROPAGE_E2E_PATH, ['intropage_addpanel' => (string) $row, 'dashboard_id' => '1', 'header' => 'false']);

		expect((int) intropage_e2e_cell('SELECT COUNT(*) FROM plugin_intropage_panel_dashboard WHERE user_id = ? AND panel_id = ?', [$user, $row]))->toBe(1);
	});

	it('creates nothing for a panel the user was not granted', function () {
		$client = intropage_e2e_client('e2e_viewer');
		$user   = intropage_e2e_user_id('e2e_viewer');
		$row    = intropage_e2e_panel_row('analyse_db', 0);
		$before = (int) intropage_e2e_cell('SELECT COUNT(*) FROM plugin_intropage_panel_data WHERE panel_id = ?', ['analyse_db']);

		$client->post(INTROPAGE_E2E_PATH, ['intropage_addpanel' => 'analyse_db', 'dashboard_id' => '1', 'header' => 'false']);

		if ($row > 0) {
			$client->post(INTROPAGE_E2E_PATH, ['intropage_addpanel' => (string) $row, 'dashboard_id' => '1', 'header' => 'false']);
		}

		expect((int) intropage_e2e_cell('SELECT COUNT(*) FROM plugin_intropage_panel_data WHERE panel_id = ?', ['analyse_db']))->toBe($before);
		expect((int) intropage_e2e_cell('SELECT COUNT(*) FROM plugin_intropage_panel_dashboard WHERE user_id = ?', [$user]))->toBe(0);
	});

	it('ignores a panel name that has no definition', function () {
		$user = intropage_e2e_user_id('e2e_viewer');

		intropage_e2e_client('e2e_viewer')->post(INTROPAGE_E2E_PATH, ['intropage_addpanel' => 'no_such_panel', 'dashboard_id' => '1', 'header' => 'false']);

		expect((int) intropage_e2e_cell('SELECT COUNT(*) FROM plugin_intropage_panel_data WHERE panel_id = ?', ['no_such_panel']))->toBe(0);
		expect((int) intropage_e2e_cell('SELECT COUNT(*) FROM plugin_intropage_panel_dashboard WHERE user_id = ?', [$user]))->toBe(0);
	});

	it('ignores a row id that does not exist', function () {
		$user = intropage_e2e_user_id('e2e_viewer');

		intropage_e2e_client('e2e_viewer')->post(INTROPAGE_E2E_PATH, ['intropage_addpanel' => '2147483000', 'dashboard_id' => '1', 'header' => 'false']);

		expect((int) intropage_e2e_cell('SELECT COUNT(*) FROM plugin_intropage_panel_dashboard WHERE user_id = ?', [$user]))->toBe(0);
	});

	it('does not link a panel to a dashboard id of zero', function () {
		$user = intropage_e2e_user_id('e2e_viewer');
		$row  = intropage_e2e_seed_panel('e2e_viewer', 'alert_host');

		intropage_e2e_exec('DELETE FROM plugin_intropage_panel_dashboard WHERE user_id = ?', [$user]);

		intropage_e2e_client('e2e_viewer')->post(INTROPAGE_E2E_PATH, ['intropage_addpanel' => (string) $row, 'dashboard_id' => '0', 'header' => 'false']);

		expect((int) intropage_e2e_cell('SELECT COUNT(*) FROM plugin_intropage_panel_dashboard WHERE user_id = ?', [$user]))->toBe(0);
	});

	it('rejects a request without a CSRF token', function () {
		$user = intropage_e2e_user_id('e2e_viewer');

		intropage_e2e_client('e2e_viewer')->request('POST', INTROPAGE_E2E_PATH, ['intropage_addpanel' => 'alert_host', 'dashboard_id' => '1', 'header' => 'false']);

		expect(intropage_e2e_panel_row('alert_host', $user))->toBe(0);
	});

	it('rejects an unauthenticated request', function () {
		$client = new IntropageE2eClient(intropage_e2e_config()['base']);

		$client->request('POST', INTROPAGE_E2E_PATH, ['intropage_addpanel' => 'alert_host', 'dashboard_id' => '1', 'header' => 'false']);

		expect((int) intropage_e2e_cell('SELECT COUNT(*) FROM plugin_intropage_panel_data WHERE panel_id = ? AND user_id = 0', ['alert_host']))->toBeGreaterThanOrEqual(0);
		expect((int) intropage_e2e_cell('SELECT COUNT(*) FROM plugin_intropage_panel_dashboard WHERE user_id = ?', [intropage_e2e_user_id('e2e_viewer')]))->toBe(0);
	});

	it('does not add a panel through a GET request', function () {
		$user = intropage_e2e_user_id('e2e_viewer');

		intropage_e2e_client('e2e_viewer')->get('intropage_addpanel=alert_host&dashboard_id=1&header=false');

		expect(intropage_e2e_panel_row('alert_host', $user))->toBe(0);
	});
});

/* ------------------------------------------------------------------ */
/* actions that change data need POST                                  */
/* ------------------------------------------------------------------ */

/**
 * Reads the stored state an action changes, for before and after checks.
 */
function intropage_e2e_probe(string $probe, int $user, int $row): string {
	switch ($probe) {
		case 'dashboards':
			return (string) intropage_e2e_cell('SELECT COUNT(*) FROM plugin_intropage_dashboard WHERE user_id = ?', [$user]);
		case 'shared':
			return (string) intropage_e2e_cell('SELECT SUM(shared) FROM plugin_intropage_dashboard WHERE user_id = ?', [$user]);
		case 'login_opts':
			return (string) intropage_e2e_cell('SELECT login_opts FROM user_auth WHERE id = ?', [$user]);
		case 'height':
			return (string) intropage_e2e_cell('SELECT height FROM plugin_intropage_panel_data WHERE id = ?', [$row]);
		case 'panels':
			return (string) intropage_e2e_cell('SELECT COUNT(*) FROM plugin_intropage_panel_data WHERE user_id = ?', [$user]);
		case 'favourites':
			return (string) intropage_e2e_cell('SELECT COUNT(*) FROM plugin_intropage_panel_data WHERE user_id = ? AND fav_graph_id IS NOT NULL', [$user]);
		default:
			return (string) intropage_e2e_cell('SELECT value FROM settings_user WHERE user_id = ? AND name = ?', [$user, $probe]);
	}
}

dataset('post only actions', [
	'remove a dashboard'        => [['intropage_action' => 'removepage_2'], 'dashboards'],
	'add a dashboard'           => [['intropage_action' => 'addpage_1'], 'dashboards'],
	'share a dashboard'         => [['intropage_action' => 'share'], 'shared'],
	'switch the login page'     => [['intropage_action' => 'loginopt_graph'], 'login_opts'],
	'grow a panel'              => [['intropage_action' => 'heightmore', 'panel_id' => '{row}'], 'height'],
	'drop a panel'              => [['intropage_action' => 'droppanel', 'panel_id' => '{row}'], 'panels'],
	'favourite a graph'         => [['intropage_action' => 'favgraph', 'graph_id' => '1'], 'favourites'],
	'set the number of lines'   => [['intropage_action' => 'lines_15'], 'intropage_number_of_lines'],
	'set the important period'  => [['intropage_action' => 'period_900'], 'intropage_important_period'],
	'set the refresh'           => [['intropage_action' => 'refresh_300'], 'intropage_autorefresh'],
	'sort by severity'          => [['intropage_action' => 'important_first'], 'intropage_display_important_first'],
	'use wider panels'          => [['intropage_action' => 'displaywide_on'], 'intropage_display_wide'],
	'change the timespan'       => [['intropage_action_timespan' => 'timespan_7200'], 'intropage_timespan'],
]);

describe('actions that change data', function () {
	beforeEach(function () {
		$this->user = intropage_e2e_user_id('e2e_viewer');
		$this->row  = intropage_e2e_seed_panel('e2e_viewer', 'alert_host');

		intropage_e2e_exec('INSERT INTO plugin_intropage_dashboard (user_id, dashboard_id, name, shared) VALUES (?, 2, ?, 0)', [$this->user, 'Second']);
		intropage_e2e_exec('DELETE FROM user_auth_realm WHERE user_id = ? AND realm_id = 7', [$this->user]);

		// favgraph reads the graph timespan that Cacti's graph pages keep in the session
		intropage_e2e_expect_log('sess_current_timespan');
	});

	it('ignores the action as GET and applies it as POST', function (array $fields, string $probe) {
		$client = intropage_e2e_client('e2e_viewer');
		$fields = array_map(fn ($value) => $value === '{row}' ? (string) $this->row : $value, $fields);
		$before = intropage_e2e_probe($probe, $this->user, $this->row);

		$client->get(http_build_query($fields + ['header' => 'false']));

		expect(intropage_e2e_probe($probe, $this->user, $this->row))->toBe($before);

		$client->post(INTROPAGE_E2E_PATH, $fields + ['header' => 'false']);

		expect(intropage_e2e_probe($probe, $this->user, $this->row))->not->toBe($before);
	})->with('post only actions');

	it('ignores the action when the CSRF token is missing', function (array $fields, string $probe) {
		$fields = array_map(fn ($value) => $value === '{row}' ? (string) $this->row : $value, $fields);
		$before = intropage_e2e_probe($probe, $this->user, $this->row);

		intropage_e2e_client('e2e_viewer')->request('POST', INTROPAGE_E2E_PATH, $fields + ['header' => 'false']);

		expect(intropage_e2e_probe($probe, $this->user, $this->row))->toBe($before);
	})->with('post only actions');

	it('ignores the action from an unauthenticated client', function (array $fields, string $probe) {
		$fields = array_map(fn ($value) => $value === '{row}' ? (string) $this->row : $value, $fields);
		$before = intropage_e2e_probe($probe, $this->user, $this->row);
		$client = new IntropageE2eClient(intropage_e2e_config()['base']);

		$client->request('POST', INTROPAGE_E2E_PATH, $fields + ['header' => 'false']);
		$client->get(http_build_query($fields + ['header' => 'false']));

		expect(intropage_e2e_probe($probe, $this->user, $this->row))->toBe($before);
	})->with('post only actions');

	it('still serves the read-only panel select as GET', function () {
		$result = intropage_e2e_client('e2e_viewer')->get('intropage_action=addpanelselect&dashboard_id=1&header=false');

		expect($result['body'])->toContain('intropage_addpanel');
	});

	it('does not switch the login option for a GET request', function () {
		intropage_e2e_client('e2e_viewer')->get('intropage_action=loginopt_console&header=false');

		expect(intropage_e2e_probe('login_opts', $this->user, $this->row))->toBe('4');
	});

	it('saves the login option for a POST request', function () {
		intropage_e2e_client('e2e_viewer')->post(INTROPAGE_E2E_PATH, ['intropage_action' => 'loginopt_console', 'header' => 'false']);

		expect(intropage_e2e_probe('login_opts', $this->user, $this->row))->toBe('2');
	});
});

/* ------------------------------------------------------------------ */
/* dashboards                                                          */
/* ------------------------------------------------------------------ */

dataset('dashboard names', [
	'plain'             => ['Core routers', 'Core routers'],
	'single quote'      => ["O'Brien", 'O&apos;Brien'],
	'double quote'      => ['The "main" board', 'The &quot;main&quot; board'],
	'markup'            => ['<b>x</b>&amp;', '&lt;b&gt;x&lt;/b&gt;&amp;'],
	'unicode'           => ["D\u{e9}faut \u{20ac}", "D\u{e9}faut \u{20ac}"],
	'thirty characters' => [str_repeat('n', 30), str_repeat('n', 30)],
]);

describe('dashboard names', function () {
	it('stores the name, keeps the shared flag and escapes the name on output', function (string $name, string $html) {
		$client = intropage_e2e_client('e2e_viewer');
		$user   = intropage_e2e_user_id('e2e_viewer');

		intropage_e2e_exec('UPDATE plugin_intropage_dashboard SET shared = 1 WHERE user_id = ? AND dashboard_id = 1', [$user]);

		$client->post(INTROPAGE_E2E_PATH, ['header' => 'false', 'action' => 'save', 'save_settings' => '1', 'name_1' => $name]);

		expect(intropage_e2e_cell('SELECT name FROM plugin_intropage_dashboard WHERE user_id = ? AND dashboard_id = 1', [$user]))->toBe($name);
		expect((int) intropage_e2e_cell('SELECT shared FROM plugin_intropage_dashboard WHERE user_id = ? AND dashboard_id = 1', [$user]))->toBe(1);

		$page = $client->get();

		expect($page['body'])->toContain('>' . $html . '</a>');

		if ($html !== $name) {
			expect($page['body'])->not->toContain('>' . $name . '</a>');
		}
	})->with('dashboard names');

	it('never creates a dashboard the user does not have', function (string $field) {
		$user = intropage_e2e_user_id('e2e_viewer');

		intropage_e2e_client('e2e_viewer')->post(INTROPAGE_E2E_PATH, ['header' => 'false', 'action' => 'save', 'save_settings' => '1', $field => 'Never created', 'name_1' => 'Kept']);

		expect((int) intropage_e2e_cell('SELECT COUNT(*) FROM plugin_intropage_dashboard WHERE user_id = ?', [$user]))->toBe(1);
		expect(intropage_e2e_cell('SELECT name FROM plugin_intropage_dashboard WHERE user_id = ? AND dashboard_id = 1', [$user]))->toBe('Kept');
	})->with(['unused id' => ['name_9'], 'not an integer' => ['name_x'], 'leading zero' => ['name_03'], 'exponent' => ['name_1e3'], 'negative' => ['name_-1']]);

	it('ignores a name posted as a list', function () {
		$user = intropage_e2e_user_id('e2e_viewer');

		intropage_e2e_client('e2e_viewer')->post(INTROPAGE_E2E_PATH, ['header' => 'false', 'action' => 'save', 'save_settings' => '1', 'name_1' => ['a', 'b']]);

		expect(intropage_e2e_cell('SELECT name FROM plugin_intropage_dashboard WHERE user_id = ? AND dashboard_id = 1', [$user]))->toBe('Default');
	});

	it('does not rename dashboards of another user', function () {
		intropage_e2e_client('e2e_viewer')->post(INTROPAGE_E2E_PATH, ['header' => 'false', 'action' => 'save', 'save_settings' => '1', 'name_1' => 'Mine']);

		expect(intropage_e2e_cell('SELECT name FROM plugin_intropage_dashboard WHERE user_id = ? AND dashboard_id = 1', [intropage_e2e_user_id('e2e_other')]))->toBe('Default');
	});

	it('does not rename through a GET request', function () {
		$user = intropage_e2e_user_id('e2e_viewer');

		// Cacti core itself logs a warning for this GET
		intropage_e2e_expect_log('Attempt to use GET method');

		intropage_e2e_client('e2e_viewer')->get('header=false&action=save&save_settings=1&name_1=Changed');

		expect(intropage_e2e_cell('SELECT name FROM plugin_intropage_dashboard WHERE user_id = ? AND dashboard_id = 1', [$user]))->toBe('Default');
	});
});

describe('dashboards', function () {
	it('adds, shares, unshares and removes a dashboard', function () {
		$client = intropage_e2e_client('e2e_viewer');
		$user   = intropage_e2e_user_id('e2e_viewer');

		$client->post(INTROPAGE_E2E_PATH, ['intropage_action' => 'addpage_1', 'header' => 'false']);

		expect((int) intropage_e2e_cell('SELECT COUNT(*) FROM plugin_intropage_dashboard WHERE user_id = ? AND dashboard_id = 2', [$user]))->toBe(1);

		$client->post(INTROPAGE_E2E_PATH, ['intropage_action' => 'share', 'header' => 'false']);
		expect((int) intropage_e2e_cell('SELECT SUM(shared) FROM plugin_intropage_dashboard WHERE user_id = ?', [$user]))->toBe(1);

		$client->post(INTROPAGE_E2E_PATH, ['intropage_action' => 'unshare', 'header' => 'false']);
		expect((int) intropage_e2e_cell('SELECT SUM(shared) FROM plugin_intropage_dashboard WHERE user_id = ?', [$user]))->toBe(0);

		$client->post(INTROPAGE_E2E_PATH, ['intropage_action' => 'removepage_2', 'header' => 'false']);
		expect((int) intropage_e2e_cell('SELECT COUNT(*) FROM plugin_intropage_dashboard WHERE user_id = ? AND dashboard_id = 2', [$user]))->toBe(0);
	});

	it('copies a shared dashboard of another user', function () {
		$owner = intropage_e2e_user_id('e2e_other');
		$user  = intropage_e2e_user_id('e2e_viewer');

		intropage_e2e_seed_panel('e2e_other', 'alert_host');
		intropage_e2e_exec('UPDATE plugin_intropage_dashboard SET shared = 1 WHERE user_id = ?', [$owner]);

		intropage_e2e_client('e2e_viewer')->post(INTROPAGE_E2E_PATH, ['intropage_action' => "useshared_1_$owner", 'header' => 'false']);

		expect((int) intropage_e2e_cell('SELECT COUNT(*) FROM plugin_intropage_dashboard WHERE user_id = ?', [$user]))->toBe(2);
		expect((int) intropage_e2e_cell('SELECT COUNT(*) FROM plugin_intropage_panel_data WHERE user_id = ?', [$user]))->toBe(1);
	});

	it('refuses to copy a dashboard that is not shared', function () {
		$owner = intropage_e2e_user_id('e2e_other');
		$user  = intropage_e2e_user_id('e2e_viewer');

		intropage_e2e_client('e2e_viewer')->post(INTROPAGE_E2E_PATH, ['intropage_action' => "useshared_1_$owner", 'header' => 'false']);

		expect((int) intropage_e2e_cell('SELECT COUNT(*) FROM plugin_intropage_dashboard WHERE user_id = ?', [$user]))->toBe(1);
	});
});

/* ------------------------------------------------------------------ */
/* panels that are removed when the permission is withdrawn            */
/* ------------------------------------------------------------------ */

describe('withdrawn panels', function () {
	it('removes the dashboard link and the data row of a withdrawn panel', function () {
		$user = intropage_e2e_user_id('e2e_viewer');

		intropage_e2e_seed_panel('e2e_viewer', 'alert_host');
		intropage_e2e_set_permissions('e2e_viewer', ['favourite_graph']);
		intropage_e2e_expect_log('One or more panels was removed');

		intropage_e2e_client('e2e_viewer')->get();

		expect(intropage_e2e_panel_row('alert_host', $user))->toBe(0);
		expect((int) intropage_e2e_cell('SELECT COUNT(*) FROM plugin_intropage_panel_dashboard WHERE user_id = ?', [$user]))->toBe(0);
	});

	it('keeps the panels that are still permitted and the system rows', function () {
		$user   = intropage_e2e_user_id('e2e_viewer');
		$system = (int) intropage_e2e_cell('SELECT COUNT(*) FROM plugin_intropage_panel_data WHERE user_id = 0');

		intropage_e2e_seed_panel('e2e_viewer', 'alert_host');
		intropage_e2e_seed_panel('e2e_viewer', 'graph_host_template');
		intropage_e2e_set_permissions('e2e_viewer', ['alert_host', 'favourite_graph']);
		intropage_e2e_expect_log('One or more panels was removed');

		intropage_e2e_client('e2e_viewer')->get();

		expect(intropage_e2e_panel_row('alert_host', $user))->toBeGreaterThan(0);
		expect(intropage_e2e_panel_row('graph_host_template', $user))->toBe(0);
		expect((int) intropage_e2e_cell('SELECT COUNT(*) FROM plugin_intropage_panel_data WHERE user_id = 0'))->toBe($system);
	});

	it('leaves other dashboards and users alone when nothing is withdrawn', function () {
		$user = intropage_e2e_user_id('e2e_viewer');

		intropage_e2e_seed_panel('e2e_viewer', 'alert_host');
		intropage_e2e_seed_panel('e2e_other', 'alert_host');

		intropage_e2e_client('e2e_viewer')->get();

		expect(intropage_e2e_panel_row('alert_host', $user))->toBeGreaterThan(0);
		expect(intropage_e2e_panel_row('alert_host', intropage_e2e_user_id('e2e_other')))->toBeGreaterThan(0);
	});
});

/* ------------------------------------------------------------------ */
/* chart labels                                                        */
/* ------------------------------------------------------------------ */

dataset('hostile template names', [
	'quote breakout'   => ["e2e-',0]];m();//", "e2e-" . chr(92) . 'u0027,0]];m();' . chr(92) . '/' . chr(92) . '/'],
	'script end tag'   => ['e2e-</script><x>', 'e2e-' . chr(92) . 'u003C' . chr(92) . '/script' . chr(92) . 'u003E'],
	'double quote'     => ['e2e-"q"&amp;', 'e2e-' . chr(92) . 'u0022q' . chr(92) . 'u0022' . chr(92) . 'u0026'],
	'backslash'        => ['e2e-a' . chr(92) . 'b', 'e2e-a' . chr(92) . chr(92) . 'b'],
	'cut inside a character' => [str_repeat("\u{20ac}", 8), chr(92) . 'u20ac' . chr(92) . 'ufffd"'],
]);

describe('chart labels', function () {
	it('writes device template names into the chart as JSON strings', function (string $name, string $expected) {
		$client = intropage_e2e_client('e2e_viewer');

		intropage_e2e_exec('INSERT INTO host_template (hash, name) VALUES (?, ?)', [bin2hex(random_bytes(16)), $name]);

		// The panel lists the 20 templates with the most devices, so give this one a device
		$template = (int) intropage_e2e_cell('SELECT MAX(id) FROM host_template WHERE name = ?', [$name]);

		intropage_e2e_exec('INSERT INTO host (host_template_id, description, hostname) VALUES (?, ?, ?)', [$template, 'e2e-device', 'e2e-device.invalid']);

		$row = intropage_e2e_seed_panel('e2e_viewer', 'graph_host_template');

		$reload = $client->get("action=reload&force=true&header=false&panel_id=$row");

		expect($reload['body'])->toContain($expected);
		expect($reload['body'])->not->toContain("['" . substr($name, 0, 20));
		expect($reload['body'])->not->toContain('</script><x>');
	})->with('hostile template names');
});

/* ------------------------------------------------------------------ */
/* console_after and the device scope                                  */
/* ------------------------------------------------------------------ */

describe('dashboard in the console', function () {
	it('renders the dashboard for a console user with the viewer realm', function () {
		intropage_e2e_reset('e2e_console');
		intropage_e2e_set_permissions('e2e_console', ['alert_host']);

		$page = intropage_e2e_client('e2e_console')->request('GET', '/index.php');

		expect($page['body'])->toContain('intropage_main');
		expect($page['body'])->toContain('</html>');
	});

	it('renders the console without a dashboard for a user without the viewer realm', function () {
		$page = intropage_e2e_client('e2e_noplugin')->request('GET', '/index.php');

		expect($page['status'])->toBe(200);
		expect($page['body'])->not->toContain('intropage_main');
		expect($page['body'])->toContain('</html>');
	});
});

describe('device scope', function () {
	it('keeps the hide_disabled preference of a user with restricted devices', function () {
		$user = intropage_e2e_user_id('e2e_viewer');
		$row  = intropage_e2e_seed_panel('e2e_viewer', 'alert_host');

		intropage_e2e_exec('UPDATE user_auth SET policy_hosts = 2 WHERE id = ?', [$user]);
		intropage_e2e_exec('INSERT INTO settings_user (user_id, name, value) VALUES (?, ?, ?)', [$user, 'hide_disabled', 'on']);

		$reload = intropage_e2e_client('e2e_viewer')->get("action=reload&force=true&header=false&panel_id=$row");

		intropage_e2e_exec('UPDATE user_auth SET policy_hosts = 1 WHERE id = ?', [$user]);

		expect($reload['body'])->toContain('ccCardHeader');
		expect(intropage_e2e_cell('SELECT value FROM settings_user WHERE user_id = ? AND name = ?', [$user, 'hide_disabled']))->toBe('on');
	});
});

/* ------------------------------------------------------------------ */
/* poller                                                              */
/* ------------------------------------------------------------------ */

describe('poller', function () {
	it('creates the data row of every system panel that has none', function () {
		$panels = array_column(intropage_e2e_rows('SELECT d.panel_id FROM plugin_intropage_panel_definition AS d INNER JOIN plugin_intropage_panel_data AS p ON p.panel_id = d.panel_id AND p.user_id = 0 WHERE d.level = 0 AND d.requires = ? ORDER BY d.panel_id LIMIT 2', ['']), 'panel_id');

		if (count($panels) < 2) {
			$this->markTestSkipped('Fewer than two dependency-free system panels are registered.');
		}

		foreach ($panels as $panel) {
			intropage_e2e_exec('DELETE FROM plugin_intropage_panel_data WHERE panel_id = ? AND user_id = 0', [$panel]);
		}

		$run = intropage_e2e_poller();

		expect($run['output'])->not->toContain('TypeError');
		expect($run['status'])->toBe(0);

		foreach ($panels as $panel) {
			expect(intropage_e2e_panel_row($panel, 0))->toBeGreaterThan(0);
		}
	});

	it('refreshes a user panel and leaves the system rows owned by user 0', function () {
		$user = intropage_e2e_user_id('e2e_viewer');
		$row  = intropage_e2e_seed_panel('e2e_viewer', 'alert_host');

		intropage_e2e_exec('UPDATE plugin_intropage_panel_data SET last_update = ? WHERE id = ?', ['2000-01-01 00:00:00', $row]);

		$run = intropage_e2e_poller();

		expect($run['status'])->toBe(0);
		expect(intropage_e2e_cell('SELECT last_update FROM plugin_intropage_panel_data WHERE id = ?', [$row]))->not->toBe('2000-01-01 00:00:00');
		expect((int) intropage_e2e_cell('SELECT COUNT(*) FROM plugin_intropage_panel_data WHERE user_id = 0 AND panel_id = ?', ['alert_host']))->toBe(0);
		expect((int) intropage_e2e_cell('SELECT user_id FROM plugin_intropage_panel_data WHERE id = ?', [$row]))->toBe($user);
	});

	it('processes the panel row of a user that no longer exists without failing', function () {
		intropage_e2e_exec('INSERT INTO plugin_intropage_panel_data (panel_id, user_id, priority, alarm, refresh_interval, trend_interval, height, last_update, last_trend_update) VALUES (?, ?, 30, ?, 300, 300, ?, ?, NOW())', ['alert_host', 987654, 'green', 'normal', '2000-01-01 00:00:00']);

		// Cacti logs an authentication error for the missing user on each cycle
		intropage_e2e_expect_log('Invalid Cacti User ID 987654');

		$run = intropage_e2e_poller();

		intropage_e2e_exec('DELETE FROM plugin_intropage_panel_data WHERE user_id = 987654');

		expect($run['output'])->not->toContain('TypeError');
		expect($run['status'])->toBe(0);
	});

	it('does nothing for a panel that is not due', function () {
		$row = intropage_e2e_seed_panel('e2e_viewer', 'alert_host');

		intropage_e2e_exec('UPDATE plugin_intropage_panel_data SET last_update = NOW(), refresh_interval = 86400 WHERE id = ?', [$row]);

		$before = intropage_e2e_cell('SELECT last_update FROM plugin_intropage_panel_data WHERE id = ?', [$row]);

		expect(intropage_e2e_poller()['status'])->toBe(0);
		expect(intropage_e2e_cell('SELECT last_update FROM plugin_intropage_panel_data WHERE id = ?', [$row]))->toBe($before);
	});
});

/* ------------------------------------------------------------------ */
/* user groups                                                         */
/* ------------------------------------------------------------------ */

describe('user groups', function () {
	it('deletes the permission row of a removed group and keeps the others', function () {
		intropage_e2e_exec('DELETE FROM user_auth_group WHERE name LIKE ?', ['e2e-%']);

		foreach (['e2e-doomed', 'e2e-kept'] as $name) {
			intropage_e2e_exec('INSERT INTO user_auth_group (name, description, graph_settings, login_opts, show_tree, show_list, show_preview, policy_graphs, policy_trees, policy_hosts, policy_graph_templates, enabled) VALUES (?, ?, ?, 1, ?, ?, ?, 1, 1, 1, 1, ?)', [$name, $name, 'on', 'on', 'on', 'on', 'on']);
		}

		$doomed = (int) intropage_e2e_cell('SELECT id FROM user_auth_group WHERE name = ?', ['e2e-doomed']);
		$kept   = (int) intropage_e2e_cell('SELECT id FROM user_auth_group WHERE name = ?', ['e2e-kept']);

		foreach ([$doomed, $kept] as $group) {
			intropage_e2e_exec('REPLACE INTO plugin_intropage_user_group_auth (user_group_id, permissions) VALUES (?, ?)', [$group, json_encode(['alert_host' => 'on'])]);
		}

		$result = intropage_e2e_client('e2e_admin')->post('/user_group_admin.php', [
			'action'         => 'actions',
			'drp_action'     => '1',
			'selected_items' => serialize([(string) $doomed]),
		]);

		expect($result['status'])->toBe(302);
		expect((int) intropage_e2e_cell('SELECT COUNT(*) FROM user_auth_group WHERE id = ?', [$doomed]))->toBe(0);
		expect((int) intropage_e2e_cell('SELECT COUNT(*) FROM plugin_intropage_user_group_auth WHERE user_group_id = ?', [$doomed]))->toBe(0);
		expect((int) intropage_e2e_cell('SELECT COUNT(*) FROM plugin_intropage_user_group_auth WHERE user_group_id = ?', [$kept]))->toBe(1);

		intropage_e2e_exec('DELETE FROM plugin_intropage_user_group_auth WHERE user_group_id = ?', [$kept]);
	});

	it('does not let a user without user management delete a group', function () {
		intropage_e2e_exec('INSERT INTO user_auth_group (name, description, graph_settings, login_opts, show_tree, show_list, show_preview, policy_graphs, policy_trees, policy_hosts, policy_graph_templates, enabled) VALUES (?, ?, ?, 1, ?, ?, ?, 1, 1, 1, 1, ?)', ['e2e-safe', 'e2e-safe', 'on', 'on', 'on', 'on', 'on']);

		$group = (int) intropage_e2e_cell('SELECT id FROM user_auth_group WHERE name = ?', ['e2e-safe']);

		intropage_e2e_client('e2e_viewer')->post('/user_group_admin.php', ['action' => 'actions', 'drp_action' => '1', 'selected_items' => serialize([(string) $group])]);

		expect((int) intropage_e2e_cell('SELECT COUNT(*) FROM user_auth_group WHERE id = ?', [$group]))->toBe(1);
	});
});

/* ------------------------------------------------------------------ */
/* favourite graphs                                                    */
/* ------------------------------------------------------------------ */

describe('favourite graphs', function () {
	afterEach(function () {
		if (intropage_e2e_config() !== null) {
			intropage_e2e_drop_graphs();
		}
	});

	it('stores a favourite graph and renders it with its title', function () {
		[, $graph] = intropage_e2e_seed_graph('e2e favourite graph');

		$client = intropage_e2e_client('e2e_viewer');
		$user   = intropage_e2e_user_id('e2e_viewer');

		// The priority of a new favourite is the highest existing one plus one, so the user needs a panel already
		intropage_e2e_seed_panel('e2e_viewer', 'alert_host');

		// Cacti's graph pages normally keep the timespan in the session; without it the plugin falls back to its default
		intropage_e2e_expect_log('sess_current_timespan');

		$client->post(INTROPAGE_E2E_PATH, ['intropage_action' => 'favgraph', 'graph_id' => (string) $graph, 'header' => 'false']);

		$row = (int) intropage_e2e_cell('SELECT id FROM plugin_intropage_panel_data WHERE user_id = ? AND fav_graph_id = ?', [$user, $graph]);

		expect($row)->toBeGreaterThan(0);
		expect((int) intropage_e2e_cell('SELECT COUNT(*) FROM plugin_intropage_panel_dashboard WHERE user_id = ? AND panel_id = ?', [$user, $row]))->toBe(1);

		$reload = $client->get("action=reload&header=false&panel_id=$row");

		expect($reload['body'])->toContain('graph_image.php?local_graph_id=' . $graph);
		expect($reload['body'])->toContain('e2e favourite graph');
	});

	it('does not store a favourite graph for a user without the favourite graph permission', function () {
		[, $graph] = intropage_e2e_seed_graph('e2e favourite graph');

		intropage_e2e_seed_panel('e2e_viewer', 'alert_host');
		intropage_e2e_set_permissions('e2e_viewer', ['alert_host']);
		intropage_e2e_expect_log('sess_current_timespan');

		$user = intropage_e2e_user_id('e2e_viewer');

		intropage_e2e_client('e2e_viewer')->post(INTROPAGE_E2E_PATH, ['intropage_action' => 'favgraph', 'graph_id' => (string) $graph, 'header' => 'false']);

		$row = (int) intropage_e2e_cell('SELECT id FROM plugin_intropage_panel_data WHERE user_id = ? AND fav_graph_id = ?', [$user, $graph]);

		// The row is stored, but the dashboard drops it as a withdrawn panel when it renders
		expect((int) intropage_e2e_cell('SELECT COUNT(*) FROM plugin_intropage_panel_dashboard WHERE user_id = ? AND panel_id = ?', [$user, $row]))->toBe(0);

		intropage_e2e_expect_log('One or more panels was removed');
	});
});

/**
 * Runs the graph_buttons hook through Cacti for a user and returns the output.
 */
function intropage_e2e_graph_hook(int $user, int $graph, ?int $timespan = null): string {
	$config = intropage_e2e_config();
	$php    = escapeshellarg(PHP_BINARY);
	$env    = 'INTROPAGE_E2E_CACTI=' . escapeshellarg($config['cacti']) . ' INTROPAGE_E2E_HOOK_USER=' . $user . ' INTROPAGE_E2E_HOOK_GRAPH=' . $graph;

	if ($timespan !== null) {
		$env .= ' INTROPAGE_E2E_HOOK_TIMESPAN=' . $timespan;
	}

	if ($config['cov'] !== '') {
		$env .= ' INTROPAGE_COVERAGE_DIR=' . escapeshellarg($config['cov']);
		$php .= ' -d memory_limit=1G -d xdebug.mode=coverage -d auto_prepend_file=' . escapeshellarg(__DIR__ . '/coverage-prepend.php');
	}

	return (string) shell_exec($env . ' ' . $php . ' ' . escapeshellarg(__DIR__ . '/hook-runner.php') . ' 2>&1');
}

describe('graph button hook', function () {
	afterEach(function () {
		if (intropage_e2e_config() !== null) {
			intropage_e2e_drop_graphs();
		}
	});

	it('adds a button that posts, and its script only once', function () {
		[, $graph] = intropage_e2e_seed_graph('e2e hook graph');

		$output = intropage_e2e_graph_hook(intropage_e2e_user_id('e2e_viewer'), $graph, 5);
		$calls  = explode('--- call 2', $output);

		expect($calls[0])->toContain('class="iconLink intropage_favgraph"');
		expect($calls[0])->toContain('data-graph="' . $graph . '"');
		expect($calls[0])->toContain('title="Add to Dashboard"');
		expect($calls[0])->not->toContain('intropage_action=favgraph');
		expect(substr_count($calls[0], '<script'))->toBe(1);
		expect($calls[1])->toContain('data-graph="' . $graph . '"');
		expect($calls[1])->not->toContain('<script');
	});

	it('offers to remove a graph that is already a favourite', function () {
		[, $graph] = intropage_e2e_seed_graph('e2e hook graph');

		$user = intropage_e2e_user_id('e2e_viewer');

		intropage_e2e_exec('INSERT INTO plugin_intropage_panel_data (panel_id, user_id, priority, alarm, refresh_interval, trend_interval, height, fav_graph_id, fav_graph_timespan, last_update, last_trend_update) VALUES (?, ?, 30, ?, 300, 300, ?, ?, 5, NOW(), NOW())', ['favourite_graph', $user, 'green', 'normal', $graph]);

		expect(intropage_e2e_graph_hook($user, $graph, 5))->toContain('title="Remove from Dashboard"');
	});

	it('explains that a custom timespan cannot be added', function () {
		[, $graph] = intropage_e2e_seed_graph('e2e hook graph');

		$output = intropage_e2e_graph_hook(intropage_e2e_user_id('e2e_viewer'), $graph, 0);

		expect($output)->toContain('Cannot add to Dashboard. Custom timespan.');
	});

	it('adds no button for a user without the favourite graph permission', function () {
		[, $graph] = intropage_e2e_seed_graph('e2e hook graph');

		intropage_e2e_set_permissions('e2e_viewer', ['alert_host']);

		$output = intropage_e2e_graph_hook(intropage_e2e_user_id('e2e_viewer'), $graph, 5);

		expect($output)->not->toContain('intropage_favgraph');
		expect($output)->not->toContain('<script');
	});

	it('does nothing without a numeric graph id', function () {
		$config = intropage_e2e_config();

		$output = (string) shell_exec('INTROPAGE_E2E_CACTI=' . escapeshellarg($config['cacti']) . ' INTROPAGE_E2E_HOOK_USER=1 INTROPAGE_E2E_HOOK_GRAPH=abc ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/hook-runner.php') . ' 2>&1');

		expect($output)->toBe('');
	});
});

/* ------------------------------------------------------------------ */
/* threshold events with several users                                 */
/* ------------------------------------------------------------------ */

describe('threshold events', function () {
	it('lists an event once however many users hold a matching permission', function () {
		require_once __DIR__ . '/../../includes/functions.php';
		require_once __DIR__ . '/../../panellib/thold.php';

		$ddl = [
			'thold_data'       => 'CREATE TABLE thold_data (id INT AUTO_INCREMENT PRIMARY KEY, local_graph_id INT NOT NULL DEFAULT 0, host_id INT NOT NULL DEFAULT 0)',
			'plugin_thold_log' => 'CREATE TABLE plugin_thold_log (id INT AUTO_INCREMENT PRIMARY KEY, threshold_id INT NOT NULL DEFAULT 0, description VARCHAR(255) NOT NULL DEFAULT \'\', time INT NOT NULL DEFAULT 0, status INT NOT NULL DEFAULT 0)',
		];

		foreach (array_keys($ddl) as $table) {
			if ((int) intropage_e2e_cell('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', [$table]) > 0) {
				$this->markTestSkipped('The Thold tables exist on this installation; not touching them.');
			}
		}

		foreach ($ddl as $create) {
			intropage_e2e_db()->exec($create);
		}

		try {
			[$host, $graph] = intropage_e2e_seed_graph('e2e threshold graph');

			intropage_e2e_exec('INSERT INTO thold_data (local_graph_id, host_id) VALUES (?, ?)', [$graph, $host]);

			$threshold = (int) intropage_e2e_cell('SELECT MAX(id) FROM thold_data');

			intropage_e2e_exec('INSERT INTO plugin_thold_log (threshold_id, description, time, status) VALUES (?, ?, ?, 1)', [$threshold, 'e2e threshold ALERT', time()]);

			foreach (['e2e_viewer', 'e2e_other', 'e2e_admin'] as $user) {
				intropage_e2e_exec('INSERT INTO user_auth_perms (user_id, item_id, type) VALUES (?, ?, 3)', [intropage_e2e_user_id($user), $host]);
			}

			// Run the panel's own query with the stubbed database layer of the unit bootstrap, then on the real database
			$GLOBALS['__test_enabled_plugins'] = ['thold' => true];
			$GLOBALS['__test_simple_perms']    = true;
			$GLOBALS['__test_events']          = [];
			$GLOBALS['__test_query']           = function ($kind, $sql, $params) {
				if (strpos($sql, 'FROM plugin_thold_log') !== false) {
					$GLOBALS['__test_events'][] = ['sql' => $sql, 'params' => $params];
				}

				return $kind === 'assoc' ? [] : '';
			};

			$restore = $GLOBALS['config']['base_path'];

			$GLOBALS['config']['base_path'] = sys_get_temp_dir() . '/intropage-thold-e2e-' . bin2hex(random_bytes(6));

			$_SESSION['sess_user_id'] = intropage_e2e_user_id('e2e_viewer');

			thold_event(['id' => 1, 'height' => 'normal', 'data' => '', 'alarm' => 'green'], intropage_e2e_user_id('e2e_viewer'));
			thold_event_detail();

			$GLOBALS['config']['base_path'] = $restore;

			unset($GLOBALS['__test_query'], $GLOBALS['__test_enabled_plugins'], $GLOBALS['__test_simple_perms']);

			expect($GLOBALS['__test_events'])->toHaveCount(2);

			foreach ($GLOBALS['__test_events'] as $event) {
				expect(intropage_e2e_rows($event['sql'], $event['params']))->toHaveCount(1);

				// Without the user condition the same query returns the row once per user holding a permission
				$unfixed = preg_replace('/ AND uap\d\.user_id = \?/', '', $event['sql']);

				expect(intropage_e2e_rows($unfixed))->toHaveCount(3);
			}
		} finally {
			intropage_e2e_exec('DELETE FROM user_auth_perms WHERE type = 3 AND user_id IN (SELECT id FROM user_auth WHERE username LIKE ?)', ['e2e_%']);
			intropage_e2e_drop_graphs();
			intropage_e2e_db()->exec('DROP TABLE plugin_thold_log');
			intropage_e2e_db()->exec('DROP TABLE thold_data');
		}
	});
});

/* ------------------------------------------------------------------ */
/* upgrade from an old version                                         */
/* ------------------------------------------------------------------ */

/**
 * Rewrites the plugin tables into their 3.0.0 shape, with per-panel columns
 * in the user authorisation table, and stores the old version.
 */
function intropage_e2e_make_legacy_schema(int $user, int $other): void {
	foreach ([
		'ALTER TABLE plugin_intropage_user_auth DROP COLUMN permissions, ADD COLUMN alert_host CHAR(2) NOT NULL DEFAULT \'on\', ADD COLUMN retired_panel CHAR(2) NOT NULL DEFAULT \'on\'',
		'ALTER TABLE plugin_intropage_dashboard DROP COLUMN shared',
		'ALTER TABLE plugin_intropage_panel_data DROP COLUMN height, DROP COLUMN trend_interval, DROP COLUMN last_trend_update',
		'ALTER TABLE plugin_intropage_panel_definition DROP COLUMN height, DROP COLUMN trefresh',
		'ALTER TABLE plugin_intropage_panel_dashboard DROP PRIMARY KEY',
		'DROP TABLE plugin_intropage_user_group_auth',
	] as $alter) {
		intropage_e2e_exec($alter);
	}

	intropage_e2e_exec('DELETE FROM plugin_intropage_user_auth');
	intropage_e2e_exec('INSERT INTO plugin_intropage_user_auth (user_id, login_opts, alert_host, retired_panel) VALUES (?, 0, ?, ?)', [$user, 'on', 'on']);
	intropage_e2e_exec('INSERT INTO plugin_intropage_user_auth (user_id, login_opts, alert_host, retired_panel) VALUES (?, 0, ?, ?)', [$other, '', 'on']);
	intropage_e2e_exec('UPDATE plugin_config SET version = ? WHERE directory = ?', ['3.0.0', 'intropage']);
}

/**
 * Builds a throwaway copy of the Cacti root for the upgrade runner: every
 * entry is a link to the real one, except plugins/intropage, which holds only
 * the files the upgrade reads or prunes (the library and panel directories
 * are links). Cacti's own plugin code resolves paths through base_path, so
 * the core directories have to be reachable below the copy.
 */
function intropage_e2e_throwaway_tree(string $cacti): string {
	$tree = sys_get_temp_dir() . '/intropage-upgrade-e2e-' . bin2hex(random_bytes(6));

	mkdir($tree . '/plugins/intropage', 0700, true);

	foreach (array_diff(scandir($cacti), ['.', '..', 'plugins']) as $entry) {
		symlink($cacti . '/' . $entry, $tree . '/' . $entry);
	}

	foreach (array_diff(scandir($cacti . '/plugins'), ['.', '..', 'intropage']) as $entry) {
		symlink($cacti . '/plugins/' . $entry, $tree . '/plugins/' . $entry);
	}

	$real = $cacti . '/plugins/intropage';

	copy($real . '/INFO', $tree . '/plugins/intropage/INFO');
	symlink($real . '/includes', $tree . '/plugins/intropage/includes');
	symlink($real . '/panellib', $tree . '/plugins/intropage/panellib');
	symlink($real . '/setup.php', $tree . '/plugins/intropage/setup.php');
	file_put_contents($tree . '/plugins/intropage/manifest.json', json_encode(['tombstones' => [], 'expected' => ['INFO', 'includes/', 'manifest.json', 'panellib/', 'setup.php'], 'whitelist' => []]));

	return $tree;
}

describe('upgrade', function () {
	it('converts a 3.0.0 installation and restores the current schema', function () {
		require_once __DIR__ . '/../../setup.php';

		$config  = intropage_e2e_config();
		$viewer  = intropage_e2e_user_id('e2e_viewer');
		$other   = intropage_e2e_user_id('e2e_other');
		$current = (string) parse_ini_file(dirname(__DIR__, 2) . '/INFO', true)['info']['version'];
		$plugin  = dirname(__DIR__, 2);
		$tree    = intropage_e2e_throwaway_tree($config['cacti']);

		intropage_e2e_make_legacy_schema($viewer, $other);

		$php = escapeshellarg(PHP_BINARY);
		$env = 'INTROPAGE_E2E_CACTI=' . escapeshellarg($config['cacti']) . ' INTROPAGE_E2E_UPGRADE_BASE=' . escapeshellarg($tree);

		if ($config['cov'] !== '') {
			$env .= ' INTROPAGE_COVERAGE_DIR=' . escapeshellarg($config['cov']);
			$php .= ' -d memory_limit=1G -d xdebug.mode=coverage -d auto_prepend_file=' . escapeshellarg(__DIR__ . '/coverage-prepend.php');
		}

		$output = [];
		$status = 1;

		exec($env . ' ' . $php . ' ' . escapeshellarg(__DIR__ . '/upgrade-runner.php') . ' 2>&1', $output, $status);

		// The setup.php helper removes links without following them
		plugin_intropage_rmtree($tree);

		expect(implode("\n", $output))->toContain('upgrade finished');
		expect($status)->toBe(0);
		expect(intropage_e2e_cell('SELECT version FROM plugin_config WHERE directory = ?', ['intropage']))->toBe($current);

		$columns = array_column(intropage_e2e_rows('SHOW COLUMNS FROM plugin_intropage_user_auth'), 'Field');

		sort($columns);

		expect($columns)->toBe(['login_opts', 'permissions', 'user_id']);

		$first  = json_decode((string) intropage_e2e_cell('SELECT permissions FROM plugin_intropage_user_auth WHERE user_id = ?', [$viewer]), true);
		$second = json_decode((string) intropage_e2e_cell('SELECT permissions FROM plugin_intropage_user_auth WHERE user_id = ?', [$other]), true);

		expect($first)->toHaveKey('alert_host', 'on');
		expect($first)->toHaveKey('favourite_graph', 'on');
		expect($first)->not->toHaveKey('retired_panel');
		expect($first)->not->toHaveKey('user_id');
		expect($second)->toHaveKey('favourite_graph', 'on');
		expect($second['alert_host'] ?? '')->toBe('');

		foreach ([
			['plugin_intropage_dashboard', 'shared'],
			['plugin_intropage_panel_data', 'height'],
			['plugin_intropage_panel_data', 'trend_interval'],
			['plugin_intropage_panel_definition', 'height'],
			['plugin_intropage_panel_definition', 'trefresh'],
		] as [$table, $column]) {
			expect((int) intropage_e2e_cell('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?', [$table, $column]))->toBe(1);
		}

		expect((int) intropage_e2e_cell('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', ['plugin_intropage_user_group_auth']))->toBe(1);

		// The real plugin tree is untouched
		expect(is_dir($plugin . '/tests'))->toBeTrue();

		// Put the permissions column back the way a fresh install creates it
		intropage_e2e_exec('ALTER TABLE plugin_intropage_user_auth MODIFY permissions BLOB NOT NULL DEFAULT \'\'');
		intropage_e2e_exec('REPLACE INTO plugin_intropage_user_auth (user_id, permissions) VALUES (?, ?)', [$viewer, json_encode(['alert_host' => 'on'])]);
	});

	it('refuses to run against a tree that is not a throwaway copy', function () {
		$config = intropage_e2e_config();
		$output = [];
		$status = 0;

		exec('INTROPAGE_E2E_CACTI=' . escapeshellarg($config['cacti']) . ' INTROPAGE_E2E_UPGRADE_BASE=' . escapeshellarg($config['cacti']) . ' ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/upgrade-runner.php') . ' 2>&1', $output, $status);

		expect($status)->toBe(1);
		expect(implode("\n", $output))->toContain('refusing to run');
	});

});
