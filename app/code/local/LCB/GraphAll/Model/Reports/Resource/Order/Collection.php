<?php
class LCB_GraphAll_Model_Reports_Resource_Order_Collection extends Mage_Reports_Model_Resource_Order_Collection
{
    /**
     * Prepare report summary from live data including all order states except cancelled
     *
     * @param string $range
     * @param mixed $customStart
     * @param mixed $customEnd
     * @param int $isFilter
     * @return Mage_Reports_Model_Resource_Order_Collection
     */
    protected function _prepareSummaryLive($range, $customStart, $customEnd, $isFilter = 0)
    {
        $this->setMainTable('sales/order');
        $adapter = $this->getConnection();

        /**
         * Reset all columns, because result will group only by 'created_at' field
         */
        $this->getSelect()->reset(Zend_Db_Select::COLUMNS);

        $expression = $this->_getSalesAmountExpression();
        if ($isFilter == 0) {
            $this->getSelect()->columns(array(
                'revenue' => new Zend_Db_Expr(
                    sprintf('SUM((%s) * %s)', $expression,
                        $adapter->getIfNullSql('main_table.base_to_global_rate', 0)
                    )
                 )
            ));
        } else {
            $this->getSelect()->columns(array(
                'revenue' => new Zend_Db_Expr(sprintf('SUM(%s)', $expression))
            ));
        }

        $dateRange = $this->getDateRange($range, $customStart, $customEnd);

        $tzRangeOffsetExpression = $this->_getTZRangeOffsetExpression(
            $range, 'created_at', $dateRange['from'], $dateRange['to']
        );

        $this->getSelect()
            ->columns(array(
                'quantity' => 'COUNT(main_table.entity_id)',
                'range' => $tzRangeOffsetExpression,
            ))
            ->where('main_table.state NOT IN (?)', array(
                Mage_Sales_Model_Order::STATE_CANCELED,
                )
            )
            ->order('range', Zend_Db_Select::SQL_ASC)
            ->group($tzRangeOffsetExpression);

        $this->addFieldToFilter('created_at', $dateRange);

        return $this;
    }
    
    /**
     * Calculate totals live report including all order states except cancelled
     *
     * @param int $isFilter
     * @return Mage_Reports_Model_Resource_Order_Collection
     */
    protected function _calculateTotalsLive($isFilter = 0)
    {
        $this->setMainTable('sales/order');
        $this->removeAllFieldsFromSelect();

        $adapter = $this->getConnection();

        $baseTotalInvoiced    = $adapter->getIfNullSql('main_table.base_total_invoiced', 'main_table.base_grand_total'); 
        $baseTotalRefunded    = $adapter->getIfNullSql('main_table.base_total_refunded', 0);
        $baseTaxInvoiced      = $adapter->getIfNullSql('main_table.base_tax_invoiced', 'main_table.base_tax_amount'); 
        $baseTaxRefunded      = $adapter->getIfNullSql('main_table.base_tax_refunded', 0);
        $baseShippingInvoiced = $adapter->getIfNullSql('main_table.base_shipping_invoiced', 'main_table.base_shipping_amount'); 
        $baseShippingRefunded = $adapter->getIfNullSql('main_table.base_shipping_refunded', 0);

        $revenueExp = sprintf('%s - %s - %s - (%s - %s - %s)',
            $baseTotalInvoiced,
            $baseTaxInvoiced,
            $baseShippingInvoiced,
            $baseTotalRefunded,
            $baseTaxRefunded,
            $baseShippingRefunded
        );

        $taxExp = sprintf('%s - %s', $baseTaxInvoiced, $baseTaxRefunded);
        $shippingExp = sprintf('%s - %s', $baseShippingInvoiced, $baseShippingRefunded);

        if ($isFilter == 0) {
            $rateExp = $adapter->getIfNullSql('main_table.base_to_global_rate', 0);
            $this->getSelect()->columns(
                array(
                    'revenue'  => new Zend_Db_Expr(sprintf('SUM((%s) * %s)', $revenueExp, $rateExp)),
                    'tax'      => new Zend_Db_Expr(sprintf('SUM((%s) * %s)', $taxExp, $rateExp)),
                    'shipping' => new Zend_Db_Expr(sprintf('SUM((%s) * %s)', $shippingExp, $rateExp))
                )
            );
        } else {
            $this->getSelect()->columns(
                array(
                    'revenue'  => new Zend_Db_Expr(sprintf('SUM(%s)', $revenueExp)),
                    'tax'      => new Zend_Db_Expr(sprintf('SUM(%s)', $taxExp)),
                    'shipping' => new Zend_Db_Expr(sprintf('SUM(%s)', $shippingExp))
                )
            );
        }

        $this->getSelect()->columns(array(
            'quantity' => 'COUNT(main_table.entity_id)'
        ))
        ->where('main_table.state NOT IN (?)', array(
            Mage_Sales_Model_Order::STATE_CANCELED,
            )
         );

        return $this;
    }
    
}
        
