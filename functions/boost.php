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

      $rrd_updates     = read_config_option('boost_rrd_update_enable', TRUE);
        $last_run_time   = read_config_option('boost_last_run_time', TRUE);
        $next_run_time   = read_config_option('boost_next_run_time', TRUE);

        $max_records     = read_config_option('boost_rrd_update_max_records', TRUE);
        $max_runtime     = read_config_option('boost_rrd_update_max_runtime', TRUE);
        $update_interval = read_config_option('boost_rrd_update_interval', TRUE);
        $peak_memory     = read_config_option('boost_peak_memory', TRUE);
        $detail_stats    = read_config_option('stats_detail_boost', TRUE);

       /* get the boost table status */
        $boost_table_status = db_fetch_assoc("SELECT *
                FROM INFORMATION_SCHEMA.TABLES WHERE table_schema=SCHEMA()
                AND (table_name LIKE 'poller_output_boost_arch_%' OR table_name LIKE 'poller_output_boost')");

        $pending_records = 0;
        $arch_records    = 0;
        $data_length     = 0;
        $engine          = '';
        $max_data_length = 0;

        foreach($boost_table_status as $table) {
                if ($table['TABLE_NAME'] == 'poller_output_boost') {
                        $pending_records += $table['TABLE_ROWS'];
                } else {
                        $arch_records += $table['TABLE_ROWS'];
                }

                $data_length    += $table['DATA_LENGTH'];
                $data_length    += $table['INDEX_LENGTH'];
                $engine          = $table['ENGINE'];
                $max_data_length = $table['MAX_DATA_LENGTH'];
        }

        $total_records  = $pending_records + $arch_records;
        $avg_row_length = ($total_records ? intval($data_length / $total_records) : 0);

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


       if ($total_records) {
    		$result['data'] .= __('Pending Boost Records: ') . number_format_i18n($pending_records, -1) . '<br/>';

                $result['data'] .=  __('Archived Boost Records: ') . number_format_i18n($arch_records, -1) . '<br/>';

		if ($total_records > ($max_records - ($max_records/10)) && $result['alarm'] == "green")	{
		    $result['alarm'] = "yellow";
            	    $result['data'] .= '<b>' . __('Total Boost Records: ') . number_format_i18n($total_records, -1) . '</b><br/>';
		    
		}
		elseif ($total_records > ($max_records - ($max_records/20)) && $result['alarm'] == "green")	{
		    $result['alarm'] = "red";
            	    $result['data'] .= '<b>' . __('Total Boost Records: ') . number_format_i18n($total_records, -1) . '</b><br/>';

		}
		else
            	    $result['data'] .= __('Total Boost Records: ') . number_format_i18n($total_records, -1) . '<br/>';

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
        $result['data'] .= __('Current Boost Table(s) Size:') . ' ' . human_filesize($data_length) . '<br/>';

        /* tell the user about the average size/record */
        $result['data'] .= __('Avg Bytes/Record:') . ' ' . human_filesize($avg_row_length) . '<br/>';


	$result['data'] .= "Last run duration: ";
        if (is_numeric($boost_last_run_duration)) {
                $result['data'] .=  $boost_last_run_duration . " s";
        } else {
                $result['data'] .= __('N/A');
        }
        $result['data'] .= '<br/>';


        $result['data'] .= __('RRD Updates:') . ' ' . ($boost_rrds_updated != '' ? number_format_i18n($boost_rrds_updated, -1):'-') . '<br/>';
        $result['data'] .= __('Maximum Records:') . ' ' . number_format_i18n($max_records, -1) .  '<br/>';

        $result['data'] .= __('Update Frequency:') . ' ' . ($rrd_updates == '' ? __('N/A') : $boost_refresh_interval[$update_interval]) . '<br/>';

        $result['data'] .= __('Next Start Time:') . ' ' . $next_run_time . '<br/>';



	
	return $result;
}

?>