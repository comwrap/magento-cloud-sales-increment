# magento-cloud-sales-increment

This module fixes sequences used for generating increments in sales entities (orders, invoices, credit memos) on Magento Cloud environment.
The problem is that on Magento Cloud environments configured setting "auto_increment_increment" for Mysql with value "3". This leads to the next sequence of "increment_id" values for sales entities:
000000001, 000000004, 000000007, 000000010... 
The magento-cloud-sales-increment module rewrites the original sales sequence model to get new sales "increment_id" values with step value "1" instead of "3". 
So result sequence of "increment_id" values will be: 000000001, 000000002, 000000003, 000000004...


### Requirements

Magento Open Source 2.3.\*|2.4.\* or Adobe Commerce 2.3.\*|2.4.\*


## Installation
*	Go to your installation directory of Magento 2 and perform the following commands
*   `composer config repositories.comwrap vcs https://github.com/comwrap/magento-cloud-sales-increment`
*	`composer require comwrap/magento-cloud-sales-increment dev-master`
*	`php bin/magento setup:upgrade`
*	`php bin/magento setup:di:compile`
*	`php bin/magento cache:clean`
