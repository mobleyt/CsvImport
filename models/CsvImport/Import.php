<?php
/**
 * CsvImport_Import class - represents a csv import event
 *
 * @copyright Copyright 2007-2012 Roy Rosenzweig Center for History and New Media
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 * @package CsvImport
 */
class CsvImport_Import extends Omeka_Record_AbstractRecord
{
    const UNDO_IMPORT_ITEM_LIMIT_PER_QUERY = 50;

    const QUEUED = 'queued';
    const IN_PROGRESS = 'in_progress';
    const COMPLETED = 'completed';

    const QUEUED_UNDO = 'queued_undo';
    const IN_PROGRESS_UNDO = 'undo_in_progress';
    const COMPLETED_UNDO = 'completed_undo';

    const IMPORT_ERROR = 'import_error';
    const UNDO_IMPORT_ERROR = 'undo_import_error';
    const OTHER_ERROR = 'other_error';

    const STOPPED = 'stopped';
    const PAUSED = 'paused';

    public $original_filename;
    public $file_path;
    public $file_position = 0;

    public $format;
    public $item_type_id;
    public $collection_id;
    public $owner_id;
    public $added;

    public $delimiter;
    public $enclosure;
    public $is_public;
    public $is_featured;
    public $row_count = 0;
    public $skipped_row_count = 0;
    public $skipped_item_count = 0;
    public $status;
    public $serialized_column_maps;

    private $_csvFile;
    private $_isHtml;
    private $_importedCount = 0;

    /**
     * Batch importing is not enabled by default.
     */
    private $_batchSize = 0;

    /**
     * An array of columnMaps, where each columnMap maps a column index number
     * (starting at 0) to an element, tag, and/or file.
     *
     * @var array
     */
    private $_columnMaps;

    protected function _initializeMixins()
    {
        $this->_mixins[] = new Mixin_Timestamp($this, 'added', null);
    }

    /**
     * Sets the status of the import.
     *
     * @param string The status of the import
     */
    public function setStatus($status)
    {
        $this->status = (string)$status;
    }

    /**
     * Sets the file path of the imported CSV file.
     *
     * @param string The file path of the imported CSV file
     */
    public function setFilePath($path)
    {
        $this->file_path = $path;
    }

    /**
     * Sets the original filename of the imported CSV file.
     *
     * @param string The original filename of the imported CSV file
     */
    public function setOriginalFilename($filename)
    {
        $this->original_filename = $filename;
    }

    /**
     * Sets the format of the imported CSV file.
     *
     * @param int $format The format of the imported CSV File.
     */
    public function setFormat($format)
    {
        $this->format = $format;
    }

    /**
     * Sets the item type id of the item type of every imported item.
     *
     * @param int $id The item type id
     */
    public function setItemTypeId($id)
    {
        if (!$id) {
            $this->item_type_id = null;
        } else {
            $this->item_type_id = (int)$id;
        }
    }

    /**
     * Sets the collection id of the collection to which the imported items
     * belong.
     *
     * @param int $id The collection id
     */
    public function setCollectionId($id)
    {
        if (!$id) {
            $this->collection_id = null;
        } else {
            $this->collection_id = (int)$id;
        }
    }

    /**
     * Sets whether the imported items are public.
     *
     * @param mixed $flag A boolean representation
     */
    public function setItemsArePublic($flag)
    {
        $booleanFilter = new Omeka_Filter_Boolean;
        $this->is_public = $booleanFilter->filter($flag);
    }

    /**
     * Sets whether the imported items are featured.
     *
     * @param mixed $flag A boolean representation
     */
    public function setItemsAreFeatured($flag)
    {
        $booleanFilter = new Omeka_Filter_Boolean;
        $this->is_featured = $booleanFilter->filter($flag);
    }

    /**
     * Sets the column delimiter in the imported CSV file.
     *
     * @param string The column delimiter of the imported CSV file
     */
    public function setColumnDelimiter($delimiter)
    {
        $this->delimiter = $delimiter;
    }

    /**
     * Sets the enclosure in the imported CSV file
     *
     * @param string The enclosure of the imported CSV file
     */
    public function setEnclosure($enclosure)
    {
        $this->enclosure = $enclosure;
    }

    /**
     * Sets the user id of the owner of the imported items.
     *
     * @param int $id The user id of the owner of the imported items
     */
    public function setOwnerId($id)
    {
        $this->owner_id = (int)$id;
    }

