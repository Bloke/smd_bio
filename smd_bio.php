<?php

// This is a PLUGIN TEMPLATE for Textpattern CMS.

// Copy this file to a new name like abc_myplugin.php.  Edit the code, then
// run this file at the command line to produce a plugin for distribution:
// $ php abc_myplugin.php > abc_myplugin-0.1.txt

// Plugin name is optional.  If unset, it will be extracted from the current
// file name. Plugin names should start with a three letter prefix which is
// unique and reserved for each plugin author ("abc" is just an example).
// Uncomment and edit this line to override:
$plugin['name'] = 'smd_bio';

// Allow raw HTML help, as opposed to Textile.
// 0 = Plugin help is in Textile format, no raw HTML allowed (default).
// 1 = Plugin help is in raw HTML.  Not recommended.
# $plugin['allow_html_help'] = 1;

$plugin['version'] = '0.50';
$plugin['author'] = 'Stef Dawson';
$plugin['author_uri'] = 'http://stefdawson.com/';
$plugin['description'] = 'Customisable user biographies / profile info.';

// Plugin load order:
// The default value of 5 would fit most plugins, while for instance comment
// spam evaluators or URL redirectors would probably want to run earlier
// (1...4) to prepare the environment for everything else that follows.
// Values 6...9 should be considered for plugins which would work late.
// This order is user-overrideable.
$plugin['order'] = '5';

// Plugin 'type' defines where the plugin is loaded
// 0 = public              : only on the public side of the website (default)
// 1 = public+admin        : on both the public and admin side
// 2 = library             : only when include_plugin() or require_plugin() is called
// 3 = admin               : only on the admin side (no AJAX)
// 4 = admin+ajax          : only on the admin side (AJAX supported)
// 5 = public+admin+ajax   : on both the public and admin side (AJAX supported)
$plugin['type'] = '5';

// Plugin "flags" signal the presence of optional capabilities to the core plugin loader.
// Use an appropriately OR-ed combination of these flags.
// The four high-order bits 0xf000 are available for this plugin's private use
if (!defined('PLUGIN_HAS_PREFS')) define('PLUGIN_HAS_PREFS', 0x0001); // This plugin wants to receive "plugin_prefs.{$plugin['name']}" events
if (!defined('PLUGIN_LIFECYCLE_NOTIFY')) define('PLUGIN_LIFECYCLE_NOTIFY', 0x0002); // This plugin wants to receive "plugin_lifecycle.{$plugin['name']}" events

$plugin['flags'] = '2';

// Plugin 'textpack' is optional. It provides i18n strings to be used in conjunction with gTxt().
// Syntax:
// ## arbitrary comment
// #@event
// #@language ISO-LANGUAGE-CODE
// abc_string_name => Localized String

$plugin['textpack'] = <<<EOT
#@smd_bio
smd_bio_admin_tab => Bio config
smd_bio_colsize => Column size
smd_bio_coltype => Column type
smd_bio_help => ?
smd_bio_help_unused => Unused for this Type
smd_bio_meta_add => Add bio field
smd_bio_meta_added => Bio field added
smd_bio_meta_deleted => Bio field and all data deleted from {affected}
smd_bio_meta_edit => Edit bio field
smd_bio_meta_not_added => Problem adding bio field: check name is not already used
smd_bio_meta_not_deleted => Problem deleting bio field: it may still exist
smd_bio_meta_updated => Bio field "{name}" updated
smd_bio_meta_update_failed => Failed to save bio field "{name}"
smd_bio_meta_update_partial => Partially saved bio field "{name}"
smd_bio_more => More
smd_bio_position => Position
smd_bio_size => Size
smd_bio_sizehelp_image => The x,y dimensions of the image/thumbnail on the Admin->Users tab. If only one value is given, the image will be square. If either value is omitted, the dimensions stored in the database will be used
smd_bio_sizehelp_numrange => Up to three comma-separated values specifying the: 1) minimum, 2) maximum, 3) permitted step of the input
smd_bio_sizehelp_text => Up to two comma-separated values specifying the number of characters: 1) of the input box on the Admin->Users tab, 2) the user is allowed to enter
smd_bio_sizehelp_textarea => Up to two comma-separated values specifying the dimensions of the textarea in characters: 1) Width, 2) Height
smd_bio_tbl_installed => Tables installed.
smd_bio_tbl_not_installed => Tables NOT installed.
smd_bio_tbl_not_removed => Tables NOT removed.
smd_bio_tbl_removed => Tables removed.
smd_bio_valhelp_image => Limit the images in the dropdown to the ones in this given parent image category. If omitted, all images are available
smd_bio_valhelp_lrc => Comma- or newline-separated list of available options in the set. If you list only item labels, the names will be automatically generated (lower case, no spaces). You may specify your own names using: name1 => Label 1, name2 -> Label 2, ...
smd_bio_valhelp_text => The default value that will appear in the text box
smd_bio_valhelp_ynr => Default value of the Yes/No checkbox. 0 (or omitted) = No; 1 = Yes
smd_bio_value => Value
EOT;

if (!defined('txpinterface'))
        @include_once('zem_tpl.php');

# --- BEGIN PLUGIN CODE ---
/**
 * smd_bio
 *
 * A Textpattern CMS plugin for storing additional biographical author data:
 *  -> Create checkboxes, select lists, radio buttons, text input, image, etc fields
 *  -> Data is captured and stored when a user is created
 *  -> Allow people to manage their own profiles via smd_user_manager
 *
 * @author Stef Dawson
 * @link   http://stefdawson.com/
 *
 * @todo Stop smd_bio_iterate from trashing the bio data for future fields of the same name.
 * @todo Attempt table repair if bio/bio_meta get out of sync.
 * @todo Ability to specify wildcards/matches for fields/authors in the client tag.
 * @todo Specify tooltip extended bio information in prefs (cf. what to do about touch devices).
 */
if (txpinterface === 'admin') {
    add_privs('smd_bio', '1');
    register_tab('extensions', 'smd_bio', gTxt('smd_bio_admin_tab'));
    register_callback('smd_bio_dispatcher', 'smd_bio');
    register_callback('smd_bio_fields', 'author_ui', 'extend_detail_form');
    register_callback('smd_bio_admin_js', 'admin_side', 'head_end');

    // Note these are all pre Txp's involvement
    register_callback('smd_bio_save', 'admin', 'author_save', 1);
    register_callback('smd_bio_save', 'admin', 'author_save_new', 1);
    register_callback('smd_bio_delete', 'admin', 'admin_multi_edit', 1);
    register_callback('smd_bio_welcome', 'plugin_lifecycle.smd_bio');

    // Doesn't hurt much to add these if the plugin's not installed.
    // Since plugins are loaded in load_order and then alphabetical,
    // at this point smd_user_manager doesn't 'exist' (b < u)
    register_callback('smd_bio_save', 'smd_um', 'smd_um_save', 1);
    register_callback('smd_bio_save', 'smd_um', 'smd_um_save_new', 1);
    register_callback('smd_bio_delete', 'smd_um', 'smd_um_multi_edit', 1);
} elseif (txpinterface === 'public') {
    if (class_exists('\Textpattern\Tag\Registry')) {
        Txp::get('\Textpattern\Tag\Registry')
            ->register('smd_bio_author')
            ->register('smd_bio_info')
            ->register('smd_bio_data')
            ->register('smd_bio_iterate')
            ->register('smd_bio_articles')
            ->register('smd_if_bio')
            ->register('smd_if_bio_is')
            ->register('smd_if_bio_first_author')
            ->register('smd_if_bio_last_author');
    }
}

register_callback('smd_bio_form_submit', 'mem_form.submit');

// Intercept image and extended bio display on Admin->Users panel
$smd_bio_step = gps('smd_bio_step');
if ($smd_bio_step == 'smd_bio_get_image') {
    smd_bio_get_image();
}
if ($smd_bio_step == 'smd_bio_get_ebio') {
    smd_bio_get_ebio();
}

if (!defined('SMD_BIO')) {
    define("SMD_BIO", 'smd_bio');
}
if (!defined('SMD_BIO_META')) {
    define("SMD_BIO_META", 'smd_bio_meta');
}

// -------------------------------------------------------------
function smd_bio_get_styles() {
    $smd_bio_styles = array(
        'meta' =>
         '.smd_bio_toggler { display:none; }',
        'tooltip' =>
         '#tooltip { position:absolute; border:1px solid #333; background:#f7f5d1; padding:10px 15px; opacity:.9; color:#333; display:none; max-width:60%; }',
    );
    return $smd_bio_styles;
}

// -------------------------------------------------------------
// Install/uninstall jumpoff point
function smd_bio_welcome($evt, $stp) {
    $msg = '';
    switch ($stp) {
        case 'installed':
            smd_bio_table_install(0);
            $msg = 'Pimp your users';
            break;
        case 'deleted':
            smd_bio_table_remove(0);
            break;
    }
    return $msg;
}

// ************************
// BIO CONFIGURATION
// ------------------------
function smd_bio_dispatcher($evt, $stp) {
    $available_steps = array(
        'smd_bio_config'          => false,
        'smd_bio_table_install'   => false,
        'smd_bio_table_remove'    => false,
        'smd_bio_meta_add'        => true,
        'smd_bio_multi_edit'      => true,
        'smd_bio_meta_save'       => true,
        'smd_bio_save_pane_state' => true,
    );

    if (!$stp or !bouncer($stp, $available_steps)) {
        $stp = 'smd_bio_config';
    }
    $stp();
}

// ------------------------
// The Extensions->Bio config panel, made up of two areas: the edit pane and the list pane
function smd_bio_config($msg='') {
    smd_bio_table_install(0);

    pagetop(gTxt('smd_bio_admin_tab'), $msg);

    echo n.'<div id="smd_bio_container" class="txp-container">'.
        n.smd_bio_meta_edit().
        n.smd_bio_meta_list().
        n.'</div>';
}

// ------------------------
function smd_bio_meta_edit() {
    $smd_bio_types = smd_bio_get_types();
    $smd_bio_coltypes = smd_bio_get_coltypes();

    $vars = array('step', 'id', 'title', 'name', 'type', 'coltype', 'colsize', 'size', 'val', 'position');
    $rs = array();

    extract(gpsa($vars));
    $colsize = (int)$colsize;

    if ($id && $step == 'meta_edit') {
        $id = assert_int($id);
        $rs = safe_row('*', SMD_BIO_META, "id = $id");
        extract($rs);
    }

    if ($step == 'smd_bio_meta_save' || $step == 'smd_bio_meta_add' || $step == 'smd_bio_multi_edit') {
        foreach ($vars as $var) {
            $$var = '';
        }
    }

    $caption = gTxt(($step == 'meta_edit') ? 'smd_bio_meta_edit' : 'smd_bio_meta_add');

    // Make the name/val pairs for the type selectInput
    $selv = array();
    foreach ($smd_bio_types as $widx => $wval) {
        $selv[$widx] = $wval['name'];
    }

    // Make the name/val pairs for the coltype selectInput
    foreach($smd_bio_coltypes as $ctype => $cdata) {
        $coltypes[$ctype] = $cdata['title'];
    }

    $toggleState = get_pref('pane_smd_bio_coltype_visible') ? true : false;

    return hed($caption, 1, ' class="txp-heading"').
        form(
            '<div class="txp-edit">'.
            inputLabel('name', ($id && $step == 'meta_edit' ? strong($name) : fInput('text', 'name', $name, '', '', '', '', '', 'name')), 'name').
            inputLabel('title', fInput('text', 'title', $title, '', '', '', '', '', 'title'), 'title').
            inputLabel('smd_bio_widget_type', selectInput('type', $selv, $type, false, '', 'smd_bio_widget_type') .sp. '<a id="smd_bio_colgroup" class="txp-summary lever'.(($toggleState) ? ' expanded' : '').'" href="#">'.gTxt('smd_bio_more').'</a>', 'type').
            inputLabel('smd_bio_coltype', selectInput('coltype', $coltypes, $coltype, false, '', 'smd_bio_coltype'), 'smd_bio_coltype', '', 'txp-form-field smd_bio_coltype '.(($toggleState) ? '' : ' smd_bio_toggler')).
            inputLabel('smd_bio_colsize', fInput('number', 'colsize', $colsize, '', '', '', '', '', 'smd_bio_colsize'), 'smd_bio_colsize', '', 'txp-form-field smd_bio_coltype '.(($toggleState) ? '' : ' smd_bio_toggler')).
            inputLabel('smd_bio_size', fInput('text', 'size', $size, '', '', '', '', '', 'smd_bio_size'), 'smd_bio_size', 'smd_bio_size').
            inputLabel('smd_bio_value', text_area('val', '100', '300', $val, 'smd_bio_value'), 'smd_bio_value', 'smd_bio_val').
            inputLabel('smd_bio_position', fInput('text', 'position', $position, '', '', '', '', '', 'smd_bio_position'), 'smd_bio_position').
            graf(
                fInput('submit', 'save', gTxt('save'), 'publish'),
                array('class' => 'txp-edit-actions')
            ).

            eInput('smd_bio').
            ($id ? hInput('id', $id).hInput('name', $name).sInput('smd_bio_meta_save') : sInput('smd_bio_meta_add')).
            tag(' ', 'span', ' id="smd_bio_size_help_text" title="'.gTxt('smd_bio_sizehelp_text').'"').
            tag(' ', 'span', ' id="smd_bio_size_help_number" title="'.gTxt('smd_bio_sizehelp_numrange').'"').
            tag(' ', 'span', ' id="smd_bio_size_help_range" title="'.gTxt('smd_bio_sizehelp_numrange').'"').
            tag(' ', 'span', ' id="smd_bio_size_help_list" title="'.gTxt('smd_bio_help_unused').'"').
            tag(' ', 'span', ' id="smd_bio_size_help_multilist" title="'.gTxt('smd_bio_help_unused').'"').
            tag(' ', 'span', ' id="smd_bio_size_help_radio" title="'.gTxt('smd_bio_help_unused').'"').
            tag(' ', 'span', ' id="smd_bio_size_help_yesnoradio" title="'.gTxt('smd_bio_help_unused').'"').
            tag(' ', 'span', ' id="smd_bio_size_help_checkbox" title="'.gTxt('smd_bio_help_unused').'"').
            tag(' ', 'span', ' id="smd_bio_size_help_textarea" title="'.gTxt('smd_bio_sizehelp_textarea').'"').
            tag(' ', 'span', ' id="smd_bio_size_help_image" title="'.gTxt('smd_bio_sizehelp_image').'"').
            tag(' ', 'span', ' id="smd_bio_size_help_email" title="'.gTxt('smd_bio_sizehelp_text').'"').
            tag(' ', 'span', ' id="smd_bio_size_help_url" title="'.gTxt('smd_bio_sizehelp_text').'"').
            tag(' ', 'span', ' id="smd_bio_size_help_datetime" title="'.gTxt('smd_bio_sizehelp_text').'"').
            tag(' ', 'span', ' id="smd_bio_size_help_date" title="'.gTxt('smd_bio_sizehelp_text').'"').
            tag(' ', 'span', ' id="smd_bio_size_help_month" title="'.gTxt('smd_bio_sizehelp_text').'"').
            tag(' ', 'span', ' id="smd_bio_size_help_week" title="'.gTxt('smd_bio_sizehelp_text').'"').
            tag(' ', 'span', ' id="smd_bio_size_help_time" title="'.gTxt('smd_bio_sizehelp_text').'"').

            tag(' ', 'span', ' id="smd_bio_val_help_text" title="'.gTxt('smd_bio_valhelp_text').'"').
            tag(' ', 'span', ' id="smd_bio_val_help_number" title="'.gTxt('smd_bio_valhelp_text').'"').
            tag(' ', 'span', ' id="smd_bio_val_help_range" title="'.gTxt('smd_bio_valhelp_text').'"').
            tag(' ', 'span', ' id="smd_bio_val_help_list" title="'.gTxt('smd_bio_valhelp_lrc').'"').
            tag(' ', 'span', ' id="smd_bio_val_help_multilist" title="'.gTxt('smd_bio_valhelp_lrc').'"').
            tag(' ', 'span', ' id="smd_bio_val_help_radio" title="'.gTxt('smd_bio_valhelp_lrc').'"').
            tag(' ', 'span', ' id="smd_bio_val_help_yesnoradio" title="'.gTxt('smd_bio_valhelp_ynr').'"').
            tag(' ', 'span', ' id="smd_bio_val_help_checkbox" title="'.gTxt('smd_bio_valhelp_lrc').'"').
            tag(' ', 'span', ' id="smd_bio_val_help_textarea" title="'.gTxt('smd_bio_valhelp_text').'"').
            tag(' ', 'span', ' id="smd_bio_val_help_image" title="'.gTxt('smd_bio_valhelp_image').'"').
            tag(' ', 'span', ' id="smd_bio_val_help_email" title="'.gTxt('smd_bio_valhelp_text').'"').
            tag(' ', 'span', ' id="smd_bio_val_help_url" title="'.gTxt('smd_bio_valhelp_text').'"').
            tag(' ', 'span', ' id="smd_bio_val_help_datetime" title="'.gTxt('smd_bio_valhelp_text').'"').
            tag(' ', 'span', ' id="smd_bio_val_help_date" title="'.gTxt('smd_bio_valhelp_text').'"').
            tag(' ', 'span', ' id="smd_bio_val_help_month" title="'.gTxt('smd_bio_valhelp_text').'"').
            tag(' ', 'span', ' id="smd_bio_val_help_week" title="'.gTxt('smd_bio_valhelp_text').'"').
            tag(' ', 'span', ' id="smd_bio_val_help_time" title="'.gTxt('smd_bio_valhelp_text').'"').
        '</div>'
    );
}

