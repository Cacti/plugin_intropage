<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

require_once __DIR__ . '/../../includes/functions.php';

/**
 * Creates a temporary log file for tail_log() tests.
 *
 * @param string $contents The contents to write to the log file.
 *
 * @return string The path to the temporary log file.
 */
function intropage_test_create_log(string $contents): string {
	$path = tempnam(sys_get_temp_dir(), 'intropage-tail-');

	if ($path === false || file_put_contents($path, $contents) === false) {
		throw new RuntimeException('Unable to create temporary log file');
	}

	return $path;
}

/**
 * Removes a temporary log file created by the test helper.
 *
 * @param string $path The temporary log file path.
 *
 * @return void
 */
function intropage_test_remove_log(string $path): void {
	if (file_exists($path)) {
		unlink($path);
	}
}

describe('tail_log', function () {
	it('does not return a spurious empty row for a newline-terminated log', function () {
		$path = intropage_test_create_log("first\nsecond\n");

		try {
			expect(tail_log($path, 1))->toBe(array('second'));
		} finally {
			intropage_test_remove_log($path);
		}
	});

	it('returns the requested rows across the read-buffer boundary', function () {
		$rows = array_fill(0, 900, 'row');
		$path = intropage_test_create_log(implode("\n", $rows) . "\n");

		try {
			expect(tail_log($path, 3))->toBe(array('row', 'row', 'row'));
		} finally {
			intropage_test_remove_log($path);
		}
	});

	it('preserves an unterminated final row', function () {
		$path = intropage_test_create_log("first\nsecond");

		try {
			expect(tail_log($path, 1))->toBe(array('second'));
		} finally {
			intropage_test_remove_log($path);
		}
	});

	it('preserves an empty record before the final terminator', function () {
		$path = intropage_test_create_log("first\n\n");

		try {
			expect(tail_log($path, 2))->toBe(array('first', ''));
		} finally {
			intropage_test_remove_log($path);
		}
	});

	it('returns no rows for an empty file', function () {
		$path = intropage_test_create_log('');

		try {
			expect(tail_log($path))->toBe(array());
		} finally {
			intropage_test_remove_log($path);
		}
	});
});
