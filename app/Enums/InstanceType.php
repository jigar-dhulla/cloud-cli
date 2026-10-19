<?php

namespace App\Enums;

enum InstanceType: string
{
    case APP = 'app';
    case SERVICE = 'service';
    case QUEUE = 'queue';
    case MANAGED_QUEUE = 'managed_queue';
}
