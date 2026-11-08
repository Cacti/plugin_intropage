<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Line and bar panel charts must be generated at their configured height and
 * must let billboard fit the container width (like pie and treemap), so the
 * SVG is the right size the moment it renders. The old fixed size (height 100,
 * width 150) relied on the browser's resizeCharts() to correct it after load,
 * which never runs for these charts when the inline bb.generate() script is
 * executed asynchronously on a reload; the chart then stayed at 150x100 until
 * an unrelated window resize fired resizeCharts().
 */

require_once __DIR__ . '/../../includes/functions.php';

describe('intropage_prepare_graph chart sizing', function () {
	beforeEach(function () {
		// Five lines => a 180px chart (see intropage_prepare_graph()).
		$GLOBALS['__test_user_setting']['intropage_number_of_lines'] = 5;
	});

	afterEach(function () {
		unset($GLOBALS['__test_user_setting']['intropage_number_of_lines']);
	});

	it('generates a line chart at the configured height and no fixed width', function () {
		$html = intropage_prepare_graph([
			'line' => [
				'title1' => 'DS all records',
				'label1' => ['2026-01-01 00:00:00', '2026-01-01 00:05:00'],
				'data1'  => [1, 2],
				'unit1'  => ['title' => 'records'],
			],
		], 1);

		// Wrapper reserves the height and the chart is generated at the same height.
		expect($html)->toContain('style="height: 180px;"');
		expect($html)->toContain('"size":{"height":180}');
		// No leftover fixed size that depends on resizeCharts() to correct it.
		expect($html)->not->toContain('"height":100');
		expect($html)->not->toContain('"width":150');
	});

	it('generates a bar chart at the configured height and no fixed width', function () {
		$html = intropage_prepare_graph([
			'bar' => [
				'title1' => 'Bars',
				'title0' => 'Bars',
				'label1' => ['2026-01-01 00:00:00', '2026-01-01 00:05:00'],
				'data0'  => [1, 2],
				'unit1'  => ['title' => 'items'],
			],
		], 1);

		expect($html)->toContain('style="height: 180px;"');
		expect($html)->toContain('"size":{"height":180}');
		expect($html)->not->toContain('"height":100');
		expect($html)->not->toContain('"width":150');
	});
});
