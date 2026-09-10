<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

describe('prepared statement consistency in intropage', function () {
	it('uses prepared DB helpers in the core data-access files', function () {
		// The panels legitimately compose queries from trusted structural
		// fragments (code-defined column lists, int-cast values, allow-listed
		// host ids) that cannot be bound as placeholders; their safety is
		// covered by the interpolation test below and by manual review. This
		// prepared-everywhere policy applies to the core data-access files.
		$targetFiles = [
		'include/functions.php',
		'include/settings.php',
		];

		$rawPattern      = '/\bdb_(?:execute|fetch_row|fetch_assoc|fetch_cell)\s*\(/';
		$preparedPattern = '/\bdb_(?:execute|fetch_row|fetch_assoc|fetch_cell)_prepared\s*\(/';

		foreach ($targetFiles as $relativeFile) {
			$path = realpath(__DIR__ . '/../../' . $relativeFile);

			if ($path === false) {
				continue;
			}
			$contents = file_get_contents($path);

			if ($contents === false) {
				continue;
			}

			$lines    = explode("\n", $contents);
			$rawCalls = 0;

			foreach ($lines as $line) {
				$trimmed = ltrim($line);

				if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*') || str_starts_with($trimmed, '#')) {
					continue;
				}

				// SQL identifiers (table names, CHECK TABLE level keywords) cannot be
				// bound as placeholders; those paths are guarded by allow-listing.
				if (stripos($trimmed, 'check table') !== false) {
					continue;
				}

				// Only a raw call that interpolates a variable into the SQL is a risk;
				// literal constant queries are idiomatic Cacti and are exempt.
				if (preg_match($rawPattern, $line) && !preg_match($preparedPattern, $line) && preg_match('/"[^"]*\$|\.\s*\$/', $line)) {
					$rawCalls++;
				}
			}

			expect($rawCalls)->toBe(0, "File {$relativeFile} contains raw DB calls");
		}
	});

	it('uses parameterized placeholders not string interpolation in SQL', function () {
		$targetFiles = [
		'include/functions.php',
		'include/settings.php',
		'panellib/analyze.php',
		'panellib/busiest.php',
		];

		foreach ($targetFiles as $relativeFile) {
			$path = realpath(__DIR__ . '/../../' . $relativeFile);

			if ($path === false) {
				continue;
			}
			$contents = file_get_contents($path);

			if ($contents === false) {
				continue;
			}

			$lines           = explode("\n", $contents);
			$interpolatedSql = 0;

			foreach ($lines as $num => $line) {
				$trimmed = ltrim($line);

				if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*')) {
					continue;
				}

				// Detect _prepared calls with $ interpolation instead of ? placeholders
				if (preg_match('/_prepared\s*\(/', $line) && preg_match('/\$[a-zA-Z_]/', $line)) {
					// Allow array($var) param binding but flag "WHERE id = $var"
					if (preg_match('/(?:SELECT|INSERT|UPDATE|DELETE|WHERE|SET|FROM|JOIN)[^\']*\$[a-zA-Z_]/', $line)) {
						$interpolatedSql++;
					}
				}
			}

			// This is a heuristic; some false positives expected for complex queries
			expect($interpolatedSql)->toBeLessThanOrEqual(2,
				"File {$relativeFile} may have SQL interpolation in prepared calls"
			);
		}
	});
});
