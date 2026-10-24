<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * End-to-end coverage against a running Cacti with this plugin installed:
 * real login, real CSRF token, real requests to intropage.php, and the real
 * database. It needs a web server and the Cacti database, so it is skipped
 * unless these environment variables are set:
 *
 *   INTROPAGE_E2E_URL      base URL of Cacti, no trailing slash,
 *                          e.g. http://127.0.0.1:8080 or http://host/cacti
 *   INTROPAGE_E2E_DSN      PDO DSN of the Cacti database
 *   INTROPAGE_E2E_DB_USER  database user
 *   INTROPAGE_E2E_DB_PASS  database password
 *   INTROPAGE_E2E_CACTI    Cacti root directory (for the poller script);
 *                          defaults to the directory two levels above this
 *                          plugin
 *
 * The test creates one local user (e2e_viewer) with only the Intropage
 * Viewer realm and removes it again. Do not run it against a production
 * installation.
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

const INTROPAGE_E2E_USER = 'e2e_viewer';
const INTROPAGE_E2E_PASS = 'e2e-pass-1';
const INTROPAGE_E2E_PATH = '/plugins/intropage/intropage.php';

/**
 * Reads the environment configuration for the end-to-end tests.
 *
 * @return array|null The settings, or null when the stack is not configured.
 */
