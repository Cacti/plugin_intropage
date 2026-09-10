<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

describe('output escaping in intropage', function () {
	it('does not interpolate raw variables into HTML attributes', function () {
		$uiFiles = [
		'include/functions.php',
		'include/settings.php',
		'panellib/analyze.php',
		'panellib/busiest.php',
		];

		foreach ($uiFiles as $relativeFile) {
			$path = realpath(__DIR__ . '/../../' . $relativeFile);

			if ($path === false) {
				continue;
			}
			$contents = file_get_contents($path);

			if ($contents === false) {
				continue;
			}

			$lines     = explode("\n", $contents);
			$dangerous = 0;

			foreach ($lines as $line) {
				$trimmed = ltrim($line);

				if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*')) {
					continue;
				}

				// value="$row[...] without html_escape wrapping
				if (preg_match('/value\s*=\s*["\'"]\s*<\?php\s+echo\s+\$/', $line)) {
					$dangerous++;
				}

				// title="<?php print $something without escaping
				if (preg_match('/(?:title|alt|placeholder)\s*=.*print\s+\$(?!_|config)/', $line)) {
					if (!str_contains($line, 'html_escape') && !str_contains($line, '__esc') && !str_contains($line, 'htmlspecialchars')) {
						$dangerous++;
					}
				}
			}

			expect($dangerous)->toBe(0,
				"File {$relativeFile} has unescaped variables in HTML attributes"
			);
		}
	});

	it('uses html_escape or __esc for user-controlled output', function () {
		$uiFiles = [
		'include/functions.php',
		'include/settings.php',
		'panellib/analyze.php',
		'panellib/busiest.php',
		];

		$totalEscapeCalls = 0;

		foreach ($uiFiles as $relativeFile) {
			$path = realpath(__DIR__ . '/../../' . $relativeFile);

			if ($path === false) {
				continue;
			}
			$contents = file_get_contents($path);

			if ($contents === false) {
				continue;
			}

			$totalEscapeCalls += preg_match_all('/html_escape|__esc\(|htmlspecialchars/', $contents);
		}

		// At least some escaping should be present in UI files
		expect($totalEscapeCalls)->toBeGreaterThan(0,
			'UI files should contain at least one html_escape/__esc call'
		);
	});
});
