<?php

namespace App\Enums;

/** clients.status — ERD v1.2 / BRD §7.1. */
enum ClientStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
