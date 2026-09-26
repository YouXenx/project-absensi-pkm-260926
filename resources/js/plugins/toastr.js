import $ from 'jquery';
import toastr from 'toastr';
import 'toastr/build/toastr.css';

/**
 * Toastr (jQuery plugin) for light, non-blocking notifications: "data tersimpan", a quick row action, a load error.
 * Loaded on demand by flash.js the first time a toast is shown. Confirmations and important results stay SweetAlert.
 */

toastr.options = {
    positionClass: 'toast-top-right',
    closeButton: true,
    newestOnTop: true,
    progressBar: true,
    preventDuplicates: true,
    timeOut: 3500,
    extendedTimeOut: 1500,
    showDuration: 150,
    hideDuration: 200,
    escapeHtml: true,
};

// Toastr appends to <body> lazily; keep it above the modal backdrop but below SweetAlert (z-index 1060).
$(() => $('#toast-container').css('z-index', 1050));

const TYPES = { success: 'success', error: 'error', warning: 'warning', info: 'info', question: 'info' };

export default function notify(icon, text, title) {
    toastr[TYPES[icon] ?? 'info'](text, title);
}
