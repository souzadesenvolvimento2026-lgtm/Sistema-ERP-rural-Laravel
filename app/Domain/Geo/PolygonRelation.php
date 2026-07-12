<?php

namespace App\Domain\Geo;

enum PolygonRelation
{
    case Disjoint;
    case Touching;
    case PartialOverlap;
    case Equal;
    case Contains;
    case ContainedBy;
}
