<?php return array (
  'Modules\\Inventory\\Providers\\EventServiceProvider' => 
  array (
  ),
  'Modules\\ItemMaker\\Providers\\EventServiceProvider' => 
  array (
  ),
  'Modules\\OrderInvoice\\Providers\\EventServiceProvider' => 
  array (
    'Modules\\OrderInvoice\\app\\Events\\InvoicePaid' => 
    array (
      0 => 'Modules\\OrderInvoice\\app\\Listeners\\UpdateOrderStatus',
      1 => 'Modules\\OrderInvoice\\app\\Listeners\\CommitInventory',
    ),
    'Modules\\OrderInvoice\\app\\Events\\OrderRefunded' => 
    array (
      0 => 'Modules\\OrderInvoice\\app\\Listeners\\RollbackInventory',
      1 => 'Modules\\OrderInvoice\\app\\Listeners\\RefundInvoice',
    ),
  ),
  'Modules\\UserCreator\\Providers\\EventServiceProvider' => 
  array (
  ),
  'Illuminate\\Foundation\\Support\\Providers\\EventServiceProvider' => 
  array (
  ),
);