// ------------------------
function smd_bio_meta_list() {

    $smd_bio_types = smd_bio_get_types();

    extract(gpsa(array('sort', 'dir', 'crit', 'search_method')));
    if ($sort === '') $sort = get_pref('smd_bio_meta_sort_column', 'name');
    if ($dir === '') $dir = get_pref('smd_bio_meta_sort_dir', 'asc');
    $dir = ($dir == 'desc') ? 'desc' : 'asc';

    if (!in_array($sort, array('name', 'title', 'type', 'size', 'val', 'position'))) $sort = 'position';

    $sort_sql   = $sort.' '.$dir;

    set_pref('smd_bio_meta_sort_column', $sort, 'smd_bio', 2, '', 0, PREF_PRIVATE);
    set_pref('smd_bio_meta_sort_dir', $dir, 'smd_bio', 2, '', 0, PREF_PRIVATE);

    $switch_dir = ($dir == 'desc') ? 'asc' : 'desc';

    $rs = safe_rows_start('*', SMD_BIO_META, '1=1 ORDER BY '.$sort_sql);
    $out = array();

    if ($rs) {
        $out[] =
        n. '<form id="smd_bio_form" action="index.php" method="post" name="longform" class="multi_edit_form">'.

        n.'<div class="txp-listtables">'.
        startTable('', '', 'txp-list').
        n.'<thead>'.
        tr(
            hCell(fInput('checkbox', 'select_all', 0, '', '', '', '', '', 'select_all'), '', ' scope="col" title="'.gTxt('toggle_all_selected').'" class="txp-list-col-multi-edit"').
            column_head('name', 'name', 'smd_bio', true, $switch_dir, '', '', ('name' == $sort) ? $dir : '').
            column_head('title', 'title', 'smd_bio', true, $switch_dir, '', '', ('title' == $sort) ? $dir : '').
            column_head('type', 'type', 'smd_bio', true, $switch_dir, '', '', ('type' == $sort) ? $dir : '').
            column_head(gTxt('smd_bio_size'), 'size', 'smd_bio', true, $switch_dir, '', '', ('size' == $sort) ? $dir : '').
            column_head('value', 'val', 'smd_bio', true, $switch_dir, '', '', ('val' == $sort) ? $dir : '').
            column_head(gTxt('smd_bio_position'), 'position', 'smd_bio', true, $switch_dir, '', '', ('position' == $sort) ? $dir : '')
        ).
        n.'</thead>'.
        n.'<tbody>';

        while ($a = nextRow($rs)) {
            extract(doSpecial($a));

            $out[] = tr(
                td(fInput('checkbox', 'selected[]', $name), '', 'txp-list-col-multi-edit').
                td(eLink('smd_bio', 'meta_edit', 'id', $id, $name)).
                td($title).
                td($smd_bio_types[$type]['name']).
                td($size).
                td($val).
                td($position)
            );
        }

        $out[] = '</tbody>'.
            endTable().
            n.'</div>'.
            smd_bio_multiedit_form('', $sort, $dir, $crit, $search_method).
            tInput().
            '</form>';
    }
    return join(n, $out);
}

// ------------------------
function smd_bio_multiedit_form($page, $sort, $dir, $crit, $search_method) {

    $methods = array(
        'delete' => gTxt('delete'),
    );

    return multi_edit($methods, 'smd_bio', 'smd_bio_multi_edit', $page, $sort, $dir, $crit, $search_method);
}

// ------------------------
function smd_bio_multi_edit()
{
    $selected = ps('selected');

    if (!$selected or !is_array($selected))
    {
        smd_bio();
    }

    $selected = array_map('assert_string', $selected);
    $method   = ps('edit_method');
    $changed  = array();
    $key = '';

    switch ($method)
    {
        case 'delete':
            return smd_bio_meta_del($selected);
            break;

        default:
            $key = '';
            $val = '';
            break;
    }

    smd_bio();
}

// ------------------------
function smd_bio_meta_make_list($val) {

    if (strpos(strtolower($val), 'smd_bio_fn') !== false) {
        // Special function syntax so call the designated
        // function to retrieve the value(s)
        $params = do_list($val, '|');
        array_shift($params); // Remove SMD_BIO_FN
        $fn = array_shift($params);

        $fnbits = do_list($fn, '::');
        $fncall = (isset($fnbits[1])) ? array($fnbits[0], $fnbits[1]) : $fnbits[0];
        $val = call_user_func_array($fncall, $params);

        if (is_array($val)) {
            // Fake a string list of name => val pairs
            $out = array();
            foreach ($val as $idx => $item) {
                $out[] = str_replace(',', '&#44;', $idx . ' => ' . $item); // Preserve commas by encoding them
            }
            $val = join(', ', $out);
        }
    }

    // Parse the values
    $wvals = do_list($val, '\r\n');
    if (count($wvals) == 1) {
        $wvals = do_list($val);
    }
    return join(', ', doArray($wvals, 'trim'));
}

// ------------------------
function smd_bio_meta_save() {
    $smd_bio_coltypes = smd_bio_get_coltypes();
    $smd_bio_unused = smd_bio_get_unused();

    extract(doSlash(psa(array('id', 'name', 'title', 'type', 'coltype', 'colsize', 'size', 'position'))));
    $val = ps('val'); // Defer doSlash() until later

    $id = assert_int($id);
    $val = doSlash(smd_bio_meta_make_list($val));
    $hasDefault = $smd_bio_coltypes[$coltype]['has_default'];

    // Validate the input to ensure items are emptied for types where they're unused
    foreach ($smd_bio_unused as $unused => $excludes) {
        if (in_array($type, $excludes)) {
            $$unused = '';
        }
    }

    // Validate the input to ensure items that require a column size have one...
    if (empty($colsize) && $smd_bio_coltypes[$coltype]['size_req'] !== false) {
        $colsize = $smd_bio_coltypes[$coltype]['size_req'];
    }

    // ... and that ones that don't require one are removed
    if ($smd_bio_coltypes[$coltype]['size_req'] === false) {
        $colsize = null;
    }

    // Try to adjust column type/size if applicable
    $rs = safe_alter(SMD_BIO, "CHANGE `$name` `$name` $coltype" . (($colsize) ? "($colsize)" : '') . " NULL" . (($hasDefault) ? " DEFAULT NULL" : ''));

    if ($rs) {
        $rs = safe_update(SMD_BIO_META, "
            title = '$title',
            type = '$type',
            size = '$size',
            coltype = '$coltype',
            " . (($colsize) ? "colsize = $colsize," : '') . "
            val = '$val',
            position = '$position'",
            "id = $id"
        );

        if ($rs) {
            $msg = gTxt('smd_bio_meta_updated', array('{name}' => $name));
        } else {
            $msg = array(gTxt('smd_bio_meta_update_partial', array('{name}' => $name)), E_WARNING);
        }
    } else {
        $msg = array(gTxt('smd_bio_meta_update_failed', array('{name}' => $name)), E_WARNING);
    }

    smd_bio_config($msg);
}