    /**
     * Sets whether the import is raw text or html.
     *
     * @param mixed $flag A boolean representation
     */
    public function setIsHtml($flag)
    {
        $this->_isHtml = $flag;
    }

    /**
     * Sets the column maps for the import.
     *
     * @param CsvImport_ColumnMap_Set|array $maps The set of column maps
     * @throws InvalidArgumentException
     */
    public function setColumnMaps($maps)
    {
        if ($maps instanceof CsvImport_ColumnMap_Set) {
            $mapSet = $maps;
        } else if (is_array($maps)) {
            $mapSet = new CsvImport_ColumnMap_Set($maps);
        } else {
            throw new InvalidArgumentException("Maps must be either an "
                . "array or an instance of CsvImport_ColumnMap_Set.");
        }
        $this->_columnMaps = $mapSet;
    }

    /**
     * Set the number of items to create before pausing the import.
     *
     * Used primarily for performance reasons, i.e. long-running imports may
     * time out or hog system resources in such a way that prevents other
     * imports from running.  When used in conjunction with Omeka_Job and
     * resume(), this can be used to spawn multiple sequential jobs for a given
     * import.
     *
     * @param int $size
     */
    public function setBatchSize($size)
    {
        $this->_batchSize = (int)$size;
    }

    /**
     * Executes before the record is deleted.
     *
     * @param array $args
     */
    protected function beforeSave($args)
    {
        $this->serialized_column_maps = serialize($this->getColumnMaps());
    }

    /**
     * Executes after the record is deleted.
     */
    protected function afterDelete()
    {
        if (file_exists($this->file_path)) {
            unlink($this->file_path);
        }
    }

    /**
     * Returns whether there is an error.
     *
     * @return boolean Whether there is an error
     */
    public function isError()
    {
        return $this->isImportError() ||
               $this->isUndoImportError() ||
               $this->isOtherError();
    }

    /**
     * Returns whether there is an error with the import process.
     *
     * @return boolean Whether there is an error with the import process
     */
    public function isImportError()
    {
        return $this->status == self::IMPORT_ERROR;
    }

    /**
     * Returns whether there is an error with the undo import process.
     *
     * @return boolean Whether there is an error with the undo import process
     */
    public function isUndoImportError()
    {
        return $this->status == self::UNDO_IMPORT_ERROR;
    }

    /**
     * Returns whether there is an error that is neither related to an import
     * nor undo import process.
     *
     * @return boolean Whether there is an error that is neither related to an
     * import nor undo import process
     */
    public function isOtherError()
    {
        return $this->status == self::OTHER_ERROR;
    }

    /**
     * Returns whether the import is stopped.
     *
     * @return boolean Whether the import is stopped
     */
    public function isStopped()
    {
        return $this->status == self::STOPPED;
    }

    /**
     * Returns whether the import is queued.
     *
     * @return boolean Whether the import is queued
     */
    public function isQueued()
    {
        return $this->status == self::QUEUED;
    }

    /**
     * Returns whether the undo import is queued.
     *
     * @return boolean Whether the undo import is queued
     */
    public function isQueuedUndo()
    {
        return $this->status == self::QUEUED_UNDO;
    }

    /**
     * Returns whether the import is completed.
     *
     * @return boolean Whether the import is completed
     */
    public function isCompleted()
    {
        return $this->status == self::COMPLETED;
    }

    /**
     * Returns whether the import is undone.
     *
     * @return boolean Whether the import is undone
     */
    public function isUndone()
    {
        return $this->status == self::COMPLETED_UNDO;
    }

    /**
     * Imports the CSV file.  This function can only be run once.
     * To import the same csv file, you will have to create another instance of
     * CsvImport_Import and run start.
     * Sets import status to self::IN_PROGRESS.
     *
     * @return boolean Whether the import was successful
     */
    public function start()
    {
        $this->status = self::IN_PROGRESS;
        $this->_countRows();
        $this->save();
        $this->_log("Started import.");
        $this->_importLoop($this->file_position);
        return !$this->isError();
    }

    /**
     * Completes the import.
     * Sets import status to self::COMPLETED
     *
     * @return boolean Whether the import was successfully completed
     */
    public function complete()
    {
        if ($this->isCompleted()) {
            $this->_log("Cannot complete an import that is already completed.");
            return false;
        }
        $this->status = self::COMPLETED;
        $this->save();
        $this->_log("Completed importing $this->_importedCount items (skipped "
            . "$this->skipped_row_count rows).");
        return true;
    }

