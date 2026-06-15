<?php

namespace App\Enums;

enum BlockType: string
{
    case Text = 'text';
    case Image = 'image';
    case Signature = 'signature';
    case QrCode = 'qrcode';

    /**
     * Types backed by a stored image file (rendered as an <img>), as opposed
     * to text or the generated QR code.
     */
    public function isImageLike(): bool
    {
        return $this === self::Image || $this === self::Signature;
    }
}
