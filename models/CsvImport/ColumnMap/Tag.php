<?php
/**
 * CsvImport_ColumnMap_Tag class
 *
 * @copyright Copyright 2007-2012 Roy Rosenzweig Center for History and New Media
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 * @package CsvImport
 */
class CsvImport_ColumnMap_Tag extends CsvImport_ColumnMap
{
    const TAG_DELIMITER_OPTION_NAME = 'csv_import_tag_delimiter';
    const DEFAULT_TAG_DELIMITER = ',';

    private $_tagDelimiter;

    /**
     * @param string $columnName
     * @param string $tagDelimiter
     */
    public function __construct($columnName, $tagDelimiter = null)
    {
        parent::__construct($columnName);
        $this->_type = CsvImport_ColumnMap::TYPE_TAG;
        if ($tagDelimiter !== null) {
            $this->_tagDelimiter = $tagDelimiter;
        } else {
            $this->_tagDelimiter = self::getDefaultTagDelimiter();
        }
    }

    /**
     * Map a row to an array of tags.
     *
     * @param array $row The row to map
     * @param array $result
     * @return array The array of tags
     */
    public function map($row, $result)
    {
       
        $rawTags = array($row[$this->_columnName]);

        $tags = array_shift($rawTags);

        $collectionTitle = $tags;
        if ($collectionTitle != '') {
            $collection = $this->_getCollectionByTitle($collectionTitle);
            if ($collection) {
                $tags = $collection->id;
            }
            elseif(!$collection){
                $metadata = array('public' => false, 'featured' => false);
                $elementTexts = array( MODS => array( Title Info:Title => array( array(‘text’ => $tags, ‘html’ => false))));                insert_collection($metadata,$elementTexts);
                $collection = $this->_getCollectionByTitle($collectionTitle);
                $tags = $collection->id;
            }
            }
        }
        return $tags;
    }


    /**
     * Return the tag delimiter.
     *
     * @return string The tag delimiter
     */
    public function getTagDelimiter()
    {
        return $this->_tagDelimiter;
    }

    /**
     * Returns the default tag delimiter.
     * Uses the default tag delimiter specified in the options table if
     * available.
     *
     * @return string The default tag delimiter
     */
    static public function getDefaultTagDelimiter()
    {
        if (!($delimiter = get_option(self::TAG_DELIMITER_OPTION_NAME))) {
            $delimiter = self::DEFAULT_TAG_DELIMITER;
        }
        return $delimiter;
    }
    
    /**
     * Return a collection by its title.
     *
     * @param string $name The collection name
     * @return Collection The collection
     */
    protected function _getCollectionByTitle($name)
    {
        $db = get_db();
        $elementTable = $db->getTable('Element');
        $element = $elementTable->findByElementSetNameAndElementName('Dublin Core', 'Title');
        $collectionTable = $db->getTable('Collection');
        $select = $collectionTable->getSelect();
        $select->joinInner(array('s' => $db->ElementText),
                           's.record_id = collections.id', array());
        $select->where("s.record_type = 'Collection'");
        $select->where("s.element_id = ?", $element->id);
        $select->where("s.text = ?", $name);
        $collection = $collectionTable->fetchObject($select);
        if (!$collection) {
            _log("Collection not found. Collections must be created with identical names prior to import", Zend_Log::NOTICE);
            return false;
        }
        return $collection;
    }
}
