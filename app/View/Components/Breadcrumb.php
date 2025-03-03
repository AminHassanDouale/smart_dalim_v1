<?php

namespace App\View\Components;

use Illuminate\View\Component;

class Breadcrumb extends Component
{
    public function __construct(
        public bool $separator = true
    ) {}

    public function render()
    {
        return view('components.breadcrumb');
    }
}
