<?php

namespace App\Enums;

enum WhatsappStatus: string
{
    case Queued = 'queued'; case Sent = 'sent'; case Failed = 'failed';
}
