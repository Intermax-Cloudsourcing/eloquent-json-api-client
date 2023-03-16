<?php

declare(strict_types=1);

namespace Intermax\EloquentJsonApiClient;

enum RelationType
{
    case Many;
    case One;
}
