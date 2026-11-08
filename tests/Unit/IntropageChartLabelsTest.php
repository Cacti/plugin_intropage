<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Pie and treemap labels come from the database (device template names), so
 * they must reach the inline chart script as JSON, never as raw text.
 */

require_once __DIR__ . '/../../includes/functions.php';

describe('intropage_chart_column', function () {
	it('encodes a plain label and a numeric value', function () {
		expect(intropage_chart_column('Linux', 12))->toBe('["Linux",12],');
	});

	it('keeps quotes, brackets and script tags inside the string', function () {
		$column = intropage_chart_column("a'\"]];</script>&", 3);

		expect($column)->toBe('["a\u0027\u0022]];\u003C\/script\u003E\u0026",3],');
		expect(json_decode(rtrim($column, ','), true))->toBe(["a'\"]];</script>&", 3]);
	});

	it('replaces a non-numeric value with zero', function () {
		expect(intropage_chart_column('x', 'abc'))->toBe('["x",0],');
	});

	it('keeps a numeric string as a number', function () {
		expect(intropage_chart_column('x', '7'))->toBe('["x",7],');
	});

	it('substitutes invalid UTF-8 instead of dropping the column', function () {
		expect(intropage_chart_column("bad\xFF", 1))->toBe('["bad\ufffd",1],');
	});
});

describe('intropage_prepare_graph label handling', function () {
	foreach (['pie', 'treemap'] as $type) {
		it("does not let a $type label end its string literal", function () use ($type) {
			$label = "x',0]];marker();//";

			$html = intropage_prepare_graph([
				$type => [
					'title' => 'Templates',
					'label' => [$label],
					'data'  => [4],
				],
			], 1);

			expect($html)->toContain('["x\u0027,0]];marker();\/\/",4],');
			expect($html)->not->toContain("['x',0]]");
			expect($html)->not->toContain("',0]];marker()");
		});
	}
});
