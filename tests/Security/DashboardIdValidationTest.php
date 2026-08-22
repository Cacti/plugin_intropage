<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require_once __DIR__ . '/../../include/functions.php';

describe('dashboard identifier validation', function () {
	it('returns canonical database integers', function () {
		expect(intropage_parse_dashboard_id('0'))->toBe(0)
			->and(intropage_parse_dashboard_id('42'))->toBe(42)
			->and(intropage_parse_dashboard_id('2147483647'))->toBe(2147483647);
	});

	it('rejects numeric strings that MySQL would coerce', function ($value) {
		expect(intropage_parse_dashboard_id($value))->toBeNull();
	})->with([
		'scientific notation' => '1e3',
		'leading plus'        => '+3',
		'trailing whitespace' => '3 ',
		'decimal'             => '3.14',
		'leading zero'        => '03',
		'negative'            => '-1',
		'out of range'        => '2147483648',
		'empty'               => '',
	]);
});
