<?php

declare(strict_types=1);

// This file should NOT trigger the EfferentObjectCouplingRule
// It references only a few classes

class LowCouplingClass
{
    public function __construct(
        private \Illuminate\Http\Request $request,
        private \Illuminate\Database\Eloquent\Model $model
    ) {}

    public function process(): string
    {
        return 'processed';
    }
}