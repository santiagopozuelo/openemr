<?php
// Copyright (C) 2010-2017 Rod Roark <rod@sunsetsystems.com>
//
// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; either version 2
// of the License, or (at your option) any later version.
require_once("../globals.php");
require_once("$srcdir/acl.inc");
require_once("$srcdir/options.inc.php");

use OpenEMR\Core\Header;

$typeid = formData('typeid', 'R') + 0;
$parent = formData('parent', 'R') + 0;
$ordtype = isset($_REQUEST['addfav']) ? $_REQUEST['addfav'] : '';
$disabled = $ordtype ? "disabled" : '';
$labid = isset($_GET['labid']) ? $_GET['labid'] + 0 : 0;
$info_msg = "";

function QuotedOrNull($fld)
{
    $fld = add_escape_custom(trim($fld));
    if ($fld) {
        return "'$fld'";
    }

    return "NULL";
}

function invalue($name)
{
    $fld = formData($name, "P", true);
    return "'$fld'";
}

function rbinput($name, $value, $desc, $colname)
{
    global $row;
    $ret = "<input type='radio' name='$name' value='$value'";
    if ($row[$colname] == $value) {
        $ret .= " checked";
    }

    $ret .= " />$desc";
    return $ret;
}

function rbvalue($rbname)
{
    $tmp = $_POST[$rbname];
    if (!$tmp) {
        $tmp = '0';
    }

    return "'$tmp'";
}

function cbvalue($cbname)
{
    return empty($_POST[$cbname]) ? 0 : 1;
}

function recursiveDelete($typeid)
{
    $res = sqlStatement("SELECT procedure_type_id FROM " .
        "procedure_type WHERE parent = '$typeid'");
    while ($row = sqlFetchArray($res)) {
        recursiveDelete($row['procedure_type_id']);
    }

    sqlStatement("DELETE FROM procedure_type WHERE " .
        "procedure_type_id = '$typeid'");
}


?>
<!DOCTYPE html>
<html>
<head>
    <?php Header::setupHeader(['opener','topdialog','datetime-picker', 'jquery-ui', 'jquery-ui-base']);?>

<title><?php echo $typeid ? xlt('Edit') : xlt('Add New'); ?> <?php echo xlt('Order/Result Type'); ?></title>


<style>
.disabled {
    pointer-events: none;
    opacity: 0.50;
    font-weight: bold;
}

td {
    font-size: 10pt;
}

.inputtext {
    padding-left: 2px;
    padding-right: 2px;
}

.button {
    font-family: sans-serif;
    font-size: 9pt;
    font-weight: bold;
}

.ordonly {

}

.resonly {

}
.label-div > a {
    display:none;
}
.label-div:hover > a {
   display:inline-block;
}
div[id$="_info"] {
    background: #F7FAB3;
    padding: 20px;
    margin: 10px 15px 0px 15px;
}
div[id$="_info"] > a {
    margin-left:10px;
}
@media only screen {
        fieldset > [class*="col-"] {
        width: 100%;
        text-align:left!Important;
    }
}
</style>

<script language="JavaScript">

<?php require($GLOBALS['srcdir'] . "/restoreSession.php"); ?>

// The name of the form field for find-code popup results.
var rcvarname;

// This is for callback by the find-code popup.
// Appends to or erases the current list of related codes.
function set_related(codetype, code, selector, codedesc) {
    var f = document.forms[0];
    var s = f[rcvarname].value;
    if (code) {
        if (s.length > 0) s += ';';
        s += codetype + ':' + code;
    } else {
        s = '';
    }
    f[rcvarname].value = s;
}

// This is for callback by the find-code popup.
// Returns the array of currently selected codes with each element in codetype:code format.
function get_related() {
 return document.forms[0][rcvarname].value.split(';');
}

// This is for callback by the find-code popup.
// Deletes the specified codetype:code from the currently selected list.
function del_related(s) {
 my_del_related(s, document.forms[0][rcvarname], false);
}

// This invokes the find-code popup.
function sel_related(varname) {
    if (typeof varname == 'undefined') {
        varname = 'form_related_code';
    }
    rcvarname = varname;
    let url = '../patient_file/encounter/find_code_dynamic.php';
    if (varname == 'form_diagnosis_code')
        url = '../patient_file/encounter/find_code_dynamic.php?codetype=<?php echo attr(collect_codetypes("diagnosis", "csv")); ?>';

    dlgopen(url, '_codeslkup', 985, 800, '', '<?php echo xla("Select Default Codes"); ?>');
}

