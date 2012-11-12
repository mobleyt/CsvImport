<?php
class CsvImport_ColumnMap_SourceItemId extends CsvImport_ColumnMap {

    public function __construct($columnName)
    {
        parent::__construct($columnName);
        $this->_targetType = CsvImport_ColumnMap::SOURCE_ITEM_ID;
    }

    public function map($row, $result)
    {
        $result = $row[$this->_columnName];
        return $result;
    }
}