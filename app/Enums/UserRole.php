<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Organization = 'organization';
    case Recipient = 'recipient';
}