// call back for procedure picker
function set_new_fav(result) {
    var f = document.forms[0];
    f.form_procedure_code.value = result.procedure_code;
    f.form_name.value = result.name;
    f.form_lab_id.value = result.lab_id;
    f.form_procedure_code.value = result.procedure_code;
    f.form_procedure_type.value = "for";
    f.form_body_site.value = result.body_site;
    f.form_specimen.value = result.specimen;
    f.form_route_admin.value = result.route_admin;
    f.form_laterality.value = result.laterality;
    f.form_description.value = result.description;
    f.form_units.value = result.units;
    f.form_range.value = result.range;
    f.form_standard_code.value = result.standard_code;

}

function doOrdPicker(e){
    e.preventDefault();
    let labid = $("#form_lab_id").val();
    let title = '<?php echo xla("Find Procedure Order"); ?>';
    dlgopen('find_order_popup.php?addfav=1&labid=' + labid, '_blank', 850, 500, '', title);
}

// Show or hide sections depending on procedure type.
function proc_type_changed() {
    var f = document.forms[0];
    var pt = f.form_procedure_type;
    var ix = pt.selectedIndex;
    if (ix < 0) ix = 0;
    var ptval = pt.options[ix].value;
    var ptpfx = ptval.substring(0, 3);
    $('.ordonly').hide();
    $('.resonly').hide();
    $('.fgponly').hide();
    $('.foronly').hide();
    if (ptpfx == 'ord') $('.ordonly').show();
    if (ptpfx == 'for') $('.foronly').show();
    if (ptpfx == 'res' || ptpfx == 'rec') $('.resonly').show();
    if (ptpfx == 'fgp') $('.fgponly').show(); // Favorites
    if (ptpfx == 'grp') {
        $('#form_legend').html(
            "<?php echo xlt('Enter Details for Group'); ?>" + "   <i id='grp' class='fa fa-info-circle oe-text-black oe-superscript enter-details-tooltip' aria-hidden='true'></i>");
    } else if (ptpfx == 'fgp') {
        $('#form_legend').html(
            "<?php echo xlt('Enter Details for Custom Favorite Group'); ?>" + "   <i id='ord' class='fa fa-info-circle oe-text-black oe-superscript enter-details-tooltip' aria-hidden='true'></i>");
    } else if (ptpfx == 'ord') {
        $('#form_legend').html(
            "<?php echo xlt('Enter Details for Individual Procedures'); ?>" + "   <i id='ord' class='fa fa-info-circle oe-text-black oe-superscript enter-details-tooltip' aria-hidden='true'></i>");
    } else if (ptpfx == 'for') {
        $('#form_legend').html(
            "<?php echo xlt('Enter Details for Individual Custom Favorite Item'); ?>" + "   <i id='ord' class='fa fa-info-circle oe-text-black oe-superscript enter-details-tooltip' aria-hidden='true'></i>");
    }
    else if (ptpfx == 'res') {
        $('#form_legend').html(
            "<?php echo xlt('Enter Details for Discrete Results'); ?>" + "   <i id='res' class='fa fa-info-circle oe-text-black oe-superscript enter-details-tooltip' aria-hidden='true'></i>");
    } else if (ptpfx == 'rec') {
        $('#form_legend').html(
            "<?php echo xlt('Enter Details for Recommendation'); ?>" + "   <i id='rec' class='fa fa-info-circle oe-text-black oe-superscript enter-details-tooltip' aria-hidden='true'></i>");
    }
}
    $(function () {
        proc_type_changed();
    });

</script>

