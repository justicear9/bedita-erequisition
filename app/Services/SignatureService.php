<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SignatureService
{
    public function storeFromUpload(User $user, UploadedFile $file): string
    {
        $this->deleteCurrent($user);

        $path = $file->storeAs(
            'signatures/users',
            'user-'.$user->id.'-'.Str::uuid().'.'.$file->getClientOriginalExtension(),
            'public',
        );

        $user->update(['signature_path' => $path]);

        return $path;
    }

    public function storeFromDataUrl(User $user, string $dataUrl): string
    {
        if (! preg_match('/^data:image\/(png|jpeg|jpg);base64,/', $dataUrl)) {
            throw ValidationException::withMessages([
                'signature' => 'Invalid signature image.',
            ]);
        }

        $this->deleteCurrent($user);

        [, $data] = explode(',', $dataUrl, 2);
        $binary = base64_decode($data, true);
        if ($binary === false) {
            throw ValidationException::withMessages([
                'signature' => 'Could not read signature image.',
            ]);
        }

        $path = 'signatures/users/user-'.$user->id.'-'.Str::uuid().'.png';
        Storage::disk('public')->put($path, $binary);
        $user->update(['signature_path' => $path]);

        return $path;
    }

    public function clear(User $user): void
    {
        $this->deleteCurrent($user);
        $user->update(['signature_path' => null]);
    }

    /**
     * Copy the actor's profile signature onto a requisition snapshot path.
     */
    public function snapshotForRequisition(User $actor, string $prefix): string
    {
        if (! $actor->signature_path || ! Storage::disk('public')->exists($actor->signature_path)) {
            throw ValidationException::withMessages([
                'signature' => 'Add your signature in Profile before continuing.',
            ]);
        }

        $ext = pathinfo($actor->signature_path, PATHINFO_EXTENSION) ?: 'png';
        $dest = 'signatures/requisitions/'.$prefix.'-'.Str::uuid().'.'.$ext;
        Storage::disk('public')->copy($actor->signature_path, $dest);

        return $dest;
    }

    public function url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    private function deleteCurrent(User $user): void
    {
        if ($user->signature_path && Storage::disk('public')->exists($user->signature_path)) {
            Storage::disk('public')->delete($user->signature_path);
        }
    }
}
