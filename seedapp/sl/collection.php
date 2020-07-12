<?php

/* Seed collection manager
 *
 * Copyright 2020 Seeds of Diversity Canada
 */

define( "SEEDROOT", "../../" );
define( "SEED_LOG_DIR", SEEDROOT."../logs" );

$config_KFDB = ['seeds1' => ['kfdbUserid'   => 'seeds',
                            'kfdbPassword' => 'seeds',
                            'kfdbDatabase' => 'seeds']];


include_once( SEEDROOT."seedConfig.php" );


$oApp = SEEDConfig_NewAppConsole_LoginNotRequired( [] );




