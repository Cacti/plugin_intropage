<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2015-2020 Petr Macek                                      |
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
 | https://github.com/xmacan/                                              |
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

function intropage_display_panel($panel_id, $type, $header, $dispdata) {
        global $config;

        $selectedTheme = get_selected_theme();
        switch ($selectedTheme) {
                case 'dark':
                case 'paper-plane':
                        $bgcolor = '#202020';
                        break;
                case 'sunrise':
                        $bgcolor = '';
                        break;
                default:
                        $bgcolor = '#f5f5f5';
        }

        print '<li id="panel_' . $panel_id . '" class="ui-state-default flexchild">';
        print '<div class="cactiTable" style="text-align:left; float: left; box-sizing: border-box;">';

        print '<div class="panel_header color_' . $type . '">';
        print $header;

        if ($panel_id > 990) {
                printf("<a href='#' title='" . __esc('You cannot disable this panel', 'intropage') . "' class='header_link'><i class='fa fa-times'></i></a>\n
        } else {
                printf("<a href='%s' data-panel='panel_$panel_id' class='header_link droppanel' title='" . __esc('Disable panel', 'intropage') . "'><i class=
        }

        printf("<a href='#' id='reloadid_" . $panel_id . "' title='" . __esc('Reload Panel', 'intropage') . "' class='header_link reload_panel_now'><i class=

        if (isset($dispdata['detail']) && !empty($dispdata['detail'])) {
                printf("<a href='#' title='" . __esc('Show Details', 'intropage') . "' class='header_link maxim' detail-panel='%s'><i class='fa fa-window-max
        }

        print " </div>\n";
        print " <table class='cactiTable'>\n";
        print "     <tr><td class='textArea' style='vertical-align: top;'>\n";

        print "<div class='panel_data'>\n";
        print __('Loading data ...', 'intropage');
        print "</div>\n";       // end of panel_data
        print "</td></tr>\n\n";
        html_end_box(false);
        print "</li>\n\n";
}


