<?php

function boost() {
    global $config, $boost_refresh_interval, $boost_max_runtime;
	
    $result = array(
	'name' => 'Boost statistics',
	'alarm' => 'green',
	'data' => '',
	'detail' => '',
    );
	
// dodelat alerty
// zkusit u te translace rovnou mezeru

// from lib/boost.php
function boost_file_size_display($file_size, $digits = 2) {
        if ($file_size > 1024) {
                $file_size = $file_size / 1024;

                if ($file_size > 1024) {
                        $file_size = $file_size / 1024;

                        if ($file_size > 1024) {
                                $file_size = $file_size / 1024;
                                $file_suffix = ' GBytes';
                        } else {
                                $file_suffix = ' MBytes';
                        }
                } else {
                        $file_suffix = ' KBytes';
                }
        } else {
                $file_suffix = ' Bytes';
        }

        $file_size = number_format_i18n($file_size, $digits) . $file_suffix;

        return $file_size;
}

      $rrd_updates     = read_config_option('boost_rrd_update_enable', TRUE);
        $last_run_time   = read_config_option('boost_last_run_time', TRUE);
        $next_run_time   = read_config_option('boost_next_run_time', TRUE);

        $max_records     = read_config_option('boost_rrd_update_max_records', TRUE);
        $max_runtime     = read_config_option('boost_rrd_update_max_runtime', TRUE);
        $update_interval = read_config_option('boost_rrd_update_interval', TRUE);
        $peak_memory     = read_config_option('boost_peak_memory', TRUE);
        $detail_stats    = read_config_option('stats_detail_boost', TRUE);



        $boost_status = read_config_option('boost_poller_status', TRUE);
        if ($boost_status != '') {
                $boost_status_array = explode(':', $boost_status);

                $boost_status_date  = $boost_status_array[1];

                if (substr_count($boost_status_array[0], 'complete'))    $boost_status_text = __('Idle');
                elseif (substr_count($boost_status_array[0], 'running')) $boost_status_text = __('Running');
                elseif (substr_count($boost_status_array[0], 'overrun')) { $boost_status_text = __('Overrun Warning'); $result['alarm'] = "red"; }
                elseif (substr_count($boost_status_array[0], 'timeout')) { $boost_status_text = __('Timed Out');  $result['alarm'] = "red"; }
                else   $boost_status_text = __('Other');
        } else {
                $boost_status_text = __('Never Run');
                $boost_status_date = '';
        }


       $stats_boost = read_config_option('stats_boost', TRUE);
        if ($stats_boost != '') {
                $stats_boost_array = explode(' ', $stats_boost);

                $stats_duration = explode(':', $stats_boost_array[0]);
                $boost_last_run_duration = $stats_duration[1];

                $stats_rrds = explode(':', $stats_boost_array[1]);
                $boost_rrds_updated = $stats_rrds[1];
        } else {
                $boost_last_run_duration = '';
                $boost_rrds_updated = '';
        }


	$result['data'] .= __('Boost On-demand Updating:') . ' ' . ($rrd_updates == '' ? 'Disabled' : $boost_status_text) . '<br/>';

	$data_length = db_fetch_cell("SELECT data_length 
                FROM INFORMATION_SCHEMA.TABLES WHERE table_schema=SCHEMA()
                AND (table_name LIKE 'poller_output_boost_arch_%' OR table_name LIKE 'poller_output_boost')");

        /* tell the user how big the table is */
        $result['data'] .= __('Current Boost Table(s) Size:') . ' ' . boost_file_size_display($data_length, 2) . '<br/>';

        /* tell the user about the average size/record */
        // $result['data'] .= __('Avg Bytes/Record:') . ' ' . boost_file_size_display($avg_row_length, 0) . '<br/>';


	$result['data'] .= "Last run duration: ";
        if (is_numeric($boost_last_run_duration)) {
                $result['data'] .=  $boost_last_run_duration . " s";
        } else {
                $result['data'] .= __('N/A');
        }
        $result['data'] .= '<br/>';


        $result['data'] .= __('RRD Updates:') . ' ' . ($boost_rrds_updated != '' ? number_format_i18n($boost_rrds_updated, -1):'-') . '<br/>';
        $result['data'] .= __('Maximum Records:') . ' ' . number_format_i18n($max_records, -1) . ' ' . __('Records') . '<br/>';

        $result['data'] .= __('Update Frequency:') . ' ' . ($rrd_updates == '' ? __('N/A') : $boost_refresh_interval[$update_interval]) . '<br/>';

        $result['data'] .= __('Next Start Time:') . ' ' . $next_run_time . '<br/>';


/* moved to analyse graph/host/tree
// orphaned
    $sql_result = db_fetch_assoc ("SELECT dtd.local_data_id, dtd.name_cache, dtd.active, dtd.rrd_step, dt.name AS data_template_name, dl.host_id, dtd.data_source_profile_id, COUNT(DISTINCT gti.local_graph_id) AS deletable FROM data_local AS dl INNER JOIN data_template_data AS dtd ON dl.id=dtd.local_data_id LEFT JOIN data_template AS dt ON dl.data_template_id=dt.id LEFT JOIN data_template_rrd AS dtr ON dtr.local_data_id=dtd.local_data_id LEFT JOIN graph_templates_item AS gti ON (gti.task_item_id=dtr.id) GROUP BY dl.id HAVING deletable=0 ORDER BY `name_cache` ASC");
    $result['data'] .= "Orphaned DS: " . count($sql_result) . "<br/>";
    if (count($sql_result) > 0) {
        if ($result['alarm'] == "green")
            $result['alarm'] = "yellow";

	$result['detail'] .= "Orphaned DS detail:<br/>";
    	foreach($sql_result as $row) {

	    $result['detail'] .= "<a href=\"" . htmlspecialchars($config['url_path']) . "data_sources.php?action=ds_edit&id=" . $row['local_data_id'] . "\">" .
	    $row['name_cache'] . "</a><br/>\n"; 

	}
    }
*/



	
	return $result;
}

?>