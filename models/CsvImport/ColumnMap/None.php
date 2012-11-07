<?php
class CsvImport_ColumnMap_None extends CsvImport_ColumnMap {

    public function __construct($columnName)
    {
        parent::__construct($columnName);
        $this->_targetType = CsvImport_ColumnMap::METADATA_NONE;
    }

    public function map($row, $result)
    {
        $result = $row[$this->_columnName];
        return $result;
    }
}
