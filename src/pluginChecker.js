$(document).ready(function() {
    $.post(
        '/wp-content/plugins/pluginchecker/pluginchecker.php',
        {
            pluginCheck: 'true'
        }
    ).done(function(data) {
        console.log(data);
    });
});
