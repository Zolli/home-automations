<?php

declare(strict_types=1);

namespace Shared\Alert\Mitigation\Collection;

use Shared\Alert\Mitigation\MitigationType;
use Shared\Config\Collection\NamedTypeCollection;

/** @extends NamedTypeCollection<MitigationType> */
final readonly class MitigationTypeCollection extends NamedTypeCollection {}
