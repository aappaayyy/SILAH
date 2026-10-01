<?php

namespace App\Enums;

enum RequestStatus: string
{
    case Pending = 'Pending'; case Approved = 'Approved'; case Rejected = 'Rejected';
}
