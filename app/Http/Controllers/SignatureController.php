<?php

namespace App\Http\Controllers;

use App\Services\SignatureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SignatureController extends Controller
{
    public function __construct(private SignatureService $signatures) {}

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'signature' => ['nullable', 'image', 'max:2048'],
            'signature_data' => ['nullable', 'string'],
        ]);

        $user = $request->user();

        if ($request->hasFile('signature')) {
            $this->signatures->storeFromUpload($user, $request->file('signature'));
        } elseif (! empty($data['signature_data'])) {
            $this->signatures->storeFromDataUrl($user, $data['signature_data']);
        } else {
            return back()->withErrors(['signature' => 'Draw or upload a signature.']);
        }

        return back()->with('success', 'Signature saved.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->signatures->clear($request->user());

        return back()->with('success', 'Signature removed.');
    }
}
