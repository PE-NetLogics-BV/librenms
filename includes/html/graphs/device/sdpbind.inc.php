<?php

$rrd_filename = Rrd::name($device['hostname'], 'sdpbind-' . $vars['traffic_id']);

$ds_in = 'INOCTETS';
$ds_out = 'OUTOCTETS';

$unit_text = 'Bits/sec';

require 'includes/html/graphs/generic_data.inc.php';
