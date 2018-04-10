<?php

WP_CLI::add_command( 'tennis draw', 'DrawCommands' );

/**
 * Implements all commands for manipulating tennis draw objects
 */
class DrawCommands extends WP_CLI_Command {

    /**
     * Move a match to a given destination
     * The target club and event must first be set
     * using 'tennis env'
     *
     * ## EXAMPLES
     *
     *     wp tennis move <round> <source> <destination>
     * 
     * ## EXAMPLES
     *
     *     wp tennis move 1 10 13
     *
     * @when after_wp_load
     */
    function move( $args, $assoc_args ) {

        CmdlineSupport::preCondtion();
    }

    /**
     * Move a match forward by given steps
     * The target club and event must first be set
     * using 'tennis env'
     *
     * ## EXAMPLES
     *
     *     wp tennis moveup <round> <source> <steps>
     *
     * @when after_wp_load
     */
    function moveup( $args, $assoc_args ) {

        CmdlineSupport::preCondtion();
    }
    
    /**
     * Move a match backward by given steps
     * The target club and event must first be set
     * using 'tennis env'
     *
     * ## EXAMPLES
     *
     *     wp tennis movedown <round> <source> <steps>
     *
     * @when after_wp_load
     */
    function movedown( $args, $assoc_args ) {

        CmdlineSupport::preCondtion();
    }
}
