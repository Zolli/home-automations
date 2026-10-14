<?php

declare(strict_types=1);

namespace Shared\Alert\Signal\Collection;

use Shared\Alert\Signal\SignalType;
use Shared\Config\Collection\NamedTypeCollection;

/** @extends NamedTypeCollection<SignalType> */
final readonly class SignalTypeCollection extends NamedTypeCollection {}
