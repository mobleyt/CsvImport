<?php

class CsvImport_ColumnMap_File extends CsvImport_ColumnMap
{
    public function __construct($columnName)
    {
        parent::__construct($columnName);
        $this->_targetType = CsvImport_ColumnMap::TARGET_TYPE_FILE;
    }

    public function map($row, $result)
    {
        $urlString = trim($row[$this->_columnName]);
        if ($urlString) {
            $urls = explode(',', $urlString);
            foreach ($urls as $key => $url) {
                $urls[$key] = array(
                    'source' => $url,
                    'name' => $url,
                    'order' => $key + 1,
                );
            }
            $result[] = $urls;
        }
        return $result;
    }
}