// ------------------------
function smd_bio_meta_add() {
    $smd_bio_coltypes = smd_bio_get_coltypes();
    $smd_bio_unused = smd_bio_get_unused();

    extract(doSlash(psa(array('name', 'title', 'type', 'coltype', 'colsize', 'size', 'position'))));
    $val = ps('val'); // Defer doSlash() until later

    // Use the title as name if it's omitted
    if ($name === '' && $title !== '') {
        $name = $title;
    }

    $name = smd_bio_sanitize($name);

    if (!empty($name) && smd_bio_meta_check($name)) {
        $size = (empty($size)) ? 25 : $size;
        $hasDefault = $smd_bio_coltypes[$coltype]['has_default'];

        // Validate the input to ensure items are emptied for types where they're unused
        foreach ($smd_bio_unused as $unused => $excludes) {
            if (in_array($type, $excludes)) {
                $$unused = '';
            }
        }

        // Validate the input to ensure items that require a column size have one...
        if (empty($colsize) && $smd_bio_coltypes[$coltype]['size_req'] !== false) {
            $colsize = $smd_bio_coltypes[$coltype]['size_req'];
        }
        // ... and that ones that don't require one are removed
        if ($smd_bio_coltypes[$coltype]['size_req'] === false) {
            $colsize = null;
        }

        $ret = safe_alter(SMD_BIO, "ADD `$name` $coltype" . (($colsize) ? "($colsize)" : '') . " NULL" . (($hasDefault) ? " DEFAULT NULL" : ''));
        $val = doSlash(smd_bio_meta_make_list($val));

        if ($ret) {
            $rs = safe_insert(SMD_BIO_META, "
                name = '$name',
                title = '$title',
                type = '$type',
                size = '$size',
                coltype = '$coltype',
                " . (($colsize) ? "colsize = $colsize," : '') . "
                val = '$val',
                position = '$position'
            ");

            if ($rs) {
                smd_bio_config(gTxt('smd_bio_meta_added'));
                return;
            }
        }
    }

    smd_bio_config(array(gTxt('smd_bio_meta_not_added'), E_ERROR));
}

// ------------------------
function smd_bio_meta_del($names) {

    $changed = array();

    $names  = $names ? array_map('assert_string', $names) : array(assert_string(ps('name')));
    $message = '';

    foreach ($names as $name) {
        $exists = smd_bio_meta_check($name);
        $ret = @safe_alter(SMD_BIO, "DROP COLUMN `$name`");
        if ($ret || $exists) {
            $ret = safe_delete(SMD_BIO_META, "name='$name'");
            $changed[] = $name;
        }
    }
    smd_bio_config(gTxt('smd_bio_meta_deleted', array('{affected}' => join(', ', $changed))));
}

// ------------------------
function smd_bio_meta_check($col) {
    $ucols = getThings('describe `'.PFX.'txp_users`');
    $bcols = getThings('describe `'.PFX.SMD_BIO.'`');
    $cols = array_merge($ucols, $bcols);
    return (!in_array($col, $cols));
}

// ************************
// ADMIN -> USERS PANEL
// ------------------------
function smd_bio_fields($evt, $stp, $mt, $data) {
    global $smd_um_event, $txp_user;

    $out = $vals = array();

    $rstep = gps('step');
    if(smd_bio_table_exist()) {
        if (in_array($rstep, array('', 'author_edit', 'smd_um_edit', 'smd_um_save', 'smd_um_change_pass'))) {
            extract(gpsa(array('user_id')));

            if (empty($user_id) && !has_privs('smd_um.usr.create')) {
                // This is a self-edit from smd_user_manager, thus the user_id has not been sent
                $user_id = safe_field('user_id','txp_users',"name = '".doSlash($txp_user)."'");
            }
            // Shame we have to double de-clutch here but we can't index on user_id unfortunately ('cos when
            // inserting new users, the bio functions run _BEFORE_ an auto_increment ID has been generated by Txp)
            $uname = safe_field('name','txp_users',"user_id = '".doSlash($user_id)."'");
            $vals = safe_row('*', SMD_BIO, "user_ref='".doSlash($uname)."'");
        }

        $widgets = safe_rows('*', SMD_BIO_META, '1=1 ORDER BY position');

        foreach ($widgets as $widget) {
            $val = ($vals && isset($vals[$widget['name']])) ? $vals[$widget['name']] : $widget['val'];
            $title = ($widget['title']) ? $widget['title'] : $widget['name'];
            $sizeopts = do_list($widget['size']);
            $size1 = $sizeopts[0];
            $size2 = (isset($sizeopts[1])) ? $sizeopts[1] : '';
            $size3 = (isset($sizeopts[2])) ? $sizeopts[2] : '';
            $name = 'smd_bio_'.$widget['name'];
            switch($widget['type']) {
                case 'list':
                    $selv = smd_bio_splitval($widget['val']);
                    list($selv, $dflt) = smd_bio_get_default($selv, $val);
                    $out[] = inputLabel(
                        $name,
                        selectInput($name, $selv, $dflt, false, '', $name),
                        $title,
                        '',
                        'txp-form-field smd_bio_select '.$name
                    );
                    break;
                case 'multilist':
                    $val = ($vals && isset($vals[$widget['name']])) ? $vals[$widget['name']] : '';
                    $selv = smd_bio_splitval($widget['val']);
                    list($selv, $dflt) = smd_bio_get_default($selv, $val);
                    $use_val = (isset($vals[$widget['name']]) && ($vals[$widget['name']] !== '' || $vals[$widget['name']] !== null)) ? $val : $dflt; // Don't use defaults if this field has been previously saved
                    $selectedVals = do_list($use_val);
                    $items = array();
                    $items[] = '<select name="'.$name.'" class="list multiple" multiple="multiple" onchange="smd_bio_multisel(\''.$name.'\');">';
                    foreach ($selv as $idx => $lbl) {
                        // Not using selectInput() because it doesn't support multiples
                        $items[] = '<option value="ms_'.$idx.'" '.((in_array($idx, $selectedVals)) ? ' selected="selected"' : '') . '>' . $lbl . '</option>';
                    }
                    $items[] = '</select>';
                    $out[] = inputLabel(
                        $name,
                        join(n,$items).fInput('hidden',$name,$use_val,'','','','','',$name),
                        $title,
                        '',
                        'txp-form-field smd_bio_select '.$name
                    );
                    break;
                case 'radio':
                    $selv = smd_bio_splitval($widget['val']);
                    list($selv, $dflt) = smd_bio_get_default($selv, $val);
                    $out[] = inputLabel(
                        $name,
                        radioSet($selv, $name, $dflt),
                        $title,
                        '',
                        'txp-form-field smd_bio_radio '.$name
                    );
                    break;
                case 'yesnoradio':
                    $out[] = inputLabel(
                        $name,
                        yesnoRadio($name, $val),
                        $title,
                        '',
                        'txp-form-field smd_bio_radio '.$name
                    );
                    break;
                case 'checkbox':
                    $val = ($vals && isset($vals[$widget['name']])) ? $vals[$widget['name']] : '';
                    $selv = smd_bio_splitval($widget['val']);
                    list($selv, $dflt) = smd_bio_get_default($selv, $val);
                    $use_val = (isset($vals[$widget['name']]) && ($vals[$widget['name']] !== '' || $vals[$widget['name']] !== null)) ? $val : $dflt; // Don't use defaults if this field has been previously saved
                    $checkedVals = do_list($use_val);
                    $items = array();
                    foreach ($selv as $idx => $lbl) {
                        // Not using checkbox() because it doesn't support onclick in 4.5.x
                        $items[] = '<input type="checkbox" name="cb_'.$name.'" value="'.$idx.'"'.((in_array($idx, $checkedVals)) ? ' checked="checked"' : '') . ' class="checkbox" onclick="smd_bio_checkbox(\''.$name.'\');" />'.$lbl;
                    }
                    $out[] = inputLabel(
                        $name,
                        join('', $items).fInput('hidden',$name,$use_val,'','','','','',$name),
                        $title,
                        '',
                        'txp-form-field smd_bio_checkbox '.$name
                    );
                    break;
                case 'textarea':
                    // Not using text_area() because it doesn't have class attribute in 4.5.x
                    $size1 = ($size1 == '' || $size1 == 0) ? 40 : $size1;
                    $size2 = ($size2 == '' || $size2 == 0) ? 5 : $size2;
                    $out[] = inputLabel(
                        $name,
                        '<textarea id="'.$name.'" name="'.$name.'" class="smd_bio_textarea" cols="'.$size1.'" rows="'.$size2.'">'.txpspecialchars($val).'</textarea>',
                        $title,
                        '',
                        'txp-form-field txp-form-field-textarea smd_bio_textarea '.$name
                    );
                    break;
                case 'image':
                    $parent = $widget['val'];
                    $val = ($vals && isset($vals[$widget['name']])) ? $vals[$widget['name']] : '';
                    $where = ($parent) ? "category='".doSlash($parent)."'" : '1=1';
                    $tree = safe_rows('*', 'txp_image', $where. ' ORDER BY name');
                    $selv = array();
                    foreach ($tree as $row) {
                        $selv[$row['id']] = $row['name'];
                    }
                    $out[] = inputLabel(
                        $name,
                        '<input type="text" value="'.txpspecialchars($val).'" id="'.$name.'" name="'.$name.'" size='.INPUT_XSMALL.' class="smd_bio_image_id input_xsmall" />'.selectInput($name.'_list', $selv, $val, true, '').'<span class="smd_bio_image"></span><span class="smd_bio_image_data" title="'.(($size1) ? $size1 : '').','.(($size2) ? $size2 : $size1).'"></span>',
                        $title,
                        '',
                        'txp-form-field smd_bio_image_cell '.$name
                    );
                    break;
                case 'number':
                case 'range':
                    $min = ($size1 == '') ? '' : " min={$size1}";
                    $max = ($size2 == '') ? '' : " max={$size2}";
                    $jmp = ($size3 == '') ? '' : " step={$size3}";
                    $out[] = inputLabel(
                        $name,
                        '<input type="'.$widget['type'].'" value="'.txpspecialchars($val).'" name="'.$name.'" id="'.$name.'"'.$min.$max.$jmp.'" class="smd_bio_'.$widget['type'].'" />',
                        $title,
                        '',
                        'txp-form-field smd_bio_'.$widget['type'].' '.$name
                    );
                    break;
                case 'date':
                case 'month':
                case 'week':
                case 'time':
                case 'datetime':
                case 'email':
                case 'url':
                case 'text':
                    // Not using fInput() because it has no MAXLENGTH property and doesn't support HTML 5 elements
                    $size1 = ($size1 == '' || $size1 == 0) ? 25 : $size1;
                    $size2 = ($size2 == '' || $size2 == 0) ? $size1 : $size2;
                    $out[] = inputLabel(
                        $name,
                        '<input type="'.$widget['type'].'" value="'.txpspecialchars($val).'" name="'.$name.'" id="'.$name.'" size="'.$size1.'" maxlength="'.$size2.'" class="smd_bio_'.$widget['type'].'" />',
                        $title,
                        '',
                        'txp-form-field smd_bio_'.$widget['type'].' '.$name
                    );
                    break;
            }
        }
    }
    return join('', $out);
}

// ------------------------
// Read a name/val array and remove any [*] marker which indicates a default item
function smd_bio_get_default($list, $curr) {
    $out = array();
    $dflt = '';
    $new_dflt = array();
    foreach ($list as $item => $value) {
        $value = str_replace('&#44;', ',', $value); // Revert encoded commas to real literals @see smd_bio_meta_make_list
        if (($pos = strpos($value, '[*]')) !== false) {
            $out[$item] = substr($value, 0, $pos);
            $new_dflt[] = $item;
        } else {
            $out[$item] = $value;
        }
        if ($item == $curr) {
            $dflt = $item;
        }
    }
    $dflt = ($dflt) ? $dflt : join(',', $new_dflt);

    return array($out, $dflt);
}

// ------------------------
// AJAX calls
function smd_bio_get_image() {
    global $img_dir;

    $id = gps('id');
    if ($id) {
        $rs = safe_row('*', 'txp_image', "id = '".doSlash($id)."'");
        extract($rs);
        $out = array();
        $out['thumb'] = ($thumbnail) ? hu.$img_dir.'/'.$id.'t'.$ext : '';
        $out['image'] = hu.$img_dir.'/'.$id.$ext;
        $out['w'] = $w;
        $out['h'] = $h;
        $out['thw'] = ($thumb_w) ? $thumb_w : '';
        $out['thh'] = ($thumb_h) ? $thumb_h : '';
        send_xml_response($out);
    } else {
        send_xml_response();
    }
    exit;
}

// ------------------------
// IMPORTANT: constants NOT used for table names
function smd_bio_get_ebio() {
    include_once txpath.'/publish/taghandlers.php';

    $id = gps('id');
    $core = array('user_ref');
    $rs = safe_row('*', 'smd_bio', "user_ref = '".doSlash($id)."'");
    $meta = safe_rows('*', 'smd_bio_meta', "1=1");
//  $include = array_merge($core, array('mug', 'cell', 'department')); // TODO: get these from prefs/meta table
    $allowed_types = array('text', 'textarea', 'email', 'url', 'date', 'month', 'week', 'time', 'datetime', 'number', 'range');
    $out = array();
    foreach ($rs as $idx => $val) {
//      if (in_array($idx, $include)) {
            if (in_array($idx, $core)) {
                $out[$idx] = doSlash($val);
            } else {
                foreach($meta as $row) {
                    if ($row['name'] == $idx) {
                        if ($row['type'] == "image") {
                            // Crude str_replace() to remove javascript-breaking single quotes
                            $out[$idx] = 'smd_image::'.str_replace("'", '', thumbnail(array('id'=> $val)));
                        } else if (in_array($row['type'], $allowed_types)) {
                            $out[$idx] = $row['title'].'::'.txpspecialchars(strip_tags($val), ENT_QUOTES);
                        }
                        break;
                    }
                }
            }
//      }
    }
    send_xml_response($out);
    exit;
}

// Inject admin-side javascript
// ------------------------
function smd_bio_admin_js($evt, $stp) {
    global $event, $step;

    $smd_bio_styles = smd_bio_get_styles();

    $runon = array(
        'admin' => array(
            'evt' => array('admin', 'smd_um'),
            'stp' => array('', 'smd_um', 'smd_um_edit', 'smd_um_edit', 'smd_um_save', 'smd_um_save_new', 'smd_um_change_pass', 'author_edit', 'author_save', 'author_save_new'),
        ),
        'bio' => array(
            'evt' => array('smd_bio'),
        ),
    );

    // ********
    // js+css for Admin->Users tab
    // ********
    if (in_array($event, $runon['admin']['evt']) !== false && in_array($step, $runon['admin']['stp']) !== false) {
        $css_custom = safe_field('css', 'txp_css', "name='smd_bio'");
        $css = '<style type="text/css">' . $css_custom .n. $smd_bio_styles['tooltip'] . '</style>';

    echo <<<EOJS
<script type="text/javascript">
//<![CDATA[

// Concatenate checkbox options for storage
function smd_bio_checkbox(dest) {
    var out = [];
    jQuery("#user_edit :checkbox").each(function() {
        var item = jQuery(this);
        if (item.attr('name').replace('cb_','') == dest) {
            if (item.prop('checked') == true) {
                out.push(item.val());
            }
        }
    });
    jQuery('#'+dest).val(out.join(','));
}
// Concatenate multi select list options for storage
function smd_bio_multisel(dest) {
    var out = [];
    jQuery("#user_edit select.multiple").each(function() {
        var item = jQuery(this);
        if (item.attr('name') == dest) {
            // You're the one that I want, ooh ooh oooohhh
            jQuery(item).children(":selected").each(function() {
                out.push(jQuery(this).val().replace('ms_',''));
            });
        }
    });
    jQuery('#'+dest).val(out.join(','));
}
jQuery(function() {
    // Grab images from the server when the select/textbox change
    jQuery(".smd_bio_image_id").blur(function() {
        id = jQuery(this).attr('id');
        val = jQuery(this).val();
        smd_bio_get_image(id, val);
    }).blur();
    jQuery(".smd_bio_image_cell select").change(function() {
        id = jQuery(this).parent().find('.smd_bio_image_id').attr('id');
        val = jQuery(this).val();
        smd_bio_get_image(id, val);
    });
    function smd_bio_get_image(id, val) {
        jQuery("#" + id + " ~ select option[value='"+val+"']").prop("selected", true);
        jQuery("#" + id).val(val);
        var dims = jQuery("#" + id + " ~ .smd_bio_image_data").attr('title');
        var size = new Array();
        if (dims) {
            size = dims.split(",");
        }
        sendAsyncEvent({
                event: textpattern.event,
                smd_bio_step: 'smd_bio_get_image',
                id: val
            }, function(data) {
                data = jQuery(data);
                if (data) {
                    var full = 0;
                    var imgLink = data.find('thumb').attr('value');
                    if (imgLink == '') {
                        full = 1;
                        var imgLink = data.find('image').attr('value');
                    }
                    if (imgLink) {
                        if (size[0] == '') {
                            if (full == 1) {
                                size[0] = data.find('w').attr('value');
                            } else {
                                size[0] = data.find('thw').attr('value');
                            }
                        }
                        if (size[1] == '') {
                            if (full == 1) {
                                size[1] = data.find('h').attr('value');
                            } else {
                                size[1] = data.find('thh').attr('value');
                            }
                        }
                        jQuery("#" + id + " ~ .smd_bio_image").fadeIn().html('<img src="'+imgLink+'" width="'+size[0]+'" height="'+size[1]+'" />');
                    } else {
                        jQuery("#" + id + " ~ .smd_bio_image").fadeOut().empty();
                    }
                } else {
                    jQuery("#" + id + " ~ .smd_bio_image").fadeOut().empty();
                }
            }
        );
    }

    // Grab the extended info when hovering an author in the list
    jQuery("#users_form tbody tr, #smd_um_form tbody tr").hover(function(e) {
        var tt = '';
        var row = jQuery(this);
        var hovItem = row.find(".login-name");

        if (row.data('tooltip') == undefined) {
            var person = row.find(".login-name").text();

            sendAsyncEvent({
                    event: textpattern.event,
                    smd_bio_step: 'smd_bio_get_ebio',
                    id: person
                }, function(data) {
                    data = jQuery(data);
                        var entry = data.find('user_ref');
                        out = '';
                        entry.nextAll().each(function(item) {
                            node = jQuery(this).context.nodeName;
                            if (node != 'http-status') {
                                vall = jQuery(this).attr('value');
                                if (vall) {
                                    vsplit = vall.split('::');
                                    if (vsplit[0].indexOf('smd_image') < 0) {
                                        out += ((vsplit.length>1) ? vsplit[0] : node) + ': ';
                                    }
                                    vall = ((vsplit.length>1) ? vsplit[1] : vsplit[0]);
                                    out += vall + '<br/>';
                                } else {
                                    out += node+': ';
                                }
                            }
                        });
                        row.data('tooltip', out);
//                      hovItem.trigger('mouseover'); // Trigger the hover state when the data is loaded
                    }
            );
        }

        xOffset = 30;
        yOffset = 25;
        hovItem.hover(function(e) {
            var tt = jQuery(this).parent().data('tooltip');
            if (tt != '') {
                jQuery("body").append("<p id='tooltip'>"+ tt +"</p>");
                jQuery("#tooltip")
                    .css("top",(e.pageY - xOffset) + "px")
                    .css("left",(e.pageX + yOffset) + "px")
                    .fadeIn("fast");
            }
        },
        function() {
            jQuery("#tooltip").remove();
        });
        hovItem.mousemove(function(e) {
            jQuery("#tooltip")
                .css("top",(e.pageY - xOffset) + "px")
                .css("left",(e.pageX + yOffset) + "px");
        });
    });
});
//]]>
</script>
{$css}
EOJS;
    }

    // ********
    // js for Extensions->Bio config tab
    // ********
    if (in_array($event, $runon['bio']['evt'])) {
        $smd_bio_types = smd_bio_get_types();
        $smd_bio_unused = smd_bio_get_unused();

        $css = '<style type="text/css">' . $smd_bio_styles['meta'] . '</style>';
        $js_unused = join(',', doArray($smd_bio_unused['size'], 'doQuote'));

        foreach ($smd_bio_types as $type => $data) {
            $type_json[] = 'smd_bio_types["'.$type.'"] = { "dflt_type": "'.$data['coltype'].'", "dflt_size": "'.$data['colsize'].'", "fixed": "'.$data['fixed'].'" };';
        }
        $type_json = join(n, $type_json);

        echo <<<EOJS
<script type="text/javascript">
//<![CDATA[
var smd_bio_unused = [{$js_unused}];
var smd_bio_types = [];
var destColtype = "#page-smd_bio select[name='coltype']";
var destColsize = "#page-smd_bio input[name='colsize']";
{$type_json}

jQuery(function() {
    // Perform actions when the Type is changed
    // Action #1: size box. Auto-update this when Edit clicked as well as when select list is altered
    jQuery("#smd_bio_widget_type").change(function() {
        var theType = jQuery("#smd_bio_widget_type option:selected").val();
        var destSize = "#page-smd_bio input[name='size']";

        // Grey out the Size box for those items that don't use it
        if (jQuery.inArray(theType, smd_bio_unused) > -1) {
            jQuery(destSize).prop("disabled", true);
            jQuery(destSize).parent().prev().css("color", '#999');
        } else {
            jQuery(destSize).prop("disabled", false);
            jQuery(destSize).parent().prev().css("color", '');
        }
    }).change();

    // Action #2: coltype/colsize. Can't amalgamate these with the 'size' onchange because
    // this only changes when select list changed (i.e. not when Edit first clicked)
    jQuery("#smd_bio_widget_type").change(function() {
        var theType = jQuery("#smd_bio_widget_type option:selected").val();

        // Preselect the coltype + colsize based on the defaults
        jQuery(destColtype + " option[value='"+smd_bio_types[theType].dflt_type+"']").prop('selected', true);
        jQuery(destColsize).val(smd_bio_types[theType].dflt_size);

        if (smd_bio_types[theType].fixed == '') {
            jQuery(destColtype).prop("disabled", false);
            jQuery(destColsize).prop("disabled", false);
            jQuery(destColtype).parent().prev().css("color", '');
            jQuery(destColsize).parent().prev().css("color", '');
        } else {
            jQuery(destColtype).prop("disabled", true);
            jQuery(destColsize).prop("disabled", true);
            jQuery(destColtype).parent().prev().css("color", '#999');
            jQuery(destColsize).parent().prev().css("color", '#999');
        }
    });

    // Force the auto-change if the type is of a fixed variety
    theType = jQuery("#smd_bio_widget_type option:selected").val();
    if (smd_bio_types[theType].fixed != '') {
        jQuery("#smd_bio_widget_type").change();
    }

    // When clicking Save, enable the coltype/colsize boxes so the values are transmitted in the _POST array
    jQuery("#page-smd_bio input[name='save']").click(function() {
        jQuery(destColtype).prop("disabled", false);
        jQuery(destColsize).prop("disabled", false);
    });

    // Pop up the help tooltips based on the current Type
    jQuery(".edit-smd-bio-size .pophelp").hover(
        function(e) {
            var theType = jQuery("#smd_bio_widget_type option:selected").val();
            spanid = 'span#smd_bio_size_help_'+theType;
            this.title = jQuery(spanid).attr("title");
        }, function(e) {
            this.title = '';
        });
    jQuery(".edit-smd-bio-value .pophelp").hover(
        function(e) {
            var theType = jQuery("#smd_bio_widget_type option:selected").val();
            spanid = 'span#smd_bio_val_help_'+theType;
            this.title = jQuery(spanid).attr("title");
        }, function(e) {
            this.title = '';
        });

    // Handle opening/closing the coltype area
    jQuery('#smd_bio_colgroup').click(function(ev) {
        ev.preventDefault();
        jQuery(this).toggleClass('expanded');
        jQuery('.smd_bio_coltype').toggle();

        sendAsyncEvent(
            {
                event: textpattern.event,
                step: 'smd_bio_save_pane_state',
                pane: 'coltype',
                visible: (jQuery(this).hasClass('expanded'))
            }
        );
    });
});
//]]>
</script>
{$css}
EOJS;
    }
}

// ------------------------
// Make the name/val pairs for selectInput / radio / checkbox sets
function smd_bio_splitval($val) {
    $selv = array();

    $wvals = preg_split("/[\r\n,]+/", $val, -1, PREG_SPLIT_NO_EMPTY);
    foreach ($wvals as $wval) {
        $prts = explode('=>', $wval);
        if (count($prts) == 1) {
            $prts[1] = $prts[0];
            $prts[0] = smd_bio_sanitize($prts[0]);
        }
        $selv[trim($prts[0])] = trim($prts[1]);
    }
    return $selv;
}

// ------------------------
// Super-sanitize the passed value so we can make variable names from the returned string
function smd_bio_sanitize($val) {
    return strtolower(str_replace("-", "_", sanitizeForUrl($val)));
}

// ------------------------
function smd_bio_save($evt, $stp) {
    global $prefs;

    if (smd_bio_table_exist()) {
        $targetvars = array();
        extract(doSlash(psa(array('privs', 'name', 'email', 'RealName', 'user_id'))));
        if (get_pref('smd_bio_sanitize_name', 0) > 0) {
            // Sanitize and pass the new name forward to the actual txp_user save routine
            $name = strtolower(sanitizeForUrl($name));
            $_POST['name'] = $name;
        }
        $length = function_exists('mb_strlen') ? mb_strlen($name, '8bit') : strlen($name);

        if (($user_id || $name) and $length <= 64 and is_valid_email($email)) {
            foreach ($_POST as $idx => $item) {
                if (strpos($idx, 'smd_bio_') === 0) {
                    $targetvars[] = $idx;
                }
            }

            // Double de-clutch again... dammit :-(
            $user_id = gps('user_id');
            if ($user_id) {
                $user_ref = safe_field('name','txp_users',"user_id = '$user_id'");
            } else {
                $user_ref = $name;
            }
            extract(gpsa($targetvars));
            $bcols = getThings('describe `'.PFX.SMD_BIO.'`');
            $sqlSet = array();
            foreach ($targetvars as $var) {
                $colname = str_replace('smd_bio_', '', $var);
                if (in_array($colname, $bcols)) {
                    $sqlSet[] = "`$colname` = '".doSlash($$var)."'";
                }
            }
            if ($sqlSet) {
                $rs = safe_upsert(SMD_BIO, join(',', $sqlSet), "`user_ref` = '".doSlash($user_ref)."'");
            }
        }
    }
}

// ------------------------
function smd_bio_delete($evt, $stp) {
    global $txp_user;

    if (smd_bio_table_exist()) {
        // Since we are executing 'pre' delete we need to unfortunately duplicate some of the checks
        // from txp_admin.php so we minimise the opportunity to delete someone by mistake
        $selected = ps('selected');
        $method = ps('edit_method');
        if (!$selected or !is_array($selected)) {
            return;
        }
        if ($method != 'delete') {
            return;
        }

        $names = safe_column('name', 'txp_users', "name IN ('".join("','", doSlash($selected))."') AND name != '".doSlash($txp_user)."'");
        if (!$names) return;

        $assign_assets = ps('assign_assets');
        if ($assign_assets === '') {
            return;
        } elseif (in_array($assign_assets, $names)) {
            return;
        } else {
            // All the checks passed -- do it
            safe_delete(SMD_BIO, "user_ref IN ('".join("','", doSlash($names))."')");
        }
    }
}

// -------------------------------------------------------------
function smd_bio_save_pane_state() {
    global $event;
    $panes = array('coltype');
    $pane = gps('pane');
    if (in_array($pane, $panes)) {
        set_pref("pane_smd_bio_{$pane}_visible", (gps('visible') == 'true' ? '1' : '0'), $event, PREF_HIDDEN, 'yesnoradio', 0, PREF_PRIVATE);
        send_xml_response();
    } else {
        send_xml_response(array('http-status' => '400 Bad Request'));
    }
}

// -------------------------------------------------------------
function smd_bio_form_submit() {
    global $mem_form_type;

    if (!in_array($mem_form_type, array('smd_bio', 'mem_self_register', 'mem_self_user_edit'))) return;

    $author = smd_bio_find_author();

    if ($author) {
        $core_cols = smd_bio_core_cols();
        $meta_cols = safe_column('name', SMD_BIO_META, '1=1');
        $query_params = array(
            'core' => array(
                'tbl' => 'txp_users',
                'ref' => 'name',
            ),
            'bio' => array(
                'tbl' => SMD_BIO,
                'ref' => 'user_ref',
            )
        );

        foreach (stripPost() as $key => $val) {
            // Only care about smd_bio_ prefixed entries in $_POST
            if (strpos($key, 'smd_bio_') !== false) {
                // Strip off the known prefix
                $raw_key = str_replace('smd_bio_', '', $key);
                if (in_array($raw_key, $core_cols)) {
                    $query_params['core']['cols'][] = doSlash($raw_key) . '='. doQuote(doSlash($val));
                } else if (in_array($raw_key, $meta_cols)) {
                    $query_params['bio']['cols'][] = doSlash($raw_key) . '='. doQuote(doSlash($val));
                }
            }
        }

        // If there are some cols set we're good to update/insert depending if $author exists or not
        foreach ($query_params as $type => $data) {
            if (isset($data['cols'])) {
                $params = join(', ', $data['cols']);
                safe_upsert($data['tbl'], $params, $data['ref'] .'='. doQuote(doSlash($author)));
            }
        }
    }
}

// Try some of the usual suspects for locating authorship
function smd_bio_find_author($author_in='', $places=array('biotag', 'txpuser', 'ili', 'profile', 'selfreg', 'list', 'article', 'image', 'file', 'link') ) {
    global $smd_bio_author, $txp_user, $mem_form_type, $mem_profile, $pretext, $thisarticle, $thisimage, $thisfile, $thislink;
    static $smd_bio_ili = 0;

    $places = is_array($places) ? $places : do_list($places);

    // Check for any passed-in author first
    $author = $author_in;

    foreach ($places as $place) {
        if ($author != '') break;
        switch ($place) {
            case 'biotag':
                // From smd_bio_author tag
                $author = ($smd_bio_author != '') ? $smd_bio_author : $author;
            break;
            case 'txpuser':
                // From global user variable
                $author = ($txp_user != '') ? $txp_user : $author;
            break;
            case 'ili':
                // From currently logged-in user
                $smd_bio_ili = ($smd_bio_ili === 0) ? is_logged_in() : $smd_bio_ili;
                if ($smd_bio_ili) {
                    $author = $smd_bio_ili['name'];
                }
            break;
            case 'profile':
                // From current self-edit user profile
                $author = (isset($mem_profile['name'])) ? $mem_profile['name'] : $author;
            break;
            case 'selfreg':
                // New author from mem_self_reg?
                $aname = ps('name');
                if ($aname == '') {
                    // New author from custom mem_form?
                    $aname = ps('smd_bio_name');
                }
                if ( ($mem_form_type == 'mem_self_register' || $mem_form_type == 'smd_bio') && ($aname != '') ) {
                    // As long as $aname doesn't exist, let this new author in
                    //TODO: Cache this?
                    $exists = safe_row('*', 'txp_user', "name='".doSlash($aname)."'");
                    if (!$exists) {
                        $author = $aname;
                    }
                }
            break;
            case 'list':
                $author = (isset($pretext['author'])) ? $pretext['author'] : $author;
            break;
            case 'article':
                $author = (isset($thisarticle['authorid'])) ? $thisarticle['authorid'] : $author;
            break;
            case 'image':
                $author = (isset($thisimage['author'])) ? $thisimage['author'] : $author;
            break;
            case 'file':
                $author = (isset($thisfile['author'])) ? $thisfile['author'] : $author;
            break;
            case 'link':
                $author = (isset($thislink['author'])) ? $thislink['author'] : $author;
            break;
        }
    }

    return $author;
}

// -------------------------------------------------------------
// Return a list of core columns in the txp_users table.
// This could be done programmatically but isn't: save a
// query, save the world
function smd_bio_core_cols() {
    // The indices are the gTxt() names for the associated field
    return array(
        'id'         => 'user_id',
        'name'       => 'name',
        'real_name'  => 'RealName',
        'email'      => 'email',
        'privileges' => 'privs',
        'date'       => 'last_access'
    );
}

// -------------------------------------------------------------
// In alphabetical order or sorting on the admin panel gets screwy
function smd_bio_get_types() {
    $smd_bio_types = array(
        'checkbox' => array(
                'name'    => 'Checkbox(es)',
                'coltype' => 'varchar',
                'colsize' => 255,
                'fixed'   => true,
                ),
        'date' => array(
                'name'    => 'Date',
                'coltype' => 'date',
                'colsize' => null,
                'fixed'   => false,
                ),
        'datetime' => array(
                'name'    => 'Date/Time',
                'coltype' => 'datetime',
                'colsize' => null,
                'fixed'   => false,
                ),
        'email' => array(
                'name'    => 'E-mail',
                'coltype' => 'varchar',
                'colsize' => 254,
                'fixed'   => false,
                ),
        'image' => array(
                'name'    => 'Image',
                'coltype' => 'int',
                'colsize' => 11,
                'fixed'   => true,
                ),
        'list' => array(
                'name'    => 'Select list',
                'coltype' => 'varchar',
                'colsize' => 255,
                'fixed'   => true,
                ),
        'month' => array(
                'name'    => 'Month',
                'coltype' => 'varchar',
                'colsize' => 255,
                'fixed'   => false,
                ),
        'multilist' => array(
                'name'    => 'Multi select list',
                'coltype' => 'varchar',
                'colsize' => 255,
                'fixed'   => true,
                ),
        'number' => array(
                'name'    => 'Number',
                'coltype' => 'int',
                'colsize' => 11,
                'fixed'   => false,
                ),
        'radio' => array(
                'name'    => 'Radio set',
                'coltype' => 'varchar',
                'colsize' => 255,
                'fixed'   => true,
                ),
        'range' => array(
                'name'    => 'Range',
                'coltype' => 'int',
                'colsize' => 11,
                'fixed'   => false,
                ),
        'text' => array(
                'name'    => 'Text box',
                'coltype' => 'varchar',
                'colsize' => 255,
                'fixed'   => false,
                ),
        'textarea' => array(
                'name'    => 'Text area',
                'coltype' => 'mediumtext',
                'colsize' => 4096,
                'fixed'   => false,
                ),
        'time' => array(
                'name'    => 'Time',
                'coltype' => 'time',
                'colsize' => null,
                'fixed'   => false,
                ),
        'url' => array(
                'name'    => 'URL',
                'coltype' => 'varchar',
                'colsize' => 255,
                'fixed'   => false,
                ),
        'week' => array(
                'name'    => 'Week',
                'coltype' => 'varchar',
                'colsize' => 255,
                'fixed'   => false,
                ),
        'yesnoradio' => array(
                'name'    => 'Yes/no radio',
                'coltype' => 'varchar',
                'colsize' => 255,
                'fixed'   => true,
                ),
    );

    return $smd_bio_types;
}

// -------------------------------------------------------------
// In alphabetical order or sorting on the admin panel gets screwy
function smd_bio_get_coltypes() {
    $smd_bio_coltypes = array(
        'date' => array(
            'title'       => 'Date [YYYY-MM-DD]',
            'size_req'    => false,
            'has_default' => true,
        ),
        'datetime' => array(
            'title'       => 'Date + time [YYYY-MM-DD HH:MN:SS]',
            'size_req'    => false,
            'has_default' => true,
        ),
        'double' => array(
            'title'       => 'Double precision float',
            'size_req'    => false,
            'has_default' => true,
        ),
        'float' => array(
            'title'       => 'Floating point number',
            'size_req'    => false,
            'has_default' => true,
        ),
        'int' => array(
            'title'       => 'Integer [up to 4294967295]',
            'size_req'    => '11',
            'has_default' => true,
        ),
        'longtext' => array(
            'title'       => 'Long text [up to 4GB]',
            'size_req'    => false,
            'has_default' => false,
        ),
        'mediumint' => array(
            'title'       => 'Medium integer [up to 16777215]',
            'size_req'    => '8',
            'has_default' => true,
        ),
        'mediumtext' => array(
            'title'       => 'Medium text [up to 16MB]',
            'size_req'    => false,
            'has_default' => false,
        ),
        'smallint' => array(
            'title'       => 'Small integer [up to 65535]',
            'size_req'    => '4',
            'has_default' => true,
        ),
        'text' => array(
            'title'       => 'Text [up to 64KB chars]',
            'size_req'    => false,
            'has_default' => false,
        ),
        'time' => array(
            'title'       => 'Time [HH:MN:SS]',
            'size_req'    => false,
            'has_default' => true,
        ),
        'timestamp' => array(
            'title'       => 'Timestamp [seconds since UNIX epoch]',
            'size_req'    => false,
            'has_default' => true,
        ),
        'tinyint' => array(
            'title'       => 'Tiny integer [up to 255]',
            'size_req'    => '3',
            'has_default' => true,
        ),
        'tinytext' => array(
            'title'       => 'Tiny text [up to 255 chars]',
            'size_req'    => false,
            'has_default' => false,
        ),
        'varbinary' => array(
            'title'       => 'Binary varchar [size subject to max row size]',
            'size_req'    => '255',
            'has_default' => true,
        ),
        'varchar' => array(
            'title'       => 'Varchar [size subject to max row size]',
            'size_req'    => '255',
            'has_default' => true,
        ),
    );

    return $smd_bio_coltypes;
}

// -------------------------------------------------------------
function smd_bio_get_unused() {
    $smd_bio_unused = array(
        'size' => array('list', 'multilist', 'radio', 'yesnoradio', 'checkbox'),
    );

    return $smd_bio_unused;
}

// ************************
// TABLE MANAGEMENT
// ------------------------
function smd_bio_table_exist() {
    static $smd_bio_table_ok = array();

    if (isset($smd_bio_table_ok['meta'])) {
        return ($smd_bio_table_ok['meta'] === $smd_bio_table_ok['field']);
    }

    $meta = safe_count(SMD_BIO_META, '1=1');
    $flds = @safe_show('columns', SMD_BIO);

    $smd_bio_table_ok['meta'] = (int)$meta;
    $smd_bio_table_ok['field'] = (int)count($flds) - 1; // Subtract the user_ref column as it's always present

    return($smd_bio_table_ok['meta'] === $smd_bio_table_ok['field']);
}

// ------------------------
function smd_bio_table_install($showpane=1) {
    global $DB;

    $smd_bio_types = smd_bio_get_types();
    $smd_bio_coltypes = smd_bio_get_coltypes();

    $GLOBALS['txp_err_count'] = 0;
    $msg = '';
    $debug = gps('debug');

    $ret = '';
    $sql = array();
    $sql[] = "CREATE TABLE IF NOT EXISTS `".PFX.SMD_BIO."` (
        `user_ref` varchar(64) NOT NULL default '',
        UNIQUE KEY `user_ref` (`user_ref`)
    ) ENGINE=MyISAM, CHARACTER SET=utf8, PACK_KEYS=1";

    $sql[] = "CREATE TABLE IF NOT EXISTS `".PFX.SMD_BIO_META."` (
        `id` int(4) NOT NULL auto_increment,
        `title` varchar(64) NULL default '' COLLATE utf8_general_ci,
        `name` varchar(64) NOT NULL default '' COLLATE utf8_general_ci,
        `type` set(".doQuote(join("','", array_keys($smd_bio_types))).") NOT NULL default 'text',
        `size` varchar(10) NULL default 0,
        `coltype` set(".doQuote(join("','", array_keys($smd_bio_coltypes))).") NOT NULL default 'varchar',
        `colsize` smallint(4) NULL default 0,
        `val` text NULL COLLATE utf8_general_ci,
        `position` varchar(16) NULL default '',
        PRIMARY KEY (`id`),
        UNIQUE KEY (`name`)
    ) ENGINE=MyISAM, CHARACTER SET=utf8, AUTO_INCREMENT=1";

    if ($debug) {
        dmp($sql);
    }
    foreach ($sql as $qry) {
        $ret = safe_query($qry);
        if ($ret===false) {
            $GLOBALS['txp_err_count']++;
            echo "<b>".$GLOBALS['txp_err_count'].".</b> ".mysql_error()."<br />\n";
            echo "<!--\n $qry \n-->\n";
        }
    }

    // Handle upgrades from v0.3x to v0.40.
    // Upgrade table collation if necessary
    $ret = getRows("SHOW TABLE STATUS WHERE name IN ('".PFX.SMD_BIO."', '".PFX.SMD_BIO_META."')");
    if ($ret[0]['Collation'] != 'utf8_general_ci') {
        $ret = safe_alter(SMD_BIO_META, 'CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci');
    }
    if ($ret[1]['Collation'] != 'utf8_general_ci') {
        $ret = safe_alter(SMD_BIO, 'CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci');
    }

    // Alter the position field from int to varchar so positioning can be non-numeric
    $ret = getThings("SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = '".PFX.SMD_BIO_META."' AND table_schema = '" . $DB->db . "' AND column_name = 'position'");
    if ($ret != 'varchar') {
        safe_alter(SMD_BIO_META, "CHANGE `position` `position` VARCHAR( 16 ) NULL DEFAULT ''", $debug);
    }
    // Add the coltype and colsize columns
    $flds = getThings('SHOW COLUMNS FROM `'.PFX.SMD_BIO_META.'`');
    if (!in_array('coltype', $flds)) {
        safe_alter(SMD_BIO_META, "ADD `coltype` SET(".doQuote(join("','", array_keys($smd_bio_coltypes))).") NOT NULL default '' after `size`", $debug);
        safe_alter(SMD_BIO_META, "ADD `colsize` SMALLINT(4) NULL default 0 after `coltype`", $debug);
    }
    // Add multiple select list & date flavour support to the 'type' set, and rename 'text_input' to just 'text'
    $toChange = safe_column('id', SMD_BIO_META, 'type="text_input"');
    $fld = getRows("SHOW FULL COLUMNS FROM `".PFX.SMD_BIO_META."` LIKE 'type'");
    $ft = $fld[0]['Type'];
    if ( (strpos($ft, 'multilist') === false) || (strpos($ft, 'text_input') !== false) || (strpos($ft, 'month') === false) ) {
        safe_alter(SMD_BIO_META, "CHANGE `type` `type` SET(".doQuote(join("','", array_keys($smd_bio_types))).")", $debug);
    }
    if ($toChange) {
        safe_update(SMD_BIO_META, 'type="text"', "id in ('". join("','", $toChange) ."')", $debug);
    }

    if ($GLOBALS['txp_err_count'] == 0) {
        $msg = gTxt('smd_bio_tbl_installed');
    } else {
        $msg = gTxt('smd_bio_tbl_not_installed');
    }

    if ($showpane) {
        smd_bio_config($msg);
    }
}

