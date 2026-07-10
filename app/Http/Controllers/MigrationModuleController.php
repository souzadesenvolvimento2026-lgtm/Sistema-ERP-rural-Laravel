<?php

namespace App\Http\Controllers;

use App\Services\ModuleDataService;
use Illuminate\View\View;

class MigrationModuleController extends Controller
{
    public function __construct(private ModuleDataService $modules)
    {
    }

    public function dashboard(): View
    {
        return view('dashboard', $this->modules->dashboardData());
    }

    public function module(string $module): View
    {
        return view('modules.index', $this->modules->moduleData($module));
    }
}
