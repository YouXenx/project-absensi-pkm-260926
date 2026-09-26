<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Create/edit forms live in modals on the index pages (components/modal-form + resources/js/modal.js).
 *
 *  - create/edit endpoints return the modal payload as JSON; a plain browser visit is sent to the index page.
 *  - store/update/destroy and row actions answer Ajax with JSON (SweetAlert message, the page reloads its DataTables);
 *    a non-Ajax submit still gets a redirect with the same message as a session flash.
 *  - Validation errors come back as Laravel's standard 422 JSON and are shown inline in the modal.
 */
trait RespondsToModalForms
{
    /**
     * @param  array{action?: string, method?: string, title?: string, confirm?: string|null, values?: array<string, mixed>, slots?: array<string, string>}  $payload
     */
    protected function modalForm(Request $request, array $payload, string $indexUrl): JsonResponse|RedirectResponse
    {
        if (! $request->expectsJson()) {
            return redirect()->to($indexUrl);
        }

        return response()->json($payload);
    }

    /**
     * A successful write. $message is a toast text, or SweetAlert options (icon, title, text/html) for a dialog.
     *
     * @param  string|array<string, mixed>  $message
     */
    protected function modalSaved(Request $request, string|array $message, string $indexUrl): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(is_array($message) ? ['alert' => $message] : ['message' => $message]);
        }

        return redirect()->to($indexUrl)->with(is_array($message) ? 'alert' : 'success', $message);
    }

    /**
     * A write refused by a business rule (not a validation error on one field), e.g. deleting a class that still has students.
     */
    protected function modalRejected(Request $request, string $message, string $indexUrl, int $status = 422): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], $status);
        }

        return redirect()->to($indexUrl)->with('error', $message);
    }
}