// ------------------------
// Drop table if in database
function smd_bio_table_remove($showpane=1) {
    $ret = $msg = '';
    $sql = array();
    $GLOBALS['txp_err_count'] = 0;
    $sql[] = "DROP TABLE IF EXISTS " .PFX.SMD_BIO. "; ";
    $sql[] = "DROP TABLE IF EXISTS " .PFX.SMD_BIO_META. "; ";
    if(gps('debug')) {
        dmp($sql);
    }
    foreach ($sql as $qry) {
        $ret = safe_query($qry);
        if ($ret===false) {
            $GLOBALS['txp_err_count']++;
            echo "<b>".$GLOBALS['txp_err_count'].".</b> ".mysql_error()."<br />\n";
            echo "<!--\n $qry \n-->\n";
        }
    }
    if ($GLOBALS['txp_err_count'] == 0) {
        $msg = gTxt('smd_bio_tbl_removed');
    } else {
        $msg = gTxt('smd_bio_tbl_not_removed');
    }

    if ($showpane) {
        smd_bio_config($msg);
    }
}

// ************************
// PUBLIC-SIDE TAGS
// ------------------------
// Wrapper to permit bio info to be displayed for multiple authors
function smd_bio_author($atts, $thing = null) {
    global $smd_bio_author, $smd_bio_meta_data;

    extract(lAtts(array(
        'author'      => '',
        'form'        => '',
        'sort'        => 'RealName asc',
        'wraptag'     => '',
        'break'       => '',
        'breakclass'  => '',
        'class'       => '',
        'label'       => '',
        'labeltag'    => '',
        'auto_detect' => 'profile, list, article, image, file, link',
    ), $atts));

    $author = smd_bio_find_author($author, $auto_detect);
    $author = do_list($author);
    $authors = array();
    $thing = (empty($form)) ? $thing : fetch_form($form);

    // Set up any sorting clause
    if ($sort != '') {
        $sort = do_list($sort);
        $sortout = array();
        foreach ($sort as $sitems) {
            $sortbits = do_list($sitems, ' ');
            $sort_col = $sortbits[0];
            $sort_ord = (isset($sortbits[1]) && in_array($sortbits[1], array('asc', 'desc'))) ? $sortbits[1] : 'asc';
            if ($sort_col) {
                $sortout[] = '`' . $sort_col . '` ' . $sort_ord;
            }
        }
        if ($sortout) {
            $sort = ' ORDER BY '.join(',', $sortout);
        }
    }

    // Expand any priv levels
    foreach ($author as $user) {
        if (strpos(strtolower($user), "smd_privs") === 0) {
            $aprivs = explode(':', $user);
            array_shift($aprivs); // Remove smd_privs token from the array
            $rows = safe_query('SELECT txpu.name FROM ' . PFX . 'txp_users AS txpu LEFT JOIN ' . PFX.SMD_BIO . ' AS smdb ON txpu.name = smdb.user_ref WHERE privs in (' . doQuote(join("','", $aprivs)) . ')'.$sort);
            if ($rows) {
                while ($a = nextRow($rows)) {
                    $authors[] = $a['name'];
                }
            }
        } else if (strpos(strtolower($user), "smd_all") === 0) {
            $rows = safe_query('SELECT txpu.name FROM ' . PFX . 'txp_users AS txpu LEFT JOIN ' . PFX.SMD_BIO . ' AS smdb ON txpu.name = smdb.user_ref WHERE 1=1'.$sort);
            if ($rows) {
                while ($a = nextRow($rows)) {
                    $authors[] = $a['name'];
                }
            }
        } else {
            $authors[] = $user;
        }
    }

    // Parse content for each author: inject current author into global
    $out = array();
    $author_count = count($authors) - 1;
    foreach ($authors as $idx => $smd_bio_author) {
        $smd_bio_meta_data['author']['first'] = ($idx === 0);
        $smd_bio_meta_data['author']['last'] = ($idx === $author_count);
        $toParse = (empty($thing)) ? $smd_bio_author : $thing;
        $out[] = parse($toParse);
    }

    return doLabel($label, $labeltag).doWrap($out, $wraptag, $break, $class, $breakclass);
}

