<?php

namespace App\Enums;

enum ConnectedIntegrationStatus: string
{
    case Connected = 'connected';
    case ReauthorizationRequired = 'reauthorization_required';
    case Revoked = 'revoked';
}
