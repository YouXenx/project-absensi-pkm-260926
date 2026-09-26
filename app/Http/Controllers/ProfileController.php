<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Pengaturan Akun" for admin and "Profil Saya" for guru. Always works on the
 * signed-in user; the role route group decides which URL each role may use.
 */
class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('pages.profile.edit', ['user' => $request->user()]);
    }

    public function update(ProfileRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->update($request->profileData());

        return redirect()
            ->route($user->isAdmin() ? 'admin.akun.edit' : 'guru.profil.edit')
            ->with('success', 'Profil berhasil diperbarui.');
    }
}
