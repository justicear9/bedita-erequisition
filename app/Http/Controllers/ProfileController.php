<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\ProfileUpdateRequest;
use App\Models\ApprovalDelegation;
use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        $canDelegate = in_array($user->role, [UserRole::Manager, UserRole::InventoryManager], true);

        $delegations = [];
        $delegateCandidates = [];

        if ($canDelegate) {
            $delegations = ApprovalDelegation::query()
                ->with(['delegate:id,name,email', 'delegator:id,name,email'])
                ->where(function ($q) use ($user) {
                    $q->where('delegator_id', $user->id)
                        ->orWhere('delegate_id', $user->id);
                })
                ->latest()
                ->get()
                ->map(fn (ApprovalDelegation $d) => [
                    'id' => $d->id,
                    'delegator' => $d->delegator,
                    'delegate' => $d->delegate,
                    'starts_at' => $d->starts_at?->toDateString(),
                    'ends_at' => $d->ends_at?->toDateString(),
                    'active' => $d->isCurrentlyActive(),
                    'is_mine' => $d->delegator_id === $user->id,
                ]);

            $delegateCandidates = User::query()
                ->where('role', $user->role)
                ->where('id', '!=', $user->id)
                ->orderBy('name')
                ->get(['id', 'name', 'email']);
        }

        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => session('status'),
            'signatureUrl' => $user->signature_url,
            'hasSignature' => $user->has_signature,
            'canDelegate' => $canDelegate,
            'delegations' => $delegations,
            'delegateCandidates' => $delegateCandidates,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
