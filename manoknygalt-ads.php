<?php
/*
Plugin Name: ManoKnyga.lt ads
Plugin URI: http://www.manoknyga.lt/rekomenduoti.html
Description: Šaunus pluginas rodantis ManoKnygos.lt reklamą jūsų bloge ir leidžiantis uždirbti manoknyga.lt litų iš kiekvieno jūsų atvesto kliento.
Version: 0.3
Author: Juozas Kaziukėnas
Author URI: http://www.juokaz.com/
*/

define('MANOKNYGA_CACHE_TIME', 60*60*15);
define('MANOKNYGA_TIMEOUT', 3);

class ManoKnygaPluginLoader {

    function Enable() {

        // This registers the widget. About time.
        register_sidebar_widget('ManoKnyga.lt', array('ManoKnygaPluginLoader', 'PrintWidget'));

        // This registers the (optional!) widget control form.
        register_widget_control('ManoKnyga.lt', array('ManoKnygaPluginLoader', 'PrintControl'), 400, 500);

        add_action('admin_menu', array('ManoKnygaPluginLoader', 'SetupAdminMenu'));
    }

    static function setupAdminMenu () {
        add_options_page('ManoKnyga.lt', 'ManoKnyga.lt', 8, basename(__FILE__), array('ManoKnygaPluginLoader', 'PrintAdmin'));
    }

    // This function prints the sidebar widget--the cool stuff!
    static function PrintWidget ($args) {

        // $args is an array of strings which help your widget
        // conform to the active theme: before_widget, before_title,
        // after_widget, and after_title are the array keys.
        extract($args);

        if (!($banner = self::getBannerData()))
        return false;

        $options = get_option('manoknyga_widget');
        $title = htmlspecialchars($options['title'], ENT_QUOTES);

        // It's important to use the $before_widget, $before_title,
        // $after_title and $after_widget variables in your output.
        echo $before_widget;
        echo $before_title . $title . $after_title;
        echo $banner->getData();
        echo $after_widget;
    }

    // This is the function that outputs the form to let users edit
    // the widget's title and so on. It's an optional feature, but
    // we'll use it because we can!
    static function PrintControl () {

        // Collect our widget's options.
        $options = get_option('manoknyga_widget');

        // This is for handing the control form submission.
        if ( $_POST['manoknyga-submit'] ) {
            // Clean up control form submission options
            $newoptions['title'] = strip_tags(stripslashes($_POST['manoknyga-title']));

            // If original widget options do not match control form
            // submission options, update them.
            if ( $options != $newoptions ) {
                $options = $newoptions;
                update_option('manoknyga_widget', $options);
            }
        }

        // Format options as valid HTML. Hey, why not.
        $title = htmlspecialchars($options['title'], ENT_QUOTES);

        // The HTML below is the control form for editing options.
        echo <<<EOL
                <div>
                <label for="manoknyga-title" style="line-height:35px;display:block;">Pavadinimas: <input type="text" id="manoknyga-title" name="manoknyga-title" value="$title" /></label>
                <input type="hidden" name="manoknyga-submit" id="manoknyga-submit" value="1" />
                </div>
EOL;
    }