    /**
     * Completes the undo import.
     * Sets import status to self::COMPLETED_UNDO
     *
     * @return boolean Whether the undo import was successfully completed
     */
    public function completeUndo()
    {
        if ($this->isUndone()) {
            $this->_log("Cannot complete an undo import that is already undone.");
            return false;
        }
        $this->status = self::COMPLETED_UNDO;
        $this->save();
        $this->_log("Completed undoing the import.");
        return true;
    }

    /**
     * Resumes the import.
     * Sets import status to self::IN_PROGRESS
     *
     * @return boolean Whether the import was successful after it was resumed
     */
    public function resume()
    {
        if (!$this->isQueued() && !$this->isQueuedUndo()) {
            $this->_log("Cannot resume an import or undo import that has not been queued.");
            return false;
        }

        $undoImport = $this->isQueuedUndo();

        if ($this->isQueued()) {
            $this->status = self::IN_PROGRESS;
            $this->save();
            $this->_log("Resumed import.");
            $this->_importLoop($this->file_position);
        } else {
            $this->status = self::IN_PROGRESS_UNDO;
            $this->save();
            $this->_log("Resumed undo import.");
            $this->_undoImportLoop();
        }

        return !$this->isError();
    }

    /**
     * Stops the import or undo import.
     * Sets import status to self::STOPPED
     *
     * @return boolean Whether the import or undo import was stopped due to an error
     */
    public function stop()
    {
        // If the import or undo import loops were prematurely stopped while in
        // progress, then there is an error, otherwise there is no error, i.e.
        // the import or undo import was completed
        if ($this->status != self::IN_PROGRESS and
            $this->status != self::IN_PROGRESS_UNDO) {
            return false; // no error
        }

        // The import or undo import loop was prematurely stopped
        $logMsg = "Stopped import or undo import due to error";
        if ($error = error_get_last()) {
            $logMsg .= ": " . $error['message'];
        } else {
            $logMsg .= '.';
        }
        $this->status = self::STOPPED;
        $this->save();
        $this->_log($logMsg, Zend_Log::ERR);
        return true; // stopped with an error
    }

    /**
     * Queue the import.
     * Sets import status to self::QUEUED
     *
     * @return boolean Whether the import was successfully queued
     */
    public function queue()
    {
        if ($this->isError()) {
            $this->_log("Cannot queue an import that has an error.");
            return false;
        }

        if ($this->isStopped()) {
            $this->_log("Cannot queue an import that has been stopped.");
            return false;
        }

        if ($this->isCompleted()) {
            $this->_log("Cannot queue an import that has been completed.");
            return false;
        }

        if ($this->isUndone()) {
            $this->_log("Cannot queue an import that has been undone.");
            return false;
        }

        $this->status = self::QUEUED;
        $this->save();
        $this->_log("Queued import.");
        return true;
    }

    /**
     * Queue the undo import.
     * Sets import status to self::QUEUED_UNDO
     *
     * @return boolean Whether the undo import was successfully queued
     */
    public function queueUndo()
    {
        if ($this->isUndoImportError()) {
            $this->_log("Cannot queue an undo import that has an undo import error.");
            return false;
        }

        if ($this->isOtherError()) {
            $this->_log("Cannot queue an undo import that has an error.");
            return false;
        }

        if ($this->isStopped()) {
            $this->_log("Cannot queue an undo import that has been stopped.");
            return false;
        }

        if ($this->isUndone()) {
            $this->_log("Cannot queue an undo import that has been undone.");
            return false;
        }

        $this->status = self::QUEUED_UNDO;
        $this->save();
        $this->_log("Queued undo import.");
        return true;
    }

    /**
     * Undo the import.
     * Sets import status to self::IN_PROGRESS_UNDO and then self::COMPLETED_UNDO
     *
     * @return boolean Whether the import was successfully undone
     */
    public function undo()
    {
        $this->status = self::IN_PROGRESS_UNDO;
        $this->save();
        $this->_log("Started undo import.");
        $this->_undoImportLoop();
        return !$this->isError();
    }

    /**
     * Returns the CsvImport_File object for the import.
     *
     * @return CsvImport_File
     */
    public function getCsvFile()
    {
        if (empty($this->_csvFile)) {
            $this->_csvFile = new CsvImport_File($this->file_path, $this->delimiter, $this->enclosure);
        }
        return $this->_csvFile;
    }

