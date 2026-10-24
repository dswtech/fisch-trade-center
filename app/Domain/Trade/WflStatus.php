<?php

namespace App\Domain\Trade;

enum WflStatus: string
{
    case Win = 'win';
    case Fair = 'fair';
    case Lose = 'lose';
    case Unavailable = 'unavailable';
}
