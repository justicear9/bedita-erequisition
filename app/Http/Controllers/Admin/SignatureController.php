<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class SignatureController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Signatures/Index', [
            'users' => User::query()
                ->with('department')
                ->orderBy('name')
                ->get()
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role_label' => $user->role->label(),
                    'department' => $user->department?->name,
                    'has_signature' => $user->has_signature,
                    'signature_url' => $user->signature_url,
                ]),
        ]);
    }
}
