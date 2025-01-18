<?php

namespace App\View\Components\Breadcrumb;

use Illuminate\View\Component;

class Item extends Component
{
    public function __construct(
        public bool $first = false
    ) {}

    public function render()
    {
        return view('components.breadcrumb.item');
    }
}
