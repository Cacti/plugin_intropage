<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * The NTP check must work for IPv4 and IPv6 servers, given as an address,
 * an address in brackets or a name. Name lookups are replaced by a closure;
 * the network part talks to a UDP server that a child PHP process runs on
 * the loopback address, so no external host is needed.
 */

require_once __DIR__ . '/../../includes/functions.php';

const INTROPAGE_NTP_OFFSET = 2208988800;

/**
 * Starts a one-shot UDP server on a loopback address in a child process.
 *
 * @param string $bind    udp:// address to bind, port 0 picks a free one
 * @param string $mode    reply, short or silent
 * @param int    $seconds Unix time the reply carries
 *
 * @return array|null The process handle, its pipes, the port and the script
 *                    path, or null when the address cannot be bound here.
 */
function intropage_ntp_server(string $bind, string $mode, int $seconds): ?array {
	$script = sys_get_temp_dir() . '/intropage-ntp-' . bin2hex(random_bytes(6)) . '.php';

	file_put_contents($script, <<<'PHP'
<?php
[, $bind, $mode, $seconds] = $argv;

$server = @stream_socket_server($bind, $errno, $errstr, STREAM_SERVER_BIND);

if ($server === false) {
	print "unavailable\n";

	exit(0);
}

print substr(strrchr(stream_socket_get_name($server, false), ':'), 1) . "\n";
fflush(STDOUT);

stream_socket_recvfrom($server, 512, 0, $peer);

if ($mode === 'reply') {
	stream_socket_sendto($server, str_repeat("\0", 32) . pack('N', (int) $seconds) . str_repeat("\0", 12), 0, $peer);
} elseif ($mode === 'short') {
	stream_socket_sendto($server, 'short', 0, $peer);
} else {
	sleep(3);
}
PHP);

	$process = proc_open([PHP_BINARY, $script, $bind, $mode, (string) $seconds], [1 => ['pipe', 'w']], $pipes);
	$line    = trim((string) fgets($pipes[1]));

	if (!is_numeric($line)) {
		proc_terminate($process);
		proc_close($process);
		unlink($script);

		return null;
	}

	return ['process' => $process, 'pipes' => $pipes, 'port' => (int) $line, 'script' => $script];
}

function intropage_ntp_server_stop(array $server): void {
	proc_terminate($server['process']);
	proc_close($server['process']);
	unlink($server['script']);
}

describe('intropage_ntp_record_addresses', function () {
	it('reads the address from A and AAAA records', function () {
		$records = [
			['host' => 'ntp.test', 'type' => 'A', 'ip' => '192.0.2.10'],
			['host' => 'ntp.test', 'type' => 'AAAA', 'ipv6' => '2001:db8::10'],
		];

		expect(intropage_ntp_record_addresses($records))->toBe(['192.0.2.10', '2001:db8::10']);
	});

	it('skips records without a valid address', function () {
		expect(intropage_ntp_record_addresses([['type' => 'CNAME', 'target' => 'a.test'], ['ip' => 'not-an-ip'], []]))->toBe([]);
	});
});

describe('intropage_ntp_addresses', function () {
	it('uses an address as it is', function (string $setting, string $expected) {
		$lookup = static function (string $host): array {
			throw new RuntimeException("no lookup expected for $host");
		};

		expect(intropage_ntp_addresses($setting, $lookup))->toBe([$expected]);
	})->with([
		'IPv4'                  => ['192.0.2.7', '192.0.2.7'],
		'IPv4 with spaces'      => ['  192.0.2.7 ', '192.0.2.7'],
		'IPv6'                  => ['2001:db8:0:0:0:0:0:1', '2001:db8:0:0:0:0:0:1'],
		'IPv6 compressed'       => ['2001:db8::1', '2001:db8::1'],
		'IPv6 loopback'         => ['::1', '::1'],
		'IPv6 in brackets'      => ['[2001:db8::1]', '2001:db8::1'],
		'IPv6 in brackets, set' => [' [::1] ', '::1'],
	]);

	it('resolves a name with only an A record', function () {
		expect(intropage_ntp_addresses('ntp.test', static fn () => ['192.0.2.1', '192.0.2.2']))->toBe(['192.0.2.1']);
	});

	it('resolves a name with only an AAAA record', function () {
		expect(intropage_ntp_addresses('ntp.test', static fn () => ['2001:db8::1', '2001:db8::2']))->toBe(['2001:db8::1']);
	});

	it('tries the IPv6 address before the IPv4 address for a name with both', function () {
		$lookup = static fn () => ['192.0.2.1', '2001:db8::1', '192.0.2.2', '2001:db8::2'];

		expect(intropage_ntp_addresses('ntp.test', $lookup))->toBe(['2001:db8::1', '192.0.2.1']);
	});

	it('returns nothing for a name that does not resolve', function () {
		expect(intropage_ntp_addresses('missing.test', static fn () => []))->toBe([]);
	});

	it('resolves a name through the system resolver by default', function () {
		expect(intropage_ntp_addresses('localhost'))->toContain('127.0.0.1');
	});
});