</head>

    <body class="body_top">
        <div class= "container">
            <?php
            // If we are saving, then save and close the window.
            //
            if ($_POST['form_save']) {
                $p_procedure_code = invalue('form_procedure_code');

                if ($_POST['form_procedure_type'] == 'grp') {
                    $p_procedure_code = "''";
                }

                $sets =
                "name = " . invalue('form_name') . ", " .
                "lab_id = " . invalue('form_lab_id') . ", " .
                "procedure_code = $p_procedure_code, " .
                "procedure_type = " . invalue('form_procedure_type') . ", " .
                "body_site = " . invalue('form_body_site') . ", " .
                "specimen = " . invalue('form_specimen') . ", " .
                "route_admin = " . invalue('form_route_admin') . ", " .
                "laterality = " . invalue('form_laterality') . ", " .
                "description = " . invalue('form_description') . ", " .
                "units = " . invalue('form_units') . ", " .
                "`range` = " . invalue('form_range') . ", " .
                "standard_code = " . invalue('form_standard_code') . ", " .
                "related_code = " . (isset($_POST['form_diagnosis_code']) ? invalue('form_diagnosis_code') : invalue('form_related_code')) . ", " .
                "seq = " . invalue('form_seq');

                if ($typeid) {
                    sqlStatement("UPDATE procedure_type SET $sets WHERE procedure_type_id = '$typeid'");
                    // Get parent ID so we can refresh the tree view.
                    $row = sqlQuery("SELECT parent FROM procedure_type WHERE " .
                        "procedure_type_id = '$typeid'");
                    $parent = $row['parent'];
                } else {
                    $newid = sqlInsert("INSERT INTO procedure_type SET parent = '$parent', $sets");
                    // $newid is not really used in this script
                }
            } elseif ($_POST['form_delete']) {
                if ($typeid) {
                    // Get parent ID so we can refresh the tree view after deleting.
                    $row = sqlQuery("SELECT parent FROM procedure_type WHERE " .
                        "procedure_type_id = '$typeid'");
                    $parent = $row['parent'];
                    recursiveDelete($typeid);
                }
            }

            if ($_POST['form_save'] || $_POST['form_delete']) {
                // Find out if this parent still has any children.
                $trow = sqlQuery("SELECT procedure_type_id FROM procedure_type WHERE parent = '$parent' LIMIT 1");
                // Close this window and redisplay the updated list.
                echo "<script language='JavaScript'>\n";
                if ($info_msg) {
                    echo " alert('$info_msg');\n";
                }

                echo " window.close();\n";
                echo " if (opener.refreshFamily) opener.refreshFamily($parent,'true');\n";
                echo "</script></body></html>\n";
                exit();
            }

            if ($typeid) {
                $row = sqlQuery("SELECT * FROM procedure_type WHERE procedure_type_id = '$typeid'");
            }
            $info_icon_title = xl("Click to reveal more information");
            ?>
            <div class="row">
                <div class="col-sm-12">
                    <form method='post' name='theform' class="form-horizontal"
                        action='types_edit.php?typeid=<?php echo $typeid ?>&parent=<?php echo $parent ?>'>
                        <!-- no restoreSession() on submit because session data are not relevant -->
                        <fieldset>
                            <legend name="form_legend" id="form_legend"><?php echo xlt('Enter Details'); ?>   <i id='enter_details' class='fa fa-info-circle oe-text-black oe-superscript enter-details-tooltip' aria-hidden='true'></i></legend>
                            <div class="row" >
                                <div class="col-sm-12">
                                    <div class="clearfix">
                                        <div class="col-sm-12 label-div">
                                            <label class="control-label" for="form_procedure_type"><?php echo xlt('Procedure Tier'); ?>:</label> <a href="#procedure_type_info"  class="info-anchor icon-tooltip"  data-toggle="collapse" ><i class="fa fa-question-circle" aria-hidden="true"></i></a>
                                        </div>
                                        <div class="col-sm-12">
                                            <?php
                                            $ordd = $ordtype ? $ordtype : $row['procedure_type'];
                                            echo generate_select_list(
                                                'form_procedure_type',
                                                'proc_type',
                                                $ordd,
                                                xl('The type of this entity'),
                                                ' ',
                                                "$disabled",
                                                'proc_type_changed()'
                                            );
                                            ?>
                                        </div>
                                    </div>
                                    <div id="procedure_type_info" class="collapse">
                                        <a href="#procedure_type_info" data-toggle="collapse" class="oe-pull-away"><i class="fa fa-times oe-help-x" aria-hidden="true"></i></a>
                                        <p><?php echo xlt("In order to properly store and retrieve test results and place new orders, tests/orders have to be setup in 
                                            a hierarchical manner");?></p>
                                        <p><strong><?php echo xlt("Single Tests");?>:</strong></p>
                                        <p><?php echo xlt("Group > Procedure Order > Discrete Result");?></p>
                                        <p><?php echo xlt("Tier 1 - Group - e.g. Serum Chemistry");?></p>
                                        <p><?php echo xlt("Tier 2 - Procedure Order - e.g. Serum Uric Acid");?></p>
                                        <p><?php echo xlt("Tier 3 - Discrete Result - e.g. Serum Uric Acid - will hold the returned result value and Default Units, Default Range etc");?></p>
                                        <p><?php echo xlt("Recommendation - Optional");?></p>
                                        <p><strong><?php echo xlt("For a Recognized Panel of Tests");?>:</strong></p>
                                        <p><?php echo xlt("Group > Group > Procedure Order > Discrete Result");?></p>
                                        <p><?php echo xlt("Tier 1 - Group - e.g. Serum Chemistry");?></p>
                                        <p><?php echo xlt("Tier 2 - Group (will display in category column as Sub Group) - e.g. Organ/Disease Panel");?></p>
                                        <p><?php echo xlt("Tier 3 - Procedure Order - e.g. Electrolyte Panel");?></p>
                                        <p><?php echo xlt("Tier 4 - Discrete Result - The actual test names to hold the results returned Na, K, Cl, CO2 and Default Units, Default Range etc");?></p>
                                        <p><?php echo xlt("The difference between the two is that for a panel of tests that are ordered together the individual tests are represented by Discrete Result only and these tests cannot be ordered separately unless they have also been setup as single tests");?></p>
                                        <p><strong><?php echo xlt("For Custom Groups");?>: <i class="fa fa-exclamation-circle oe-text-red" aria-hidden="true"></i>&nbsp;<?php echo xlt("New in openEMR ver 5.0.2 "); ?></strong></p>
                                        <p><?php echo xlt("Custom Favorite Group > Custom Favorite Item > Discrete results");?></p>
                                        <p><?php echo xlt("As the fist step choose Group or Custom Favorite Group, as the case may be, as the Top Level Tier 1 and fill in the required details");?></p>
                                        <p><?php echo xlt("For detailed instructions close the 'Enter Details' pop-up and click on the Help icon on the main form. ");?><i class="fa fa-question-circle" aria-hidden="true"></i></p>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-12">
                                    <div class="clearfix">
                                        <div class="col-sm-12 label-div">
                                            <label class="control-label" for="form_name"><?php echo xlt('Name'); ?>:</label><a href="#name_info" class="icon-tooltip" data-toggle="collapse"><i class="fa fa-question-circle" aria-hidden="true"></i></a>
                                        </div>
                                        <div class="col-sm-12">
                                            <input type='text' name='form_name' id='form_name 'maxlength='63'
                                                value='<?php echo htmlspecialchars($row['name'], ENT_QUOTES); ?>'
                                                title='<?php echo xlt('Your name for this category, procedure or result'); ?>'
                                                 class='form-control'>
                                        </div>
                                    </div>
                                    <div id="name_info" class="collapse">
                                        <a href="#name_info" data-toggle="collapse" class="oe-pull-away"><i class="fa fa-times oe-help-x" aria-hidden="true"></i></a>
                                        <p><?php echo xlt("Name for this Category, Procedure or Result");?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-12">
                                    <div class="clearfix">
                                        <div class="col-sm-12 label-div">
                                            <label class="control-label" for="form_description"><?php echo xlt('Description'); ?>:</label><a href="#description_info" class="icon-tooltip" data-toggle="collapse"><i class="fa fa-question-circle" aria-hidden="true"></i></a>
                                        </div>
                                        <div class="col-sm-12">
                                            <input type='text' name='form_description' id='form_description'
                                                maxlength='255'
                                                value='<?php echo htmlspecialchars($row['description'], ENT_QUOTES); ?>'
                                                title='<?php echo xlt('Description of this procedure or result code'); ?>'
                                                class='form-control'>
                                        </div>
                                    </div>
                                    <div id="description_info" class="collapse">
                                        <a href="#description_info" data-toggle="collapse" class="oe-pull-away"><i class="fa fa-times oe-help-x" aria-hidden="true"></i></a>
                                        <p><?php echo xlt("A short description of this procedure or result code");?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-12">
                                    <div class="clearfix">
                                        <div class="col-sm-12 label-div">
                                            <label class="control-label" for="form_seq"><?php echo xlt('Sequence'); ?>:</label><a href="#sequence_info" class="icon-tooltip" data-toggle="collapse"><i class="fa fa-question-circle" aria-hidden="true"></i></a>
                                        </div>
                                        <div class="col-sm-12">
                                            <input type='text' name='form_seq' id=='form_seq' maxlength='11'
                                                value='<?php echo $row['seq'] + 0; ?>'
                                                title='<?php echo xla('Relative ordering of this entity'); ?>'
                                                class='form-control'>
                                        </div>
                                    </div>
                                    <div id="sequence_info" class="collapse">
                                        <a href="#sequence_info" data-toggle="collapse" class="oe-pull-away"><i class="fa fa-times oe-help-x" aria-hidden="true"></i></a>
                                        <p><?php echo xlt("The order in which the Category, Procedure or Result appears");?></p>
                                        <p><?php echo xlt("If value is left as zero, will be sorted alphabetically");?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-12 ordonly  fgponly foronly">
                                    <div class="clearfix">
                                        <div class="col-sm-12 label-div">
                                            <label class="control-label" for="form_lab_id"><?php echo xlt('Order From'); ?>:</label><a href="#order_info" class="icon-tooltip" data-toggle="collapse"><i class="fa fa-question-circle" aria-hidden="true"></i></a>
                                        </div>
                                        <div class="col-sm-12">
                                            <?php
                                            if ($ordtype == 'for') {
                                                //$title = xl('This Custom Favorite item can only be sent to the displayed lab, the one that was chosen in the Custom Favorite Group');
                                                $ord_disabled = 'disabled';
                                            } elseif ($ordtype == 'fgp') {
                                                //$title = xl('You cannot edit the already chosen lab, if sending to different lab delete entry and create a new one');
                                                $ord_disabled = 'disabled';
                                            } else {
                                                $title = xl('The entity performing this procedure');
                                                $ord_disabled = '';
                                            }
                                            ?>
                                            <select name='form_lab_id' id='form_lab_id' class='form-control <?php echo $ord_disabled ;?>'
                                                title='<?php echo attr($title); ?>'>
                                                <?php
                                                if ($ordtype) {
                                                    $ppres = sqlStatement("SELECT ppid, name FROM procedure_providers WHERE ppid = ? ORDER BY name, ppid", array($labid));
                                                } else {
                                                    $ppres = sqlStatement("SELECT ppid, name FROM procedure_providers " . "ORDER BY name, ppid");
                                                }


                                                while ($pprow = sqlFetchArray($ppres)) {
                                                    echo "<option value='" . attr($pprow['ppid']) . "'";
                                                    if ($pprow['ppid'] == $row['lab_id']) {
                                                        echo " selected";
                                                    }

                                                    echo ">" . text($pprow['name']) . "</option>";
                                                }
                                                ?>
                                               </select>
                                        </div>
                                    </div>
                                    <div id="order_info" class="collapse">
                                         <a href="#order_info" data-toggle="collapse" class="oe-pull-away"><i class="fa fa-times oe-help-x" aria-hidden="true"></i></a>
                                        <p><?php echo xlt("The entity performing this procedure");?></p>
                                        <p><?php echo xlt("The entity for a Custom Favorite Item is the entity chosen for the Custom Favorite Group and cannot be changed");?></p>
                                        <p><?php echo xlt("Once saved the entity for a Custom Favorite Group cannot be changed. If you need to change the entity you have to delete this entry and create a new one");?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-12 ordonly resonly fgponly foronly">
                                    <div class="clearfix">
                                        <div class="col-sm-12 label-div">
                                            <label class="control-label" for="form_procedure_code"><?php echo xlt('Identifying Code'); ?>:</label><a href="#procedure_code_info" class="icon-tooltip" data-toggle="collapse"><i class="fa fa-question-circle" aria-hidden="true"></i></a>
                                        </div>
                                        <div class="col-sm-12">
                                            <input type='text' name='form_procedure_code' id='form_procedure_code'
                                                maxlength='31'
                                                value='<?php echo htmlspecialchars($row['procedure_code'], ENT_QUOTES); ?>'
                                                title='<?php echo xla('The vendor-specific code identifying this procedure or result'); ?>'
                                                class='form-control'>
                                        </div>
                                    </div>
                                    <div id="procedure_code_info" class="collapse">
                                        <a href="#procedure_code_info" data-toggle="collapse" class="oe-pull-away"><i class="fa fa-times oe-help-x" aria-hidden="true"></i></a>
                                        <p><?php echo xlt("The vendor-specific code identifying this procedure or result. If no vendor enter any arbitrary unique number, preferably a 5 digit zero-padded e.g. 00211");?></p>
                                        <p><?php echo xlt("For proper display of results this is a required field");?></p>
                                        <p><i class="fa fa-exclamation-circle oe-text-orange"  aria-hidden="true"></i> <strong><?php echo xlt("Important - the Identifying Code for Custom Favorite Group is always user defined");?></strong></p>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-12 ordonly foronly">
                                    <div class="clearfix">
                                        <div class="col-sm-12 label-div">
                                            <label class="control-label" for="form_standard_code"><?php echo xlt('Standard Code'); ?>:</label><a href="#standard_code_info" class="icon-tooltip" data-toggle="collapse"><i class="fa fa-question-circle" aria-hidden="true"></i></a>
                                        </div>
                                        <div class="col-sm-12">
                                            <input type='text' name='form_standard_code' id='form_standard_code'
                                                value='<?php echo attr($row['standard_code']); ?>'
                                                title='<?php echo xla('Enter the LOINC code for this procedure'); ?>'
                                                class='form-control'>
                                        </div>
                                    </div>
                                    <div id="standard_code_info" class="collapse">
                                        <a href="#standard_code_info" data-toggle="collapse" class="oe-pull-away"><i class="fa fa-times oe-help-x" aria-hidden="true"></i></a>
                                        <p><?php echo xlt("Enter the Logical Observation Identifiers Names and Codes (LOINC) code for this procedure. LOINC is a database and universal standard for identifying medical laboratory observations.");?></p>
                                        <p><?php echo xlt("This code is optional if only using manual lab data entry");?></p>
                                        <p><?php echo xlt("Generally a good idea to include it");?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-12 foronly">
                                    <div class="clearfix">
                                        <div class="col-sm-12 label-div">
                                            <label class="control-label" for="form_diagnosis_code"><?php echo xlt('Diagnosis Codes'); ?>:</label><a href="#diagnosis_code_info" class="icon-tooltip" data-toggle="collapse"><i class="fa fa-question-circle" aria-hidden="true"></i></a>
                                        </div>
                                        <div class="col-sm-12">
                                            <input type='text'  name='form_diagnosis_code' id='form_diagnosis_code'
                                                   value='<?php echo attr($row['related_code']) //data stored in related_code field?>'
                                                   onclick='sel_related("form_diagnosis_code")'
                                                   title='<?php echo xla('Click to select diagnosis or procedure code to default to order'); ?>'
                                                   class='form-control' readonly />
                                        </div>
                                    </div>
                                    <div id="diagnosis_code_info" class="collapse">
                                        <a href="#diagnosis_code_info" data-toggle="collapse" class="oe-pull-away"><i class="fa fa-times oe-help-x" aria-hidden="true"></i></a>
                                        <p><?php echo xlt("Click to select a default diagnosis or procedure code for this order");?></p>
                                        <p><?php echo xlt("A default code is optional as the needed code can be entered at the time of placing the actual order");?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-12 ordonly foronly">
                                    <div class="clearfix">
                                        <div class="col-sm-12 label-div">
                                            <label class="control-label" for="form_body_site"><?php echo xlt('Body Site'); ?>:</label><a href="#body_site_info" class="icon-tooltip" data-toggle="collapse"><i class="fa fa-question-circle" aria-hidden="true"></i></a>
                                        </div>
                                        <div class="col-sm-12">
                                            <?php
                                                generate_form_field(array(
                                                    'data_type' => 1,
                                                    'field_id' => 'body_site',
                                                    'list_id' => 'proc_body_site',
                                                    'description' => xl('Body site, if applicable')
                                                ), $row['body_site']);
                                                ?>
                                        </div>
                                    </div>
                                    <div id="body_site_info" class="collapse">
                                        <a href="#body_site_info" data-toggle="collapse" class="oe-pull-away"><i class="fa fa-times oe-help-x" aria-hidden="true"></i></a>
                                        <p><?php echo xlt("Enter the relevant site if applicable.");?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-12 ordonly foronly">
                                    <div class="clearfix">
                                        <div class="col-sm-12 label-div">
                                            <label class="control-label" for="form_specimen"><?php echo xlt('Specimen Type'); ?>:</label><a href="#specimen_info" class="icon-tooltip" data-toggle="collapse"><i class="fa fa-question-circle" aria-hidden="true"></i></a>
                                        </div>
                                        <div class="col-sm-12">
                                            <?php
                                                generate_form_field(array(
                                                    'data_type' => 1,
                                                    'field_id' => 'specimen',
                                                    'list_id' => 'proc_specimen',
                                                    'description' => xl('Specimen Type')
                                                ), $row['specimen']);
                                                ?>
                                        </div>
                                    </div>
                                    <div id="specimen_info" class="collapse">
                                        <a href="#specimen_info" data-toggle="collapse" class="oe-pull-away"><i class="fa fa-times oe-help-x" aria-hidden="true"></i></a>
                                        <p><?php echo xlt("Enter the specimen type if applicable.");?></p>
                                        <p><?php echo xlt("This code is optional, but is a good practise to do so.");?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-12 ordonly foronly">
                                    <div class="clearfix">
                                        <div class="col-sm-12 label-div">
                                            <label class="control-label" for="form_route_admin"><?php echo xlt('Administer Via'); ?>:</label><a href="#administer_via_info" class="icon-tooltip" data-toggle="collapse"><i class="fa fa-question-circle" aria-hidden="true"></i></a>
                                        </div>
                                        <div class="col-sm-12">
                                            <?php
                                                generate_form_field(array(
                                                    'data_type' => 1,
                                                    'field_id' => 'route_admin',
                                                    'list_id' => 'proc_route',
                                                    'description' => xl('Route of administration, if applicable')
                                                ), $row['route_admin']);
                                                ?>
                                        </div>
                                    </div>
                                    <div id="administer_via_info" class="collapse">
                                        <a href="#administer_via_info" data-toggle="collapse" class="oe-pull-away"><i class="fa fa-times oe-help-x" aria-hidden="true"></i></a>
                                        <p><?php echo xlt("Enter the specimen type if applicable.");?></p>
                                        <p><?php echo xlt("This code is optional.");?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-12 ordonly foronly">
                                    <div class="clearfix">
                                        <div class="col-sm-12 label-div">
                                            <label class="control-label" for="form_laterality"><?php echo xlt('Laterality'); ?>:</label><a href="#laterality_info" class="icon-tooltip" data-toggle="collapse"><i class="fa fa-question-circle" aria-hidden="true"></i></a>
                                        </div>
                                        <div class="col-sm-12">
                                            <?php
                                                generate_form_field(array(
                                                    'data_type' => 1,
                                                    'field_id' => 'laterality',
                                                    'list_id' => 'proc_lat',
                                                    'description' => xl('Laterality of this procedure, if applicable')
                                                ), $row['laterality']);
                                            ?>
                                        </div>
                                    </div>
                                    <div id="laterality_info" class="collapse">
                                        <a href="#laterality_info" data-toggle="collapse" class="oe-pull-away"><i class="fa fa-times oe-help-x" aria-hidden="true"></i></a>
                                        <p><?php echo xlt("Enter the laterality of this procedure, if applicable.");?></p>
                                        <p><?php echo xlt("This code is optional.");?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-12 resonly">
                                    <div class="clearfix">
                                        <div class="col-sm-12 label-div">
                                            <label class="control-label" for="form_units"><?php echo xlt('Default Units'); ?>:</label><a href="#units_info" class="icon-tooltip" data-toggle="collapse"><i class="fa fa-question-circle" aria-hidden="true"></i></a>
                                        </div>
                                        <div class="col-sm-12">
                                            <?php
                                                generate_form_field(array(
                                                    'data_type' => 1,
                                                    'field_id' => 'units',
                                                    'list_id' => 'proc_unit',
                                                    'description' => xl('Optional default units for manual entry of results')
                                                ), $row['units']);
                                            ?>
                                        </div>
                                    </div>
                                    <div id="units_info" class="collapse">
                                        <a href="#units_info" data-toggle="collapse" class="oe-pull-away"><i class="fa fa-times oe-help-x" aria-hidden="true"></i></a>
                                        <p><?php echo xlt("Enter the default units for this test.");?></p>
                                        <p><?php echo xlt("This code is optional, but is a good practise.");?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-12 resonly">
                                    <div class="clearfix">
                                        <div class="col-sm-12 label-div">
                                            <label class="control-label" for="form_range"><?php echo xlt('Default Range'); ?>:</label><a href="#range_info" class="icon-tooltip" data-toggle="collapse"><i class="fa fa-question-circle" aria-hidden="true"></i></a>
                                        </div>
                                        <div class="col-sm-12">
                                            <input type='text' name='form_range' id='form_range' maxlength='255'
                                                value='<?php echo htmlspecialchars($row['range'], ENT_QUOTES); ?>'
                                                title='<?php echo xla('Optional default range for manual entry of results'); ?>'
                                                class='form-control' >
                                        </div>
                                    </div>
                                    <div id="range_info" class="collapse">
                                        <a href="#range_info" data-toggle="collapse" class="oe-pull-away"><i class="fa fa-times oe-help-x" aria-hidden="true"></i></a>
                                        <p><?php echo xlt("Enter the default range values if applicable, used in manual entry of results.");?></p>
                                        <p><?php echo xlt("This code is optional.");?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-12 resonly">
                                    <div class="clearfix">
                                        <div class="col-sm-12 label-div">
                                            <label class="control-label" for="form_related_code"><?php echo xlt('Followup Services'); ?>:</label><a href="#related_code_info" class="icon-tooltip" data-toggle="collapse"><i class="fa fa-question-circle" aria-hidden="true"></i></a>
                                        </div>
                                        <div class="col-sm-12">
                                            <input type='text'  name='form_related_code' id='form_related_code'
                                                value='<?php echo attr($row['related_code']) ?>'
                                                onclick='sel_related("form_related_code")'
                                                title='<?php echo xla('Click to select services to perform if this result is abnormal'); ?>'
                                                class='form-control' readonly />
                                        </div>
                                    </div>
                                    <div id="related_code_info" class="collapse">
                                        <a href="#related_code_info" data-toggle="collapse" class="oe-pull-away"><i class="fa fa-times oe-help-x" aria-hidden="true"></i></a>
                                        <p><?php echo xlt("Click to select services to perform if this result is abnormal.");?></p>
                                        <p><?php echo xlt("This code is optional.");?></p>
                                    </div>
                                </div>
                            </div>
                        </fieldset>
                        <?php //can change position of buttons by creating a class 'position-override' and adding rule text-alig:center or right as the case may be in individual stylesheets ?>
                        <div class="form-group clearfix" id="button-container">
                            <div class="col-sm-12 text-left position-override">
                                <div class="btn-group btn-group-pinch" role="group">
                                    <button type='submit' name='form_save'  class="btn btn-default btn-save"  value='<?php echo xla('Save'); ?>'><?php echo xlt('Save'); ?></button>
                                    <button type="button" class="btn btn-link btn-cancel btn-separate-left" onclick='window.close()';><?php echo xlt('Cancel');?></button>
                                    <?php if ($typeid) { ?>
                                        <button type='submit' name='form_delete'  class="btn btn-default btn-cancel btn-delete btn-separate-left" value='<?php echo xla('Delete'); ?>'><?php echo xlt('Delete'); ?></button>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div><!--end of conatainer div-->
        <script>
            //jqury-ui tooltip
            $(function () {
                //for jquery tooltip to function if jquery 1.12.1.js is called via jquery-ui in the Header::setupHeader
                // the relevant css file needs to be called i.e. jquery-ui-darkness - to get a black tooltip
                $('.icon-tooltip').attr("title", "<?php echo xla('Click to see more information'); ?>").tooltip({
                    show: {
                        delay: 700,
                        duration: 0
                    }
                });
                $('.enter-details-tooltip').attr( "title", "<?php echo xla('Additional help to fill out this form is available by hovering over labels of each box and clicking on the dark blue help ? icon that is revealed'); ?>" + ". " + "<?php echo xla('On mobile devices tap once on the label to reveal the help icon and tap on the icon to show the help section'); ?>.").tooltip();
                $('#form_procedure_type').click(function(){
                    $('.enter-details-tooltip').attr( "title", "<?php echo xla('Additional help to fill out this form is available by hovering over labels of each box and clicking on the dark blue help ? icon that is revealed'); ?>" + ". " + "<?php echo xla('On mobile devices tap once on the label to reveal the help icon and tap on the icon to show the help section'); ?>.").tooltip();
                });
            });
        </script>
    </body>
</html>