    /**
     * Returns the set of column maps for the import.
     *
     * @throws UnexpectedValueException
     * @return CsvImport_ColumnMap_Set The set of column maps for the import
     */
    public function getColumnMaps()
    {
        if ($this->_columnMaps === null) {
            $columnMaps = unserialize($this->serialized_column_maps);
            if (!($columnMaps instanceof CsvImport_ColumnMap_Set)) {
                throw new UnexpectedValueException("Column maps must be "
                    . "an instance of CsvImport_ColumnMap_Set. Instead, the "
                    . "following was given: " . var_export($columnMaps, true));
            }
            $this->_columnMaps = $columnMaps;
        }
        return $this->_columnMaps;
    }

    /**
     * Returns the number of items currently imported.  If a user undoes an
     * import, this number decreases to the number of items left to remove.
     *
     * @return int The number of items imported minus the number of items undone
     */
    public function getImportedItemCount()
    {
        $iit = $this->getTable('CsvImport_ImportedItem');
        $sql = $iit->getSelectForCount()->where('`import_id` = ?');
        $importedItemCount = $this->getDb()->fetchOne($sql, array($this->id));
        return $importedItemCount;
    }

    /**
     * Returns the number of rows in the file currently imported.
     *
     * @return int The number of rows of the file, valid or not.
     */
    protected function _countRows()
    {
        $rows = $this->getCsvFile()->getIterator();
        $rows->rewind();
        $rows->skipInvalidRows(true);
        $valid_row_count = 0;
        while ($rows->valid()) {
            $rows->next();
            $valid_row_count++;
        }
        $this->row_count = $valid_row_count + $rows->getSkippedCount();
    }

    /**
     * Runs the import loop.
     *
     * @param int $startAt A row number in the CSV file.
     * @throws Exception
     * @return boolean Whether the import loop was successfully run
     */
    protected function _importLoop($startAt = null)
    {
        try {
            register_shutdown_function(array($this, 'stop'));
            $rows = $this->getCsvFile()->getIterator();
            $rows->rewind();
            if ($startAt) {
                $rows->seek($startAt);
            }

            $rows->skipInvalidRows(true);
            $this->_log("Running item import loop at %time%.");
            $this->_log("Memory usage: %memory%.");
            while ($rows->valid()) {
                $row = $rows->current();
                $index = $rows->key();
                $this->skipped_row_count += $rows->getSkippedCount();

                switch ($this->format) {
                    case 'Report':
                    case 'Item':
                        $record = $this->_addItemFromRow($row);
                        break;
                    case 'File':
                        $record = $this->_updateFileFromRow($row);
                        break;
                    case 'Mix':
                        $result = $this->getColumnMaps()->map($row);
                        $record = (isset($result[CsvImport_ColumnMap::TYPE_FILE_URL])
                                && !empty($result[CsvImport_ColumnMap::TYPE_FILE_URL]))
                            ? $this->_addFileFromRow($row)
                            : $this->_addItemFromRow($row);
                        break;
                    case 'Update':
                        $record = $this->_updateFromRow($row);
                        break;
                    default:
                        $record = null;
                }

                if (empty($record)) {
                    $this->skipped_item_count++;
                    $this->_log("Skipped item on row #{$index}.", Zend_Log::WARN);
                }
                else {
                    release_object($record);
                }

                $this->file_position = $this->getCsvFile()->getIterator()->tell();
                if ($this->_batchSize && ($index % $this->_batchSize == 0)) {
                    $this->_log("Completed importing batch of $this->_batchSize items at %time%.");
                    $this->_log("Memory usage: %memory%.");
                    return $this->queue();
                }
                $rows->next();
            }
            $this->skipped_row_count += $rows->getSkippedCount();
            return $this->complete();
        } catch (Omeka_Job_Worker_InterruptException $e) {
            // Interruptions usually indicate that we should resume from
            // the last stopping position.
            return $this->queue();
        } catch (Exception $e) {
            $this->status = self::IMPORT_ERROR;
            $this->save();
            $this->_log($e, Zend_Log::ERR);
            throw $e;
        }
    }

