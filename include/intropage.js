/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2023 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

// display/hide detail
$(function() {
	$('.flexchild').css('background-color', $('body').css('background-color'));

	$(window).resize(function() {
		resizeGraphsPanel();
		resizeCharts();
	});

	$(window).on('orientationchange', function() {
		resizeGraphsPanel();
		resizeCharts();
	}).trigger('orientationchange');

	if (pageName == 'index.php') {
		intropage_page = urlPath + pageName;
	} else {
		intropage_page = urlPath + 'plugins/intropage/intropage.php';
	}

	initPage();
});

function resizeCharts() {
	$('.chart_wrapper:has(svg)').each(function() {
		var chart      = $(this).attr('id');
		var windWidth  = $(window).width() - 34;
		var panelWidth = $(this).closest('.panel_wrapper').width() - 34;
		var width = Math.min(windWidth, panelWidth);
		var height     = $(this).closest('.panel_wrapper').height() - 54;
//		console.log('Chart:'+chart+', Width:'+width+', Height:'+height);

		if (panels[chart] != undefined) {
			panels[chart].resize({ width: width, height:height });
		}
	});
}

function resizeGraphsPanel() {
	$('img.intrograph').each(function() {
		var graphWidth = $(this).width();
		var panel      = $(this).closest('.flexchild');
		var panelWidth = panel.width();
		var quarter    = parseInt(($('#main').width() / 4) - 10);
		var third      = parseInt(($('#main').width() / 3) - 10);

		//console.log('Graph:'+graphWidth+', Panel:'+panelWidth+', Quarter:'+quarter+', Third:'+third);
		if (graphWidth > panelWidth) {
			if (panel.hasClass('quarter-panel')) {
				panel.removeClass('quarter-panel').addClass('third-panel');
			} else if (panel.hasClass('third-panel')) {
				panel.removeClass('third-panel').addClass('half-panel');
			}
		} else if (panel.hasClass('half-panel')) {
			if (graphWidth < third) {
				panel.removeClass('half-width').addClass('third-width');
			}

			if (graphWidth < quarter) {
				panel.removeClass('third-width').addClass('quarter-width');
			}
		} else if (panel.hasClass('third-panel')) {
			if (graphWidth < quarter) {
				panel.removeClass('third-width').addClass('quarter-width');
			}
		}
	});
}

function setupHidden() {
	$('.flexchild').each(function(i) {
		var item = $(this);
		var item_clone = item.clone();
		item.data('clone', item_clone);
		var position = item.position();
		item_clone.css({ left: position.left, top: position.top, visibility: 'hidden' }).attr('data-pos', i+1);
		$('#cloned-slides').append(item_clone);
	});
}

function addPanel() {
	$.post(intropage_page, {
		header: 'false',
		dashboard_id: dashboard_id,
		__csrf_magic: csrfMagicToken,
		intropage_addpanel: $('#intropage_addpanel').val()
	}).done(function(data) {
		$('#main').html(data);
		applySkin();
		initPage();
	});
}

function actionPanel() {
	var option = $('#intropage_action').val();

	if (option == 'loginopt_tab') {
		document.location = urlPath + 'plugins/intropage/intropage.php?dashboard_id='+dashboard_id+'&intropage_action='+option;
	} else if (option == 'loginopt_console') {
		document.location = urlPath + 'index.php?dashboard_id='+dashboard_id+'&intropage_action='+option;
	} else {
		$.post(intropage_page, {
			header: 'false',
			dashboard_id: dashboard_id,
			__csrf_magic: csrfMagicToken,
			intropage_action: option
		}).done(function(data) {
			$('#main').html(data);
			applySkin();
			initPage();
		});
	}
}

function setPageRefresh() {
	clearAllTimeouts();

	if (intropage_autorefresh > 0) {
		refresh = setInterval(reload_all, intropage_autorefresh*1000);
	} else if (intropage_autorefresh == -1) {
		pollerRefresh = setTimeout(function() {
			refresh = setInterval(testPoller, 10000);
		}, 30000);
	}
}

