<form id="csvimport" method="post" action="">
<?php
    $format = $this->format;
    $colNames = $this->columnNames;
    $colExamples = $this->columnExamples;
?>
    <table id="column-mappings" class="simple" cellspacing="0" cellpadding="0">
    <thead>
    <tr>
        <th><?php __('Column'); ?></th>
        <th><?php echo __('Example from CSV File'); ?></th>
        <th><?php echo __('Map To Element'); ?></th>
        <th><?php echo __('Use HTML?'); ?></th>
        <?php if ($format != 'File') { ?>
        <th><?php echo __('Collection?'); ?></th>
        <th><?php echo __('File?'); ?></th>
        <?php } else { ?>
        <th><?php echo __('Filename?'); ?></th>
        <?php } ?>
    </tr>
    </thead>
    <tbody>
<?php for ($i = 0; $i < count($colNames); $i++): ?>
        <tr>
        <td><strong><?php echo html_escape($colNames[$i]); ?></strong></td>
        <?php $exampleString = $colExamples[$colNames[$i]]; ?>
        <td>&quot;<?php echo html_escape(substr($exampleString, 0, 47)); ?>&quot;<?php if (strlen($exampleString) > 47) { echo '...';} ?></td>
        <?php echo $this->form->getSubForm("row$i"); ?>
        </tr>
<?php endfor; ?>
    </tbody>
    </table>
    <fieldset>
    <?php echo $this->form->submit; ?>
    </fieldset>
</form>
