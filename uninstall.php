<?php
//if uninstall not called from WordPress exit
if ( !defined( 'WP_UNINSTALL_PLUGIN' ) )
    exit ();

// else cleanup
delete_option( 'attachment_cruncher' );
?>