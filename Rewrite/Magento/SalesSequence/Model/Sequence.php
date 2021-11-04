<?php

declare(strict_types=1);

namespace Comwrap\CloudSalesIncrement\Rewrite\Magento\SalesSequence\Model;

use Magento\Framework\App\ResourceConnection as AppResource;
use Magento\Framework\DB\Adapter\AdapterInterface as DBAdapter;
use Magento\Framework\DB\Adapter\DuplicateException;
use Magento\Framework\DB\Sequence\SequenceInterface;
use Magento\SalesSequence\Model\Meta;

class Sequence implements SequenceInterface
{
    /**
     * @var string
     */
    private string $lastIncrementId;

    /**
     * @var Meta
     */
    private Meta $meta;

    /**
     * @var DBAdapter
     */
    private DBAdapter $connection;

    /**
     * @var string
     */
    private string $pattern;

    /**
     * @param Meta $meta
     * @param AppResource $resource
     * @param string $pattern
     */
    public function __construct(
        Meta $meta,
        AppResource $resource,
        string $pattern = \Magento\SalesSequence\Model\Sequence::DEFAULT_PATTERN
    ) {
        $this->meta = $meta;
        $this->connection = $resource->getConnection('sales');
        $this->pattern = $pattern;
    }

    /**
     * Retrieve current value
     *
     * @return string
     */
    public function getCurrentValue(): string
    {
        if (!isset($this->lastIncrementId)) {
            return '';
        }

        return sprintf(
            $this->pattern,
            $this->meta->getActiveProfile()->getPrefix(),
            $this->calculateCurrentValue(),
            $this->meta->getActiveProfile()->getSuffix()
        );
    }

    /**
     * Retrieve next value
     *
     * @return string
     * @throws \Exception
     */
    public function getNextValue(): string
    {
        $this->lastIncrementId = $this->getNextSequence();

        return $this->getCurrentValue();
    }

    /**
     * Calculate current value depends on start value
     *
     * @return string
     */
    private function calculateCurrentValue(): string
    {
        return (string) (($this->lastIncrementId - $this->meta->getActiveProfile()->getStartValue())
            * $this->meta->getActiveProfile()->getStep() + $this->meta->getActiveProfile()->getStartValue());
    }

    /**
     * Insert and returns next sequence value.
     *
     * Used to avoid generation autoincrement value "+3" on magento cloud staging and production environments
     *
     * @return string
     * @throws \Exception
     */
    private function getNextSequence(): string
    {
        $select = $this->connection->select();
        $select->from($this->meta->getSequenceTable(), 'MAX(sequence_value)');
        $lastValue = ($this->connection->fetchOne($select)) ?: 0;

        return $this->insertToSequence($lastValue + $this->meta->getActiveProfile()->getStep());
    }

    /**
     * Try to insert new sequence value
     *
     * If got duplicate extension increase last sequence value and try again.
     *
     * @param int $nextValue
     * @return string
     * @throws \Exception
     */
    private function insertToSequence(int $nextValue): string
    {
        try {
            $this->connection->insert(
                $this->meta->getSequenceTable(),
                [
                    'sequence_value' => $nextValue
                ]
            );

            return $this->connection->lastInsertId($this->meta->getSequenceTable());
        } catch (DuplicateException $e) {
            // The value already exists in the sequence table, so let's assume it's the last value
            $lastValue = $nextValue;

            return $this->insertToSequence($lastValue + $this->meta->getActiveProfile()->getStep());
        }
    }
}