    /**
     * Runs the undo import loop.
     *
     * @throws Exception
     * @return boolean Whether the undo import loop was successfully run
     */
    protected function _undoImportLoop()
    {
        try {
            $itemLimitPerQuery = self::UNDO_IMPORT_ITEM_LIMIT_PER_QUERY;
            $batchSize = intval($this->_batchSize);
            if ($batchSize > 0) {
                $itemLimitPerQuery = min($itemLimitPerQuery, $batchSize);
            }
            register_shutdown_function(array($this, 'stop'));
            $db = $this->getDb();
            $searchSql = "SELECT `item_id` FROM $db->CsvImport_ImportedItem"
                       . " WHERE `import_id` = " . (int)$this->id
                       . " LIMIT " . $itemLimitPerQuery;
            $it = $this->getTable('Item');
            $deletedItemCount = 0;
            while ($itemIds = $db->fetchCol($searchSql)) {
                $inClause = 'IN (' . join(', ', $itemIds) . ')';
                $items = $it->fetchObjects($it->getSelect()
                                              ->where("`items`.`id` $inClause"));
                $deletedItemIds = array();
                foreach ($items as $item) {
                    $itemId = $item->id;
                    $item->delete();
                    release_object($item);
                    $deletedItemIds[] = $itemId;
                    $deletedItemCount++;
                    if ($batchSize > 0 && $deletedItemCount == $batchSize) {
                        $inClause = 'IN (' . join(', ', $deletedItemIds) . ')';
                        $db->delete($db->CsvImport_ImportedItem, "`item_id` $inClause");
                        $this->_log("Completed undoing the import of a batch of $batchSize items.");
                        $this->_log("Memory usage: %memory%.");
                        return $this->queueUndo();
                    }
                }
                $db->delete($db->CsvImport_ImportedItem, "`item_id` $inClause");
            }
            return $this->completeUndo();
        } catch (Omeka_Job_Worker_InterruptException $e) {
            if ($db && $deletedItemIds) {
                $inClause = 'IN (' . join(', ', $deletedItemIds) . ')';
                $db->delete($db->CsvImport_ImportedItem, "`item_id` $inClause");
            }
            return $this->queueUndo();
        } catch (Exception $e) {
            $this->status = self::UNDO_IMPORT_ERROR;
            $this->save();
            $this->_log($e, Zend_Log::ERR);
            throw $e;
        }
    }

    /**
     * Adds a new item based on a row string in the CSV file and returns it.
     *
     * @param string $row A row string in the CSV file
     * @return Item|boolean The inserted item or false if an item could not be
     * added.
     */
    protected function _addItemFromRow($row)
    {
        $result = $this->getColumnMaps()->map($row);

        $sourceItemId = isset($result[CsvImport_ColumnMap::TYPE_SOURCE_ITEM_ID])
            ? $result[CsvImport_ColumnMap::TYPE_SOURCE_ITEM_ID]
            : '';

        $tags = $result[CsvImport_ColumnMap::TYPE_TAG];
        $itemMetadata = array(
            Builder_Item::ITEM_TYPE_ID => $this->item_type_id,
            Builder_Item::COLLECTION_ID => $tags,
            Builder_Item::IS_PUBLIC => $this->is_public,
            Builder_Item::IS_FEATURED => $this->is_featured,
        );

        // If this is coming from CSV Report or from mixed records, bring in the
        // item metadata coming from the file.
        if (!empty($result[CsvImport_ColumnMap::TYPE_ITEM_TYPE])) {
            $itemMetadata[Builder_Item::ITEM_TYPE_NAME] = $result[CsvImport_ColumnMap::TYPE_ITEM_TYPE];
        }
        if (!is_null($result[CsvImport_ColumnMap::TYPE_COLLECTION])) {
            $itemMetadata[Builder_Item::COLLECTION_ID] = $result[CsvImport_ColumnMap::TYPE_COLLECTION];
        }
        if (!is_null($result[CsvImport_ColumnMap::TYPE_PUBLIC])) {
            $itemMetadata[Builder_Item::IS_PUBLIC] = $result[CsvImport_ColumnMap::TYPE_PUBLIC];
        }
        if (!is_null($result[CsvImport_ColumnMap::TYPE_FEATURED])) {
            $itemMetadata[Builder_Item::IS_FEATURED] = $result[CsvImport_ColumnMap::TYPE_FEATURED];
        }

        $elementTexts = $result[CsvImport_ColumnMap::TYPE_ELEMENT];
        // Keep only non empty fields to avoid removing them (allow update).
        $elementTexts = array_values(array_filter($elementTexts, 'self::_removeEmptyElement'));
        // Trim metadata to avoid spaces.
        $elementTexts = $this->_trimElementTexts($elementTexts);
        try {
            $item = insert_item($itemMetadata, $elementTexts);
        } catch (Omeka_Validator_Exception $e) {
            $this->_log($e, Zend_Log::ERR);
            return false;
        } catch (Omeka_Record_Builder_Exception $e) {
            $this->_log($e, Zend_Log::ERR);
            return false;
        }

        $fileUrls = $result[CsvImport_ColumnMap::TYPE_FILE];
        if (!$this->_attachFilesToItem($item, $fileUrls)) {
            return false;
        }

        // Makes it easy to unimport the item later.
        $this->_recordImportedItemId($item->id, $sourceItemId);
        return $item;
    }

