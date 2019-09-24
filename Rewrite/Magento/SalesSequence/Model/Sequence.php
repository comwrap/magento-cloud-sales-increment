<?php

namespace Comwrap\CloudSalesIncrement\Rewrite\Magento\SalesSequence\Model;


use Magento\Framework\App\ResourceConnection as AppResource;
use Magento\SalesSequence\Model\Meta;
use Magento\Framework\DB\Adapter\DuplicateException;

class Sequence extends \Magento\SalesSequence\Model\Sequence
{   

    /**
     * @var string
     */
    private $lastIncrementId;

    /**
     * @var Meta
     */
    private $meta;

    /**
     * @var false|\Magento\Framework\DB\Adapter\AdapterInterface
     */
    private $connection;

    /**
     * @var string
     */
    private $pattern;

    public function __construct(Meta $meta, AppResource $resource, string $pattern = \Magento\SalesSequence\Model\Sequence::DEFAULT_PATTERN)
    {
        \Magento\SalesSequence\Model\Sequence::__construct($meta, $resource, $pattern);
        $this->meta = $meta;
        $this->connection = $resource->getConnection('sales');
        $this->pattern = $pattern;
    }

    /**
     * Retrieve current value
     *
     * @return string
     */
    public function getCurrentValue()
    {
        if (!isset($this->lastIncrementId)) {
            return null;
        }

        return sprintf(
            $this->pattern,
            $this->meta->getActiveProfile()->getPrefix(),
            $this->calculateCurrentValue(),
            $this->meta->getActiveProfile()->getSuffix()
        );
    }

    /**
     *  Retrieve next value     *
     *
     * @return string
     * @throws \Exception
     */
    public function getNextValue()
    {
        $this->lastIncrementId = $this->getNextSequence();
        return $this->getCurrentValue();
    }

    /**
     * Insert and returns next sequence value.
     * Used to avoid generation autoincrement value "+3"
     * on magento cloud staging and production environments
     * @return int
     * @throws \Exception
     */
    protected function getNextSequence()
    {
        $lastValSelect = $this->connection->select();
        $lastValSelect->from($this->meta->getSequenceTable(), 'MAX(sequence_value)');
        $lastVal = ($this->connection->fetchOne($lastValSelect)) ?: 0;
        return $this->insertToSequence($lastVal);        
    }

    /**
    * Try to insert new last sequence value. If got duplicate extension increase last sequence value and try again. 
    * @param int $lastVal
    * @throws \Exception
    */
    protected function insertToSequence($lastVal)
    {
        try {
            $this->connection->insert(
                $this->meta->getSequenceTable(),
                [
                    'sequence_value' =>
                        (int) $lastVal + (int) $this->meta->getActiveProfile()->getStep()
                ]
            );
            return $this->connection->lastInsertId($this->meta->getSequenceTable());
        } catch (DuplicateException $e) {
            return $this->insertToSequence((int) $lastVal + (int) $this->meta->getActiveProfile()->getStep());
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Calculate current value depends on start value
     *
     * @return string
     */
    private function calculateCurrentValue()
    {
        return ($this->lastIncrementId - $this->meta->getActiveProfile()->getStartValue())
            * $this->meta->getActiveProfile()->getStep() + $this->meta->getActiveProfile()->getStartValue();
    }
}