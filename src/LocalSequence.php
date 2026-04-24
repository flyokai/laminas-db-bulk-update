<?php

namespace Flyokai\LaminasDbBulkUpdate;

class LocalSequence
{
    private int $sequence = 2_000_000_000;

    public function next(): int
    {
        return ++$this->sequence;
    }
}