    /**
     * Attachs a list of  files to an item.
     *
     * @param Item $item
     * @param array $fileUrls An array of the urls of files to attach to item.
     * @param boolean $itemDelete Delete item (default) or not if the file can't
     *   be ingested.
     * @return boolean True if success, false else.
     */
    protected function _attachFilesToItem($item, $fileUrls, $itemDelete = true)
    {
        foreach ($fileUrls as $fileUrl) {
            // Set the transfer strategy according to file name.
            $parsedFileUrl = parse_url($fileUrl);
            if (!isset($parsedFileUrl['scheme']) || $parsedFileUrl['scheme'] == 'file') {
                $transferStrategy = 'Filesystem';
                $fileUrl = $parsedFileUrl['path'];
            }
            else {
                $transferStrategy = 'Url';
            }

            // Import the file and attach it to the item.
            try {
                $files = insert_files_for_item($item,
                    $transferStrategy,
                    $fileUrl,
                    array('ignore_invalid_files' => false));
            } catch (Omeka_File_Ingest_InvalidException $e) {
                $msg = __("Invalid file URL '%s': %s",
                    $fileUrl, $e->getMessage());
                $this->_log($msg, Zend_Log::ERR);
                if ($itemDelete) {
                    $item->delete();
                }
                release_object($item);
                return false;
            } catch (Omeka_File_Ingest_Exception $e) {
                $msg = __("Could not import file '%s': %s",
                    $fileUrl, $e->getMessage());
                $this->_log($msg, Zend_Log::ERR);
                if ($itemDelete) {
                    $item->delete();
                }
                release_object($item);
                return false;
            }
            release_object($files);
        }
        return true;
    }

    /**
     * Adds a new file and add metadata based on a row string in the CSV file
     * and returns it.
     *
     * @param string $row A row string in the CSV file
     * @return File|boolean The inserted file or false if the file could not be
     * added.
     */
    protected function _addFileFromRow($row)
    {
        $result = $this->getColumnMaps()->map($row);

        // Check if the file url is present.
        $fileUrl = $result[CsvImport_ColumnMap::TYPE_FILE_URL];
        if (empty($fileUrl)) {
            $msg = __('You should give the path or the url of the file to import.');
            $this->_log($msg, Zend_Log::ERR);
            return false;
        }

        // Check if the source item id is present.
        if (!isset($result[CsvImport_ColumnMap::TYPE_SOURCE_ITEM_ID])
                || empty($result[CsvImport_ColumnMap::TYPE_SOURCE_ITEM_ID])
            ) {
            $msg = __('No indication of the source item to which attach filename.', $fileUrl);
            $this->_log($msg, Zend_Log::ERR);
            return false;
        }

        // Looking for the item id.
        $sourceItemId = $result[CsvImport_ColumnMap::TYPE_SOURCE_ITEM_ID];
        $csvImportedItem = get_db()->getTable('CsvImport_ImportedItem')->findBy(array(
            'source_item_id' => $sourceItemId,
            'import_id' => $this->id,
        ), 1);
        if (empty($csvImportedItem)) {
            $msg = __('No item with the source item id "%s" does exist in the database.', $sourceItemId)
                . ' ' . __('File rows should always be imported after the item to which they are attached.');
            $this->_log($msg, Zend_Log::ERR);
            return false;
        }
        $item = get_record_by_id('Item', $csvImportedItem[0]->item_id);

        // Set the transfer strategy according to file name.
        $parsedFileUrl = parse_url($fileUrl);
        if (!isset($parsedFileUrl['scheme']) || $parsedFileUrl['scheme'] == 'file') {
            $transferStrategy = 'Filesystem';
            $fileUrl = $parsedFileUrl['path'];
        }
        else {
            $transferStrategy = 'Url';
        }

        // Import the file and attach it to the item.
        try {
            $files = insert_files_for_item($item,
                $transferStrategy,
                $fileUrl,
                array('ignore_invalid_files' => false));
        } catch (Omeka_File_Ingest_InvalidException $e) {
            $msg = __("Error occurred when attempting to ingest '%s' as a file: %s",
                $fileUrl, $e->getMessage());
            $this->_log($msg, Zend_Log::ERR);
            return false;
        }
        // Need to release file in order to update all current data, because
        // $file->save() is not enough.
        $file_id = $files[0]->id;
        release_object($files);
        $file = get_record_by_id('File', $file_id);

        // Update file with new metadata.
        $this->_updateRecord($file, $result);
        return $file;
    }

