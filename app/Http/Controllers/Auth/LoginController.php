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
        return view('pages.auth.login', ['chatbot' => $this->chatbot()]);
    }

    /**
     * Content of the FAQ help widget: the texts from config/chatbot.php plus ready-made contact links.
     * Static information only; nothing here depends on, or reveals, user accounts.
     *
     * @return array{
     *     greeting: list<string>,
     *     questions: list<array{id: string, label: string, answer: list<string>, shows_contact?: bool}>,
     *     contact: array{whatsapp: array{label: string, url: string}|null, email: array{label: string, url: string}|null},
     * }
     */
    private function chatbot(): array
    {
        $whatsapp = trim((string) config('chatbot.contact.whatsapp'));
        $email = trim((string) config('chatbot.contact.email'));

        // wa.me wants digits only, with the country code: 0812… becomes 62812….
        $digits = preg_replace('/\D+/', '', $whatsapp);
        $digits = str_starts_with($digits, '0') ? '62'.substr($digits, 1) : $digits;

        return [
            'greeting' => config('chatbot.greeting'),
            'questions' => config('chatbot.questions'),
            'contact' => [
                'whatsapp' => $digits === '' ? null : [
                    'label' => $whatsapp,
                    'url' => "https://wa.me/{$digits}?text=".rawurlencode((string) config('chatbot.contact.whatsapp_message')),
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
