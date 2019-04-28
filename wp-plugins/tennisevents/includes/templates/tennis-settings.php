<h1> Options for <?php bloginfo('name');?></h1>
<?php settings_errors(); ?>
<form method="post" action="options.php">
    <?php settings_fields('gw-tennis-settings-group'); ?>
    <?php do_settings_sections('gwtennissettings'); ?>
    <?php submit_button(
        //  '' // some text
        // ,'' // type
        // ,'' // name
        // ,'' // wrap
        // ,'' // other attributes
    ); ?>
</form>