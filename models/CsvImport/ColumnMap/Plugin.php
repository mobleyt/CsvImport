<?php

class CsvImport_ColumnMap_Plugin extends CsvImport_ColumnMap
{
    public function __construct($columnName)
    {
        parent::__construct($columnName);
        $this->_targetType = CsvImport_ColumnMap::METADATA_PLUGIN;
    }

    public function map($row, $result)
    {
        $result = json_decode($row[$this->_columnName]);
        return $result;
    }
}
