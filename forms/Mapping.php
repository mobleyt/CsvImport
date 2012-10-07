<?php
/**
 * @version $Id$
 * @copyright Center for History and New Media, 2008-2011
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package CsvImport
 */

/**
 * The form on csv-import/index/map-columns.
 *
 * @package CsvImport
 * @author CHNM
 * @copyright Center for History and New Media, 2008-2011
 */
class CsvImport_Form_Mapping extends Omeka_Form
{
    private $_itemTypeId;
    private $_columnNames = array();
    private $_columnExamples = array();
    private $_recordTypeId;

    public function init()
    {
        parent::init();
        $this->setAttrib('id', 'csvimport-mapping');
        $this->setMethod('post');

        $elementsByElementSetName =
            csv_import_get_elements_by_element_set_name($this->_itemTypeId);
        $elementsByElementSetName = array('' => 'Select Below')
                                  + $elementsByElementSetName;
        foreach ($this->_columnNames as $index => $colName) {
            $rowSubForm = new Zend_Form_SubForm();
            $selectElement = $rowSubForm->createElement('select',
                'element',
                array(
                    'class' => 'map-element',
                    'multiOptions' => $elementsByElementSetName,
                    'multiple' => false // see ZF-8452
                )
            );
            $selectElement->setIsArray(true);
            $rowSubForm->addElement($selectElement);

            // if record type is file, add checkbox for filename
            if ($this->_recordTypeId == 3) {
                $rowSubForm->addElement('checkbox', 'html');
                $rowSubForm->addElement('checkbox', 'filename');
            }
            else {
                $rowSubForm->addElement('checkbox', 'html');
                $rowSubForm->addElement('checkbox', 'tags');
                $rowSubForm->addElement('checkbox', 'file');
            }
            $this->_setSubFormDecorators($rowSubForm);
            $this->addSubForm($rowSubForm, "row$index");
        }

        $this->addElement('submit', 'submit',
            array('label' => 'Import CSV File',
                  'class' => 'submit submit-medium'));
    }

    public function loadDefaultDecorators()
    {
        $this->setDecorators(array(
            array('ViewScript', array(
                'viewScript' => 'index/map-columns-form.php',
                'itemTypeId' => $this->_itemTypeId,
                'form' => $this,
                'columnExamples' => $this->_columnExamples,
                'columnNames' => $this->_columnNames,
                'recordTypeId' => $this->_recordTypeId,
            )),
        ));
    }

    public function setColumnNames($columnNames)
    {
        $this->_columnNames = $columnNames;
    }

    public function setColumnExamples($columnExamples)
    {
        $this->_columnExamples = $columnExamples;
    }

    public function setItemTypeId($itemTypeId)
    {
        $this->_itemTypeId = $itemTypeId;
    }
    // sets record type id
    public function setRecordTypeId($recordTypeId)
    {
        $this->_recordTypeId = $recordTypeId;
    }

    public function getMappings()
    {
        $columnMaps = array();
        foreach ($this->_columnNames as $key => $colName) {
            if ($map = $this->getColumnMap($key, $colName)) {
                if (is_array($map)) {
                    $columnMaps = array_merge($columnMaps, $map);
                } else {
                    $columnMaps[] = $map;
                }
            }
        }
        return $columnMaps;
    }

    private function isTagMapped($index)
    {
        if ($this->getSubForm("row$index")->tags) {
            return $this->getSubForm("row$index")->tags->isChecked();
        }
    }

    private function isFileMapped($index)
    {
        if ($this->getSubForm("row$index")->file) {
            return $this->getSubForm("row$index")->file->isChecked();
        }
    }
    // return true if filename box is checked
    private function isFilenameMapped($index)
    {
        if ($this->getSubForm("row$index")->filename) {
            return $this->getSubForm("row$index")->filename->isChecked();
        }
    }

    private function getMappedElementId($index)
    {
        return $this->_getRowValue($index, 'element');
    }

    private function _getRowValue($row, $name)
    {
        return $this->getSubForm("row$row")->$name->getValue();
    }

    private function _setSubFormDecorators($subForm)
    {
        // Get rid of the fieldset tag that wraps subforms by default.
        $subForm->setDecorators(array(
            'FormElements',
        ));

        // Each subform is a row in the table.
        foreach ($subForm->getElements() as $el) {
            $el->setDecorators(array(
                array('decorator' => 'ViewHelper'),
                array('decorator' => 'HtmlTag',
                      'options' => array('tag' => 'td')),
            ));
        }
    }

    /**
     * Get the mappings from one column in the CSV file.
     *
     * Some columns can have multiple mappings; these are represented
     * as an array of maps.
     *
     * @return CsvImport_ColumnMap|array|null A ColumnMap or an array of
     *  ColumnMaps
     */
    private function getColumnMap($index, $columnName)
    {
        $columnMap = array();

        if ($this->isTagMapped($index)) {
            $columnMap[] = new CsvImport_ColumnMap_Tag($columnName);
        }

        if ($this->isFileMapped($index)) {
            $columnMap[] = new CsvImport_ColumnMap_File($columnName);
        }
        // add filename to columnMap
        if ($this->isFilenameMapped($index)) {
            $columnMap[] = new CsvImport_ColumnMap_Filename($columnName);
        }

        $elementIds = $this->getMappedElementId($index);
        $isHtml = $this->_getRowValue($index, 'html');
        foreach ($elementIds as $elementId) {
            // Make sure to skip empty mappings
            if (!$elementId) {
                continue;
            }

            $elementMap = new CsvImport_ColumnMap_Element($columnName);
            $elementMap->setOptions(array('elementId' => $elementId,
                                         'isHtml' => $isHtml));
            $columnMap[] = $elementMap;
        }

        return $columnMap;
    }
}
