<?php

namespace Modules\Inventory\Enums;

enum BatchStatus : string
{
    case ACTIVE = 'active';
    case PARTIAL = 'partial';
    case EXPIRED = 'expired';
    case QUARANTINED = 'quarantined';
}
