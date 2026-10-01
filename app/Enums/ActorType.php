<?php

namespace App\Enums;

enum ActorType: string
{
    case System = 'System'; case Admin = 'Admin'; case Guest = 'Guest';
}