function initPage() {
	$('#intropage_addpanel').unbind().change(function() {
		addPanel();
	});

	$('#intropage_action').unbind().change(function() {
		actionPanel();
	});

	setPageRefresh();

	$('.article').hide();

	$('.quarter-panel').css('width', intropage_panel_quarter_width);
	$('.third-panel').css('width', intropage_panel_third_width);
	$('.half-panel').css('width', intropage_panel_half_width);

	$('#obal').sortable({
		tolerance: 'pointer',
		forcePlaceholderSize: true,
		forceHelperSize: false,
		placeholder: '.flexchild',
		handle: '.panel_header',
		helpler: 'clone',
		delay: 500,
		revert: 'invalid',
		scroll: false,
		dropOnEmpty: false,
		start: function(e, ui){
			var minWidth = Math.min.apply(null,
				$('.flexchild').map(function() {
					return $(this).width();
				}).get()
			);

			ui.helper.width(minWidth);
			$('#obal .flexchild').css({'width': minWidth, 'flex-grow': '0'});

			ui.helper.addClass('exclude-me');
			ui.helper.data('clone').hide();
			$('.cloned-slides .flexchild').css('visibility', 'visible');
		},
		stop: function(event, ui) {
			$('#obal .flexchild.exclude-me').each(function() {
				var item = $(this);
				var clone = item.data('clone');
				var position = item.position();

				clone.css('left', position.left);
				clone.css('top', position.top);
				clone.show();

				item.removeClass('exclude-me');
				$('.flexchild').css('width', '');
			});

			$('#obal .flexchild').each(function() {
				var item = $(this);
				var clone = item.data('clone');

				clone.attr('data-pos', item.index());
			});

			$('#obal .flexchild').css('visibility', 'visible');
			$('.cloned-slides .flexchild').css('visibility', 'hidden');
			$('#obal .flexchild').css({'width': '', 'flex-grow': '1'});

			resizeGraphsPanel();
			resizeCharts();
		},
		change: function(event, ui) {
			$('#obal li:not(.exclude-me, .ui-sortable-placeholder)').each(function() {
				var item = $(this);
				var clone = item.data('clone');
				clone.stop(true, false);
				var position = item.position();
				clone.animate({ left: position.left, top:position.top}, 500);
			});
		},
		update: function(event, ui) {
			// change order
			var xdata = new Array();
			$('#obal li').each(function() {
				xdata.push($(this).attr('id'));
			});

			$.get(intropage_page, { xdata:xdata, intropage_action:'order' });
		}
	});

	$('.droppanel').click(function(event) {
		event.preventDefault();
		var panel_div_id = $(this).attr('data-panel');
		var page = $(this).attr('href');

		$('#'+panel_div_id).remove();

		$.get(page, function() {
			var url = page.replace('droppanel', 'addpanelselect&header=false');

			$.get(url)
				.done(function(data) {
					checkForRedirects(data, url);

					$('#intropage_addpanel').selectmenu('destroy').replaceWith(data);
					$('#intropage_addpanel').selectmenu().unbind().change(function() {
						addPanel();
					});

					applySkin();
					resizeGraphsPanel();
					resizeCharts();
				})
				.fail(function(data) {
					getPresentHTTPErrorOrRedirect(data, url);
				});
		});
	});

	// enable/disable move panel/copy text
	$('#switch_copytext').off('click').on('click', function() {
		if (!intropage_drag) {
			$('#obal').sortable('enable');
			$('#switch_copytext').attr('title', intropage_text_panel_disable);
			$('.flexchild').css('cursor','move');
			intropage_drag = true;
		} else {
			$('#obal').sortable('disable');
			$('#switch_copytext').attr('title', intropage_text_panel_enable);
			$('.flexchild').css('cursor','default');
			intropage_drag = false;
		}
	});

	// reload single panel function
	$('.reload_panel_now').off('click').on('click', function(event) {
		if ($(this).data('lastClick') + 1000 > new Date().getTime()) {
			event.stopPropagation();
			return false;
		}

		$(this).data('lastClick', new Date().getTime());

		var panel_id = $(this).attr('id').split('_').pop();

		reload_panel(panel_id, true, false);
		Pace.stop();
	});

	reload_all();
	setupHidden();
	resizeGraphsPanel();
	resizeCharts();

	$(window).trigger('resize');
}

