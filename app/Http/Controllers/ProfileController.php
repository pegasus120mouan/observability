<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Http\Requests\UpdateProfileRequest;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('profile.edit');
    }

    public function update(UpdateProfileRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();
        $passwordChanged = filled($data['password'] ?? null);

        $user->fill(['name' => $data['name']]);

        if ($passwordChanged) {
            $user->password = $data['password'];
        }

        $user->save();

        $auditLogger->log(
            $passwordChanged ? AuditAction::PasswordChanged : AuditAction::UserUpdated,
            $user,
            newValues: ['name' => $user->name],
            actor: $user,
        );

        return back()->with('status', 'Profile updated.');
    }
}
