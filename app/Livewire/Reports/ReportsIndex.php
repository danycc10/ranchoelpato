<?php

namespace App\Livewire\Reports;

use Livewire\Component;

class ReportsIndex extends Component
{
    public function render()
    {
        return view('livewire.reports.reports-index')
            ->layout('layouts.app');
    }
}