// Display biographical field data from a given user's profile
function smd_bio_info($atts, $thing = null) {
    global $smd_bio_data, $smd_bio_option_data;

    // Data cache
    static $bio_info = array();
    static $meta = array();
    static $cmeta = array();
    static $metacols = array();

    extract(lAtts(array(
        'author'      => '', // Deprecated: use smd_bio_author tag as a wrapper instead
        'fields'      => 'SMD_ALL',
        'items'       => 'SMD_ALL', // Deprecated: use fields instead
        'exclude'     => '',
        'form'        => '',
        'wraptag'     => '',
        'break'       => '',
        'class'       => '',
        'label'       => '1',
        'labeltag'    => '',
        'labelclass'  => 'SMD_DFLT', // Deprecated
        'itemwraptag' => '', // Deprecated: use break
        'itemclass'   => 'SMD_DFLT', // Deprecated: use breakclass
        'breakclass'  => 'SMD_DFLT',
        'show_empty'  => 0,
        'prefix'      => 'smd_bio_', // Only for replacement variables
        'auto_detect' => 'biotag, list, article, image, file, link',
        'debug'       => 0,
    ), $atts));

    if (isset($atts['author'])) {
        trigger_error(gTxt('deprecated_attribute', array('{name}' => 'author')) . '. Use the smd_bio_author tag as a wrapper instead.', E_USER_NOTICE);
        unset($author);
    }
    if (isset($atts['items'])) {
        trigger_error(gTxt('deprecated_attribute', array('{name}' => 'items')) . '. Use fields instead.', E_USER_NOTICE);
        $fields = $items;
        unset($items);
    }
    if (isset($atts['itemwraptag'])) {
        trigger_error(gTxt('deprecated_attribute', array('{name}' => 'itemwraptag')) . '. Use break instead.', E_USER_NOTICE);
        $break = $itemwraptag;
        unset($itemwraptag);
    }
    if (isset($atts['itemclass'])) {
        trigger_error(gTxt('deprecated_attribute', array('{name}' => 'itemclass')) . '. Use breakclass instead.', E_USER_NOTICE);
        $breakclass = $itemclass;
        unset($itemclass);
    }
    if (isset($atts['labelclass'])) {
        trigger_error(gTxt('deprecated_attribute', array('{name}' => 'labelclass')), E_USER_NOTICE);
        unset($labelclass);
    }

    $author = smd_bio_find_author('', $auto_detect);

    $thing = (empty($form)) ? $thing : fetch_form($form);
    $smd_bio_data = $smd_bio_option_data = array();
    $bio_types = array_keys(smd_bio_get_types());

    $fields = do_list($fields);
    $exclude = do_list($exclude);
    $final = '';

    $coreCols = smd_bio_core_cols();
    $list_types = array('checkbox', 'radio', 'yesnoradio', 'list', 'multilist');
    $mm_types = array('number', 'range');

    if ($author) {
        $meta = (empty($meta)) ? safe_rows('*', SMD_BIO_META, '1=1') : $meta;
        $cmeta = (empty($cmeta)) ? safe_show('columns', 'txp_users') : $cmeta;
        $metacols = (empty($metacols)) ? safe_column('name', SMD_BIO_META, '1=1') : $metacols;
        $num = count($meta);

        foreach($cmeta as $info) {
            if (!in_array($info['Field'], $coreCols)) continue;
            $meta[$num]['name'] = $info['Field'];
            $tField = join('', array_keys($coreCols, $info['Field']));
            $meta[$num]['title'] = ((gTxt($tField) == $tField) ? gTxt('smd_bio_'.$tField) : gTxt($tField));
            $length = (($off = strpos($info['Type'], '(')) !== false) ? $off : strlen($info['Type']); // Find the first open bracket or end of string
            $cmtype = substr($info['Type'], 0, $length);
            $cmtype = in_array($cmtype, $bio_types)? $cmtype : 'text';
            $meta[$num]['type'] = $cmtype;
            $num++;
        }
        if ($debug > 1) {
            echo '++ META DATA ++';
            dmp($meta);
        }

        // Exclusions override given fields
        if ($exclude[0] != '' || in_array('SMD_ALL', $fields)) {
            $fields = $metacols;
            $fields = array_merge($fields, $coreCols);
        }

        if (isset($bio_info[$author])) {
            $cbio = $bio_info[$author]['cbio'];
            $ebio = $bio_info[$author]['ebio'];
        } else {
            $cbio = $bio_info[$author]['cbio'] = safe_row('*', 'txp_users', "name='".doSlash($author)."'");
            $ebio = $bio_info[$author]['ebio'] = safe_row('*', SMD_BIO, "user_ref='".doSlash($author)."'");
        }

        $replacements = $out = $toParse = array();
        $isSingle = ($thing === null) ? true : false;
        $numFields = count($fields);

        foreach ($fields as $iref => $whatnot) {
            $idx = -1;
            if (in_array($whatnot, $exclude)) continue;

            // Find the meta row
            foreach ($meta as $num => $data) {
                if ($data['name'] == $whatnot) {
                    $idx = $num;
                    break;
                }
            }

            if ($idx > -1) {
                if (in_array($whatnot, $coreCols)) {
                    $field = isset($cbio[$whatnot]) ? $cbio[$whatnot] : '';
                } else {
                    $field = isset($ebio[$whatnot]) ? $ebio[$whatnot] : '';
                }

                $theName = $meta[$idx]['name'];
                $theTitle = $meta[$idx]['title'];
                $prefixedName = $prefix.$theName;
                $fixedName = 'smd_bio_'.$theName; // Used for widget names so we know the indices in the $_POST array
                $theClass = ($breakclass=='SMD_DFLT') ? $prefixedName : (($breakclass) ? $breakclass : '');
                $replacements['{'.$prefixedName.'_name}'] = $theName;
                $replacements['{'.$prefixedName.'_title}'] = $theTitle;
                $replacements['{'.$prefixedName.'_class}'] = $theClass;
                $replacements['{'.$prefixedName.'_type}'] = $meta[$idx]['type'];
                $smd_bio_data[$theName]['name'] = $theName;
                $smd_bio_data[$theName]['title'] = $theTitle;
                $smd_bio_data[$theName]['class'] = $theClass;
                $smd_bio_data[$theName]['type'] = $meta[$idx]['type'];
                if (!in_array($meta[$idx]['type'], $list_types) && isset($meta[$idx]['val'])) {
                    $smd_bio_data[$theName]['default'] = $meta[$idx]['val'];
                    $replacements['{'.$prefixedName.'_default}'] = $meta[$idx]['val'];
                }
                $widget = '';

                if ($field || $show_empty) {
                    if (in_array($meta[$idx]['type'], $list_types)) {
                        $field = do_list($field);
                        $field = join(', ',$field);
                    }

                    $fieldContent = ($meta[$idx]['type'] == 'textarea') ? nl2br($field) : $field;
                    $replacements['{'.$prefixedName.'}'] = $fieldContent;
                    $smd_bio_data[$theName]['value'] = $fieldContent;

                    // For backwards compatibility(ish) with v0.3x
                    if ($numFields == 1) {
                        $replacements['{'.$prefix.'info_item}'] = $fieldContent; // Deprecated, use info_value instead
                        $replacements['{'.$prefix.'info_value}'] = $fieldContent;
                        $replacements['{'.$prefix.'info_name}'] = $theName;
                        $replacements['{'.$prefix.'info_title}'] = $theTitle;
                        $replacements['{'.$prefix.'info_itemclass}'] = $theClass; // Deprecated: use info_class instead
                        $replacements['{'.$prefix.'info_class}'] = $theClass;
                    }

                    if (in_array($meta[$idx]['type'], $list_types)) {
                        $chosens = do_list($field);
                        $nv = smd_bio_splitval($meta[$idx]['val']);
                        list($nv, $dflt) = smd_bio_get_default($nv, $field);
                        $dflts = do_list($dflt);
                        $listctr=1;
                        $chosenctr=0;
                        foreach($nv as $listitem => $listlabel) {
                            $replacements['{'.$prefixedName.'_option_'.$listctr.'}'] = $listitem;
                            $replacements['{'.$prefixedName.'_title_'.$listctr.'}'] = $listlabel;
                            $smd_bio_data[$theName]['option_'.$listctr] = $listitem;
                            $smd_bio_data[$theName]['title_'.$listctr] = $listlabel;
                            if (in_array($listitem, $chosens)) {
                                $chosenctr++;
                                $replacements['{'.$prefixedName.'_chosen_option_'.$chosenctr.'}'] = $listitem;
                                $replacements['{'.$prefixedName.'_chosen_title_'.$chosenctr.'}'] = $listlabel;
                                $smd_bio_data[$theName]['chosen_option_'.$chosenctr] = $listitem;
                                $smd_bio_data[$theName]['chosen_title_'.$chosenctr] = $listlabel;
                            }
                            $listctr++;
                        }
                        $dfltctr = 1;
                        foreach($dflts as $dfltitem) {
                            $replacements['{'.$prefixedName.'_default_option_'.$dfltctr.'}'] = $dfltitem;
                            $smd_bio_data[$theName]['default_option_'.$dfltctr] = $dfltitem;
                            $dfltctr++;
                        }

                        // TODO: maybe hand-code all input types so they can have classes added
                        switch ($meta[$idx]['type']) {
                            case 'checkbox':
                                $citems = array();
                                foreach ($nv as $idx => $lbl) {
                                    $citems[] = checkbox($fixedName, 'cb_'.$idx, (in_array($idx, $dflts) ? '1' : '0')) . $lbl;
                                }
                                $widget = join(n, $citems);
                            break;
                            case 'yesnoradio':
                                $widget = yesnoRadio($fixedName, $dflt);
                            break;
                            case 'radio':
                                $widget = radioSet($nv, $fixedName, $dflt);
                            break;
                            case 'list':
                                $widget = selectInput($fixedName, $nv, $dflt, false, '', $prefixedName);
                            break;
                            case 'multilist':
                                $mitems = array();
                                $mitems[] = '<select name="'.$fixedName.'" class="list multiple '.$theClass.'" multiple="multiple">';
                                foreach ($nv as $idx => $lbl) {
                                    // Not using selectInput() because it doesn't support multiples
                                    $mitems[] = '<option value="ms_'.$idx.'" '.((in_array($idx, $dflts)) ? ' selected="selected"' : '') . '>' . $lbl . '</option>';
                                }
                                $mitems[] = '</select>';
                                $widget = join(n, $mitems);
                            break;
                        }

                        $replacements['{'.$prefixedName.'_option_count}'] = $listctr-1;
                        $replacements['{'.$prefixedName.'_chosen_count}'] = $chosenctr;
                        $replacements['{'.$prefixedName.'_default_count}'] = $dfltctr-1;
                        $smd_bio_data[$theName]['option_count'] = $listctr-1;
                        $smd_bio_data[$theName]['chosen_count'] = $chosenctr;
                        $smd_bio_data[$theName]['default_count'] = $dfltctr-1;
                    } else if (in_array($meta[$idx]['type'], $mm_types)) {
                        $sizes = do_list($meta[$idx]['size']);
                        $min = ($sizes[0] === '') ? '' : ' min="' . $sizes[0] . '"';
                        $max = (isset($sizes[1]) && $sizes[1] !== '') ? ' max="' . $sizes[1] . '"' : '';
                        $jmp = (isset($sizes[2]) && $sizes[2] !== '') ? ' step="' . $sizes[2] . '"': '';
                        $cls = ($theClass) ? ' class="'.$theClass.'"' : '';
                        $widget = '<input type="'.$meta[$idx]['type'].'" value="'.$field.'" name="'.$fixedName.'"'.$cls.$min.$max.$jmp.' />';
                    } else if ($meta[$idx]['type'] == 'textarea') {
                        $sizes = do_list($meta[$idx]['size']);
                        $w = ($sizes[0] === '') ? '' : ' cols="' . $sizes[0] . '"';
                        $h = (isset($sizes[1]) && $sizes[1] !== '') ? ' rows="' . $sizes[1] . '"': '';
                        $cls = ($theClass) ? ' class="'.$theClass.'"' : '';
                        // TODO: maybe use text_area() when 4.6.0 released as it may support classes
                        $widget = '<textarea name="' . $fixedName . '" id="' . $prefixedName . '"'. $cls.$w.$h.'>'.$field.'</textarea>';
                    } else if ($meta[$idx]['type'] == 'image') {
                        $widget = fInput('text', $fixedName, $field, $theClass, '', '', '', '', $prefixedName);
                    } else {
                        $widget = fInput($meta[$idx]['type'], $fixedName, $field, $theClass, '', '', '', '', $prefixedName);
                    }

                    $smd_bio_data[$theName]['widget'] = $widget;
                    $replacements['{'.$prefixedName.'_widget}'] = $widget;

                    // Without container/form, build up generic output
                    if ($isSingle) {
                        $taglab = (($label==1) ? $theTitle : (($label=='') ? '' : (($label) ? $label : $theName)));
                        $toParse[] = doLabel($taglab, $labeltag) . (($break) ? doTag($field, $break, $theClass) : $field);
                    }
                }
            }
        }

        if ($debug) {
            echo '++ BIO REPLACEMENTS FOR ' .$author. ' ++';
            dmp($replacements);
        }

        if (!$isSingle) {
            $toParse[] = $thing;
        }

        $out[] = parse(strtr(join(n, $toParse), $replacements));
        $final = doWrap($out, $wraptag, '', $class);
    }
    return $final;
}