    /**
     * Adds file metadata based on a row string in the CSV file and returns it.
     *
     * @param string $row A row string in the CSV file
     * @return File|boolean The inserted file or false if metadata can't be
     * added.
     */
    protected function _updateFileFromRow($row)
    {
        $result = $this->getColumnMaps()->map($row);

        $fileUrl = $result[CsvImport_ColumnMap::TYPE_FILE_URL];
        if (empty($fileUrl)) {
            $msg = __('You should give the path or the url of the file to import.');
            $this->_log($msg, Zend_Log::ERR);
            return false;
        }

        $file = get_db()->getTable('File')->findBySql('original_filename = ?', array($fileUrl), true);
        if (empty($file)) {
            $msg = __('File "%s" does not exist in the database.', $fileUrl)
                . ' ' . __('No item associated with it was found.')
                . ' ' . __('Add items first before importing file metadata.');
            $this->_log($msg, Zend_Log::ERR);
            return false;
        }

        // Update file with new metadata.
        $this->_updateRecord($file, $result);
        return $file;
    }

    /**
     * Update a record based on a row string in the CSV file and returns it.
     *
     * @param string $row A row string in the CSV file
     * @return Record|boolean The updated record or false if no record could be
     * updated.
     */
    protected function _updateFromRow($row)
    {
        $map = $this->getColumnMaps()->map($row);

        $updateIdentifier = isset($map[CsvImport_ColumnMap::TYPE_UPDATE_IDENTIFIER])
            ? $map[CsvImport_ColumnMap::TYPE_UPDATE_IDENTIFIER]
            : 'internal id';
        $recordType = isset($map[CsvImport_ColumnMap::TYPE_RECORD_TYPE])
            ? $map[CsvImport_ColumnMap::TYPE_RECORD_TYPE]
            : 'Item';
        $recordIdentifier = isset($map[CsvImport_ColumnMap::TYPE_RECORD_IDENTIFIER])
            ? $map[CsvImport_ColumnMap::TYPE_RECORD_IDENTIFIER]
            : '';

        $record = $this->_getRecordByIdentifier($recordIdentifier, $recordType, $updateIdentifier);

        // No record can be updated.
        if (empty($record)) {
            $msg = __('You try to update the record "%s", but it does not exist.', $recordIdentifier);
            $this->_log($msg, Zend_Log::ERR);
            return false;
        }

        // If there are files to attach to an item, import it separately.
        if (method_exists($record, 'addFile')) {
            $fileUrls = $map[CsvImport_ColumnMap::TYPE_FILE];
            if (!$this->_attachFilesToItem($record, $fileUrls, false)) {
                return false;
            }
        }

        // Update of a record.
        $updateMode = isset($map[CsvImport_ColumnMap::TYPE_UPDATE_MODE])
            ? $map[CsvImport_ColumnMap::TYPE_UPDATE_MODE]
            : 'Add';

        return $this->_updateRecord($record, $map, $updateMode);
    }

    /**
     * Adds metadata to an existing record.
     *
     * @param Record $record An existing and checked record object.
     * @param array $map A mapped row.
     * @param string $mode Update mode: "Add", "Replace" or "Replace all".
     *
     * @return Record|boolean
     *   The updated record or false if metadata can't be updated.
     */
    protected function _updateRecord($record, $map, $mode = 'Add')
    {
        // Import metadata.
        $elementTexts = $map[CsvImport_ColumnMap::TYPE_ELEMENT];
        // Trim metadata to avoid spaces.
        $elementTexts = $this->_trimElementTexts($elementTexts);
        // Keep only non empty fields to avoid removing them to allow update.
        if ($mode == 'Add' || $mode == 'Replace') {
            $elementTexts = array_values(array_filter($elementTexts, 'self::_removeEmptyElement'));
        }
        // Overwrite existing element text values if wanted.
        if ($mode == 'Replace' || $mode == 'Replace all') {
            foreach ($elementTexts as $key => $info) {
                if ($info['element_id']) {
                    $record->deleteElementTextsbyElementId((array) $info['element_id']);
                }
            }
        }
        // To reset keys is needed to avoid bug when there is no DC Title.
        $elementTexts = array_values($elementTexts);

        $record->addElementTextsByArray($elementTexts);

        $record->save();
        return $record;
    }