function intropage_e2e_config(): ?array {
	$url = getenv('INTROPAGE_E2E_URL');
	$dsn = getenv('INTROPAGE_E2E_DSN');

	if ($url === false || $url === '' || $dsn === false || $dsn === '') {
		return null;
	}

	return [
		'url'   => rtrim($url, '/'),
		'dsn'   => $dsn,
		'user'  => (string) getenv('INTROPAGE_E2E_DB_USER'),
		'pass'  => (string) getenv('INTROPAGE_E2E_DB_PASS'),
		'cacti' => getenv('INTROPAGE_E2E_CACTI') ?: dirname(__DIR__, 3),
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
	 * @param array<string, string> $fields
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
	public function token(): string {
		$page = $this->request('GET', INTROPAGE_E2E_PATH);

		preg_match('/csrfMagicToken\s*=\s*[\'"]([^\'"]+)[\'"]/', $page['body'], $m);

		return $m[1] ?? '';
	}

	/**
	 * @param array<string, string> $fields
	 *
	 * @return array{status: int, location: string, body: string}
	 */
	public function post(string $path, array $fields): array {
		$fields['__csrf_magic'] = $this->token();

		return $this->request('POST', $path, $fields);
	}
}

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

function intropage_e2e_exec(string $sql, array $params = []): void {
	intropage_e2e_db()->prepare($sql)->execute($params);
}

function intropage_e2e_user_id(): int {
	return (int) intropage_e2e_cell('SELECT id FROM user_auth WHERE username = ?', [INTROPAGE_E2E_USER]);
}

function intropage_e2e_set_permissions(array $panels): void {
	$permissions = [];

	foreach ($panels as $panel) {
		$permissions[$panel] = 'on';
	}

	intropage_e2e_exec('REPLACE INTO plugin_intropage_user_auth (user_id, permissions) VALUES (?, ?)', [intropage_e2e_user_id(), json_encode($permissions)]);
}

/**
 * Removes the test user's dashboards and panel rows between tests.
 */
function intropage_e2e_reset(): void {
	$id = intropage_e2e_user_id();

	intropage_e2e_exec('DELETE FROM plugin_intropage_panel_dashboard WHERE user_id = ?', [$id]);
	intropage_e2e_exec('DELETE FROM plugin_intropage_panel_data WHERE user_id = ?', [$id]);
	intropage_e2e_exec('DELETE FROM plugin_intropage_dashboard WHERE user_id = ?', [$id]);
	intropage_e2e_exec('INSERT INTO plugin_intropage_dashboard (user_id, dashboard_id, name, shared) VALUES (?, 1, ?, 0)', [$id, 'Default']);
}

/**
 * Returns the size of cacti.log, or null when it is missing or not writable
 * by this process. The test that provokes a logged warning on purpose uses
 * it to put the log back, because the CI job fails on any warning left in it.
 */
function intropage_e2e_log_size(): ?int {
	$log = intropage_e2e_config()['cacti'] . '/log/cacti.log';

	return (is_file($log) && is_writable($log)) ? (int) filesize($log) : null;
}

function intropage_e2e_log_since(int $size): string {
	$log = intropage_e2e_config()['cacti'] . '/log/cacti.log';

	return (string) file_get_contents($log, false, null, $size);
}

function intropage_e2e_log_restore(int $size): void {
	$log    = intropage_e2e_config()['cacti'] . '/log/cacti.log';
	$handle = fopen($log, 'r+');

	if ($handle !== false) {
		ftruncate($handle, $size);
		fclose($handle);
	}
}

function intropage_e2e_panel_row(string $panel_id, int $user_id): int {
	return (int) intropage_e2e_cell('SELECT id FROM plugin_intropage_panel_data WHERE panel_id = ? AND user_id = ?', [$panel_id, $user_id]);
}

beforeAll(function () {
	if (intropage_e2e_config() === null) {
		return;
	}

	$config = intropage_e2e_config();

	intropage_e2e_exec('DELETE FROM user_auth WHERE username = ?', [INTROPAGE_E2E_USER]);
	intropage_e2e_exec('INSERT INTO user_auth (username, password, realm, full_name, must_change_password, enabled, login_opts) VALUES (?, MD5(?), 0, ?, ?, ?, 4)', [INTROPAGE_E2E_USER, INTROPAGE_E2E_PASS, 'E2E Viewer', '', 'on']);

	$realm = 100 + (int) intropage_e2e_cell("SELECT id FROM plugin_realms WHERE file = 'intropage.php'");

	intropage_e2e_exec('INSERT INTO user_auth_realm (user_id, realm_id) VALUES (?, ?)', [intropage_e2e_user_id(), $realm]);

	$GLOBALS['__intropage_e2e_client'] = new IntropageE2eClient($config['url']);
	$GLOBALS['__intropage_e2e_ready']  = $GLOBALS['__intropage_e2e_client']->login(INTROPAGE_E2E_USER, INTROPAGE_E2E_PASS);
});

afterAll(function () {
	if (intropage_e2e_config() === null) {
		return;
	}

	$id = intropage_e2e_user_id();

	if ($id > 0) {
		intropage_e2e_reset();

		intropage_e2e_exec('DELETE FROM plugin_intropage_dashboard WHERE user_id = ?', [$id]);
		intropage_e2e_exec('DELETE FROM plugin_intropage_user_auth WHERE user_id = ?', [$id]);
		intropage_e2e_exec('DELETE FROM user_auth_realm WHERE user_id = ?', [$id]);
		intropage_e2e_exec('DELETE FROM user_auth WHERE id = ?', [$id]);
	}

	intropage_e2e_exec("DELETE FROM host_template WHERE name LIKE 'e2e-label%'");
});

beforeEach(function () {
	if (intropage_e2e_config() === null) {
		$this->markTestSkipped('Set INTROPAGE_E2E_URL and INTROPAGE_E2E_DSN to run the end-to-end tests.');
	}

	expect($GLOBALS['__intropage_e2e_ready'])->toBeTrue('login as the end-to-end user failed');

	intropage_e2e_reset();
	intropage_e2e_set_permissions(['alert_host', 'graph_host_template', 'favourite_graph']);
});

it('refuses to reload or describe a system panel the user was not granted', function () {
	$client = $GLOBALS['__intropage_e2e_client'];
	$row    = intropage_e2e_panel_row('analyse_db', 0);

	if ($row === 0) {
		$this->markTestSkipped('analyse_db is not registered on this installation.');
	}

	$reload = $client->request('GET', INTROPAGE_E2E_PATH . "?action=reload&force=true&header=false&panel_id=$row");
	$detail = $client->request('GET', INTROPAGE_E2E_PATH . "?action=details&header=false&panel_id=$row");

	expect(trim($reload['body']))->toBe('Panel not found');
	expect(trim($detail['body']))->toBe('Panel Not Found');
});

it('does not add a panel the user was not granted', function () {
	$client = $GLOBALS['__intropage_e2e_client'];
	$user   = intropage_e2e_user_id();
	$row    = intropage_e2e_panel_row('analyse_db', 0);

	// The dashboard render that follows a POST drops links to withdrawn
	// panels again, so the lasting effect of a refused add is that no panel
	// data row is created for it.
	$before = (int) intropage_e2e_cell('SELECT COUNT(*) FROM plugin_intropage_panel_data WHERE panel_id = ?', ['analyse_db']);

	$client->post(INTROPAGE_E2E_PATH, ['intropage_addpanel' => 'analyse_db', 'dashboard_id' => '1', 'header' => 'false']);

	if ($row > 0) {
		$client->post(INTROPAGE_E2E_PATH, ['intropage_addpanel' => (string) $row, 'dashboard_id' => '1', 'header' => 'false']);
	}

	expect((int) intropage_e2e_cell('SELECT COUNT(*) FROM plugin_intropage_panel_data WHERE panel_id = ?', ['analyse_db']))->toBe($before);
	expect((int) intropage_e2e_cell('SELECT COUNT(*) FROM plugin_intropage_panel_dashboard WHERE user_id = ?', [$user]))->toBe(0);
});

it('adds and reloads a panel the user was granted', function () {
	$client = $GLOBALS['__intropage_e2e_client'];
	$user   = intropage_e2e_user_id();

	$client->post(INTROPAGE_E2E_PATH, ['intropage_addpanel' => 'alert_host', 'dashboard_id' => '1', 'header' => 'false']);

	$row = intropage_e2e_panel_row('alert_host', $user);

	expect($row)->toBeGreaterThan(0);
	expect((int) intropage_e2e_cell('SELECT COUNT(*) FROM plugin_intropage_panel_dashboard WHERE user_id = ? AND panel_id = ? AND dashboard_id = 1', [$user, $row]))->toBe(1);

	$reload = $client->request('GET', INTROPAGE_E2E_PATH . "?action=reload&force=true&header=false&panel_id=$row");

	expect($reload['body'])->toContain('ccCardHeader');
});

it('ignores dashboard changes sent as GET and applies them as POST', function () {
	$client = $GLOBALS['__intropage_e2e_client'];
	$user   = intropage_e2e_user_id();

	intropage_e2e_exec('INSERT INTO plugin_intropage_dashboard (user_id, dashboard_id, name, shared) VALUES (?, 2, ?, 0)', [$user, 'Second']);

	$client->request('GET', INTROPAGE_E2E_PATH . '?header=false&intropage_action=removepage_2');
	$client->request('GET', INTROPAGE_E2E_PATH . '?header=false&intropage_action=share');

	expect((int) intropage_e2e_cell('SELECT COUNT(*) FROM plugin_intropage_dashboard WHERE user_id = ? AND dashboard_id = 2', [$user]))->toBe(1);
	expect((int) intropage_e2e_cell('SELECT SUM(shared) FROM plugin_intropage_dashboard WHERE user_id = ?', [$user]))->toBe(0);

	$client->post(INTROPAGE_E2E_PATH, ['header' => 'false', 'intropage_action' => 'removepage_2']);

	expect((int) intropage_e2e_cell('SELECT COUNT(*) FROM plugin_intropage_dashboard WHERE user_id = ? AND dashboard_id = 2', [$user]))->toBe(0);
});

it('keeps the shared flag when dashboards are renamed', function () {
	$client = $GLOBALS['__intropage_e2e_client'];
	$user   = intropage_e2e_user_id();

	intropage_e2e_exec('UPDATE plugin_intropage_dashboard SET shared = 1 WHERE user_id = ? AND dashboard_id = 1', [$user]);

	$client->post(INTROPAGE_E2E_PATH, ['header' => 'false', 'action' => 'save', 'save_settings' => '1', 'name_1' => 'Renamed', 'name_9' => 'Never created']);

	expect(intropage_e2e_cell('SELECT name FROM plugin_intropage_dashboard WHERE user_id = ? AND dashboard_id = 1', [$user]))->toBe('Renamed');
	expect((int) intropage_e2e_cell('SELECT shared FROM plugin_intropage_dashboard WHERE user_id = ? AND dashboard_id = 1', [$user]))->toBe(1);
	expect((int) intropage_e2e_cell('SELECT COUNT(*) FROM plugin_intropage_dashboard WHERE user_id = ? AND dashboard_id = 9', [$user]))->toBe(0);
});

it('writes device template names into the chart as JSON strings', function () {
	$client = $GLOBALS['__intropage_e2e_client'];
	$user   = intropage_e2e_user_id();
	$name   = "e2e-label',0]];x();//";

	intropage_e2e_exec('INSERT INTO host_template (hash, name) VALUES (?, ?)', [md5(uniqid('', true)), substr($name, 0, 20)]);

	$client->post(INTROPAGE_E2E_PATH, ['intropage_addpanel' => 'graph_host_template', 'dashboard_id' => '1', 'header' => 'false']);

	$row = intropage_e2e_panel_row('graph_host_template', $user);

	expect($row)->toBeGreaterThan(0);

	$reload = $client->request('GET', INTROPAGE_E2E_PATH . "?action=reload&force=true&header=false&panel_id=$row");

	expect($reload['body'])->toContain('e2e-label\u0027,0]];x');
	expect($reload['body'])->not->toContain("e2e-label',0]]");
});

it('removes panels from the dashboard when their permission is withdrawn', function () {
	$client = $GLOBALS['__intropage_e2e_client'];
	$user   = intropage_e2e_user_id();

	$client->post(INTROPAGE_E2E_PATH, ['intropage_addpanel' => 'alert_host', 'dashboard_id' => '1', 'header' => 'false']);

	$row = intropage_e2e_panel_row('alert_host', $user);

	expect($row)->toBeGreaterThan(0);

	intropage_e2e_set_permissions(['favourite_graph']);

	$mark = intropage_e2e_log_size();

	$client->request('GET', INTROPAGE_E2E_PATH);

	expect(intropage_e2e_panel_row('alert_host', $user))->toBe(0);
	expect((int) intropage_e2e_cell('SELECT COUNT(*) FROM plugin_intropage_panel_dashboard WHERE user_id = ?', [$user]))->toBe(0);

	// The cleanup logs a warning for the user on purpose; check it, then put
	// cacti.log back so the CI log scan does not trip over it.
	if ($mark !== null) {
		expect(intropage_e2e_log_since($mark))->toContain('One or more panels was removed');

		intropage_e2e_log_restore($mark);
	}
});

it('lets the poller create the data row of a system panel that has none', function () {
	$config = intropage_e2e_config();
	$panel  = intropage_e2e_cell('SELECT d.panel_id FROM plugin_intropage_panel_definition AS d INNER JOIN plugin_intropage_panel_data AS p ON p.panel_id = d.panel_id AND p.user_id = 0 WHERE d.level = 0 AND d.requires = ? ORDER BY d.panel_id LIMIT 1', ['']);

	if (!$panel) {
		$this->markTestSkipped('No dependency-free system panel is registered.');
	}

	intropage_e2e_exec('DELETE FROM plugin_intropage_panel_data WHERE panel_id = ? AND user_id = 0', [$panel]);

	$script = escapeshellarg($config['cacti'] . '/plugins/intropage/poller_intropage.php');
	$output = [];
	$status = 0;

	exec(escapeshellarg(PHP_BINARY) . ' -q ' . $script . ' 2>&1', $output, $status);

	$text = implode("\n", $output);

	expect($text)->not->toContain('TypeError');
	expect($status)->toBe(0);
	expect(intropage_e2e_panel_row($panel, 0))->toBeGreaterThan(0);
});
