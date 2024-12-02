<h1>Tennis Event Settings for <?php bloginfo('name');?></h1>
<?php 
    settings_errors();
    function tennis_settings_admin_notices() {
        settings_errors('gwtennissettings');
    } 
    add_action('admin_notices', 'tennis_settings_admin_notices');
?>
<form method="post" action="options.php">
    <?php settings_fields('gw-tennis-settings-group'); ?>
    <?php do_settings_sections('gwtennissettings'); ?>
    <?php submit_button(
        'Save Tennis Settings' // some text
        // ,'' // type
        // ,'' // name
        // ,'' // wrap
        // ,'' // other attributes
        );
     ?>
    <div id="tennis-event-message"></div>
</form>