    static 	function PrintAdmin () {

        $options = get_option('manoknyga');

        // Easiest test to see if we have been submitted to
        if(isset($_POST['submit'])) {
            // Clean up control form submission options
            $newoptions['ref_id'] = strip_tags(stripslashes($_POST['ref_id']));
            if (is_array($_POST['categories']))
            $newoptions['categories'] = strip_tags(stripslashes(implode(',', $_POST['categories'])));
            $newoptions['size'] = strip_tags(stripslashes($_POST['size']));

            // If original widget options do not match control form
            // submission options, update them.
            if ( $options != $newoptions ) {
                $options = $newoptions;
                update_option('manoknyga', $options);
                $ol_flash = "Nustatymai išsaugoti.";
            }
        } // endif

        if ($ol_flash != '') echo '<div id="message"class="updated fade"><p>' . $ol_flash . '</p></div>';

        $params = get_option('manoknyga_params_cache');

        if (time()-$params['time'] > MANOKNYGA_CACHE_TIME) // Valanda
        {
            $params = self::getContents('http://www.manoknyga.lt/reklama/duomenys.html');

            if ($params)
            $params = unserialize($params);

            $params['time'] = time();

            update_option('manoknyga_params_cache', $params);
        }

        $options['categories'] = explode(',', $options['categories']);

        if (!is_array($options['categories']))
        $options['categories'] = array();

        echo '<div class="wrap">';
        echo '<h2>ManoKnyga.lt reklamos nustatymai</h2>';
        echo '<p>Šis įskiepis skirtas paprastam ir patogiam ManoKnyga.lt knygų reklamos valdymui</p>
                <form action="" method="post">
                <input type="hidden" name="redirect" value="true" />
                <ol>
                <li>Pirmiausia, <a href="https://www.manoknyga.lt/prisijungti.html" target="_blank">susikurk vartotoją</a> ManoKnyga.lt svetainėje. Šiame vartotojuje kaupsis uždirbti pinigai ir matysis visa reklamos statistika.</li>
                <li>Kai susikūrei vartotoją, nukopijuok Rekomendavimo ID iš <a href="https://www.manoknyga.lt/rekomenduoti.html#Reklama" target="_blank">šio</a> puslapio:<br /><input type="text" name="ref_id" value="' . htmlentities($options['ref_id']) . '" size="10" /></li>
                <li>Nurodyk katetoriją(-as):<br /><select name="categories[]" multiple="true" size="10" style="width: none; height: 150px;">';

        foreach ($params['categories'] as $id => $category)
        echo '<option value="' . $id . '"' . (in_array($id, $options['categories']) ? 'selected="selected"' : '') . '>' . $category . '</option>';

        echo '</select></li>
                <li>Ir reklamos dydį:<br /><select name="size">';

        foreach ($params['sizes'] as $size)
        echo '<option value="' . $size . '"' . ($options['size'] == $size ? 'selected="selected"' : '') . '>' . htmlentities($size) . '</option>';

        echo '</select></li>
                </ol>
                <p class="submit">
                        <input type="submit" value="Išsaugoti" class="button-primary" name="Submit"/>
                </p>
                </form>';
        echo '</div>';
    }


    static function getBannerData () {

        // Collect data from cache
        if ($cache = get_option('manoknyga_widget_cache')) {
            if (time()-$cache->getTime() < MANOKNYGA_CACHE_TIME)
            return $cache;
        }

        // Collect our widget's options, or define their defaults.
        $options = get_option('manoknyga');
        $ref_id = urlencode($options['ref_id']);
        $categories = urlencode($options['categories']);
        $size = urlencode($options['size']);

        if (!$categories || !$size)
        return false;

        $params = get_option('manoknyga_params_cache');

        $data = sprintf($params['code'], $ref_id, $categories, $size);

        $cache = new ManoKnygaBanner($data, time());

        update_option('manoknyga_widget_cache', $cache);

        return $cache;
    }

    static function getContents($url) {

        $crl = curl_init();
        $timeout = MANOKNYGA_TIMEOUT;
        curl_setopt ($crl, CURLOPT_URL,$url);
        curl_setopt ($crl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt ($crl, CURLOPT_CONNECTTIMEOUT, $timeout);
        $ret = curl_exec($crl);
        curl_close($crl);
        return $ret;
    }

    static function GetBaseName() {
        return plugin_basename(__FILE__);
    }

    static function GetPluginFile() {
        return __FILE__;
    }

    static function GetVersion() {

        if(!function_exists('get_plugin_data')) {
            if(file_exists(ABSPATH . 'wp-admin/includes/plugin.php')) require_once(ABSPATH . 'wp-admin/includes/plugin.php'); //2.3+
            else if(file_exists(ABSPATH . 'wp-admin/admin-functions.php')) require_once(ABSPATH . 'wp-admin/admin-functions.php'); //2.1
            else return "0.ERROR";
        }
        $data = get_plugin_data(__FILE__);
        return $data['Version'];
    }
}

function manoknyga_banner () {
    echo ManoKnygaPluginLoader::getBannerData()->getData();
}

// Delays plugin execution until Dynamic Sidebar has loaded first.
add_action('plugins_loaded', array('ManoKnygaPluginLoader', 'Enable'));

class ManoKnygaBanner {

    private $_data = null;

    private $_time = null;

    function __construct($data, $time) {

        $this->_data = $data;
        $this->_time = $time;
    }

    function getData() {
        return $this->_data;
    }

    function getTime() {
        return $this->_time;
    }
}
?>
