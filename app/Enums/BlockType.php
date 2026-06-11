<?php

namespace App\Enums;

enum BlockType: string
{
    case Text = 'text';
    case Image = 'image';
    case QrCode = 'qrcode';
}