// Output data
function smd_bio_data($atts, $thing = null) {
    global $smd_bio_data, $smd_bio_option_data;

    extract(lAtts(array(
        'field'   => '',
        'item'    => 'value',
        'wraptag' => '',
        'break'   => '',
        'class'   => '',
        'debug'   => 0,
    ), $atts));

    $bdata = is_array($smd_bio_data) ? $smd_bio_data : array();
    $idata = is_array($smd_bio_option_data) ? $smd_bio_option_data : array();
    $datapool = array_merge($bdata, $idata);

    if ($debug) {
        echo '++ AVAILABLE BIO DATA ++';
        dmp($datapool);
    }

    $items = do_list($item);
    $out = array();
    foreach ($items as $it) {
        if (isset($datapool[$field][$it])) {
            $out[] = $datapool[$field][$it];
        }
    }

    return doWrap($out, $wraptag, $break, $class);
}

// Iterate over N multi-items
function smd_bio_iterate($atts, $thing = null) {
    global $smd_bio_data, $smd_bio_option_data;

    extract(lAtts(array(
        'field'      => '',
        'using'      => 'chosen', // chosen, default, all
        'display'    => 'title', // option, title
        'prefix'     => 'smd_bio_', // Only for replacement variables
        'wraptag'    => '',
        'class'      => '',
        'break'      => '',
        'form'       => '',
        'limit'      => 0,
        'offset'     => 0,
        'debug'      => 0,
    ), $atts));

    if ($debug > 1) {
        echo '++ AVAILABLE BIO DATA ++';
        dmp($smd_bio_data);
    }

    // Validation 1
    if (!isset($smd_bio_data[$field])) {
        return;
    }

    // Validation 2
    $usingMap = array('all' => '', 'chosen' => 'chosen', 'default' => 'default');
    $using = isset($usingMap[$using]) ? $using : 'chosen';

    // Validation 3
    $displayMap = array('option' => 'option', 'title' => 'title');
    $display = isset($displayMap[$display]) ? $display : 'title';

    switch ($using) {
        case 'chosen':
        case 'default':
            $countType = $using.'_count';
            break;
        case 'all':
        default:
            $countType = 'option_count';
        break;
    }

    $out = $reps = $stash = array();

    // Whip through the lists to gather the info for later.
    if (isset($smd_bio_data[$field]['option_count'])) {
        for ($idx = 1; $idx <= $smd_bio_data[$field]['option_count']; $idx++) {
            $opt = $smd_bio_data[$field]['option_'.$idx];
            $ttl = $smd_bio_data[$field]['title_'.$idx];
            $stash['option'][$opt] = $ttl;
        }
    }
    if (isset($smd_bio_data[$field]['chosen_count'])) {
        for ($idx = 1; $idx <= $smd_bio_data[$field]['chosen_count']; $idx++) {
            $opt = $smd_bio_data[$field]['chosen_option_'.$idx];
            $ttl = $smd_bio_data[$field]['chosen_title_'.$idx];
            $stash['chosen'][$opt] = $ttl;
        }
    }
    if (isset($smd_bio_data[$field]['default_count'])) {
        for ($idx = 1; $idx <= $smd_bio_data[$field]['default_count']; $idx++) {
            $opt = $smd_bio_data[$field]['default_option_'.$idx];
            $stash['default'][] = $opt;
        }
    }

    $checked_types = array('checkbox', 'radio', 'yesnoradio');
    $selected_types = array('list', 'multilist');

    // Do the iteration for real now, taking limit and offset into account.
    // Can only iterate over items that have a count.
    if (isset($smd_bio_data[$field][$countType])) {
        for ($idx = 1; $idx <= $smd_bio_data[$field][$countType]; $idx++) {
            if (( ($idx-1) >= $offset ) && ( $limit == 0 || (($idx-1) < $limit+$offset) )) {
                $key = (($usingMap[$using]) ? $usingMap[$using]. '_' : '') . ($using === 'default' && $display === 'title'? 'option' : $displayMap[$display]) . '_' . $idx;
                $uval = $usingMap[$using] ? $usingMap[$using].'_' : '';
                $opt = $smd_bio_data[$field][$uval . 'option_'.$idx];

                // No title in smd_bio_data for default, so fudge it
                if ($using === 'default') {
                    $ttl = $stash['option'][$opt];
                } else {
                    $ttl = $smd_bio_data[$field][$uval . 'title_'.$idx];
                }

                $reps = array(
                    '{'.$prefix.'iterate_option}' => $opt,
                    '{'.$prefix.'iterate_title}' => $ttl,
                    '{'.$prefix.'iterate_count}' => $idx,
                );

                // When iterating all options, indicate which are default and which are chosen
                if ($using === 'all') {
                    if (isset($stash['default']) && in_array($opt, $stash['default'])) {
                        $reps['{'.$prefix.'iterate_is_default}'] = '1';
                        $smd_bio_option_data[$field]['iterate_is_default'] = '1';
                    } else {
                        $reps['{'.$prefix.'iterate_is_default}'] = '0';
                        $smd_bio_option_data[$field]['iterate_is_default'] = '0';
                    }

                    if (isset($stash['chosen']) && array_key_exists($opt, $stash['chosen'])) {
                        $flavour = $smd_bio_data[$field]['type'];
                        $html_sel = (in_array($flavour, $checked_types)) ? 'checked' : ((in_array($flavour, $selected_types)) ? 'selected' : '1');
                        $reps['{'.$prefix.'iterate_is_chosen}'] = $html_sel;
                        $smd_bio_option_data[$field]['iterate_is_chosen'] = $html_sel;
                    } else {
                        $reps['{'.$prefix.'iterate_is_chosen}'] = '';
                        $smd_bio_option_data[$field]['iterate_is_chosen'] = '';
                    }
                }

                $smd_bio_option_data[$field]['iterate_option'] = $opt;
                $smd_bio_option_data[$field]['iterate_title'] = $ttl;
                $smd_bio_option_data[$field]['iterate_count'] = $idx;
                if ($debug) {
                    echo '++ ITERATOR REPLACEMENTS ++';
                    dmp($reps);
                }
                $out[] = ($thing !== null) ? parse(strtr($thing, $reps)) : ( ($form !== '') ? parse_form(strtr($form, $reps)) : $smd_bio_data[$field][$key] );
            }
        }
    }

    return doWrap($out, $wraptag, $break, $class);
}

