<?php

namespace App\Enums;

enum TemplateStatus: string
{
    case Draft = 'draft';
    case Ready = 'ready';
    case Archived = 'archived';
}
