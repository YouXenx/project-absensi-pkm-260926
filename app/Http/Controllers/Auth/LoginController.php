<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('pages.auth.login', ['faq' => $this->faq()]);
    }

    /**
     * Content of the FAQ modal: the texts from config/faq.php plus ready-made contact links.
     * Static information only; nothing here depends on, or reveals, user accounts.
     *
     * @return array{
     *     title: string,
     *     subtitle: string,
     *     questions: list<array{id: string, label: string, answer: list<string>}>,
     *     contact: array{whatsapp: array{label: string, url: string}|null, email: array{label: string, url: string}|null},
     * }
     */
    private function faq(): array
    {
        $whatsapp = trim((string) config('faq.contact.whatsapp'));
        $email = trim((string) config('faq.contact.email'));

        // wa.me wants digits only, with the country code: 0812… becomes 62812….
        $digits = preg_replace('/\D+/', '', $whatsapp);
        $digits = str_starts_with($digits, '0') ? '62'.substr($digits, 1) : $digits;

        return [
            'title' => config('faq.title'),
            'subtitle' => config('faq.subtitle'),
            'questions' => config('faq.questions'),
            'contact' => [
                'whatsapp' => $digits === '' ? null : [
                    'label' => $whatsapp,
                    'url' => "https://wa.me/{$digits}?text=".rawurlencode((string) config('faq.contact.whatsapp_message')),
                ],
                'email' => $email === '' ? null : [
                    'label' => $email,
                    'url' => "mailto:{$email}",
                ],
            ],
        ];
    }

    /**
     * One form for every role; the destination is decided by the user's role column.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = $request->user();

        return redirect()
            ->route($user->role->dashboardRoute())
            ->with('success', "Selamat datang, {$user->name}!");
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Anda berhasil logout.');
    }
}