    /**
     * Get a record from an identifier.
     *
     * @param string $recordIdentifier The identifier of the record to update.
     * @param string $recordType The type of the record to update.
     * @param string $identifier The type of identifier used to identify
     * the record to update.
     *
     * @return Item|boolean The record to update or false if no one exists.
     */
    protected function _getRecordByIdentifier($recordIdentifier, $recordType = 'Item', $identifier = 'internal id')
    {
        $record = false;

        if (empty($recordIdentifier)) {
        }
        elseif ($identifier == 'internal id') {
            if ($recordType != 'Any') {
                $record = get_record_by_id($recordType, $recordIdentifier);
            }
        }
        elseif ($identifier == 'original_filename') {
            if ($recordType == 'Any' || $recordType == 'File') {
                $record = get_db()->getTable('File')->findBySql(
                    'original_filename = ?',
                    array('original_filename' => $recordIdentifier),
                    true);
            }
        }
        else {
            $db = get_db();

            $elementSet = trim(substr($identifier, 0, strrpos($identifier, ':')));
            $element = trim(substr($identifier, strrpos($identifier, ':') + 1));

            // Use of ordered placeholders.
            $bind = array();
            $bind[] = $elementSet;
            $bind[] = $element;
            $bind[] = $recordIdentifier;
            $sql_record_type = '';
            if ($recordType !== 'Any') {
                $sql_record_type = 'AND element_texts.record_type = ?';
                $bind[] = $recordType;
            }

            $sql = "
                SELECT items.id, element_texts.record_type
                FROM {$db->Item} items
                    JOIN {$db->ElementText} element_texts
                        ON items.id = element_texts.record_id
                    JOIN {$db->Element} elements
                        ON element_texts.element_id = elements.id
                    JOIN {$db->ElementSet} element_sets
                        ON elements.element_set_id = element_sets.id
                WHERE element_sets.name = ?
                    AND elements.name = ?
                    AND element_texts.text = ?
                    $sql_record_type
                LIMIT 1
            ";
            $result = $db->fetchRow($sql, $bind);

            if (!empty($result)) {
                $record = get_record_by_id($result['record_type'], $result['id']);
            }
        }

        return $record;
    }

    /**
     * Check if an element is an element without empty string .
     *
     * @param string $element
     *   Element to check.
     *
     * @return boolean
     *   True if the element is an element without empty string.
     */
    private function _removeEmptyElement($element)
    {
        // Don't remove 0.
        return (isset($element['text']) && $element['text'] !== '');
    }

    /**
     * Check if an element is an element without empty string.
     *
     * @param string $element
     *   Element to check.
     *
     * @return array
     *   Array of trimed element texts.
     */
    private function _trimElementTexts($elementTexts)
    {
        foreach ($elementTexts as &$element) {
            if (isset($element['text'])) {
                $element['text'] = trim($element['text']);
            }
        }
        return $elementTexts;
    }

    /**
     * Records that an item was successfully imported in the database.
     *
     * @param int $itemId The id of the item imported
     * @param string $sourceItemId The source item id of the item imported
     */
    protected function _recordImportedItemId($itemId, $sourceItemId)
    {
        $csvImportedItem = new CsvImport_ImportedItem();
        $csvImportedItem->setArray(array(
            'item_id' => $itemId,
            'source_item_id' => $sourceItemId,
            'import_id' => $this->id,
        ));
        $csvImportedItem->save();
        $this->_importedCount++;
    }

    /**
     * Log an import message
     * Every message will log the import ID.
     * Messages that have %memory% will include memory usage information.
     *
     * @param string $msg The message to log
     * @param int $priority The priority of the message
     */
    protected function _log($msg, $priority = Zend_Log::DEBUG)
    {
        $prefix = "[CsvImport][#{$this->id}]";
        $msg = str_replace('%memory%', memory_get_usage(), $msg);
        $msg = str_replace('%time%', date('Y-m-d G:i:s'), $msg);
        _log("$prefix $msg", $priority);
    }
}
