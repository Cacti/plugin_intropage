<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * INFO declares compat = 1.2.17. Cacti 1.2 ships
 * include/vendor/composer/platform_check.php, required from autoload_real.php
 * and reached from global.php at boot, and it throws when PHP_VERSION_ID is
 * below 80000. So 1.2 cannot run under PHP 8.0 and the plugin floor is
 * exactly 8.0. CACTI_PHP_VERSION_MINIMUM still says 5.4.0 but nothing reads
 * it; it appears once in the 1.2 tree, at its own definition.
 *
 * PHP 8.0 functions and syntax are therefore fine here. 8.1 and later are not.
 * Syntax is gated by the php80-floor job in plugin-ci-workflow.yml, which runs
 * php -l under 8.0 and rejects enums, readonly, never and first-class
 * callables exactly. php -l cannot see a call to a function that does not
 * exist yet, so those are checked below. Tokens are inspected rather than raw
 * text so comments and string literals cannot trigger a false positive.
 */

describe('post-8.0 functions stay out of intropage runtime code', function () {
	// 8.1 and later only. str_contains(), str_starts_with(), str_ends_with(),
	// get_debug_type(), fdiv() and preg_last_error_msg() are 8.0 and allowed.
	$laterThan80 = [
		'array_is_list',
		'enum_exists',
		'fsync',
		'fdatasync',
		'json_validate',
		'mb_str_pad',
		'array_find',
		'array_any',
		'array_all',
	];

	$calledFunctions = function (string $path): array {
		$source = file_get_contents($path);

		if ($source === false) {
			return [];
		}

		$tokens = token_get_all($source);
		$called = [];

		// T_NAME_FULLY_QUALIFIED covers \\str_contains(); it is 8.0+, so guard it.
		$nameTokens = [T_STRING];

		if (defined('T_NAME_FULLY_QUALIFIED')) {
			$nameTokens[] = T_NAME_FULLY_QUALIFIED;
			$nameTokens[] = T_NAME_QUALIFIED;
		}

		$total = count($tokens);

		foreach ($tokens as $index => $token) {
			if (!is_array($token) || !in_array($token[0], $nameTokens, true)) {
				continue;
			}

			// A polyfill declares the name; that is not a call to it.
			$isDeclaration = false;

			for ($back = $index - 1; $back >= 0; $back--) {
				$prior = $tokens[$back];

				if (is_array($prior) && in_array($prior[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
					continue;
				}

				$isDeclaration = is_array($prior) && $prior[0] === T_FUNCTION;

				break;
			}

			if ($isDeclaration) {
				continue;
			}

			// Only count it as a call when the next non-whitespace token is '('.
			for ($next = $index + 1; $next < $total; $next++) {
				$peek = $tokens[$next];

				if (is_array($peek) && $peek[0] === T_WHITESPACE) {
					continue;
				}

				if ($peek === '(') {
					$called[strtolower(ltrim($token[1], '\\'))] = true;
				}

				break;
			}
		}

		return array_keys($called);
	};

	$runtimeFiles = function (): array {
		$root  = dirname(__DIR__, 2);
		$found = [];

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
		);

		foreach ($iterator as $file) {
			$path = $file->getPathname();

			if (substr($path, -4) !== '.php') {
				continue;
			}

			$relative = ltrim(str_replace($root, '', $path), '/');

			// Match nested trees too, not only a top-level tests/ or vendor/.
			if (preg_match('#(^|/)(tests|vendor)/#', $relative)) {
				continue;
			}

			$found[$relative] = $path;
		}

		ksort($found);

		return $found;
	};

	it('finds runtime files to scan', function () use ($runtimeFiles) {
		expect(count($runtimeFiles()))->toBeGreaterThan(0);
	});

	it('calls no functions newer than PHP 8.0', function () use ($runtimeFiles, $calledFunctions, $laterThan80) {
		$offenders = [];

		foreach ($runtimeFiles() as $relative => $path) {
			$found = array_intersect($calledFunctions($path), $laterThan80);

			if ($found !== []) {
				$offenders[] = $relative . ': ' . implode(', ', $found);
			}
		}

		expect($offenders)->toBe([]);
	});
});
