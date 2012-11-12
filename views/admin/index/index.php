<?php
    head(array('title' => 'CSV Import', 'bodyclass' => 'primary', 'content_class' => 'horizontal-nav'));
?>
<h1>CSV Import</h1>
<?php echo $this->navigation()->menu()->setUlClass('section-nav'); ?>

<div id="primary">
    <?php echo flash(); ?>
    <h2>Step 1: Select File and Item Settings</h2>
    <?php echo $this->form; ?>
</div>

<script type="text/javascript">
    var radio_type = document.csvimport.record_type_id;

    onload = function() {
        document.getElementById("fieldset-recordtype").style.display = "none";
        document.getElementById("fieldset-recordtypeno").style.display = "block";
    };
    
    radio_type[0].onclick = function() {
        document.getElementById("fieldset-recordtype").style.display = "none";
        document.getElementById("fieldset-recordtypeno").style.display = "block";
    };
    radio_type[1].onclick = function() {
        document.getElementById("fieldset-recordtype").style.display = "block";
        document.getElementById("fieldset-recordtypeno").style.display = "none";
    };
    radio_type[2].onclick = function() {
        document.getElementById("fieldset-recordtype").style.display = "block";
        document.getElementById("fieldset-recordtypeno").style.display = "none";
    };
</script>
    
<script type="text/javascript">
//<![CDATA[
jQuery(document).ready(function () {
    jQuery('#omeka_csv_export').click(Omeka.CsvImport.toggleImportOptions);
});
//]]>
</script>

<?php
    foot();
?>
