<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Requisition;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'users' => User::query()->count(),
                'departments' => Department::query()->count(),
                'requisitions' => Requisition::query()->count(),
                'with_signatures' => User::query()->whereNotNull('signature_path')->count(),
            ],
        ]);
    }
}
