<div class="field">
    <label for="csv_import_delimiter_multivalues">
        <?php echo __('Delimiter used for multivalues');?>
    </label>
    <div class="inputs">
        <?php echo __v()->formText('csv_import_delimiter_multivalues', get_option('csv_import_delimiter_multivalues'), null);?>
        <p class="explanation">
            <?php echo __('This delimiter separes more than one value in one field. It is used only with CsvReport. Default is "^^". Recommended is "|^^OMK^^|"');?>
        </p>
    </div>
</div>