// Convenience conditional to test a field/item. Use smd_if for more advanced conditional logic
function smd_if_bio($atts, $thing = null) {
    global $smd_bio_data, $smd_bio_option_data;

    extract(lAtts(array(
        'field'          => '',
        'item'           => 'value',
        'value'          => null,
        'case_sensitive' => 1,
        'debug'          => 0,
    ), $atts));

    $bdata = is_array($smd_bio_data) ? $smd_bio_data : array();
    $idata = is_array($smd_bio_option_data) ? $smd_bio_option_data : array();
    $datapool = array_merge($bdata, $idata);
    if ($debug) {
        echo '++ AVAILABLE BIO DATA ++';
        dmp($datapool);
    }

    $result = false;

    if (isset($datapool[$field][$item])) {
        if ($value !== null) {
            $cval = ($case_sensitive) ? $value : strtolower($value);
            $citm = ($case_sensitive) ? $datapool[$field][$item] : strtolower($datapool[$field][$item]);
            $result = ((string)$citm === (string)$cval);
        } else {
            $result = ($datapool[$field][$item] !== '');
        }
    }

    return parse(EvalElse($thing, $result));
}

// Conditional to test bio meta data
function smd_if_bio_is($atts, $thing = null) {
    global $smd_bio_meta_data;

    extract(lAtts(array(
        'type'  => 'author', // author, field
        'item'  => 'first', // first, last
        'debug' => 0,
    ), $atts));

    $datapool = is_array($smd_bio_meta_data) ? $smd_bio_meta_data : array();
    if ($debug) {
        echo '++ AVAILABLE BIO META DATA ++';
        dmp($datapool);
    }

    $result = false;

    if (isset($datapool[$type][$item])) {
        $result = $datapool[$type][$item];
    }

    return parse(EvalElse($thing, $result));
}

// Convenience conditional
function smd_if_bio_first_author($atts, $thing = null) {
    return smd_if_bio_is(array('type' => 'author', 'item' => 'first'), $thing);
}
// Convenience conditional
function smd_if_bio_last_author($atts, $thing = null) {
    return smd_if_bio_is(array('type' => 'author', 'item' => 'last'), $thing);
}


