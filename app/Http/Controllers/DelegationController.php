<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\ApprovalDelegation;
use App\Models\User;
use App\Services\DelegationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DelegationController extends Controller
{
    public function __construct(private DelegationService $delegations) {}

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless(
            in_array($user->role, [UserRole::Manager, UserRole::InventoryManager, UserRole::Admin], true),
            403,
        );

        $data = $request->validate([
            'delegator_id' => [
                Rule::requiredIf(fn () => $user->role === UserRole::Admin),
                'nullable',
                'integer',
                'exists:users,id',
            ],
            'delegate_id' => ['required', 'integer', 'exists:users,id', 'different:delegator_id'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $delegator = $user->role === UserRole::Admin && ! empty($data['delegator_id'])
            ? User::query()->findOrFail($data['delegator_id'])
            : $user;

        abort_unless(
            in_array($delegator->role, [UserRole::Manager, UserRole::InventoryManager], true),
            403,
            'Delegator must be a manager or Head of Inventory.',
        );

        if ($user->role !== UserRole::Admin && $delegator->id !== $user->id) {
            abort(403);
        }

        $delegate = User::query()->findOrFail($data['delegate_id']);

        $this->delegations->create(
            $delegator,
            $delegate,
            $data['starts_at'] ?? null,
            $data['ends_at'] ?? null,
        );

        return back()->with('success', 'Delegation created.');
    }

    public function destroy(Request $request, ApprovalDelegation $delegation): RedirectResponse
    {
        $user = $request->user();

        $allowed = $user->role === UserRole::Admin
            || $delegation->delegator_id === $user->id
            || $delegation->delegate_id === $user->id;

        abort_unless($allowed, 403);

        $this->delegations->revoke($delegation);

        return back()->with('success', 'Delegation revoked.');
    }
}
