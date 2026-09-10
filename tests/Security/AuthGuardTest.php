<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

describe('auth guard presence in intropage', function () {
	it('includes auth.php or global.php in all UI entry points', function () {
		// intropage.php is the only web entry point; the include/ and panellib/
		// files are libraries it pulls in after auth.php has run.
		$uiFiles = [
		'intropage.php',
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

			// Files that include setup.php or are library files don't need direct auth
			if (str_starts_with($relativeFile, 'include/') || str_starts_with($relativeFile, 'lib/')) {
				continue;
			}

			if (str_starts_with($relativeFile, 'poller_')) {
				continue;
			}

			$hasAuth = (
				str_contains($contents, 'auth.php') ||
				str_contains($contents, 'global.php') ||
				str_contains($contents, 'global_arrays.php')
			);

			expect($hasAuth)->toBeTrue(
				"File {$relativeFile} does not include auth.php or global.php"
			);
		}
	});

	it('validates numeric IDs from request variables before DB queries', function () {
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

			// Check for get_filter_request_var usage for numeric IDs
			if (preg_match('/get_request_var\s*\(\s*[\'"]id[\'"]/', $contents)) {
				// Should use get_filter_request_var for 'id' params
				$hasFilter = (
					str_contains($contents, 'get_filter_request_var') ||
					str_contains($contents, 'input_validate_input_number') ||
					str_contains($contents, 'form_input_validate')
				);

				expect($hasFilter)->toBeTrue(
					"File {$relativeFile} uses get_request_var for IDs without validation"
				);
			}
		}
	});
});
