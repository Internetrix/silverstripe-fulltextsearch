<?php

global $databaseConfig;
if (isset($databaseConfig['type'])) SearchUpdater::bind_manipulation_capture();

Deprecation::notification_version('1.0.0', 'fulltextsearch');

Solr::configure_server(array(
		'host' => 'localhost',
		'indexstore' => array(
				'mode' => 'file',
				'path' => BASE_PATH . '/.solr'
		)
));