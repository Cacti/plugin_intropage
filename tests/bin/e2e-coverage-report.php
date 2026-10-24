<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Traceability report for a change: lists every added or modified executable
 * line of the non-test PHP files and the tests that execute it, from
 *
 *   - the Xdebug dumps written by tests/Integration/coverage-prepend.php while
 *     the end-to-end tests drove the real entry points (one sub-directory per
 *     test), and
 *   - optionally the PHPUnit XML coverage report of the unit tests
 *     (pest --coverage-xml=DIR), which names the covering test for each line.
 *
 * Branch data from the end-to-end dumps is used to list branches of the
 * changed lines that no end-to-end request reached.
 *
 * Usage: php tests/bin/e2e-coverage-report.php <base-ref> <e2e-dir> [<unit-xml-dir>] [--lines]
 *
 * It only reads files and runs git; run it from the plugin directory.
 */

if (PHP_SAPI !== 'cli') {
	http_response_code(404);

	exit;
}

if ($argc < 3) {
	fwrite(STDERR, "usage: e2e-coverage-report.php <base-ref> <e2e-dir> [<unit-xml-dir>] [--lines]\n");

	exit(2);
}

$base      = $argv[1];
$e2e_dir   = rtrim($argv[2], '/');
$unit_dir  = isset($argv[3]) && $argv[3] !== '--lines' ? rtrim($argv[3], '/') : '';
$per_line  = in_array('--lines', $argv, true);

/**
 * Added or modified line numbers per non-test PHP file.
 *
 * @return array<string, int[]>
 */
function report_changed_lines(string $base): array {
	$diff = shell_exec('git diff --no-ext-diff --no-color -U0 --diff-filter=AM ' . escapeshellarg($base . '...HEAD') . ' -- ' . escapeshellarg('*.php') . ' ' . escapeshellarg(':!tests'));
	$out  = [];

	foreach (preg_split('/^diff --git /m', (string) $diff, -1, PREG_SPLIT_NO_EMPTY) as $chunk) {
		if (!preg_match('#^a/(\S+) b/(\S+)#', $chunk, $name)) {
			continue;
		}

		preg_match_all('/^@@ -\S+ \+(\d+)(?:,(\d+))? @@/m', $chunk, $hunks, PREG_SET_ORDER);

		foreach ($hunks as $hunk) {
			$count = isset($hunk[2]) && $hunk[2] !== '' ? (int) $hunk[2] : 1;

			for ($i = 0; $i < $count; $i++) {
				$out[$name[2]][] = (int) $hunk[1] + $i;
			}
		}
	}

	return $out;
}

/**
 * Merges the end-to-end dumps.
 *
 * @return array{lines: array<string, array<int, array<string, int>>>, branches: array<string, array<string, array<int, array{int, int}>>>}
 *         lines[file][line][test] = hits; branches[file][function][branch id] = [first line, hits]
 */
function report_load_e2e(string $dir): array {
	$lines    = [];
	$branches = [];

	foreach (glob($dir . '/*', GLOB_ONLYDIR) ?: [] as $test_dir) {
		$test = basename($test_dir);

		foreach (glob($test_dir . '/*.json') ?: [] as $dump) {
			$data = json_decode((string) file_get_contents($dump), true);

			if (!is_array($data)) {
				continue;
			}

			foreach ($data as $path => $info) {
				$pos = strpos($path, '/plugins/intropage/');
				$rel = $pos === false ? $path : substr($path, $pos + strlen('/plugins/intropage/'));

				foreach ($info['lines'] ?? [] as $number => $hit) {
					$lines[$rel][(int) $number][$test] = ($lines[$rel][(int) $number][$test] ?? 0) + max(0, (int) $hit);
				}

				foreach ($info['functions'] ?? [] as $function => $detail) {
					foreach ($detail['branches'] ?? [] as $id => $branch) {
						$key = (int) $id;
						$old = $branches[$rel][$function][$key] ?? [(int) ($branch['line_start'] ?? 0), 0];

						$branches[$rel][$function][$key] = [$old[0], $old[1] + (int) ($branch['hit'] ?? 0)];
					}
				}
			}
		}
	}

	return ['lines' => $lines, 'branches' => $branches];
}

