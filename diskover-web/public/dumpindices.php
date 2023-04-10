
<?php

$_SERVER["REDIRECT_STATUS"] = 200;
$_GET['index'] = "*";
require '../vendor/autoload.php';
require "../src/diskover/Diskover.php";
$disabled_indices = array();
$indices_filtered = array();

// go through each index and determine which are done indexing
foreach ($es_index_info as $key => $val) {
    // check if index not in all_index_info
    if (!array_key_exists($key, $all_index_info)) {
        continue;
    }
    
    // continue if index creation time is older than max age
    if ($maxage_str != 'all') {
        $starttime = $all_index_info[$key]['start_at'];
        $maxage = gmdate("Y-m-d\TH:i:s", strtotime($maxage_str));
        if ($maxage > $starttime) {
            continue;
        }
    }

    // continue if index name does not match
    if (isset($_GET['namecontains']) && $_GET['namecontains'] != '') {
        if (strpos($key, $_GET['namecontains']) === false) {
            continue;
        }
    }

    $indices_filtered[] = $key;

    // determine if index is still being crawled
    // if still being indexed, grab the file/dir count and size of top path and totals and add the index to disabled_indices list

    // Set the path finished to true
    if (isset($all_index_info[$key]['end_at'])) {
        $all_index_info[$key]['finished'] = 1;
    } else {
        $all_index_info[$key]['end_at'] = null;

        $diff = abs(strtotime($all_index_info[$key]['start_at']) - strtotime(gmdate("Y-m-d\TH:i:s")));
        $all_index_info[$key]['crawl_time'] = $diff;

        $searchParams = [];
        $searchParams['index'] = $key;

        $escaped_path = escape_chars($all_index_info[$key]['path']);
        if ($escaped_path === '\/') {  // root
            $pp_query = 'parent_path:' . $escaped_path . '*';
        } else {
            $pp_query = 'parent_path:(' . $escaped_path . ' OR ' . $escaped_path . '\/*)';
        }

        $searchParams['body'] = [
            'size' => 0,
            'track_total_hits' => true,
            'aggs' => [
                'total_size' => [
                    'sum' => [
                        'field' => 'size'
                    ]
                ]
            ],
            'query' => [
                'query_string' => [
                    'query' => $pp_query . ' AND type:"file"'
                ]
            ]
        ];

        // catch any errors searching doc in indices which might be corrupt or deleted
        try {
            $queryResponse = $client->search($searchParams);
        } catch (Exception $e) {
            error_log('ES error: ' .$e->getMessage());
            $ifk = array_search($key, $indices_filtered);
            unset($indices_filtered[$ifk]);
            unset($all_index_info[$key]);
            continue;
        }

        // Get count of file docs
        $all_index_info[$key]['file_count'] = $queryResponse['hits']['total']['value'];
        // Get size of file docs
        $all_index_info[$key]['file_size'] = $queryResponse['aggregations']['total_size']['value'];

        $searchParams = [];
        $searchParams['index'] = $key;

        $searchParams['body'] = [
            'size' => 0,
            'track_total_hits' => true,
            'query' => [
                'query_string' => [
                    'query' => $pp_query . ' AND type:"directory"'
                ]
            ]
        ];

        $queryResponse = $client->search($searchParams);

        // Get total count of directory docs
        $all_index_info[$key]['dir_count'] = $queryResponse['hits']['total']['value'];

        // Set the path finished to false
        $all_index_info[$key]['finished'] = 0;

        // Add to index totals
        $all_index_info[$key]['totals']['filecount'] += $all_index_info[$key]['file_count'];
        $all_index_info[$key]['totals']['filesize'] += $all_index_info[$key]['file_size'];
        $all_index_info[$key]['totals']['dircount'] += $all_index_info[$key]['dir_count'];
        $all_index_info[$key]['totals']['crawltime'] += $all_index_info[$key]['crawl_time'];
    
        # add index to disabled_indices list
        $disabled_indices[] = $key;
    }
}

$estime = number_format(microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"], 4);

if (!empty($indices_filtered)) {
    $index = getCookie('index');
    foreach ($indices_filtered as $key => $val) {
	$indexval = $all_index_info[$val];
	$newest = ($val == $latest_completed_index) ? "<i title=\"newest\" style=\"color:#FFF\" class=\"glyphicon glyphicon-calendar\"></i>" : "";
	$checked = ($val == $index) ? 'checked' : '';
	$disabled = (in_array($val, $disabled_indices)) ? true : false;
	$startat = utcTimeToLocal($indexval['start_at']);
	$endat = (is_null($indexval['end_at'])) ? "indexing..." : utcTimeToLocal($indexval['end_at']);
	$filecount = number_format($indexval['file_count']);
	$dircount = number_format($indexval['dir_count']);
	$crawltime = (is_null($indexval['crawl_time'])) ?: secondsToTime($indexval['crawl_time']);
	$inodessec = number_format(($indexval['file_count'] + $indexval['dir_count']) / $indexval['crawl_time'], 1);
	$filesize = formatBytes($indexval['file_size']);
	$indexsize = formatBytes($indexval['totals']['indexsize']);
	echo "index:\"$val\"" . 
	", path:\"" . $indexval['path']. "\"" .
	", start: \"$startat\"" . 
	", end:\"$endat\"" . 
	", crawl_time:\"$crawltime\"".
	", file_count:\"$filecount\"" .
	", dir_count:\"$dircount\"" .
	", inode_rate:\"$inodessec\"" .
	", file_size:\"$filesize\"" . 
	", index_size:\"$indexsize\"" . "\n";
    }
}
exit;


        foreach ($es_index_info as $key => $val) {
		print "[$key]\n";
		$o = $val;
		foreach ($o as $k => $v) {
			print "\t$k: $v ";
		}
		print "\n";
		$f = $o["fields"];
#		foreach ($f as $fk => $fv) {
#			print "\t$fk -> $fv ";
#		}
#		print "\n";
	}
#		print "\n";

        foreach ($all_index_info as $key => $val) {
		print "[$key]\n";
		$o = $val;
		foreach ($o as $k => $v) {
			print "\t$k: $v ";
		}
		print "\n";
		$f = $o["fields"];
#		foreach ($f as $fk => $fv) {
#			print "\t$fk -> $fv ";
#		}
#		print "\n";
	}
#		print "\n";

?>