function testPoller() {
	var url = urlPath+'plugins/intropage/intropage.php?&action=autoreload';

	$.get(url)
	.done(function(data) {
		checkForRedirects(data, url);

		if (data == 1) {
			$('#obal li').each(function() {
				var panel_id = $(this).attr('id').split('_').pop();
				reload_panel(panel_id, false, false);
				Pace.stop();
		    });

			setPageRefresh();
		}
	})
	.fail(function(data) {
		getPresentHTTPErrorOrRedirect(data, href);
	});
}

function reload_panel(panel_id, forced_update, refresh) {
	if (!refresh) {
		$('#panel_'+panel_id).find('.panel_data').css('opacity',0);
		$('#panel_'+panel_id).find('.panel_data').fadeIn('slow');
	}

	var url = urlPath+'plugins/intropage/intropage.php?action=reload&force='+forced_update+'&panel_id='+panel_id;

	$.get(url)
	.done(function(data) {
		checkForRedirects(data, url);

		if ($('#panel_'+panel_id).find('.chart_wrapper').length) {
			chart_id = $('#panel_'+panel_id).find('.chart_wrapper').attr('id');
			if (panels[chart_id] != undefined) {
				panels[chart_id].destroy();
			}
		}

		$('#panel_'+panel_id).find('.panel_data').empty().html(data);

		if (!refresh) {
			$('#panel_'+panel_id).find('.panel_data').css('opacity', 1);
		}

		resizeGraphsPanel();
		resizeCharts();
		ajaxAnchors();
	})
	.fail(function(data) {
		$('#panel_'+panel_id).find('.panel_data').html(intropage_text_data_error);
	});
}

function reload_all() {
	if ($('#overlay').dialog('instance') === undefined) {
		$('#obal li.flexchild').each(function() {
			var panel_id = $(this).attr('id').split('_').pop();
			reload_panel(panel_id, false, true);
		});
	} else if (!$('#overlay').dialog('isOpen')) {
		$('#obal li.flexchild').each(function() {
			var panel_id = $(this).attr('id').split('_').pop();
			reload_panel(panel_id, false, true);
		});
	}

	Pace.stop();
	setPageRefresh();
}


// detail to the new window
$('.maxim').click(function(event) {
	event.preventDefault();
	var panel_id = $(this).attr('detail-panel');

	var url = urlPath+'plugins/intropage/intropage.php?action=details&panel_id='+panel_id;

	$.get(url)
	.done(function(data) {
		checkForRedirects(data, url);

		$('#overlay_detail').html(data);

		var width = $('#overlay_detail').textWidth() + 150;
		var windowWidth = $(window).width();

		if (width > 1200) {
			width = 1200;
		}

		if (width > windowWidth) {
			width = windowWidth - 50;
		}

		$('#overlay').dialog({
			modal: true,
			autoOpen: true,
			buttons: [
				{
					text: intropage_text_close,
					click: function() {
						$(this).dialog('destroy');
						$('#overlay_detail').empty();
					},
					icon: 'ui-icon-heart'
				}
			],
			width: width,
			maxHeight: 650,
			resizable: true,
			title: intropage_text_panel_details,
		});

		$('#block').click(function() {
			$('#overlay').dialog('close');
		});
	})
	.fail(function(data) {
		getPresentHTTPErrorOrRedirect(data, href);
	});
});




$('body').on('click','.bus_graph', function() {

	event.preventDefault();

	var id = $(this).attr('bus_id');

	data = '<img src="' + urlPath + 'graph_image.php?disable_cache=true&graph_width=450&local_graph_id=' + id + '" />';

	$('#overlay').dialog({
		modal: true,
		autoOpen: true,
		buttons: [
			{
				text: intropage_text_close,
				click: function() {
					$(this).dialog('destroy');
					$('#overlay_detail').empty();
				},
				icon: 'ui-icon-heart'
			}
		],
		width: 600,
		height: 300,
		maxHeight: 650,
		resizable: true,
		title: 'Bussiest graph',
	});

	$('#overlay_detail').html(data);

});