/**
 * Covering unit tests per line from the PHPUnit XML report.
 *
 * @return array<string, array<int, string[]>>
 */
function report_load_unit(string $dir): array {
	$out = [];

	foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
		if ($file->getExtension() !== 'xml' || $file->getFilename() === 'index.xml') {
			continue;
		}

		$xml = simplexml_load_file($file->getPathname());

		if ($xml === false || !isset($xml->file['path'])) {
			continue;
		}

		// name is the file, path its directory relative to the source root
		$sub = trim((string) $xml->file['path'], '/');
		$rel = ($sub === '' ? '' : $sub . '/') . (string) $xml->file['name'];

		foreach ($xml->file->coverage->line ?? [] as $line) {
			foreach ($line->covered as $covered) {
				$name = preg_replace('/^.*\\\\([^\\\\]+)::__pest_evaluable_/', '$1::', (string) $covered['by']);
				$out[$rel][(int) $line['nr']][] = $name;
			}
		}
	}

	return $out;
}

/**
 * Compact, stable list of test names for a table cell.
 *
 * @param string[] $names
 */
function report_names(array $names, int $limit = 3): string {
	$names = array_values(array_unique($names));
	sort($names);

	if ($names === []) {
		return '-';
	}

	$shown = array_slice($names, 0, $limit);

	return implode('; ', $shown) . (count($names) > $limit ? ' (+' . (count($names) - $limit) . ')' : '');
}

$changed = report_changed_lines($base);
$e2e     = report_load_e2e($e2e_dir);
$unit    = $unit_dir !== '' ? report_load_unit($unit_dir) : [];

$uncovered = [];

print "| File | Changed executable lines | Unit | End-to-end | Neither |\n|---|---|---|---|---|\n";

$detail = '';

foreach ($changed as $file => $lines) {
	$executable = array_values(array_filter(array_unique($lines), static function (int $line) use ($file, $e2e): bool {
		return isset($e2e['lines'][$file][$line]);
	}));

	// Lines Xdebug never saw as executable (comments, braces, signatures) are not counted.
	sort($executable);

	$in_unit = 0;
	$in_e2e  = 0;
	$neither = [];

	foreach ($executable as $line) {
		$by_unit = $unit[$file][$line] ?? [];
		$by_e2e  = array_keys(array_filter($e2e['lines'][$file][$line], static function (int $hits): bool {
			return $hits > 0;
		}));

		$in_unit += $by_unit !== [] ? 1 : 0;
		$in_e2e  += $by_e2e !== [] ? 1 : 0;

		if ($by_unit === [] && $by_e2e === []) {
			$neither[]   = $line;
			$uncovered[] = $file . ':' . $line;
		}

		if ($per_line) {
			$detail .= sprintf("| %s:%d | %s | %s |\n", $file, $line, report_names($by_unit), report_names($by_e2e));
		}
	}

	printf("| %s | %d | %d | %d | %s |\n", $file, count($executable), $in_unit, $in_e2e, $neither === [] ? '0' : count($neither) . ' (' . implode(', ', $neither) . ')');
}

if ($per_line) {
	print "\n| Line | Unit tests | End-to-end tests |\n|---|---|---|\n" . $detail;
}

print "\nBranches of changed lines that no end-to-end request reached:\n\n";

$none = true;

foreach ($changed as $file => $lines) {
	$lookup = array_flip($lines);

	foreach ($e2e['branches'][$file] ?? [] as $function => $branches) {
		foreach ($branches as $id => [$line, $hits]) {
			if ($hits === 0 && isset($lookup[$line])) {
				printf("- %s:%d in %s (branch %d)\n", $file, $line, $function, $id);

				$none = false;
			}
		}
	}
}

if ($none) {
	print "- none\n";
}

exit($uncovered === [] ? 0 : 1);
