<?php

namespace App\Domain\Geo;

enum PointLocation: int
{
    case Outside = -1;
    case Boundary = 0;
    case Inside = 1;
}
