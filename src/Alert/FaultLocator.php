<?php

declare(strict_types=1);

namespace Shared\Alert;

use Shared\Alert\Rule\Fault;
use Stewart\Contracts\HaContext;
use Stewart\Contracts\Registry\Area;

final readonly class FaultLocator
{
    public function findArea(Fault $fault, HaContext $ha): ?Area
    {
        $entityId = $fault->source->entityId;

        if ($entityId === null) {
            return null;
        }

        $registry = $ha->getRegistry();
        $areaId = $registry->findEntityPlacement($entityId)->areaId;

        return $areaId === null ? null : $registry->findArea($areaId);
    }
}