// ------------------------
// A wrapper to article_custom that auto sets the user to the one specified
// or the current article's author.
// NOTE: lAtts() is NOT used because that limits the plugin attributes.
function smd_bio_articles($atts, $thing = null) {
    global $thisarticle;

    $author = (isset($atts['author'])) ? $atts['author'] : (isset($thisarticle['authorid']) ? $thisarticle['authorid'] : '');
    $atts['author'] = $author;
    return parseArticles($atts, '1', $thing);
}
# --- END PLUGIN CODE ---
if (0) {
?>
<!--
# --- BEGIN PLUGIN HELP ---
h1. smd_bio

Configure additional user biographical information to be collected when authors are created, then show that info as bylines against your articles. Like custom fields for user info.

h2. Features

* Define only the info you require to be collected about your users -- totally customisable.
* Info is entered/edited on the _Admin->Users_ tab beneath the existing user info (i.e. Publishers only) but also integrates with smd_user_manager.
* Choose from a variety of types of content: text, radio buttons, select lists, checkboxes, images...
* Output any pieces of biographical info in your article flow in a variety of ways.
* Image thumbnail preview/selection on the _Admin->Users_ tab.
* Optional CSS to modify the layout of the _Admin->Users_ tab.

h2. Installation / Uninstallation

p(important). Requires Textpattern 4.5+

Download the plugin from either "textpattern.org":http://textpattern.org/plugins/1116/smd_bio, or the "software page":http://stefdawson.com/sw, paste the code into the Textpattern _Admin->Plugins_ pane, install and enable the plugin. The plugin's tables will be installed automatically. Visit the "forum thread":http://forum.textpattern.com/viewtopic.php?id=31496 for more info or to report on the success or otherwise of the plugin.

When you visit the _Extensions->Bio config_ page, the plugin's tables will be checked and installed/upgraded automatically if not present already. This is a convenience for people who run the plugin from the cache directory.

To uninstall, delete the plugin from the _Admin->Plugins_ page. *All additional user tables and bio information will be removed* so be sure you have backed everything up first.

h2(#smd_bio_config). Configuring bio fields

Visit the _Extensions->Bio config_ tab. Add bio fields such as cell/work/fax numbers, department, mugshot, postal address, job title, whatever you wish. Just add one at a time and hit Save in between. The fields will be listed below the form, and can be sorted at will. Each field comprises:

; *Name*
: An internal name with which you wish to refer to your bio field. Avoid characters such as spaces and 'weird' characters here. *This cannot be changed, once set*
; *Title*
: A human-friendly name for your bio field. This is displayed to the left of the input control on the _Admin->Users_ panel, and as a label on your article pages.
: If you omit the *Name* then this Title will be "dumbed down" to valid characters and used as the Name.
; *Type*
: The type of data you wish to collect. This will be the flavour of input control you see on the _Admin->Users_ panel
; *Column type* (click 'More' to toggle)
: The database column type that is created. If you don't know what this is, just accept the default, otherwise specify which type of data this column is to hold
: Note that some types of biographical information (e.g. images) are forced to be of a certain column type
: IMPORTANT: if you alter this after it has been created, any existing bio data in the field will be altered to suit and *you may lose information*. Please backup first
; *Column size* (click 'More' to toggle)
: Some column types -- most notably the varchar types -- require a column width (or display width) to be specified. Put a value here if you wish to use a size other than the default. If you don't know what this is, just accept the default
: Note that most column types do not require a size so if you do specify one it will be ignored
: IMPORTANT: if you alter this after it has been created, any existing bio data in the field will be altered to suit and *you may lose information*. Please backup first
; *Size*
: The dimensions of the chosen input control. Takes one or two comma-separated values, the interpretation of which depends on the field _Type_:
:: For text-based input fields, the first is the width of the box (in characters) and the second is the maximum number of characters a user can type (0 = leave at default)
:: For numeric-based input fields, the values are the minimum permitted value, the maximum permitted value, and the acceptable interval (step) that value can take
:: For textareas, it is the width and height of the box in characters, respectively
:: For images, the two values are the x and y dimensions of the image/thumbnail on the _Admin->Users_ tab. If only one value is given, the image will be square. If either value is omitted, the image or thumbnail dimensions (as stored in the database) are used instead
:: For other types, the Size is currently unused
; *Value*
: Depends on the field _Type_:
:: For text- and number-based fields, this is the default value that will be placed in the text box. You can use this to initialise an entry, or offer instructions like "Type your job description here"
:: For images, it can be used to specify the parent category name under which the desired images are stored. If omitted, all images are available in the image select list
:: For list, radio and checkbox types, this is used to configure the available options:
::: Either put one option per line or use a comma to separate each option.
::: If you just list options, they will be used as labels exactly as you define them. When referring to them with the public tags, the field _names_ will be all lower case, have 'non-web-safe' characters removed, and will have spaces converted to underscores. See the "example":#smd_bio_list_example for further details.
::: In select lists, you may put an empty option at the top if you wish by beginning the list with a comma.
::: In select lists, checkbox and radio sets you may also mark label(s) with @[*]@ to indicate it is a default checked/selected value. Radio sets and single-select lists only permit one marker.
; *Position*
: The order in which the fields appear on the __Admin->Users__ tab
: You can use any alphanumeric system to sort, e.g @10, 20, 30,...@ or @a, b, c...@. The lower the value the higher up the screen it will be

h3(#smd_bio_list_example). Defining your own lists

There are a few of ways to define your own names and/or labels for use in select lists, radio sets and checkbox groups:

bc(block). label_1
label_2
label_3
...

or

bc(block). label_1, label_2, label_3, ...

or

bc(block). name_1 => label_1, name_2 => label_2, name_3...

(you may also put each name-label pair on a separate line if you wish). Here's an example for a dropdown list of Departments:

bc(block). sales => Sales
mktg => Marketing
eng => Engineering
qual => Quality assurance
it => Tech support

If you defined the list as above, your field names would be @sales@, @mktg@, @eng@, @qual@ and @it@, respectively.

If, however, you omit the field names, viz:

bc(block). Sales
Marketing
Engineering
Quality assurance
Tech support

then you would refer to the fields with: @sales@, @marketing@, @engineering@, @quality_assurance@, and @tech_support@, respectively.

For multiple select lists and checkbox groups you can optionally define some of the entries as defaults. For example in your @subscriptions@ checkbox group:

bc(block). Kerrang
Future Music[*]
NME
Sound on Sound[*]

The same system applies to single or multiple select lists and radio sets, although for single selects and radio sets, only one element may be starred (if you star more, only one of the defaults will prevail).

There is one further method of entering data, and that is to call another PHP function. Perhaps you want to offer a select list of all countries of the world. You could type them in or copy and paste them from the Internet as long as they were in the correct @key => value@ or comma-separated format. Alternatively you could put this in the _Value_ box:

bc. SMD_BIO_FN|function_name|param_1|param_2|...

As long as that named function returns a standard PHP array, the values and any keys returned will be injected into the _Value_ box automatically.

h2. Entering user data: _Admin->Users_

Your configured fields will appear on the _Admin->Users_ panel, beneath the usual crop of data input fields. Simply enter data in them and it will be saved along with the existing user data. Hit _Edit_ and any configured info will be retrieved for editing.

When choosing an image, you can either type its ID in the box or use the dropdown select list to choose an image. The chosen image will appear below the input controls.

If you wish to alter the layout of the input controls, you may create a standard stylesheet in _Presentation->Style_ and name it @smd_bio@. It will be loaded automatically when you visit the _Admin->Users_ panel.

To quickly view the extended bio information for a user, hover over the user's login name link in the list; a tooltip will appear showing some of the extended bio. The data is fetched from the server when you first hover over the row so it may take a few seconds to load (and may require you to wiggle the mouse around a bit to get it to display, sorry!)

h2. Entering user data: _Admin->User manager_

If you have the smd_user_manager plugin installed, smd_bio will hook into that plugin. When you hover over the login name of an entry in the User list, detailed bio information is retrieved and displayed as a tooltip. Editing a user will also permit Bio information to be entered.

h2. Displaying user bio info on your site

When you create a new _field_ in the "Bio Config":#smd_bio_config pane, it has various attributes like name, title, size, value, etc. The @name@ is the key: that is how you refer to the field using the @field@ or @fields@ attributes in the various tags (below).

Each field has a variety of pieces of data that you may display. These are known as @items@ and the primary ones are:

# @value@ : the current value of the field that has been selected / typed by the user in their profile.
# @title@ : the human friendly title (a.k.a. _label_) that you have assigned to your field in the Bio Config screen -- this is handy if you want to print out the title alongside the data value itself, e.g. Department: sales.
# @name@ : the field's key (shown in the 'Name' column on the Bio Config panel). This is of less everyday use, but when building your own input screens for capturing bio data, it becomes handy so you can tell mem_form the name of the field it needs to store the bio data item in.

There are other items useful for displaying the field type, various counters, or for diving deeper into the available options in lists, radio sets, checkboxes, etc, but the most important concept is that a @field@ is your bio thingamybob (it's Name / Key), and an @item@ is the part of thingamybob you want to display: its value, title, name (a.k.a. key), type, default values, and so on.

h2(#smd_bio_info). Tag: @<txp:smd_bio_info>@

Use this tag to display pieces of info from a user's biography. One or more of these tags can be employed depending on how you prefer to work. It may also be used as a container (or via the @form@ attribute) to allow you to embed other Textpattern tags.

This tag requires article context so you normally use it inside @<txp:if_individual_article>@ tags. By default it will look up the author of the currently viewed article and display the given bio fields from that author.

If you're trying to display bio info in a sidebar or on a list page that does not necessarily have article context, you need to specify the author(s) that you wish to display bio info from. In v0.3x you did that with the @authors@ attribute; in v0.4x you wrap your @<txp:smd_bio_info>@ tag in an "smd_bio_author":#smd_bio_author tag.

Use the following attributes to tweak this tag's output. The default value is unset unless otherwise noted:

; *fields*
: List of bio field _names_ you wish to display, in the order you wish to display them.
: Note you can also display bio information from the standard Textpattern user table, i.e. you can use any of the following, (case sensitive) : @user_id@, @name@ (login name), @RealName@, @email@, @privs@, @last_access@
: Default: unset (i.e. all fields)
; *exclude*
: List of bio field _names_ you do *not* wish to display. This overrides @fields@
; *form*
: The name of a Txp Form with which to process each record.
: If not specified, the tag's container will be used.
: If the container is empty, default output is used (label and field contents).
; *wraptag*
: HTML tag (without angle brackets) to wrap around each record.
; *class*
: Fixed CSS class name to add to each record's wraptag.
; *break*
: HTML tag to put between each field.
; *breakclass*
: CSS class name to add to each field's wraptag.
: The default is to automatically assign @smd_bio_*name_of_field*@ (e.g. smd_bio_cell, smd_bio_dept, etc).
; *labeltag*
: HTML tag (without angle brackets) to wrap around the field's label.
; *label*
: Whether to display a label or not for each field. Choose from:
:: *1* : (default) Display the Title of the field
:: *0* : Display the Name of the field
:: *some label* : Display the given text as a label
:: "" (i.e. empty) : Do not display any label
; *show_empty*
: Choose if you wish to hide (0) or show (1) any fields that have no data assigned to them.
: Default: 0

h2(#smd_bio_author). Tag: @<txp:smd_bio_author>@

Wrap this tag around @<txp:smd_bio_info>@ tags to display information from more than one user. The contained content will be displayed for each author.

; *author*
: Comma-separated list of author login names from which you wish to display info.
: If omitted, the current (individual article) author will be used -- functionally the same as if you just used @<txp:smd_bio_info />@ without the author wrapper tag.
: You may specify any of your comma-separated entries as @SMD_PRIVS:@ and then colon-separate the priv numbers. Any users with those matching level(s) will be displayed.
: You may also use @SMD_ALL@ to return all defined authors.
; *sort*
: Order the authors by the given comma-separated list of columns and sort directions. You can order the results by any of the built-in user columns (RealName, name, user_id, email, last_access, privs) or your own bio fields.
: For the sort order you can choose from:
:: *asc*: ascending order
:: *desc*: descending order
: Default: @RealName asc@
; *form*
: The name of a Txp Form with which to process each author.
: If not specified, the tag's container will be used.
: If the container is empty, the name of the author is displayed.
; *wraptag*
: HTML tag (without angle brackets) to wrap around the entire output.
; *class*
: CSS class name to add to the wraptag.
; *break*
: HTML tag (without angle brackets) to wrap around each author record.
; *breakclass*
: CSS class name to apply to each break tag.
; *labeltag*
: HTML tag (without angle brackets) to wrap around the label.
; *label*
: The label text to display above all author info.

h2(#smd_bio_data). Tag: @<txp:smd_bio_data>@ ^(formerly "replacement keys":#smd_bio_repkeys)^

Inside your "smd_bio_info":#smd_bio_info form or container you can display biographical information using this tag. The following attributes select which piece of information to display:

; *field*
: The bio field from you wish to display some information (e.g. cell, phone, address, department, ...).
; *item*
: Comma-separated list of actual piece(s) of information you need about the field. Choose from:
:: *value* : the field's content. Functionally equivalent to @{smd_bio_*field*}@.
:: *name* : the field's name, as defined on the Bio Config tab. Equivalent to @{smd_bio_*field*_name}@.
:: *title* : the field's "human friendly" title, as defined on the Bio Config tab. Equivalent to @{smd_bio_*field*_title}@.
:: *type* : the field's data type (text, textarea, checkbox, select list, etc). Note that there is no distinction between a single checkbox and a group of them; they are all designated @checkbox@.
:: *default* : the field's pre-initialized, or default value. This is only set for non-list field types: list fields have individual 'default_option_N' entries instead (see below).
:: *option_N* : the name (key) of the Nth option in a list (select, radio, checkbox group).
:: *title_N* : the title of the Nth option in a list.
:: *chosen_option_N* : the name (key) of the Nth selected option in a list  (select, radio, checkbox group).
:: *chosen_title_N* : the label of the Nth selected option in a list.
:: *default_option_N* : the name (key) of the Nth default option
:: *option_count* : the total number of list options.
:: *chosen_count* : the total number of selected list entries.
:: *default_count* : the total number of default list entries.
:: *iterate_option* : the name of the current option being iterated (see "smd_bio_iterate":#smd_bio_iterate).
:: *iterate_title*: the human-friendly title of the current option being iterated.
:: *iterate_count* : The option number count (starting from 1).
:: *iterate_is_default* : if you are iterating over @all@ options, this will be set to 1 if the option is one of the options that should be set by default if nothing has already been chosen. 0 otherwise.
:: *iterate_is_chosen* : if you are iterating over @all@ options, this will be set to either @checked@ or @selected@ (depending on the field's type) if the current option is chosen.
:: *widget* : an HTML input control of the correct type for this field. Multi select options are prefixed with @ms_@ and each checkbox value in a group is prefixed with @cb_@ when submitted. Note also that 'image' fields render a simple text input field because they only store a standard Textpattern image ID; if you want to do anything more elaborate you will have to roll it yourself. Use widgets inside one of the following constructs to allow updating of bio fields from the public site / dashboards:
::: @<txp:mem_form type="smd_bio">@
::: @<txp:mem_self_register>@
::: @<txp:mem_self_user_edit>@
: Default: @value@
; *wraptag*
: HTML tag (without angle brackets) to wrap around the entire output.
; *class*
: Fixed CSS class name to add to the wraptag.
; *break*
: HTML tag or characters to put between each item.

If you wish to see an entire list of available data, add @debug="1"@ to the surrounding smd_bio_info tag.

h3(#smd_bio_repkeys). @<txp:smd_bio_info>@ replacement keys ^(*deprecated*)^

The "smd_bio_data":#smd_bio_data tag will be able to fit all your bio display needs. There is, however, a legacy method of displaying data using __replacement keys__. These should be considered deprecated and their use discouraged in favour of the smd_bio_data tag. They may be removed in future versions of the plugin.

The main replacement keys are:

; @{smd_bio_*field*}@
: The value of the named field (e.g. @smd_bio_cell@, @smd_bio_department@, etc).
; @{smd_bio_*field*_name}@
: Sanitized name of the column corresponding to this named field.
; @{smd_bio_*field*_title}@
: Human-friendly title you assigned this named field.
; @{smd_bio_*field*_class}@
: Name of the class associated with this named field.

If you have elected to extract a list item such as radio, list or checkbox you will have some more replacement keys in the following format:

; @{smd_bio_*field*_option_N}@
: The value of each named option in turn, where N starts at 1 and increments.
; @{smd_bio_*field*_title_N}@
: The value of each named option's title in turn. Again, N starts at 1 and counts up for every option in your list.
; @{smd_bio_*field*_chosen_option_N}@
: The value of each selected option in turn, where N starts at 1 and increments.
: For select and radio lists there will be only one; for checkbox groups there may be more.
; @{smd_bio_*field*_chosen_title_N}@
: The value of each selected option's label in turn, where N starts at 1 and increments.
; @{smd_bio_*field*_option_count}@
: The total number of elements in the named list.
; @{smd_bio_*field*_chosen_count}@
: The total number of selected elements in the named list.

Further, if you are displaying just a single @field@, these replacements (backwards compatible with smd_bio v0.3x) are present:

; @{smd_bio_info_item}@
: The value of the current field -- Deprecated: use @{smd_bio_info_value}@ instead.
; @{smd_bio_info_name}@
: The sanitized name of the column corresponding to the field.
; @{smd_bio_info_title}@
:  The human-friendly title you assigned the field.
; @{smd_bio_info_itemclass}@
: The name of this field's class -- Deprecated: use @{smd_bio_info_class}@ instead.

h2(#smd_if_bio). Tag: @<txp:smd_if_bio>@

A simple conditional for testing a field / item. Must be used inside a @<txp:smd_bio_info />@ tag to test for the existence/value of one of your chosen bio items. Use smd_if for more complex conditional logic. Supports @<txp:else />@.

; *field*
: The bio field from you wish to check (e.g. department, preferred_contact, phone_number,...)
; *item*
: The actual piece of information you want to compare from the field. Choose from the same items as defined in the @item@ attribute for the "smd_bio_data":#smd_bio_data tag.
: Default: @value@
; *value*
: The value you wish to compare the field/item against.
: If omitted, the tag will just check for the existence of the given field/item

h2(#smd_if_bio_first_author). Tag: @<txp:smd_if_bio_first_author>@

Parses the container if the current author is the first in the list. Must be used inside a @<txp:smd_bio_info />@ tag, and supports @<txp:else />@.

h2(#smd_if_bio_last_author). Tag: @<txp:smd_if_bio_last_author>@

Parses the container if the current author is the last in the list. Must be used inside a @<txp:smd_bio_info />@ tag, and supports @<txp:else />@.

h2(#smd_bio_iterate). Tag: @<txp:smd_bio_iterate>@

Step through select list, checkbox, and radio sets with this tag, displaying info about each option as you go. Useful if you want to roll your own widgets or do some custom interaction.

; *field*
: The bio field over which you wish to iterate (e.g. preferred_contact, subscription, favourite_rockstar, ...). Must be a 'list' or 'group' field.
; *using*
: The type of info you want to iterate over. Choose from:
:: @chosen@: step over chosen (selected, checked) options.
:: @default@: step over default (pre-selected) options.
:: @all@: step over all items in the group, whether selected or not.
: Default: @chosen@
; *display*
: The piece of information you wish to output from the option. This attribute is ignored if you use a @form@ or the container. Choose from:
:: @option@: the internal name of the option
:: @title@: the option's human-friendly title
: Default: @title@
; *form*
: The name of a Textpattern Form with which to process each option.
: If not specified, the tag's container will be used.
: If the container is empty, default output is used (the option's value).
; *wraptag*
: HTML tag (without angle brackets) to wrap around the group.
; *class*
: Fixed CSS class name to add to the group's wraptag.
; *break*
: HTML tag to put between each option.
; *breakclass*
: CSS class name to add to each option's wraptag.
; *limit*
: The maximum number of options to iterate over.
: Default: 0 (i.e. all of them)
; *offset*
: The number of options to skip before starting to display options.
: Default: 0

See the "smd_bio_data":#smd_bio_data tag for details of what you can display / test inside this tag's container.

h2(#smd_bio_articles). Tag: @<txp:smd_bio_articles>@

A simple convenience wrapper for @<txp:article_custom />@ that sets the @author@ attribute to the person who wrote the current article. If you specify an author, that person will be used instead. In all other regards, the tag functions identically to "article_custom":http://textpattern.net/wiki/index.php?title=article_custom and can be used as a container if you wish.

p(important). IMPORTANT: take care when using this tag inside your default form. If you do not specify your own container or a dedicated @form@, you will receive a _circular reference error_ from Textpattern as it tries to call the default form, which calls the default form, which calls the default form...

h3(#smd_bio_examples). Examples

h2(#smd_bio_eg1). Example 1: List bio fields from author of current article

bc. <txp:smd_bio_info
     fields="jobtitle, extension, cell, department"
     labeltag="dt" wraptag="dl"
     break="dd" class="profile" />

Shows the job title, work's extension number, cell phone number and department of the current author, as a definition list with class @profile@.

h2(#smd_bio_eg2). Example 2: List profiles for named + priv level users

bc. <txp:smd_bio_author wraptag="div" class="authors"
     author="mr_pub, SMD_PRIVS:4:3">
   <txp:smd_bio_info
     fields="name, RealName, department"
     labeltag="dt" wraptag="dl"
     break="dd" />
</txp:smd_bio_author>

Shows the name, real name and department of all _Copy Editors_ (3) and _Staff Writers_ (4) and the user 'mr_pub'.

h2(#smd_bio_eg3). Example 3: Using smd_bio_articles and smd_bio_info as a container

bc. <txp:smd_bio_info fields="photo, department, RealName">
   <txp:image id='<txp:smd_bio_data field="photo" />' />
   Recent articles by
   <a href="/desks/<txp:smd_bio_data field="department" />">
      <txp:smd_bio_data field="RealName" />
   </a>:
</txp:smd_bio_info>
<txp:smd_bio_articles limit="6"
     wraptag="ul" break="li">
   <txp:permlink><txp:title /></txp:permlink>
</txp:smd_bio_articles>

Displays the author photo, the author's RealName linked to the section that explains about the department to which she belongs, then lists the 6 most recent articles by her. Note the use of @<txp:smd_bio_data />@ to feed @<txp:image />@ with the ID of the selected photo.

h2(#smd_bio_eg4). Example 4: checkboxes, lists and radios

bc. <txp:smd_bio_author="SMD_PRIVS:5">
   <txp:smd_bio_info fields="name, image, contact_by,
      subscribed, department">
      <a class="image"
        href="/blog/<txp:smd_bio_data field="name" />"
        title="browse other posts by this author">
         <img class="thumb"
           src="/images/<txp:smd_bio_data field="image" />.jpg" />
      </a>
      <div class="summary">
         <h3>Department</h3><txp:smd_bio_data field="department" />
         <h3>Bio</h3><txp:smd_bio_data field="profile" />
         <h3>Preferred contact method</h3><txp:smd_bio_data field="contact_by" />
         <h3>Subscribed to</h3><txp:smd_bio_data field="subscribed" />
      </div>
   </txp:smd_bio_info>
</txp:smd_bio_author>

h2(#smd_bio_eg5). Example 5: telephone directory of users

If you have some bio fields such as surname, forename, department, phone, avatar, and so forth you could display a quick directory of all your users as follows. The snippet of PHP is just a quick way of getting the first letter of the surname; you could be far more creative here and link to a full bio or filter by letter, and so on.

bc.. <txp:smd_bio_author author="SMD_ALL" sort="surname asc">

   <txp:smd_bio_info wraptag="dl">
      <txp:php>
         global $variable;
         $variable['initial'] = substr(
            smd_bio_data(array('field' => 'surname')),
            0, 1);
      </txp:php>

      <txp:if_different><dt><txp:variable name="initial" /></dt></txp:if_different>

      <dd class="name">
         <txp:smd_bio_data field="surname" />,
         <txp:smd_bio_data field="forename" />
      </dd>

      <txp:smd_if_bio field="department">
         <txp:smd_bio_data field="department"
            item="title, value" break=": "  wraptag="dd" />
      </txp:smd_if_bio>

      <txp:smd_if_bio field="phone">
         <txp:smd_bio_data field="phone"
            item="title, value" break=": "  wraptag="dd" />
      </txp:smd_if_bio>
   </txp:smd_bio_info>

</txp:smd_bio_author>

h2(#smd_bio_eg6). Example 6: updating a profile from the public side

With mem_form installed and some suitable privilege wrapper plugin such as rvm_privileged or cbe_frountauth you can present a public profile for your users to maintain. This example uses @<txp:mem_form>@ with the @type="smd_bio"@ attribute but the plugin is equally at home within @<txp:mem_self_register>@ (so you can capture extended bio information at sign-up time) or inside @<txp:mem_self_user_edit_form>@.

bc.. <txp:mem_form type="smd_bio">

<txp:smd_bio_info show_empty="1">
   <br /><txp:smd_bio_data field="name" item="title, widget" break=": " />
   <br /><txp:smd_bio_data field="RealName" item="title, widget" break=": " />
   <br /><txp:smd_bio_data field="email" item="title, widget" break=": " />
   <br /><txp:smd_bio_data field="avatar" item="title, widget" break=": " />
   <br /><txp:smd_bio_data field="mini_bio" item="title, value" break=": " />
   <br /><txp:smd_bio_data field="phone_home" item="title, widget" break=": " />
   <br /><txp:smd_bio_data field="phone_work" item="title, widget" break=": " />
   <br /><txp:smd_bio_data field="marketing_preference" item="title, widget" break=": " />
</txp:smd_bio_info>
<txp:mem_submit />

</txp:mem_form>

h2. Author / Credits

"Stef Dawson":http://stefdawson.com/contact. The plugin is a logical extension of pvc_users_info by Peter V. Cook (the smd_bio_articles tag is essentially the same as pvc_author_articles). Thanks also to pieman for setting the wheels in motion and net-carver for his inimitable knack of making things better.

h2. Changelog

* 23 Oct 2014 | 0.41 | Fixed array-to-string conversion in javascript comment (thanks aslsw66)
* 25 Feb 2013 | 0.40 | Improved performance and reduced server load by up to 90% (thanks jakob); plugin lifecycle aware; permitted configurable database column types/sizes and international characters; removed base64 css; added smd_bio_author, smd_bio_data, smd_bio_iterate, smd_if_bio, smd_if_bio_first_author and smd_if_bio_last_author tags; added @show_empty@ attribute; deprecated @author@, @items@ (now @fields@), @labelclass@, @itemwraptag@ (now @break@) and @itemclass@ (now @breakclass@); altered replacement key names; fixed and improved hover tooltips on _Admin->Users_ tab; increased default varchar size to 255 (thanks hablablow); added multi-select lists and permitted checkboxes to be marked as default; added more field types for HTML 5 widgets; enabled @SMD_BIO_FN|function|param|param|...@ support when defining fields to call arbitrary functions; experimental support for item="widget" to display an input control for the given field
* 08 Jun 2010 | 0.31 | Javascript only appears on admin tab (thanks redbot/Gocom)
* 31 Aug 2009 | 0.30 | Removed @item@ attribute; fixed warning message if using single items; hidden pref @smd_bio_sanitize_name@ forces sanitized login names
* 21 Aug 2009 | 0.20 | First public release; no image/thumb output; experimental @options@ attribute removed; container/form accepts Txp tags; fixed textbox size limit (thanks MattD)
* 14 Jul 2009 | 0.10 | Initial (non-public) release
# --- END PLUGIN HELP ---
-->
<?php
}
?>
