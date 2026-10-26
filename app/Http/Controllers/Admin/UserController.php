<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use App\Services\SignatureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(private SignatureService $signatures) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Users/Index', [
            'users' => User::query()
                ->with('department')
                ->orderBy('name')
                ->get()
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role->value,
                    'role_label' => $user->role->label(),
                    'department_id' => $user->department_id,
                    'department' => $user->department?->name,
                    'has_signature' => $user->has_signature,
                    'signature_url' => $user->signature_url,
                ]),
            'roles' => collect(UserRole::cases())->map(fn ($r) => [
                'value' => $r->value,
                'label' => $r->label(),
            ]),
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        User::query()->create([
            ...$data,
            'password' => Hash::make($data['password']),
            'email_verified_at' => now(),
        ]);

        return back()->with('success', 'User created.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);

        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'department_id' => $data['department_id'] ?? null,
        ];

        if (! empty($data['password'])) {
            $payload['password'] = Hash::make($data['password']);
        }

        $user->update($payload);

        return back()->with('success', 'User updated.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->withErrors(['user' => 'You cannot delete your own account.']);
        }

        $this->signatures->clear($user);
        $user->delete();

        return back()->with('success', 'User deleted.');
    }

    public function updateSignature(Request $request, User $user): RedirectResponse
    {
        $request->validate([
            'signature' => ['nullable', 'image', 'max:2048'],
            'signature_data' => ['nullable', 'string'],
        ]);

        if ($request->hasFile('signature')) {
            $this->signatures->storeFromUpload($user, $request->file('signature'));
        } elseif ($request->filled('signature_data')) {
            $this->signatures->storeFromDataUrl($user, $request->string('signature_data'));
        } else {
            return back()->withErrors(['signature' => 'Draw or upload a signature.']);
        }

        return back()->with('success', 'Signature updated for '.$user->name.'.');
    }

    public function clearSignature(User $user): RedirectResponse
    {
        $this->signatures->clear($user);

        return back()->with('success', 'Signature cleared.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user?->id),
            ],
            'role' => ['required', Rule::enum(UserRole::class)],
            'department_id' => ['nullable', 'exists:departments,id'],
            'password' => [
                $user ? 'nullable' : 'required',
                'string',
                Password::defaults(),
            ],
        ]);
    }
}