describe('intropage_ntp_lookup', function () {
	it('finds localhost from the hosts file', function () {
		expect(intropage_ntp_lookup('localhost'))->toContain('127.0.0.1');
	});

	it('returns an empty list for a name that cannot be resolved', function () {
		expect(intropage_ntp_lookup('name-that-does-not-exist.invalid'))->toBe([]);
	});
});

describe('ntp_time', function () {
	afterEach(function () {
		foreach ($this->servers ?? [] as $server) {
			intropage_ntp_server_stop($server);
		}
	});

	it('reads the time from an IPv4 server', function () {
		$this->servers = [$server = intropage_ntp_server('udp://127.0.0.1:0', 'reply', 1700000000 + INTROPAGE_NTP_OFFSET)];

		expect(ntp_time('127.0.0.1', $server['port'], 5))->toBe(1700000000);
	});

	it('reads the time from an IPv6 server given as a compressed address', function () {
		$server = intropage_ntp_server('udp://[::1]:0', 'reply', 1700000001 + INTROPAGE_NTP_OFFSET);

		if ($server === null) {
			$this->markTestSkipped('No IPv6 loopback address in this environment.');
		}

		$this->servers = [$server];

		expect(ntp_time('::1', $server['port'], 5))->toBe(1700000001);
	});

	it('reads the time from an IPv6 server given in brackets', function () {
		$server = intropage_ntp_server('udp://[::1]:0', 'reply', 1700000002 + INTROPAGE_NTP_OFFSET);

		if ($server === null) {
			$this->markTestSkipped('No IPv6 loopback address in this environment.');
		}

		$this->servers = [$server];

		expect(ntp_time('[::1]', $server['port'], 5))->toBe(1700000002);
	});

	it('uses the IPv4 address of a name that only has an A record', function () {
		$this->servers = [$server = intropage_ntp_server('udp://127.0.0.1:0', 'reply', 1700000003 + INTROPAGE_NTP_OFFSET)];

		expect(ntp_time('ntp.test', $server['port'], 5, static fn () => ['127.0.0.1']))->toBe(1700000003);
	});

	it('uses the IPv6 address of a name that only has an AAAA record', function () {
		$server = intropage_ntp_server('udp://[::1]:0', 'reply', 1700000004 + INTROPAGE_NTP_OFFSET);

		if ($server === null) {
			$this->markTestSkipped('No IPv6 loopback address in this environment.');
		}

		$this->servers = [$server];

		expect(ntp_time('ntp.test', $server['port'], 5, static fn () => ['::1']))->toBe(1700000004);
	});

	it('falls back to the IPv4 address when the IPv6 address does not answer', function () {
		$this->servers = [$server = intropage_ntp_server('udp://127.0.0.1:0', 'reply', 1700000005 + INTROPAGE_NTP_OFFSET)];

		// Nothing listens on this port on ::1, so the first attempt fails and the second succeeds
		expect(ntp_time('ntp.test', $server['port'], 2, static fn () => ['127.0.0.1', '::1']))->toBe(1700000005);
	});

	it('reports a name that does not resolve', function () {
		expect(ntp_time('missing.test', 123, 1, static fn () => []))->toBe('error: unable to resolve missing.test');
	});

	it('reports a server that does not answer within the timeout', function () {
		$this->servers = [$server = intropage_ntp_server('udp://127.0.0.1:0', 'silent', 0)];

		$start  = microtime(true);
		$result = ntp_time('127.0.0.1', $server['port'], 1);

		expect($result)->toBe('error: no answer from 127.0.0.1 within 1 seconds');
		expect(microtime(true) - $start)->toBeGreaterThanOrEqual(0.9);
	});

	it('reports an answer that is too short', function () {
		$this->servers = [$server = intropage_ntp_server('udp://127.0.0.1:0', 'short', 0)];

		expect(ntp_time('127.0.0.1', $server['port'], 5))->toBe('error: short answer from 127.0.0.1');
	});

	it('does not open a socket for something that is not an address', function () {
		expect(intropage_ntp_query('not-an-address', 123, 1))->toBe('error: connect to not-an-address failed: not an IP address');
	});

	it('returns the error of the last address when every address fails', function () {
		$result = ntp_time('ntp.test', 9, 1, static fn () => ['::zz', 'not-ip']);

		expect($result)->toBe('error: connect to not-ip failed: not an IP address');
	});
});
